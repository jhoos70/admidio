<?php

namespace MediaPlayer\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use MediaPlayer\classes\Entity\Playlist;
use MediaPlayer\classes\Entity\PlaylistItem;
use MediaPlayer\classes\MediaPlayer;

/**
 ***********************************************************************************************
 * Service of the playlists of the media player plugin
 *
 * Reads the playlists of the current organization and their items. Only items the current user
 * could see in the modules documents & files and weblinks are returned. Changes of playlists are
 * only allowed for playlist editors, see MediaPlayer::isPlaylistEditor().
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class PlaylistService
{
    private MediaAccessService $access;

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
        $this->access = new MediaAccessService($db);
    }

    /**
     * Get all playlists of the current organization with the number of items the current user could see.
     * Personal playlists of other users are not returned.
     * @return array<int,array<string,mixed>> Returns the playlists ordered by name.
     * @throws Exception
     */
    public function findAll(): array
    {
        global $gCurrentOrgId, $gCurrentUserId;

        $sql = 'SELECT * FROM ' . MediaPlayer::TABLE_PLAYLISTS . '
                 WHERE mpl_org_id = ? -- $gCurrentOrgId
                   AND (mpl_usr_id_owner IS NULL OR mpl_usr_id_owner = ?) -- $gCurrentUserId
                 ORDER BY mpl_name';
        $playlists = $this->db->queryPrepared($sql, array($gCurrentOrgId, $gCurrentUserId))->fetchAll();

        foreach ($playlists as $key => $playlist) {
            $playlists[$key]['item_count'] = count($this->findItems((int)$playlist['mpl_id']));
        }
        return $playlists;
    }

    /**
     * Get all items of a playlist that the current user could see.
     * @param int $playlistId The id of the playlist.
     * @return array<int,array<string,mixed>> Returns the items in the sequence of the playlist with the keys
     *         uuid, sequence, kind, fileUuid, type, title, description, origin, source, downloadUrl and mime.
     * @throws Exception
     */
    public function findItems(int $playlistId): array
    {
        $sql = 'SELECT mpi_uuid, mpi_sequence, mpi_fil_id, mpi_lnk_id,
                       fil_uuid, fil_name, fil_description, fil_locked,
                       fol_id, fol_name, fol_public, fol_org_id,
                       lnk_uuid, lnk_name, lnk_url, lnk_description, lnk_cat_id, cat_name
                  FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . '
             LEFT JOIN ' . TBL_FILES . ' ON fil_id = mpi_fil_id
             LEFT JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
             LEFT JOIN ' . TBL_LINKS . ' ON lnk_id = mpi_lnk_id
             LEFT JOIN ' . TBL_CATEGORIES . ' ON cat_id = lnk_cat_id
                 WHERE mpi_mpl_id = ? -- $playlistId
                 ORDER BY mpi_sequence, mpi_id';
        $rows = $this->db->queryPrepared($sql, array($playlistId))->fetchAll();

        $items = array();
        foreach ($rows as $row) {
            if ($row['mpi_fil_id'] !== null) {
                $item = $this->createFileItem($row);
            } else {
                $item = $this->createWeblinkItem($row);
            }
            if ($item !== null) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Create the data of an item that references a file. Returns null if the user could not see the file
     * or the file could not be played.
     * @param array<string,mixed> $row The columns of the item, the file and its folder.
     * @return array<string,mixed>|null
     * @throws Exception
     */
    private function createFileItem(array $row): ?array
    {
        $mediaType = MediaAccessService::getFileMediaType((string)$row['fil_name']);
        if ($mediaType === '' || !$this->access->canViewFile($row)) {
            return null;
        }

        return array(
            'uuid' => $row['mpi_uuid'],
            'sequence' => (int)$row['mpi_sequence'],
            'kind' => 'file',
            'fileUuid' => $row['fil_uuid'],
            'type' => $mediaType,
            'title' => pathinfo((string)$row['fil_name'], PATHINFO_FILENAME),
            'description' => StringUtils::strStripTags((string)$row['fil_description']),
            'origin' => $row['fol_name'],
            'source' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . '/media-player/index.php',
                array('mode' => 'stream', 'file_uuid' => $row['fil_uuid'])),
            'downloadUrl' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/documents-files.php',
                array('mode' => 'download', 'file_uuid' => $row['fil_uuid'])),
            'mime' => MediaAccessService::getFileMimeType((string)$row['fil_name'])
        );
    }

    /**
     * Create the data of an item that references a weblink. Returns null if the user could not see the weblink.
     * @param array<string,mixed> $row The columns of the item, the weblink and its category.
     * @return array<string,mixed>|null
     * @throws Exception
     */
    private function createWeblinkItem(array $row): ?array
    {
        if ($row['lnk_uuid'] === null || !$this->access->canViewWeblink($row)) {
            return null;
        }

        return array(
            'uuid' => $row['mpi_uuid'],
            'sequence' => (int)$row['mpi_sequence'],
            'kind' => 'weblink',
            'fileUuid' => '',
            'type' => MediaAccessService::getLinkMediaType((string)$row['lnk_url']),
            'title' => $row['lnk_name'],
            'description' => StringUtils::strStripTags((string)$row['lnk_description']),
            'origin' => Language::translateIfTranslationStrId((string)$row['cat_name']),
            'source' => $row['lnk_url'],
            'downloadUrl' => '',
            'mime' => ''
        );
    }

    /**
     * Get all audio and video files the current user could add to a playlist. The files are grouped by their folder.
     * @return array<int,array{0:string,1:string,2:string}> Returns entries for a select box with option groups.
     * @throws Exception
     */
    public function findSelectableFiles(): array
    {
        global $gCurrentOrgId;

        $sql = 'SELECT fil_uuid, fil_name, fil_locked, fol_id, fol_name, fol_public, fol_org_id
                  FROM ' . TBL_FILES . '
            INNER JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
                 WHERE fol_org_id = ? -- $gCurrentOrgId
                   AND fol_type = \'DOCUMENTS\'
                 ORDER BY fol_path, fol_name, fil_name';
        $rows = $this->db->queryPrepared($sql, array($gCurrentOrgId))->fetchAll();

        $files = array();
        foreach ($rows as $row) {
            if (MediaAccessService::getFileMediaType((string)$row['fil_name']) !== '' && $this->access->canViewFile($row)) {
                $files[] = array($row['fil_uuid'], $row['fil_name'], $row['fol_name']);
            }
        }
        return $files;
    }

    /**
     * Get all weblinks the current user could add to a playlist. The weblinks are grouped by their category.
     * @return array<int,array{0:string,1:string,2:string}> Returns entries for a select box with option groups.
     * @throws Exception
     */
    public function findSelectableWeblinks(): array
    {
        if (!$this->access->isWeblinksModuleAvailable()) {
            return array();
        }

        $categories = array_merge(array(0), $this->access->getVisibleLinkCategories());
        $sql = 'SELECT lnk_uuid, lnk_name, cat_name
                  FROM ' . TBL_LINKS . '
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = lnk_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($categories) . ')
                 ORDER BY cat_sequence, lnk_name';
        $rows = $this->db->queryPrepared($sql, $categories)->fetchAll();

        $weblinks = array();
        foreach ($rows as $row) {
            $weblinks[] = array($row['lnk_uuid'], $row['lnk_name'], Language::translateIfTranslationStrId((string)$row['cat_name']));
        }
        return $weblinks;
    }

    /**
     * Read a playlist of the current organization. Personal playlists of other users could not be read.
     * @param string $uuid The UUID of the playlist.
     * @return Playlist Returns the playlist.
     * @throws Exception
     */
    public function getPlaylist(string $uuid): Playlist
    {
        global $gCurrentUserId;

        $playlist = new Playlist($this->db);
        if ($uuid === '' || !$playlist->readDataByUuid($uuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        if ($playlist->getValue('mpl_usr_id_owner') !== null && (int)$playlist->getValue('mpl_usr_id_owner') !== $gCurrentUserId) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $playlist;
    }

    /**
     * Get a playlist that the current user may change. A new playlist is returned if the UUID is empty.
     * @param string $uuid The UUID of the playlist or an empty string for a new playlist.
     * @return Playlist Returns the playlist.
     * @throws Exception
     */
    public function getEditablePlaylist(string $uuid): Playlist
    {
        if (!MediaPlayer::isPlaylistEditor()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        if ($uuid === '') {
            return new Playlist($this->db);
        }
        return $this->getPlaylist($uuid);
    }

    /**
     * Save the name and the description of a playlist from the form of the edit page.
     * @param string $uuid The UUID of the playlist or an empty string for a new playlist.
     * @return string Returns the UUID of the saved playlist.
     * @throws Exception
     */
    public function save(string $uuid): string
    {
        global $gCurrentSession;

        $playlist = $this->getEditablePlaylist($uuid);
        $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        foreach ($form->validate($_POST) as $key => $value) {
            if (str_starts_with($key, 'mpl_')) {
                $playlist->setValue($key, $value);
            }
        }
        $playlist->save();

        return $playlist->getValue('mpl_uuid');
    }

    /**
     * Delete a playlist with all its items.
     * @param string $uuid The UUID of the playlist.
     * @return void
     * @throws Exception
     */
    public function delete(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        if ($uuid === '') {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $this->getEditablePlaylist($uuid)->delete();
    }

    /**
     * Add the selected files and weblinks of the form to the end of a playlist. Only files and weblinks the
     * current user could see are added.
     * @param string $uuid The UUID of the playlist.
     * @return void
     * @throws Exception
     */
    public function addItems(string $uuid): void
    {
        global $gCurrentSession;

        $playlist = $this->getEditablePlaylist($uuid);
        if ($playlist->isNewRecord()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $playlistId = (int)$playlist->getValue('mpl_id');

        $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $form->validate($_POST);

        $sql = 'SELECT MAX(mpi_sequence) FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . ' WHERE mpi_mpl_id = ?';
        $sequence = (int)$this->db->queryPrepared($sql, array($playlistId))->fetchColumn();

        foreach ($this->findFileIds((array)($formValues['media_player_files'] ?? array())) as $fileId) {
            $this->addItem($playlistId, ++$sequence, $fileId, null);
        }
        foreach ($this->findWeblinkIds((array)($formValues['media_player_weblinks'] ?? array())) as $weblinkId) {
            $this->addItem($playlistId, ++$sequence, null, $weblinkId);
        }
    }

    /**
     * Save a new item of a playlist.
     * @param int $playlistId The id of the playlist.
     * @param int $sequence The position of the item within the playlist.
     * @param int|null $fileId The id of the file or null if the item references a weblink.
     * @param int|null $weblinkId The id of the weblink or null if the item references a file.
     * @return void
     * @throws Exception
     */
    private function addItem(int $playlistId, int $sequence, ?int $fileId, ?int $weblinkId): void
    {
        $item = new PlaylistItem($this->db);
        $item->setValue('mpi_mpl_id', $playlistId);
        $item->setValue('mpi_sequence', $sequence);
        if ($fileId !== null) {
            $item->setValue('mpi_fil_id', $fileId);
        } else {
            $item->setValue('mpi_lnk_id', $weblinkId);
        }
        $item->save();
    }

    /**
     * Get the ids of the files with the given UUIDs that the current user could see and play.
     * @param array<int,string> $uuids The UUIDs of the files.
     * @return array<int,int> Returns the ids of the files in the order of the UUIDs.
     * @throws Exception
     */
    private function findFileIds(array $uuids): array
    {
        $uuids = array_values(array_filter($uuids, static function ($uuid) {
            return is_string($uuid) && $uuid !== '';
        }));
        if (count($uuids) === 0) {
            return array();
        }

        $sql = 'SELECT fil_id, fil_uuid, fil_name, fil_locked, fol_id, fol_public, fol_org_id
                  FROM ' . TBL_FILES . '
            INNER JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
                 WHERE fil_uuid IN (' . Database::getQmForValues($uuids) . ')';
        $files = array();
        foreach ($this->db->queryPrepared($sql, $uuids)->fetchAll() as $row) {
            if (MediaAccessService::getFileMediaType((string)$row['fil_name']) !== '' && $this->access->canViewFile($row)) {
                $files[$row['fil_uuid']] = (int)$row['fil_id'];
            }
        }

        $ids = array();
        foreach ($uuids as $uuid) {
            if (isset($files[$uuid])) {
                $ids[] = $files[$uuid];
            }
        }
        return $ids;
    }

    /**
     * Get the ids of the weblinks with the given UUIDs that the current user could see.
     * @param array<int,string> $uuids The UUIDs of the weblinks.
     * @return array<int,int> Returns the ids of the weblinks in the order of the UUIDs.
     * @throws Exception
     */
    private function findWeblinkIds(array $uuids): array
    {
        $uuids = array_values(array_filter($uuids, static function ($uuid) {
            return is_string($uuid) && $uuid !== '';
        }));
        if (count($uuids) === 0) {
            return array();
        }

        $sql = 'SELECT lnk_id, lnk_uuid, lnk_cat_id
                  FROM ' . TBL_LINKS . '
                 WHERE lnk_uuid IN (' . Database::getQmForValues($uuids) . ')';
        $weblinks = array();
        foreach ($this->db->queryPrepared($sql, $uuids)->fetchAll() as $row) {
            if ($this->access->canViewWeblink($row)) {
                $weblinks[$row['lnk_uuid']] = (int)$row['lnk_id'];
            }
        }

        $ids = array();
        foreach ($uuids as $uuid) {
            if (isset($weblinks[$uuid])) {
                $ids[] = $weblinks[$uuid];
            }
        }
        return $ids;
    }

    /**
     * Read an item of a playlist that the current user may change.
     * @param string $uuid The UUID of the item.
     * @return PlaylistItem Returns the item.
     * @throws Exception
     */
    private function getEditableItem(string $uuid): PlaylistItem
    {
        global $gCurrentUserId;

        if (!MediaPlayer::isPlaylistEditor()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $item = new PlaylistItem($this->db);
        if ($uuid === '' || !$item->readDataByUuid($uuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        if ($item->getValue('mpl_usr_id_owner') !== null && (int)$item->getValue('mpl_usr_id_owner') !== $gCurrentUserId) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $item;
    }

    /**
     * Remove an item from its playlist.
     * @param string $uuid The UUID of the item.
     * @return void
     * @throws Exception
     */
    public function removeItem(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $this->getEditableItem($uuid)->delete();
    }

    /**
     * Move an item one position up or down within its playlist.
     * @param string $uuid The UUID of the item.
     * @return void
     * @throws Exception
     */
    public function moveItem(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $direction = admFuncVariableIsValid($_POST, 'direction', 'string',
            array('requireValue' => true, 'validValues' => array(PlaylistItem::MOVE_UP, PlaylistItem::MOVE_DOWN)));
        $this->getEditableItem($uuid)->moveSequence($direction);
    }
}
