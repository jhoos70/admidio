<?php

namespace AdmidioPlugin\MediaPlayer\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\MediaPlayer\MediaPlayer;
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
        $this->addCssFile($plugin->getAssetUrl('libs/plyr/plyr.css'));
        $this->addCssFile($plugin->getAssetUrl('css/media_player.css'));
        $this->addJavascriptFile($plugin->getAssetUrl('libs/plyr/plyr.js'));
        $this->addJavascriptFile($plugin->getAssetUrl('js/media_player.js'));
    }

    /**
     * Create the list of all playlists of the current organization.
     * @return void
     * @throws Exception
     */
    public function createList(): void
    {
        global $gDb, $gL10n, $gCurrentSession, $gValidLogin;

        $this->setHtmlID('adm_media_player_playlists');
        $this->setHeadline($gL10n->get('PLG_MEDIA_PLAYER_NAME'));

        // every registered user could create private playlists
        if ($gValidLogin) {
            $this->addPageFunctionsMenuItem(
                'menu_item_media_player_add',
                $gL10n->get('PLG_MEDIA_PLAYER_CREATE_PLAYLIST'),
                SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'edit')),
                'bi-plus-circle-fill'
            );
        }

        $playlistService = new PlaylistService($gDb);
        $playlists = array();
        foreach ($playlistService->findAll() as $row) {
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
                'zipUrl' => SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'zip', 'playlist_uuid' => $row['mpl_uuid']))
            );
        }

        $this->smarty->assign('playlists', $playlists);
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
        $this->smarty->assign('validLogin', $GLOBALS['gValidLogin']);
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);

        if ($editMode) {
            // the playlist and the media browser are shown side by side, so the edit mode uses the full width
            $this->setContentFullWidth();
            $plugin = MediaPlayer::getPlugin();
            $this->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/jquery-ui/jquery-ui.min.js');
            $this->addJavascriptFile($plugin->getAssetUrl('js/media_player_edit.js'));
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
     * @return void
     * @throws Exception
     */
    public function createEditForm(string $playlistUuid): void
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
        // users who could not create shared playlists always create private playlists, so they don't need the checkbox;
        // new playlists are private by default, so nobody shares a playlist by accident
        if ($playlistService->canChangeVisibility($playlist)) {
            $form->addCheckbox('media_player_private', $gL10n->get('PLG_MEDIA_PLAYER_PRIVATE_PLAYLIST'),
                $playlist->isNewRecord() || !empty($playlist->getValue('mpl_usr_id_owner')), array('helpTextId' => 'PLG_MEDIA_PLAYER_PRIVATE_PLAYLIST_DESC'));
        }
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $playlist->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $playlist->getValue('mpl_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $playlist->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $playlist->getValue('mpl_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
