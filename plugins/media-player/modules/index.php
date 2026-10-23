<?php
/**
 ***********************************************************************************************
 * Media player
 *
 * Playlists with audio and video files of the module documents & files and with weblinks.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode          : list         - (default) show all playlists
 *                 playlist     - show a playlist with the player
 *                 items        - edit mode of a playlist with the media browser
 *                 edit         - form to create or edit a playlist
 *                 save         - save the name and the description of a playlist
 *                 delete       - delete a playlist
 *                 copy         - create a private copy of a playlist for the current user
 *                 media_tree   - files and weblinks that could be added to a playlist (JSON)
 *                 add          - add files and weblinks to a playlist (JSON)
 *                 reorder      - save the new order of the items of a playlist
 *                 remove_item  - remove an item from a playlist
 *                 stream       - send an audio or video file to the player
 *                 zip          - download a playlist as ZIP file
 * playlist_uuid : UUID of the playlist
 * item_uuid     : UUID of the playlist item
 * file_uuid     : UUID of the file that should be streamed
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use AdmidioPlugin\MediaPlayer\MediaPlayer;
use AdmidioPlugin\MediaPlayer\Presenter\PlaylistsPresenter;
use AdmidioPlugin\MediaPlayer\Service\PlaylistService;
use AdmidioPlugin\MediaPlayer\Service\StreamService;
use AdmidioPlugin\MediaPlayer\Service\ZipExportService;

$getMode = 'list';
try {
    require_once(__DIR__ . '/../../../system/common.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'list',
        'validValues' => array('list', 'playlist', 'items', 'edit', 'save', 'delete', 'copy', 'media_tree', 'add', 'reorder', 'remove_item', 'stream', 'zip')
    ));
    $getPlaylistUuid = admFuncVariableIsValid($_GET, 'playlist_uuid', 'uuid');
    $getItemUuid = admFuncVariableIsValid($_GET, 'item_uuid', 'uuid');
    $getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');

    MediaPlayer::checkAccess();

    switch ($getMode) {
        case 'list':
            $page = new PlaylistsPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), MediaPlayer::getPlugin()->icon);
            $page->show();
            break;

        case 'playlist':
            $page = new PlaylistsPresenter($getPlaylistUuid);
            $page->createPlaylistPage($getPlaylistUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'items':
            $page = new PlaylistsPresenter($getPlaylistUuid);
            $page->createPlaylistPage($getPlaylistUuid, true);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'copy':
            $copyUuid = (new PlaylistService($gDb))->copyPlaylist($getPlaylistUuid);
            echo json_encode(array('status' => 'success',
                'url' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'items', 'playlist_uuid' => $copyUuid))));
            break;

        case 'media_tree':
            (new PlaylistService($gDb))->getEditablePlaylist($getPlaylistUuid);
            header('Content-Type: application/json');
            echo json_encode((new PlaylistService($gDb))->findMediaTree());
            break;

        case 'add':
            $items = (new PlaylistService($gDb))->addItems($getPlaylistUuid);
            foreach ($items as $key => $item) {
                $items[$key]['removeUrl'] = SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'remove_item', 'item_uuid' => $item['uuid']));
            }
            echo json_encode(array('status' => 'success', 'items' => $items));
            break;

        case 'reorder':
            (new PlaylistService($gDb))->reorderItems($getPlaylistUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'edit':
            $page = new PlaylistsPresenter($getPlaylistUuid);
            $page->createEditForm($getPlaylistUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
            break;

        case 'save':
            (new PlaylistService($gDb))->save($getPlaylistUuid);
            $gNavigation->deleteLastUrl();
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'delete':
            (new PlaylistService($gDb))->delete($getPlaylistUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'remove_item':
            (new PlaylistService($gDb))->removeItem($getItemUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'stream':
            (new StreamService($gDb))->stream($getFileUuid);
            break;

        case 'zip':
            $playlist = (new PlaylistService($gDb))->getPlaylist($getPlaylistUuid);
            (new ZipExportService($gDb))->export($playlist);
            break;
    }
} catch (Throwable $e) {
    if ($getMode === 'stream' && !headers_sent()) {
        // the player could not show an error page, so only send a status code
        http_response_code(404);
        exit();
    }
    handleException($e, in_array($getMode, array('save', 'delete', 'copy', 'media_tree', 'add', 'reorder', 'remove_item'), true));
}
