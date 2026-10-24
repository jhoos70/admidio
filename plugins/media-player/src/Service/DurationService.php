<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

/**
 ***********************************************************************************************
 * Length of the files and weblinks of the playlists
 *
 * The length of a file is measured once by a browser and then stored, so other browsers don't have
 * to load the file only to show its length. The length of a YouTube or Vimeo video is read once by
 * the server from YouTube or Vimeo. A failed lookup is repeated at the earliest after a week.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class DurationService
{
    /**
     * The longest length that is accepted, longer values are treated as invalid.
     */
    private const MAX_DURATION = 86400;
    /**
     * Number of days after which a failed lookup of a weblink is repeated.
     */
    private const RETRY_DAYS = 7;
    /**
     * Timeout in seconds for the request to YouTube or Vimeo.
     */
    private const TIMEOUT = 5;

    private MediaAccessService $access;

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
        $this->access = new MediaAccessService($db);
    }

    /**
     * Store the lengths of files that a browser has measured. The POST parameter **durations** contains the
     * length in seconds with the UUID of the file as key. Only files the current user could see are stored
     * and an existing length is not changed.
     * @return int Returns the number of stored lengths.
     * @throws Exception
     */
    public function saveFileDurations(): int
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);

        $durations = array();
        foreach ((array)($_POST['durations'] ?? array()) as $uuid => $seconds) {
            if (is_string($uuid) && preg_match('/^[0-9a-f-]{36}$/', $uuid) && is_numeric($seconds)
                && $seconds > 0 && $seconds < self::MAX_DURATION) {
                $durations[$uuid] = (int)floor((float)$seconds);
            }
        }
        if (count($durations) === 0) {
            return 0;
        }

        $sql = 'SELECT fil_id, fil_uuid, fil_name, fil_locked, fol_id, fol_public, fol_org_id
                  FROM ' . TBL_FILES . '
            INNER JOIN ' . TBL_FOLDERS . ' ON fol_id = fil_fol_id
             LEFT JOIN ' . MediaPlayer::TABLE_DURATIONS . ' ON mdu_fil_id = fil_id
                 WHERE fil_uuid IN (' . Database::getQmForValues(array_keys($durations)) . ')
                   AND mdu_id IS NULL';
        $count = 0;
        foreach ($this->db->queryPrepared($sql, array_keys($durations))->fetchAll() as $row) {
            if (MediaAccessService::getFileMediaType((string)$row['fil_name']) !== '' && $this->access->canViewFile($row)) {
                $this->db->queryPrepared('INSERT INTO ' . MediaPlayer::TABLE_DURATIONS . ' (mdu_fil_id, mdu_duration) VALUES (?, ?)',
                    array((int)$row['fil_id'], $durations[$row['fil_uuid']]));
                ++$count;
            }
        }
        return $count;
    }

    /**
     * Get the length of a YouTube or Vimeo weblink. If the length is not stored yet, it is read from
     * YouTube or Vimeo and stored. The POST parameter **weblink_uuid** contains the UUID of the weblink.
     * @return int|null Returns the length in seconds or **null** if it is not known.
     * @throws Exception
     */
    public function getWeblinkDuration(): ?int
    {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token']);
        $uuid = (string)($_POST['weblink_uuid'] ?? '');

        $sql = 'SELECT lnk_id, lnk_url, lnk_cat_id, mdu_id, mdu_url, mdu_duration, mdu_timestamp
                  FROM ' . TBL_LINKS . '
             LEFT JOIN ' . MediaPlayer::TABLE_DURATIONS . ' ON mdu_lnk_id = lnk_id
                 WHERE lnk_uuid = ?';
        $row = $this->db->queryPrepared($sql, array($uuid))->fetch();
        if ($row === false || !$this->access->canViewWeblink($row)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $url = (string)$row['lnk_url'];
        if ($row['mdu_id'] !== null && $row['mdu_url'] === $url) {
            if ($row['mdu_duration'] !== null) {
                return (int)$row['mdu_duration'];
            }
            if (strtotime((string)$row['mdu_timestamp']) > time() - self::RETRY_DAYS * 86400) {
                return null;
            }
        }

        $duration = $this->fetchDuration($url);
        if ($row['mdu_id'] === null) {
            $this->db->queryPrepared('INSERT INTO ' . MediaPlayer::TABLE_DURATIONS . ' (mdu_lnk_id, mdu_url, mdu_duration) VALUES (?, ?, ?)',
                array((int)$row['lnk_id'], $url, $duration));
        } else {
            $this->db->queryPrepared('UPDATE ' . MediaPlayer::TABLE_DURATIONS . ' SET mdu_url = ?, mdu_duration = ?, mdu_timestamp = CURRENT_TIMESTAMP WHERE mdu_id = ?',
                array($url, $duration, (int)$row['mdu_id']));
        }
        return $duration;
    }

    /**
     * Read the length of a video from YouTube or Vimeo. YouTube has no public interface without a key,
     * so the length is read from the metadata of the video page. Vimeo offers the length with oEmbed.
     * @param string $url The URL of the weblink.
     * @return int|null Returns the length in seconds or **null** if it could not be read.
     */
    private function fetchDuration(string $url): ?int
    {
        $type = MediaAccessService::getLinkMediaType($url);
        if ($type === 'youtube'
            && preg_match('/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|shorts\/|live\/|watch\?(?:.*&)?v=))([\w-]{11})/', $url, $matches)) {
            $page = $this->request('https://www.youtube.com/watch?v=' . $matches[1]);
            if (preg_match('/itemprop="duration" content="PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?"/', $page, $time)) {
                return $this->validDuration((int)($time[1] ?? 0) * 3600 + (int)($time[2] ?? 0) * 60 + (int)($time[3] ?? 0));
            }
            if (preg_match('/"lengthSeconds":"(\d+)"/', $page, $time)) {
                return $this->validDuration((int)$time[1]);
            }
        } elseif ($type === 'vimeo') {
            $data = json_decode($this->request('https://vimeo.com/api/oembed.json?url=' . rawurlencode($url)), true);
            if (is_array($data) && isset($data['duration'])) {
                return $this->validDuration((int)$data['duration']);
            }
        }
        return null;
    }

    /**
     * @param int $seconds
     * @return int|null Returns the length or **null** if it is not a valid length.
     */
    private function validDuration(int $seconds): ?int
    {
        return ($seconds > 0 && $seconds < self::MAX_DURATION) ? $seconds : null;
    }

    /**
     * Send a GET request and return the body. The cookies accept the consent of YouTube, otherwise
     * YouTube answers requests from the European Union with its consent page.
     * @param string $url
     * @return string Returns the body or an empty string if the request failed.
     */
    private function request(string $url): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Admidio)',
            CURLOPT_HTTPHEADER => array('Accept-Language: en'),
            CURLOPT_COOKIE => 'CONSENT=YES+1; SOCS=CAI'
        ));
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return (is_string($body) && $status === 200) ? $body : '';
    }
}
