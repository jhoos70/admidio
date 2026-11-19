<?php

namespace AdmidioPlugin\MediaPlayer\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\MediaPlayer\MediaPlayer;
use AdmidioPlugin\MediaPlayer\Service\FolderService;
use AdmidioPlugin\MediaPlayer\Service\PlaylistService;

/**
 ***********************************************************************************************
 * Pages of the media player plugin
 *
 * Creates the list of playlists, the page of a playlist with the player and the edit form of a playlist.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class PlaylistsPresenter extends PagePresenter
{
    /**
     * Constructor that adds the template folder, the styles and the scripts of the plugin.
     * @param string $objectUUID UUID of the object that is shown on the page.
     * @throws Exception
     */
    public function __construct(string $objectUUID = '')
    {
        parent::__construct($objectUUID);

        $plugin = MediaPlayer::getPlugin();
        $this->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
        $this->addCssFile(MediaPlayer::getAssetUrl('libs/plyr/plyr.css'));
        $this->addCssFile(MediaPlayer::getAssetUrl('css/media_player.css'));
        $this->addJavascriptFile(MediaPlayer::getAssetUrl('libs/plyr/plyr.js'));
        $this->addJavascriptFile(MediaPlayer::getAssetUrl('js/media_player.js'));
    }

    /**
     * Create the page of a place in the folder tree: the folders and the playlists of a folder, or of the root of the
     * shared or of the private playlists. A folder tree is shown in a sidebar.
     * @param string $folderUuid The UUID of the folder or the name of a root. An empty string is the root of the shared playlists.
     * @return void
     * @throws Exception
     */
    public function createList(string $folderUuid = ''): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gValidLogin;

        $folderService = new FolderService($gDb);
        $folderUuid = $folderUuid === '' ? FolderService::ROOT_SHARED : $folderUuid;
        $isRoot = FolderService::isRoot($folderUuid);
        $folder = $isRoot ? null : $folderService->getFolder($folderUuid);
        $isPrivate = $folderService->isPrivate($folderUuid);

        $this->setHtmlID('adm_media_player_playlists');
        if ($folder === null) {
            $this->setHeadline($gL10n->get($isPrivate ? 'PLG_MEDIA_PLAYER_MY_PLAYLISTS' : 'PLG_MEDIA_PLAYER_NAME'));
        } else {
            $this->setHeadline($folder->getValue('mpf_name'));
        }

        // every registered user could create private playlists and private folders
        if ($gValidLogin) {
            $this->addPageFunctionsMenuItem(
                'menu_item_media_player_add',
                $gL10n->get('PLG_MEDIA_PLAYER_CREATE_PLAYLIST'),
                SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'edit', 'folder_uuid' => $folderUuid)),
                'bi-plus-circle-fill'
            );
            if ($folderService->canCreateIn($folderUuid)) {
                $this->addPageFunctionsMenuItem(
                    'menu_item_media_player_add_folder',
                    $gL10n->get('PLG_MEDIA_PLAYER_CREATE_FOLDER'),
                    SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_edit', 'parent_uuid' => $folderUuid)),
                    'bi-folder-plus'
                );
            }
        }

        $folders = array();
        foreach ($folderService->findChildren($folderUuid) as $row) {
            $folders[] = array_merge($row, array(
                'url' => $folderService->getFolderUrl($row['uuid']),
                'editUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_edit', 'folder_uuid' => $row['uuid'])),
                'deleteUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_delete', 'folder_uuid' => $row['uuid']))
            ));
        }
        // the personal playlists are a folder of the root, so they are found without opening the tree
        $privateRoot = null;
        if ($gValidLogin && $folderUuid === FolderService::ROOT_SHARED) {
            $privateRoot = array('url' => $folderService->getFolderUrl(FolderService::ROOT_PRIVATE));
        }

        $playlistService = new PlaylistService($gDb);
        $playlists = array();
        foreach ($playlistService->findAll($folderUuid) as $row) {
            $playlists[] = array(
                'uuid' => $row['mpl_uuid'],
                'private' => !empty($row['mpl_usr_id_owner']),
                'editable' => $playlistService->canEdit($row['mpl_usr_id_owner'], $row['mpl_usr_id_create']),
                'name' => $row['mpl_name'],
                'description' => (string)$row['mpl_description'],
                'itemCount' => $row['item_count'],
                'url' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'playlist', 'playlist_uuid' => $row['mpl_uuid'])),
                'editUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'edit', 'playlist_uuid' => $row['mpl_uuid'])),
                'deleteUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'delete', 'playlist_uuid' => $row['mpl_uuid'])),
                'zipUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'zip', 'playlist_uuid' => $row['mpl_uuid'])),
                'moveUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'move', 'playlist_uuid' => $row['mpl_uuid']))
            );
        }

        // the path from the root to the folder, to go back to a higher folder
        $path = $folderService->findPath($folderUuid);
        $breadcrumb = array();
        foreach ($path as $key => $uuid) {
            $breadcrumb[] = array(
                'name' => $key === 0 ? $gL10n->get($uuid === FolderService::ROOT_PRIVATE ? 'PLG_MEDIA_PLAYER_MY_PLAYLISTS' : 'PLG_MEDIA_PLAYER_NAME')
                    : $folderService->getFolder($uuid)->getValue('mpf_name'),
                'uuid' => $uuid,
                'private' => $path[0] === FolderService::ROOT_PRIVATE,
                'url' => $folderService->getFolderUrl($uuid),
                'current' => $key === count($path) - 1
            );
        }

        $this->addTreeViewFiles();
        $this->smarty->assign('folders', $folders);
        $this->smarty->assign('privateRoot', $privateRoot);
        $this->smarty->assign('breadcrumb', $breadcrumb);
        $this->smarty->assign('playlists', $playlists);
        $this->smarty->assign('treeUrl', SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_tree')));
        $this->smarty->assign('treeRoots', $gValidLogin ? array(FolderService::ROOT_SHARED, FolderService::ROOT_PRIVATE) : array(FolderService::ROOT_SHARED));
        $this->smarty->assign('dragTitle', $gL10n->get('PLG_MEDIA_PLAYER_DRAG_TO_FOLDER'));
        $this->smarty->assign('treeCurrentUuid', $folderUuid);
        $this->smarty->assign('treePath', json_encode($path));
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);
        $this->addHtmlByTemplate('plugin.media-player.list.tpl');
    }

    /**
     * Create the page of a playlist with the player and the list of its items. In the edit mode the page shows
     * the items that could be reordered and removed and a browser with the files and weblinks that could be added.
     * @param string $playlistUuid The UUID of the playlist.
     * @param bool $editMode **true** for the edit mode, only users who may change the playlist could open it.
     * @return void
     * @throws Exception
     */
    public function createPlaylistPage(string $playlistUuid, bool $editMode = false): void
    {
        global $gDb, $gL10n, $gCurrentSession;

        $playlistService = new PlaylistService($gDb);
        $playlist = $editMode ? $playlistService->getEditablePlaylist($playlistUuid) : $playlistService->getPlaylist($playlistUuid);
        $isEditor = $playlistService->canEdit($playlist->getValue('mpl_usr_id_owner'), $playlist->getValue('mpl_usr_id_create'));

        $this->setHtmlID($editMode ? 'adm_media_player_playlist_items' : 'adm_media_player_playlist');
        $this->setHeadline($playlist->getValue('mpl_name'));

        $items = $playlistService->findItems((int)$playlist->getValue('mpl_id'));
        $url = static function (string $mode, array $parameters = array()) use ($playlistUuid): string {
            return SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array_merge(array('mode' => $mode, 'playlist_uuid' => $playlistUuid), $parameters));
        };
        foreach ($items as $key => $item) {
            $items[$key]['removeUrl'] = SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'remove_item', 'item_uuid' => $item['uuid']));
        }

        $this->smarty->assign('playlistDescription', (string)$playlist->getValue('mpl_description'));
        $this->smarty->assign('items', $items);
        $this->smarty->assign('isEditor', $isEditor);
        $this->smarty->assign('playlistUrl', $url('playlist'));
        $this->smarty->assign('itemsUrl', $url('items'));
        $this->smarty->assign('zipUrl', $url('zip'));
        $this->smarty->assign('editUrl', $url('edit'));
        $this->smarty->assign('copyUrl', $url('copy'));
        $this->smarty->assign('durationsUrl', $url('durations'));
        $this->smarty->assign('debugLogUrl', $url('debug_log'));
        // the diagnostic mode can also be switched on on the server for users whose UUID is in this file, e.g. for an
        // installed app that has no address field for the parameter media_debug
        $debugUsersFile = ADMIDIO_PATH . FOLDER_DATA . '/media_player_debug_users.txt';
        $this->smarty->assign('debugUser', is_file($debugUsersFile)
            && in_array($GLOBALS['gCurrentUser']->getValue('usr_uuid'), array_map('trim', file($debugUsersFile)), true));
        // cover image for the media controls of phones, if the web app manifest has no icons
        $this->smarty->assign('artwork', json_encode(array(
            array('src' => ADMIDIO_URL . '/system/logo/admidio_logo_192.png', 'sizes' => '192x192', 'type' => 'image/png'),
            array('src' => ADMIDIO_URL . '/system/logo/admidio_logo_512.png', 'sizes' => '512x512', 'type' => 'image/png')
        )));
        $this->smarty->assign('linkDurationUrl', $url('link_duration'));
        $this->smarty->assign('markersUrl', $url('markers'));
        $this->smarty->assign('markerSaveUrl', $url('marker_save'));
        $this->smarty->assign('markerDeleteUrl', $url('marker_delete'));
        $this->smarty->assign('canShareMarkers', MediaPlayer::isPlaylistEditor());
        $this->smarty->assign('validLogin', $GLOBALS['gValidLogin']);
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);

        if ($editMode) {
            // the playlist and the media browser are shown side by side, so the edit mode uses the full width
            $this->setContentFullWidth();
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-ui/jquery-ui.min.js');
            $this->addJavascriptFile(MediaPlayer::getAssetUrl('js/media_player_edit.js'));
            $this->smarty->assign('mediaTreeUrl', $url('media_tree'));
            $this->smarty->assign('addUrl', $url('add'));
            $this->smarty->assign('reorderUrl', $url('reorder'));
            $this->addHtmlByTemplate('plugin.media-player.playlist-edit.tpl');
        } else {
            $this->addHtmlByTemplate('plugin.media-player.playlist.tpl');
        }
    }

    /**
     * Create the form to create a new playlist or to edit the name and the description of a playlist.
     * @param string $playlistUuid The UUID of the playlist or an empty string for a new playlist.
     * @param string $folderUuid The folder in which a new playlist is created (UUID of the folder or name of a root).
     * @return void
     * @throws Exception
     */
    public function createEditForm(string $playlistUuid, string $folderUuid = ''): void
    {
        global $gDb, $gL10n, $gCurrentSession;

        $playlistService = new PlaylistService($gDb);
        $playlist = $playlistService->getEditablePlaylist($playlistUuid);

        $this->setHtmlID('adm_media_player_playlist_edit');
        if ($playlistUuid === '') {
            $this->setHeadline($gL10n->get('PLG_MEDIA_PLAYER_CREATE_PLAYLIST'));
        } else {
            $this->setHeadline($gL10n->get('PLG_MEDIA_PLAYER_EDIT_PLAYLIST'));
        }

        $form = new FormPresenter(
            'adm_media_player_edit_form',
            'plugin.media-player.edit.tpl',
            SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'save', 'playlist_uuid' => $playlistUuid)),
            $this
        );
        $form->addInput('mpl_name', $gL10n->get('SYS_NAME'), (string)$playlist->getValue('mpl_name'),
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED));
        $form->addMultilineTextInput('mpl_description', $gL10n->get('SYS_DESCRIPTION'), (string)$playlist->getValue('mpl_description'), 4);

        // the folder of the playlist: a new playlist is created in the folder the user came from
        $folderService = new FolderService($gDb);
        if ($playlist->isNewRecord()) {
            $currentFolderUuid = $folderUuid === '' ? FolderService::ROOT_SHARED : $folderUuid;
            // the user came from a shared folder, so a user who may share playlists creates a shared one
            $private = !MediaPlayer::isPlaylistEditor() || $folderService->isPrivate($currentFolderUuid)
                || !$folderService->canCreateIn($currentFolderUuid);
            if (!$private && $folderUuid === '') {
                // the page was not opened from a folder: new playlists are private by default, so nobody
                // shares a playlist by accident
                $private = true;
            }
        } else {
            $private = !empty($playlist->getValue('mpl_usr_id_owner'));
            $currentFolderUuid = $playlist->getValue('mpl_mpf_id') === null ? ($private ? FolderService::ROOT_PRIVATE : FolderService::ROOT_SHARED)
                : (string)(new \AdmidioPlugin\MediaPlayer\Entity\PlaylistFolder($gDb, (int)$playlist->getValue('mpl_mpf_id')))->getValue('mpf_uuid');
        }
        $rootUuid = $private ? FolderService::ROOT_PRIVATE : FolderService::ROOT_SHARED;
        if ($folderService->isPrivate($currentFolderUuid) !== $private) {
            $currentFolderUuid = $rootUuid;
        }
        $this->addTreeViewFiles();
        $form->addTreeSelect(
            'media_player_folder_uuid',
            $gL10n->get('SYS_FOLDER'),
            SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_tree')),
            array('id' => $rootUuid, 'name' => $gL10n->get($private ? 'PLG_MEDIA_PLAYER_MY_PLAYLISTS' : 'PLG_MEDIA_PLAYER_NAME'), 'selectable' => true),
            array(
                'value' => $currentFolderUuid,
                'idParam' => 'folder_uuid',
                'currentId' => $currentFolderUuid,
                'currentLabel' => $gL10n->get('PLG_MEDIA_PLAYER_CURRENT_FOLDER'),
                'currentSelectable' => true,
                'path' => $folderService->findPath($currentFolderUuid),
                'helpTextId' => 'PLG_MEDIA_PLAYER_FOLDER_DESC'
            )
        );

        // users who could not create shared playlists always create private playlists, so they don't need the checkbox;
        // new playlists are private by default, so nobody shares a playlist by accident
        if ($playlistService->canChangeVisibility($playlist)) {
            $form->addCheckbox('media_player_private', $gL10n->get('PLG_MEDIA_PLAYER_PRIVATE_PLAYLIST'),
                $private, array('helpTextId' => 'PLG_MEDIA_PLAYER_PRIVATE_PLAYLIST_DESC'));
        }
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $playlist->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $playlist->getValue('mpl_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $playlist->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $playlist->getValue('mpl_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create the form to create a folder or to rename and move a folder.
     * @param string $folderUuid The UUID of the folder or an empty string for a new folder.
     * @param string $parentUuid The parent folder of a new folder (UUID of the folder or name of a root).
     * @return void
     * @throws Exception
     */
    public function createFolderForm(string $folderUuid, string $parentUuid = ''): void
    {
        global $gDb, $gL10n, $gCurrentSession;

        $folderService = new FolderService($gDb);
        $folder = $folderService->getEditableFolder($folderUuid);
        $parentUuid = $folder->isNewRecord() ? ($parentUuid === '' ? FolderService::ROOT_SHARED : $parentUuid) : $folderService->findParentUuid($folder);
        if ($folder->isNewRecord() && !$folderService->canCreateIn($parentUuid)) {
            throw new Exception('SYS_NO_RIGHTS');
        }
        $private = $folderService->isPrivate($parentUuid);
        $rootUuid = $private ? FolderService::ROOT_PRIVATE : FolderService::ROOT_SHARED;

        $this->setHtmlID('adm_media_player_folder_edit');
        $this->setHeadline($gL10n->get($folder->isNewRecord() ? 'PLG_MEDIA_PLAYER_CREATE_FOLDER' : 'PLG_MEDIA_PLAYER_EDIT_FOLDER'));

        $form = new FormPresenter(
            'adm_media_player_folder_form',
            'plugin.media-player.folder-edit.tpl',
            SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_save', 'folder_uuid' => $folderUuid)),
            $this
        );
        $form->addInput('mpf_name', $gL10n->get('SYS_NAME'), (string)$folder->getValue('mpf_name'),
            array('maxLength' => 255, 'property' => FormPresenter::FIELD_REQUIRED));
        $this->addTreeViewFiles();
        $form->addTreeSelect(
            'media_player_parent_uuid',
            $gL10n->get('PLG_MEDIA_PLAYER_PARENT_FOLDER'),
            SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'folder_tree')),
            array('id' => $rootUuid, 'name' => $gL10n->get($private ? 'PLG_MEDIA_PLAYER_MY_PLAYLISTS' : 'PLG_MEDIA_PLAYER_NAME'), 'selectable' => $folderService->canCreateIn($rootUuid)),
            array(
                'value' => $parentUuid,
                'idParam' => 'folder_uuid',
                'currentId' => $parentUuid,
                'currentLabel' => $gL10n->get('PLG_MEDIA_PLAYER_CURRENT_FOLDER'),
                'currentSelectable' => true,
                'path' => $folderService->findPath($parentUuid),
                // a folder can't be moved into itself or into its own subfolders
                'excludedId' => $folder->isNewRecord() ? '' : $folderUuid
            )
        );
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $folder->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $folder->getValue('mpf_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $folder->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $folder->getValue('mpf_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
