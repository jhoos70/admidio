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
 *                 edit         - form to create or edit a playlist
 *                 save         - save the name and the description of a playlist
 *                 delete       - delete a playlist
 *                 add_items    - add files and weblinks to a playlist
 *                 remove_item  - remove an item from a playlist
 *                 sequence     - move an item within a playlist
 *                 stream       - send an audio or video file to the player
 *                 zip          - download a playlist as ZIP file
 * playlist_uuid : UUID of the playlist
 * item_uuid     : UUID of the playlist item
 * uuid          : UUID of the playlist item (used by the mode sequence)
 * file_uuid     : UUID of the file that should be streamed
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use MediaPlayer\classes\MediaPlayer;
use MediaPlayer\classes\Presenter\PlaylistsPresenter;
use MediaPlayer\classes\Service\PlaylistService;
use MediaPlayer\classes\Service\StreamService;
use MediaPlayer\classes\Service\ZipExportService;

$getMode = 'list';
try {
    require_once(__DIR__ . '/../../system/common.php');
    // the plugin manager only loads plugin classes on the overview and the preferences page, so load the main
    // class here; getInstance() then registers the autoloader for all other classes of the plugin
    require_once(__DIR__ . '/classes/MediaPlayer.php');

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'list',
        'validValues' => array('list', 'playlist', 'edit', 'save', 'delete', 'add_items', 'remove_item', 'sequence', 'stream', 'zip')
    ));
    $getPlaylistUuid = admFuncVariableIsValid($_GET, 'playlist_uuid', 'uuid');
    // moveTableRow() of the common functions sends the item as parameter uuid
    if ($getMode === 'sequence') {
        $getItemUuid = admFuncVariableIsValid($_GET, 'uuid', 'uuid');
    } else {
        $getItemUuid = admFuncVariableIsValid($_GET, 'item_uuid', 'uuid');
    }
    $getFileUuid = admFuncVariableIsValid($_GET, 'file_uuid', 'uuid');

    MediaPlayer::getInstance();
    MediaPlayer::checkAccess();

    switch ($getMode) {
        case 'list':
            $page = new PlaylistsPresenter();
            $page->createList();
            $gNavigation->addStartUrl(CURRENT_URL, $page->getHeadline(), 'bi-music-note-beamed');
            $page->show();
            break;

        case 'playlist':
            $page = new PlaylistsPresenter($getPlaylistUuid);
            $page->createPlaylistPage($getPlaylistUuid);
            $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());
            $page->show();
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

        case 'add_items':
            (new PlaylistService($gDb))->addItems($getPlaylistUuid);
            echo json_encode(array('status' => 'success', 'url' => $gNavigation->getUrl()));
            break;

        case 'remove_item':
            (new PlaylistService($gDb))->removeItem($getItemUuid);
            echo json_encode(array('status' => 'success'));
            break;

        case 'sequence':
            (new PlaylistService($gDb))->moveItem($getItemUuid);
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
    handleException($e, in_array($getMode, array('save', 'delete', 'add_items', 'remove_item', 'sequence'), true));
}
