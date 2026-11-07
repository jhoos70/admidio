<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use AdmidioPlugin\MediaPlayer\Entity\PlaylistFolder;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

/**
 ***********************************************************************************************
 * Service of the playlist folders of the media player plugin
 *
 * The playlists are organized in folders. There are two trees: the shared folders that every user sees and
 * that are maintained like the shared playlists, and the private folders of each user. A playlist can only
 * be in a folder of the same kind. The roots of the two trees are no records, they are addressed with the
 * pseudo UUIDs **shared** and **private**.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class FolderService
{
    public const ROOT_SHARED = 'shared';
    public const ROOT_PRIVATE = 'private';

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param string $uuid A folder UUID or one of the root names.
     * @return bool Returns **true** if the UUID is the root of the shared or of the private folders.
     */
    public static function isRoot(string $uuid): bool
    {
        return $uuid === self::ROOT_SHARED || $uuid === self::ROOT_PRIVATE;
    }

    /**
     * Get a folder that the current user may see: a shared folder or a private folder of the user.
     * @param string $uuid The UUID of the folder.
     * @return PlaylistFolder Returns the folder.
     * @throws Exception
     */
    public function getFolder(string $uuid): PlaylistFolder
    {
        global $gCurrentUserId;

        $folder = new PlaylistFolder($this->db);
        if ($uuid === '' || !$folder->readDataByUuid($uuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $ownerId = $folder->getValue('mpf_usr_id_owner');
        if ($ownerId !== null && (int)$ownerId !== $gCurrentUserId) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $folder;
    }

    /**
     * Get a folder that the current user may change. A new folder is returned if the UUID is empty.
     * @param string $uuid The UUID of the folder or an empty string for a new folder.
     * @return PlaylistFolder Returns the folder.
     * @throws Exception
     */
    public function getEditableFolder(string $uuid): PlaylistFolder
    {
        global $gValidLogin;

        if ($uuid === '') {
            if (!$gValidLogin) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            return new PlaylistFolder($this->db);
        }

        $folder = $this->getFolder($uuid);
        if (!$this->canEdit($folder)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $folder;
    }

    /**
     * Check if the current user may change a folder. The rules are the same as for the playlists in it.
     * @param PlaylistFolder $folder The folder.
     * @return bool Returns **true** if the current user may rename, move and delete the folder.
     * @throws Exception
     */
    public function canEdit(PlaylistFolder $folder): bool
    {
        $ownerId = $folder->getValue('mpf_usr_id_owner');
        $creatorId = $folder->getValue('mpf_usr_id_create');

        return MediaPlayer::canEditPlaylist(
            empty($ownerId) ? null : (int)$ownerId,
            empty($creatorId) ? null : (int)$creatorId
        );
    }

    /**
     * Check if the current user may create a folder (or a playlist) in a place.
     * @param string $parentUuid The UUID of the parent folder or the name of a root.
     * @return bool Returns **true** if the user may create something there.
     * @throws Exception
     */
    public function canCreateIn(string $parentUuid): bool
    {
        global $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }
        if ($parentUuid === self::ROOT_PRIVATE) {
            return true;
        }
        if ($parentUuid === self::ROOT_SHARED) {
            return MediaPlayer::isPlaylistEditor();
        }

        $parent = $this->getFolder($parentUuid);
        return $parent->getValue('mpf_usr_id_owner') !== null || MediaPlayer::isPlaylistEditor();
    }

    /**
     * @param string $uuid The UUID of a folder or the name of a root.
     * @return bool Returns **true** if the place belongs to the private folders of the current user.
     * @throws Exception
     */
    public function isPrivate(string $uuid): bool
    {
        if ($uuid === self::ROOT_PRIVATE) {
            return true;
        }
        if ($uuid === self::ROOT_SHARED || $uuid === '') {
            return false;
        }
        return $this->getFolder($uuid)->getValue('mpf_usr_id_owner') !== null;
    }

    /**
     * Get the id of a folder for a playlist and check that the folder has the same kind as the playlist. A root
     * means no folder.
     * @param string $uuid The UUID of the folder or the name of a root or an empty string.
     * @param bool $private **true** if the playlist is private.
     * @return int|null Returns the id of the folder or **null** if the playlist is in the root. The playlist is
     *                  moved to the root if the folder has the other kind.
     * @throws Exception
     */
    public function getFolderIdForPlaylist(string $uuid, bool $private): ?int
    {
        if ($uuid === '' || self::isRoot($uuid)) {
            return null;
        }

        $folder = $this->getFolder($uuid);
        if (($folder->getValue('mpf_usr_id_owner') !== null) !== $private) {
            return null;
        }
        return (int)$folder->getValue('mpf_id');
    }

    /**
     * Get the folders of a place with the number of folders and playlists they contain.
     * @param string $parentUuid The UUID of the parent folder or the name of a root.
     * @return array<int,array<string,mixed>> Returns the folders ordered by name with the keys uuid, name, id,
     *         editable, playlistCount and folderCount.
     * @throws Exception
     */
    public function findChildren(string $parentUuid): array
    {
        global $gCurrentOrgId, $gCurrentUserId;

        $private = $this->isPrivate($parentUuid);
        $parentId = self::isRoot($parentUuid) ? null : (int)$this->getFolder($parentUuid)->getValue('mpf_id');

        $sql = 'SELECT mpf.*,
                       (SELECT COUNT(*) FROM ' . MediaPlayer::TABLE_PLAYLISTS . ' mpl WHERE mpl.mpl_mpf_id = mpf.mpf_id) AS playlist_count,
                       (SELECT COUNT(*) FROM ' . MediaPlayer::TABLE_FOLDERS . ' sub WHERE sub.mpf_mpf_id_parent = mpf.mpf_id) AS folder_count
                  FROM ' . MediaPlayer::TABLE_FOLDERS . ' mpf
                 WHERE mpf.mpf_org_id = ? -- $gCurrentOrgId
                   AND ' . ($parentId === null ? 'mpf.mpf_mpf_id_parent IS NULL' : 'mpf.mpf_mpf_id_parent = ?') . '
                   AND ' . ($private ? 'mpf.mpf_usr_id_owner = ?' : 'mpf.mpf_usr_id_owner IS NULL') . '
              ORDER BY mpf.mpf_name';
        $params = array($gCurrentOrgId);
        if ($parentId !== null) {
            $params[] = $parentId;
        }
        if ($private) {
            $params[] = $gCurrentUserId;
        }

        $folders = array();
        foreach ($this->db->queryPrepared($sql, $params)->fetchAll() as $row) {
            $folders[] = array(
                'id' => (int)$row['mpf_id'],
                'uuid' => $row['mpf_uuid'],
                'name' => $row['mpf_name'],
                'editable' => MediaPlayer::canEditPlaylist(
                    empty($row['mpf_usr_id_owner']) ? null : (int)$row['mpf_usr_id_owner'],
                    empty($row['mpf_usr_id_create']) ? null : (int)$row['mpf_usr_id_create']
                ),
                'private' => $private,
                'playlistCount' => (int)$row['playlist_count'],
                'folderCount' => (int)$row['folder_count']
            );
        }
        return $folders;
    }

    /**
     * Get the nodes for the folder tree of the sidebar and of the folder selection of the forms.
     * @param string $parentUuid The UUID of the parent folder or the name of a root.
     * @return array<int,array<string,mixed>> Returns the nodes with the keys id, name, url, hasChildren and selectable.
     * @throws Exception
     */
    public function findTreeNodes(string $parentUuid): array
    {
        $nodes = array();
        foreach ($this->findChildren($parentUuid) as $folder) {
            $nodes[] = array(
                'id' => $folder['uuid'],
                'name' => $folder['name'],
                'url' => $this->getFolderUrl($folder['uuid']),
                'hasChildren' => $folder['folderCount'] > 0,
                'selectable' => true
            );
        }
        return $nodes;
    }

    /**
     * Get the UUIDs from the root to a place, e.g. to open the tree down to the current folder.
     * @param string $uuid The UUID of a folder or the name of a root.
     * @return array<int,string> Returns the root name followed by the UUIDs of the folders, the last one is the place itself.
     * @throws Exception
     */
    public function findPath(string $uuid): array
    {
        if (self::isRoot($uuid) || $uuid === '') {
            return array($uuid === self::ROOT_PRIVATE ? self::ROOT_PRIVATE : self::ROOT_SHARED);
        }

        $folder = $this->getFolder($uuid);
        $path = array($uuid);
        $parentId = (int)$folder->getValue('mpf_mpf_id_parent');
        while ($parentId > 0) {
            $parent = new PlaylistFolder($this->db, $parentId);
            array_unshift($path, $parent->getValue('mpf_uuid'));
            $parentId = (int)$parent->getValue('mpf_mpf_id_parent');
        }
        array_unshift($path, $folder->getValue('mpf_usr_id_owner') === null ? self::ROOT_SHARED : self::ROOT_PRIVATE);

        return $path;
    }

    /**
     * @param string $uuid The UUID of a folder or the name of a root. An empty string is the root of the shared folders.
     * @return string The URL of the page that shows the place.
     */
    public function getFolderUrl(string $uuid): string
    {
        $parameters = array();
        if ($uuid !== '' && $uuid !== self::ROOT_SHARED) {
            $parameters['folder_uuid'] = $uuid;
        }
        return SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), $parameters);
    }

    /**
     * Save a folder from the form of the folder page. The parent folder is selected in a tree, a folder gets
     * the kind of its parent, a new folder in the root gets the kind of the root.
     * @param string $uuid The UUID of the folder or an empty string for a new folder.
     * @return string Returns the UUID of the parent folder or the name of the root, to show it afterwards.
     * @throws Exception
     */
    public function save(string $uuid): string
    {
        global $gCurrentSession, $gCurrentUserId, $gL10n;

        $folder = $this->getEditableFolder($uuid);
        $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $form->validate($_POST);

        $name = trim((string)($formValues['mpf_name'] ?? ''));
        if ($name === '') {
            throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME')));
        }

        $parentUuid = (string)($formValues['media_player_parent_uuid'] ?? '');
        if ($parentUuid === '') {
            $parentUuid = $folder->isNewRecord() ? self::ROOT_SHARED : $this->findParentUuid($folder);
        }
        if (!$this->canCreateIn($parentUuid)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        if ($folder->isNewRecord()) {
            $private = $this->isPrivate($parentUuid);
        } else {
            // an existing folder keeps its kind, so it can't be moved between the trees
            $private = $folder->getValue('mpf_usr_id_owner') !== null;
            if ($this->isPrivate($parentUuid) !== $private) {
                throw new Exception('PLG_MEDIA_PLAYER_FOLDER_WRONG_KIND');
            }
        }

        $parentId = self::isRoot($parentUuid) ? null : (int)$this->getFolder($parentUuid)->getValue('mpf_id');
        if ($parentId !== null && !$folder->isNewRecord()) {
            // a folder can't be moved into itself or into one of its own subfolders
            $checkId = $parentId;
            while ($checkId > 0) {
                if ($checkId === (int)$folder->getValue('mpf_id')) {
                    throw new Exception('PLG_MEDIA_PLAYER_FOLDER_INTO_ITSELF');
                }
                $checkId = (int)(new PlaylistFolder($this->db, $checkId))->getValue('mpf_mpf_id_parent');
            }
        }

        $folder->setValue('mpf_name', $name);
        $folder->setValue('mpf_mpf_id_parent', $parentId);
        if ($folder->isNewRecord()) {
            $folder->setValue('mpf_usr_id_owner', $private ? $gCurrentUserId : null);
        }
        $folder->save();

        return $parentUuid;
    }

    /**
     * Delete an empty folder.
     * @param string $uuid The UUID of the folder.
     * @return void
     * @throws Exception
     */
    public function delete(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        if ($uuid === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $folder = $this->getEditableFolder($uuid);
        $sql = 'SELECT (SELECT COUNT(*) FROM ' . MediaPlayer::TABLE_PLAYLISTS . ' WHERE mpl_mpf_id = ?)
                     + (SELECT COUNT(*) FROM ' . MediaPlayer::TABLE_FOLDERS . ' WHERE mpf_mpf_id_parent = ?)';
        $folderId = (int)$folder->getValue('mpf_id');
        if ((int)$this->db->queryPrepared($sql, array($folderId, $folderId))->fetchColumn() > 0) {
            throw new Exception('PLG_MEDIA_PLAYER_FOLDER_NOT_EMPTY');
        }
        $folder->delete();
    }

    /**
     * @param PlaylistFolder $folder A saved folder.
     * @return string The UUID of the parent folder or the name of the root the folder is in.
     * @throws Exception
     */
    public function findParentUuid(PlaylistFolder $folder): string
    {
        $parentId = (int)$folder->getValue('mpf_mpf_id_parent');
        if ($parentId > 0) {
            return (string)(new PlaylistFolder($this->db, $parentId))->getValue('mpf_uuid');
        }
        return $folder->getValue('mpf_usr_id_owner') === null ? self::ROOT_SHARED : self::ROOT_PRIVATE;
    }
}
