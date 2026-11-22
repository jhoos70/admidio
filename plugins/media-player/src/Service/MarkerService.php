<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use AdmidioPlugin\MediaPlayer\MediaPlayer;
use Ramsey\Uuid\Uuid;

/**
 ***********************************************************************************************
 * Markers of the files and weblinks: named positions the player could jump to
 *
 * A marker belongs to a file or a weblink, so it is found in every playlist that contains it.
 * A private marker is only visible to its creator. A shared marker is visible to everyone who may
 * see the file or weblink, but only users who are playlist editors may create one. A shared marker
 * could be changed by its creator and by playlist managers.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class MarkerService
{
    /**
     * The longest position that is accepted, longer values are treated as invalid.
     */
    private const MAX_POSITION = 86400;
    private const MAX_NAME_LENGTH = 100;
    private const MAX_BAR_LENGTH = 20;

    private MediaAccessService $access;

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
        $this->access = new MediaAccessService($db);
    }

    /**
     * Get the markers of a file or weblink that the current user may see: all shared markers and the
     * private markers of the user. The file or weblink is selected by the GET parameters **file_uuid** or
     * **weblink_uuid**.
     * @return array<int,array<string,mixed>> Returns the markers sorted by position with the keys uuid, position,
     *         name, bar, shared, mine and canEdit, and the key canShare for the whole list in the first level.
     * @throws Exception
     */
    public function getMarkers(): array
    {
        $target = $this->getTarget((string)($_GET['file_uuid'] ?? ''), (string)($_GET['weblink_uuid'] ?? ''));
        $column = $target['kind'] === 'file' ? 'mmk_fil_id' : 'mmk_lnk_id';

        $sql = 'SELECT mmk_uuid, mmk_position, mmk_name, mmk_bar, mmk_is_shared, mmk_usr_id_create
                  FROM ' . MediaPlayer::TABLE_MARKERS . '
                 WHERE ' . $column . ' = ?
                   AND (mmk_is_shared = true OR mmk_usr_id_create = ?)
              ORDER BY mmk_position, mmk_name, mmk_id';
        $markers = array();
        foreach ($this->db->queryPrepared($sql, array($target['id'], $GLOBALS['gCurrentUserId']))->fetchAll() as $row) {
            $markers[] = $this->createMarkerData($row);
        }

        return array('canShare' => MediaPlayer::isPlaylistEditor(), 'markers' => $markers);
    }

    /**
     * Create a marker or change an existing one with the POST parameters **position**, **name** and **shared**.
     * A new marker needs the POST parameter **file_uuid** or **weblink_uuid**, an existing marker the
     * GET parameter **marker_uuid**.
     * @param string $markerUuid The UUID of the marker or an empty string for a new marker.
     * @return array<string,mixed> Returns the saved marker like the list.
     * @throws Exception
     */
    public function save(string $markerUuid): array
    {
        global $gCurrentUserId;

        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token'] ?? '');
        $this->checkLogin();

        $position = $_POST['position'] ?? '';
        if (!is_numeric($position) || (float)$position < 0 || (float)$position >= self::MAX_POSITION) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
        $position = round((float)$position, 1);
        $name = trim(preg_replace('/\s+/u', ' ', (string)($_POST['name'] ?? '')));
        $name = mb_substr($name, 0, self::MAX_NAME_LENGTH);
        if ($name === '') {
            $tenths = (int)round($position * 10) % 10;
            $name = self::formatTime($position) . ($tenths > 0 ? '.' . $tenths : '');
        }
        // bar number or rehearsal letter in the sheet music, optional
        $bar = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['bar'] ?? ''))), 0, self::MAX_BAR_LENGTH);
        $bar = ($bar === '') ? null : $bar;
        $shared = !empty($_POST['shared']) && $_POST['shared'] !== '0';
        if ($markerUuid === '') {
            if ($shared && !MediaPlayer::isPlaylistEditor()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $target = $this->getTarget((string)($_POST['file_uuid'] ?? ''), (string)($_POST['weblink_uuid'] ?? ''));
            $markerUuid = (string)Uuid::uuid4();
            $this->db->queryPrepared('INSERT INTO ' . MediaPlayer::TABLE_MARKERS
                . ' (mmk_uuid, mmk_fil_id, mmk_lnk_id, mmk_position, mmk_name, mmk_bar, mmk_is_shared, mmk_usr_id_create)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                array($markerUuid, $target['kind'] === 'file' ? $target['id'] : null, $target['kind'] === 'link' ? $target['id'] : null,
                    $position, $name, $bar, (int)$shared, $gCurrentUserId));
        } else {
            $row = $this->getEditableMarker($markerUuid);
            // a marker that gets shared needs the same right as a new shared marker, an already shared marker stays shared
            if ($shared && !(bool)$row['mmk_is_shared'] && !MediaPlayer::isPlaylistEditor()) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            $this->db->queryPrepared('UPDATE ' . MediaPlayer::TABLE_MARKERS
                . ' SET mmk_position = ?, mmk_name = ?, mmk_bar = ?, mmk_is_shared = ?, mmk_usr_id_change = ?, mmk_timestamp_change = CURRENT_TIMESTAMP
                   WHERE mmk_id = ?',
                array($position, $name, $bar, (int)$shared, $gCurrentUserId, (int)$row['mmk_id']));
        }

        $sql = 'SELECT mmk_uuid, mmk_position, mmk_name, mmk_bar, mmk_is_shared, mmk_usr_id_create
                  FROM ' . MediaPlayer::TABLE_MARKERS . ' WHERE mmk_uuid = ?';
        return $this->createMarkerData($this->db->queryPrepared($sql, array($markerUuid))->fetch());
    }

    /**
     * Delete the marker with the UUID of the GET parameter **marker_uuid**.
     * @param string $markerUuid The UUID of the marker.
     * @return void
     * @throws Exception
     */
    public function delete(string $markerUuid): void
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token'] ?? '');
        $this->checkLogin();

        $row = $this->getEditableMarker($markerUuid);
        $this->db->queryPrepared('DELETE FROM ' . MediaPlayer::TABLE_MARKERS . ' WHERE mmk_id = ?', array((int)$row['mmk_id']));
    }

    /**
     * Format a position as minutes and seconds, or as hours, minutes and seconds.
     * @param float $seconds The position in seconds.
     * @return string Returns the formatted position, e.g. 1:05.
     */
    public static function formatTime(float $seconds): string
    {
        $total = (int)floor($seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $rest = $total % 60;
        return $hours > 0 ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest) : sprintf('%d:%02d', $minutes, $rest);
    }

    /**
     * @return void
     * @throws Exception
     */
    private function checkLogin(): void
    {
        if (!$GLOBALS['gValidLogin']) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * Prepare the data of a marker for the player.
     * @param array<string,mixed> $row The columns of the marker.
     * @return array<string,mixed>
     * @throws Exception
     */
    private function createMarkerData(array $row): array
    {
        $mine = (int)$row['mmk_usr_id_create'] === $GLOBALS['gCurrentUserId'] && $GLOBALS['gValidLogin'];
        return array(
            'uuid' => $row['mmk_uuid'],
            'position' => (float)$row['mmk_position'],
            'name' => $row['mmk_name'],
            'bar' => (string)($row['mmk_bar'] ?? ''),
            'shared' => (bool)$row['mmk_is_shared'],
            'mine' => $mine,
            'canEdit' => $this->canEdit((bool)$row['mmk_is_shared'], (int)$row['mmk_usr_id_create'])
        );
    }

    /**
     * Check if the current user may change a marker. The creator may change it. A shared marker could also be
     * changed by playlist managers.
     * @param bool $shared Is the marker shared?
     * @param int $creatorId The id of the user who has created the marker.
     * @return bool Returns **true** if the current user may change the marker.
     * @throws Exception
     */
    private function canEdit(bool $shared, int $creatorId): bool
    {
        if (!$GLOBALS['gValidLogin']) {
            return false;
        }
        return $creatorId === $GLOBALS['gCurrentUserId'] || ($shared && MediaPlayer::isPlaylistManager());
    }

    /**
     * Read a marker the current user may change. The file or weblink must still be visible to the user.
     * @param string $uuid The UUID of the marker.
     * @return array<string,mixed> Returns the columns of the marker.
     * @throws Exception
     */
    private function getEditableMarker(string $uuid): array
    {
        $sql = 'SELECT mmk_id, mmk_is_shared, mmk_usr_id_create, mmk_fil_id, mmk_lnk_id, fil_uuid, lnk_uuid
                  FROM ' . MediaPlayer::TABLE_MARKERS . '
             LEFT JOIN ' . TBL_FILES . ' ON fil_id = mmk_fil_id
             LEFT JOIN ' . TBL_LINKS . ' ON lnk_id = mmk_lnk_id
                 WHERE mmk_uuid = ?';
        $row = $this->db->queryPrepared($sql, array($uuid))->fetch();
        // the private markers of other users are not visible, so they are reported like missing markers
        if (!is_array($row) || (!(bool)$row['mmk_is_shared'] && (int)$row['mmk_usr_id_create'] !== $GLOBALS['gCurrentUserId'])
            || !$this->canEdit((bool)$row['mmk_is_shared'], (int)$row['mmk_usr_id_create'])) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $this->getTarget((string)$row['fil_uuid'], (string)$row['lnk_uuid']);
        return $row;
    }

    /**
     * Read the file or weblink of a marker and check that the current user may see it and that it can be played.
     * @param string $fileUuid The UUID of the file or an empty string.
     * @param string $weblinkUuid The UUID of the weblink or an empty string.
     * @return array{kind:string,id:int} Returns the kind (file or link) and the id of the file or weblink.
     * @throws Exception
     */
    private function getTarget(string $fileUuid, string $weblinkUuid): array
    {
        if ($fileUuid !== '' && preg_match('/^[0-9a-f-]{36}$/', $fileUuid)) {
            $sql = 'SELECT fil_id, fil_name, fil_locked, fol_id, fol_public, fol_org_id
                      FROM ' . TBL_FILES . '
                INNER JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
                     WHERE fil_uuid = ?';
            $row = $this->db->queryPrepared($sql, array($fileUuid))->fetch();
            if (is_array($row) && MediaAccessService::getFileMediaType((string)$row['fil_name']) !== '' && $this->access->canViewFile($row)) {
                return array('kind' => 'file', 'id' => (int)$row['fil_id']);
            }
        } elseif ($weblinkUuid !== '' && preg_match('/^[0-9a-f-]{36}$/', $weblinkUuid)) {
            $sql = 'SELECT lnk_id, lnk_url, lnk_cat_id FROM ' . TBL_LINKS . ' WHERE lnk_uuid = ?';
            $row = $this->db->queryPrepared($sql, array($weblinkUuid))->fetch();
            if (is_array($row) && $this->access->canViewWeblink($row)) {
                return array('kind' => 'link', 'id' => (int)$row['lnk_id']);
            }
        }
        throw new Exception('SYS_NO_RIGHTS');
    }
}
