<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use AdmidioPlugin\MediaPlayer\Entity\Playlist;
use AdmidioPlugin\MediaPlayer\Entity\PlaylistItem;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

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
     *         uuid, sequence, kind, fileUuid, weblinkUuid, type, title, description, origin, source, downloadUrl and mime.
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
            'weblinkUuid' => '',
            'type' => $mediaType,
            'title' => pathinfo((string)$row['fil_name'], PATHINFO_FILENAME),
            'description' => StringUtils::strStripTags((string)$row['fil_description']),
            'origin' => $row['fol_name'],
            'source' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(),
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
            'weblinkUuid' => $row['lnk_uuid'],
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
     * Get all audio and video files and all weblinks the current user could add to a playlist. The files are
     * returned as a tree of their folders, folders without such files are left out. The weblinks are grouped
     * by their category.
     * @return array{folders: array<int,array<string,mixed>>, weblinks: array<int,array<string,mixed>>}
     * @throws Exception
     */
    public function findMediaTree(): array
    {
        global $gCurrentOrgId, $gL10n;

        $sql = 'SELECT fol_id, fol_fol_id_parent, fol_name
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_org_id = ? -- $gCurrentOrgId
                   AND fol_type = \'DOCUMENTS\'
                 ORDER BY fol_name';
        $folders = array();
        foreach ($this->db->queryPrepared($sql, array($gCurrentOrgId))->fetchAll() as $row) {
            $folders[(int)$row['fol_id']] = array(
                'id' => (int)$row['fol_id'],
                'parent' => $row['fol_fol_id_parent'] === null ? null : (int)$row['fol_fol_id_parent'],
                'name' => (string)$row['fol_name'],
                'folders' => array(),
                'files' => array()
            );
        }

        $sql = 'SELECT fil_uuid, fil_name, fil_locked, fol_id, fol_public, fol_org_id
                  FROM ' . TBL_FILES . '
            INNER JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
                 WHERE fol_org_id = ? -- $gCurrentOrgId
                   AND fol_type = \'DOCUMENTS\'
                 ORDER BY fil_name';
        $usedFolders = array();
        foreach ($this->db->queryPrepared($sql, array($gCurrentOrgId))->fetchAll() as $row) {
            $type = MediaAccessService::getFileMediaType((string)$row['fil_name']);
            if ($type === '' || !$this->access->canViewFile($row) || !isset($folders[(int)$row['fol_id']])) {
                continue;
            }
            $folders[(int)$row['fol_id']]['files'][] = array(
                'uuid' => $row['fil_uuid'],
                'name' => pathinfo((string)$row['fil_name'], PATHINFO_FILENAME),
                'type' => $type
            );
            // the folder and all its parent folders are shown in the tree
            for ($id = (int)$row['fol_id']; $id !== null && isset($folders[$id]) && !isset($usedFolders[$id]); $id = $folders[$id]['parent']) {
                $usedFolders[$id] = true;
            }
        }

        // build the tree from the bottom up, the root folder of the organization is not shown
        $build = function (int $id) use (&$build, $folders, $usedFolders): array {
            $node = array('name' => $folders[$id]['name'], 'folders' => array(), 'files' => $folders[$id]['files']);
            foreach ($folders as $child) {
                if ($child['parent'] === $id && isset($usedFolders[$child['id']])) {
                    $node['folders'][] = $build($child['id']);
                }
            }
            return $node;
        };
        $tree = array();
        foreach ($folders as $folder) {
            if ($folder['parent'] === null && isset($usedFolders[$folder['id']])) {
                $root = $build($folder['id']);
                $tree = array_merge($tree, $root['folders']);
                // files directly in the root folder are shown in a node with the name of the module
                if (count($root['files']) > 0) {
                    array_unshift($tree, array('name' => $gL10n->get('SYS_DOCUMENTS_FILES'), 'folders' => array(), 'files' => $root['files']));
                }
            }
        }

        return array('folders' => $tree, 'weblinks' => $this->findWeblinkTree());
    }

    /**
     * Get all weblinks the current user could add to a playlist, grouped by their category.
     * @return array<int,array{name: string, links: array<int,array<string,string>>}>
     * @throws Exception
     */
    private function findWeblinkTree(): array
    {
        if (!$this->access->isWeblinksModuleAvailable()) {
            return array();
        }

        $categories = array_merge(array(0), $this->access->getVisibleLinkCategories());
        $sql = 'SELECT lnk_uuid, lnk_name, lnk_url, cat_name
                  FROM ' . TBL_LINKS . '
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = lnk_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($categories) . ')
                 ORDER BY cat_sequence, lnk_name';
        $groups = array();
        foreach ($this->db->queryPrepared($sql, $categories)->fetchAll() as $row) {
            $name = Language::translateIfTranslationStrId((string)$row['cat_name']);
            $groups[$name]['name'] = $name;
            $groups[$name]['links'][] = array(
                'uuid' => $row['lnk_uuid'],
                'name' => $row['lnk_name'],
                'type' => MediaAccessService::getLinkMediaType((string)$row['lnk_url'])
            );
        }
        return array_values($groups);
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
        global $gValidLogin;

        if ($uuid === '') {
            // every registered user could create private playlists
            if (!$gValidLogin) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            return new Playlist($this->db);
        }

        $playlist = $this->getPlaylist($uuid);
        if (!$this->canEdit($playlist->getValue('mpl_usr_id_owner'), $playlist->getValue('mpl_usr_id_create'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        return $playlist;
    }

    /**
     * Check if the current user may change a playlist with the given owner and creator.
     * @param mixed $ownerId The value of the column mpl_usr_id_owner.
     * @param mixed $creatorId The value of the column mpl_usr_id_create.
     * @return bool Returns **true** if the current user may change the playlist.
     * @throws Exception
     */
    public function canEdit(mixed $ownerId, mixed $creatorId): bool
    {
        return MediaPlayer::canEditPlaylist(
            empty($ownerId) ? null : (int)$ownerId,
            empty($creatorId) ? null : (int)$creatorId
        );
    }

    /**
     * Check if the current user may switch a playlist between private and shared. Only playlist editors
     * could create shared playlists and only the user who has created the playlist could switch it.
     * @param Playlist $playlist The playlist that should be saved.
     * @return bool Returns **true** if the current user may choose if the playlist is private.
     * @throws Exception
     */
    public function canChangeVisibility(Playlist $playlist): bool
    {
        global $gCurrentUserId;

        return MediaPlayer::isPlaylistEditor()
            && ($playlist->isNewRecord() || (int)$playlist->getValue('mpl_usr_id_create') === $gCurrentUserId);
    }

    /**
     * Save the name and the description of a playlist from the form of the edit page.
     * @param string $uuid The UUID of the playlist or an empty string for a new playlist.
     * @return string Returns the UUID of the saved playlist.
     * @throws Exception
     */
    public function save(string $uuid): string
    {
        global $gCurrentSession, $gCurrentUserId;

        $playlist = $this->getEditablePlaylist($uuid);
        $form = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $form->validate($_POST);
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'mpl_')) {
                $playlist->setValue($key, $value);
            }
        }

        // users who could not create shared playlists always create private playlists
        if ($this->canChangeVisibility($playlist)) {
            $private = !empty($formValues['media_player_private']);
            $playlist->setValue('mpl_usr_id_owner', $private ? $gCurrentUserId : null);
        } elseif ($playlist->isNewRecord()) {
            $playlist->setValue('mpl_usr_id_owner', $gCurrentUserId);
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
     * Add files and weblinks to a playlist. Only files and weblinks the current user could see are added.
     * The new items are added at the end of the playlist or before the item with the UUID of the parameter
     * **before_item_uuid**. The parameters are read from the POST request.
     * @param string $uuid The UUID of the playlist.
     * @return array<int,array<string,mixed>> Returns the new items with the data of **findItems()**.
     * @throws Exception
     */
    public function addItems(string $uuid): array
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $playlist = $this->getEditablePlaylist($uuid);
        if ($playlist->isNewRecord()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $playlistId = (int)$playlist->getValue('mpl_id');

        $sql = 'SELECT MAX(mpi_sequence) FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . ' WHERE mpi_mpl_id = ?';
        $sequence = (int)$this->db->queryPrepared($sql, array($playlistId))->fetchColumn();

        $newItems = array();
        foreach ($this->findFileIds((array)($_POST['file_uuids'] ?? array())) as $fileId) {
            $newItems[] = $this->addItem($playlistId, ++$sequence, $fileId, null);
        }
        foreach ($this->findWeblinkIds((array)($_POST['weblink_uuids'] ?? array())) as $weblinkId) {
            $newItems[] = $this->addItem($playlistId, ++$sequence, null, $weblinkId);
        }

        $beforeUuid = (string)($_POST['before_item_uuid'] ?? '');
        if ($beforeUuid !== '' && count($newItems) > 0) {
            $order = array();
            foreach ($this->findAllItemUuids($playlistId) as $itemUuid) {
                if ($itemUuid === $beforeUuid) {
                    $order = array_merge($order, $newItems);
                }
                if (!in_array($itemUuid, $newItems, true)) {
                    $order[] = $itemUuid;
                }
            }
            $this->saveSequence($playlistId, $order);
        }

        return array_values(array_filter($this->findItems($playlistId), static function (array $item) use ($newItems) {
            return in_array($item['uuid'], $newItems, true);
        }));
    }

    /**
     * Change the order of the items of a playlist. The POST parameter **item_uuids** contains the items the
     * current user could see in their new order. Items the user could not see keep their positions.
     * @param string $uuid The UUID of the playlist.
     * @return void
     * @throws Exception
     */
    public function reorderItems(string $uuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $playlist = $this->getEditablePlaylist($uuid);
        if ($playlist->isNewRecord()) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $playlistId = (int)$playlist->getValue('mpl_id');

        $allItems = $this->findAllItemUuids($playlistId);
        $newOrder = array_values(array_intersect(array_map('strval', (array)($_POST['item_uuids'] ?? array())), $allItems));
        $visibleItems = array_column($this->findItems($playlistId), 'uuid');
        if (count(array_diff($visibleItems, $newOrder)) > 0 || count($newOrder) !== count(array_unique($newOrder))) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        // the visible items fill the positions of the visible items in the new order
        $order = array();
        foreach ($allItems as $itemUuid) {
            $order[] = in_array($itemUuid, $visibleItems, true) ? array_shift($newOrder) : $itemUuid;
        }
        $this->saveSequence($playlistId, $order);
    }

    /**
     * Get the UUIDs of all items of a playlist in their order, also of the items the current user could not see.
     * @param int $playlistId The id of the playlist.
     * @return array<int,string>
     * @throws Exception
     */
    private function findAllItemUuids(int $playlistId): array
    {
        $sql = 'SELECT mpi_uuid FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . '
                 WHERE mpi_mpl_id = ? -- $playlistId
                 ORDER BY mpi_sequence, mpi_id';
        return $this->db->queryPrepared($sql, array($playlistId))->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Save the order of the items of a playlist. The sequence gets steps of 10, so items could later be
     * placed between other items.
     * @param int $playlistId The id of the playlist.
     * @param array<int,string> $itemUuids The UUIDs of all items of the playlist in their new order.
     * @return void
     * @throws Exception
     */
    private function saveSequence(int $playlistId, array $itemUuids): void
    {
        $sql = 'UPDATE ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . ' SET mpi_sequence = ? WHERE mpi_mpl_id = ? AND mpi_uuid = ?';
        $this->db->startTransaction();
        foreach (array_values($itemUuids) as $index => $itemUuid) {
            $this->db->queryPrepared($sql, array(($index + 1) * 10, $playlistId, $itemUuid));
        }
        $this->db->endTransaction();
    }

    /**
     * Save a new item of a playlist.
     * @param int $playlistId The id of the playlist.
     * @param int $sequence The position of the item within the playlist.
     * @param int|null $fileId The id of the file or null if the item references a weblink.
     * @param int|null $weblinkId The id of the weblink or null if the item references a file.
     * @return string Returns the UUID of the new item.
     * @throws Exception
     */
    private function addItem(int $playlistId, int $sequence, ?int $fileId, ?int $weblinkId): string
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

        return (string)$item->getValue('mpi_uuid');
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
        $item = new PlaylistItem($this->db);
        if ($uuid === '' || !$item->readDataByUuid($uuid)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        if (!$this->canEdit($item->getValue('mpl_usr_id_owner'), $item->getValue('mpl_usr_id_create'))) {
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
}
