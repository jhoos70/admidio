<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Documents\Entity\File;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;

/**
 ***********************************************************************************************
 * Streaming of audio and video files of the module documents & files
 *
 * Browsers request media files in parts (HTTP range requests). Without range support Safari doesn't play
 * videos and seeking is not possible. As long as the download of the module documents & files doesn't
 * support range requests the plugin streams the files itself. The view rights of the module are checked.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class StreamService
{
    private const CHUNK_SIZE = 65536;

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Send an audio or video file to the browser. If the browser requests a part of the file, only this part is sent.
     * @param string $fileUuid The UUID of the file of the module documents & files.
     * @return void
     * @throws Exception
     */
    public function stream(string $fileUuid): void
    {
        $access = new MediaAccessService($this->db);
        if (!$access->isDocumentsModuleAvailable()) {
            throw new Exception('SYS_MODULE_DISABLED');
        }

        // checks the view right of the folder and throws an exception if the user may not see the file
        $file = new File($this->db);
        $file->getFileForDownload($fileUuid);

        $fileName = (string)$file->getValue('fil_name', 'database');
        $filePath = $file->getFullFilePath();
        if (MediaAccessService::getFileMediaType($fileName) === '' || !is_file($filePath)) {
            throw new Exception('SYS_FILE_NOT_EXIST');
        }

        // release the session, otherwise all other requests of the user wait until the stream is finished
        session_write_close();

        $fileSize = (int)filesize($filePath);
        $range = $this->parseRange($_SERVER['HTTP_RANGE'] ?? '', $fileSize);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . MediaAccessService::getFileMimeType($fileName));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $fileName) . '"');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=3600');

        if ($range === null) {
            http_response_code(416);
            header('Content-Range: bytes */' . $fileSize);
            return;
        }

        [$start, $end, $partial] = $range;
        if ($partial) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
        }
        header('Content-Length: ' . ($end - $start + 1));

        $handle = fopen($filePath, 'rb');
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $buffer = fread($handle, min(self::CHUNK_SIZE, $remaining));
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            flush();
            $remaining -= strlen($buffer);
        }
        fclose($handle);
    }

    /**
     * Get the part of the file that the browser requests with the HTTP header **Range**.
     * Only a single range is supported. Multiple ranges are answered with the whole file.
     * @param string $header The value of the HTTP header **Range** e.g. **bytes=0-1023** or **bytes=-500**.
     * @param int $fileSize The size of the file in bytes.
     * @return array{0:int,1:int,2:bool}|null Returns the first and the last byte and if only a part is sent,
     *                                       or null if the range could not be satisfied.
     */
    private function parseRange(string $header, int $fileSize): ?array
    {
        if ($header === '' || preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches) !== 1) {
            return array(0, max(0, $fileSize - 1), false);
        }

        if ($matches[1] === '' && $matches[2] === '') {
            return null;
        }

        if ($matches[1] === '') {
            // suffix range: the last n bytes of the file
            $length = min((int)$matches[2], $fileSize);
            if ($length === 0) {
                return null;
            }
            return array($fileSize - $length, $fileSize - 1, true);
        }

        $start = (int)$matches[1];
        $end = $fileSize - 1;
        if ($matches[2] !== '') {
            $end = min((int)$matches[2], $fileSize - 1);
        }
        if ($start >= $fileSize || $start > $end) {
            return null;
        }
        return array($start, $end, true);
    }
}
