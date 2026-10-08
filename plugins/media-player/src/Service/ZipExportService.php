<?php

namespace AdmidioPlugin\MediaPlayer\Service;

use Admidio\Documents\Entity\File;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use AdmidioPlugin\MediaPlayer\Entity\Playlist;
use ZipArchive;

/**
 ***********************************************************************************************
 * Export of a playlist as ZIP file for offline use
 *
 * The ZIP file contains the files of the playlist that the current user could see, numbered in the order
 * of the playlist, a playlist file in the format M3U8 and a text file with the weblinks of the playlist.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class ZipExportService
{
    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Create the ZIP file of a playlist and send it to the browser.
     * @param Playlist $playlist The playlist that should be exported.
     * @return void
     * @throws Exception
     */
    public function export(Playlist $playlist): void
    {
        global $gL10n;

        $items = (new PlaylistService($this->db))->findItems((int)$playlist->getValue('mpl_id'));
        if (count($items) === 0) {
            throw new Exception('PLG_MEDIA_PLAYER_PLAYLIST_EMPTY');
        }

        $playlistName = FileSystemUtils::getSanitizedPathEntry((string)$playlist->getValue('mpl_name', 'database'));
        if ($playlistName === '') {
            $playlistName = 'playlist';
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'adm_media_');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('PLG_MEDIA_PLAYER_ZIP_ERROR');
        }

        $m3u = "#EXTM3U\n#PLAYLIST:" . $playlist->getValue('mpl_name', 'database') . "\n";
        $weblinks = '';
        $file = new File($this->db);
        $number = 0;

        foreach ($items as $item) {
            ++$number;
            $prefix = sprintf('%02d - ', $number);

            if ($item['kind'] === 'file') {
                $file->clear();
                $file->readDataByUuid($item['fileUuid']);
                $filePath = $file->getFullFilePath();
                if (!is_file($filePath)) {
                    continue;
                }
                $zipName = $prefix . FileSystemUtils::getSanitizedPathEntry((string)$file->getValue('fil_name', 'database'));
                $zip->addFile($filePath, $zipName);
                $m3u .= '#EXTINF:-1,' . $item['title'] . "\n" . $zipName . "\n";
            } else {
                $m3u .= '#EXTINF:-1,' . $item['title'] . "\n" . $item['source'] . "\n";
                $weblinks .= $prefix . $item['title'] . ': ' . $item['source'] . "\n";
            }
        }

        $zip->addFromString($playlistName . '.m3u8', $m3u);
        if ($weblinks !== '') {
            $zip->addFromString($gL10n->get('SYS_WEBLINKS') . '.txt', $weblinks);
        }
        $zip->close();

        // release the session, otherwise all other requests of the user wait until the download is finished
        session_write_close();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $playlistName) . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: private');

        readfile($zipPath);
        unlink($zipPath);
    }
}
