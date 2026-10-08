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
        global $gDb, $gL10n, $gCurrentSession;

        $this->setHtmlID('adm_media_player_playlists');
        $this->setHeadline($gL10n->get('PLG_MEDIA_PLAYER_NAME'));

        $isEditor = MediaPlayer::isPlaylistEditor();
        if ($isEditor) {
            $this->addPageFunctionsMenuItem(
                'menu_item_media_player_add',
                $gL10n->get('PLG_MEDIA_PLAYER_CREATE_PLAYLIST'),
                SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'edit')),
                'bi-plus-circle-fill'
            );
        }

        $playlists = array();
        foreach ((new PlaylistService($gDb))->findAll() as $row) {
            $playlists[] = array(
                'uuid' => $row['mpl_uuid'],
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
        $this->smarty->assign('isEditor', $isEditor);
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);
        $this->addHtmlByTemplate('plugin.media-player.list.tpl');
    }

    /**
     * Create the page of a playlist with the player and the list of its items. Playlist editors
     * could also change the order of the items, remove items and add new files and weblinks.
     * @param string $playlistUuid The UUID of the playlist.
     * @return void
     * @throws Exception
     */
    public function createPlaylistPage(string $playlistUuid): void
    {
        global $gDb, $gL10n, $gCurrentSession;

        $playlistService = new PlaylistService($gDb);
        $playlist = $playlistService->getPlaylist($playlistUuid);
        $isEditor = MediaPlayer::isPlaylistEditor();

        $this->setHtmlID('adm_media_player_playlist');
        $this->setHeadline($playlist->getValue('mpl_name'));

        $items = $playlistService->findItems((int)$playlist->getValue('mpl_id'));
        if (count($items) > 0) {
            $this->addPageFunctionsMenuItem(
                'menu_item_media_player_zip',
                $gL10n->get('PLG_MEDIA_PLAYER_DOWNLOAD_ZIP'),
                SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'zip', 'playlist_uuid' => $playlistUuid)),
                'bi-file-earmark-zip-fill'
            );
        }
        if ($isEditor) {
            $this->addPageFunctionsMenuItem(
                'menu_item_media_player_edit',
                $gL10n->get('SYS_EDIT'),
                SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'edit', 'playlist_uuid' => $playlistUuid)),
                'bi-pencil-square'
            );
            $this->addJavascript('
                $(".admidio-media-item-move").click(function() {
                    moveTableRow($(this), "' . MediaPlayer::getPageUrl() . '", "' . $gCurrentSession->getCsrfToken() . '");
                });', true);
        }

        foreach ($items as $key => $item) {
            $items[$key]['removeUrl'] = SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'remove_item', 'item_uuid' => $item['uuid']));
        }

        $this->smarty->assign('playlistDescription', (string)$playlist->getValue('mpl_description'));
        $this->smarty->assign('items', $items);
        $this->smarty->assign('isEditor', $isEditor);
        $this->smarty->assign('csrfToken', $gCurrentSession->getCsrfToken());
        $this->smarty->assign('l10n', $gL10n);
        $this->addHtmlByTemplate('plugin.media-player.playlist.tpl');

        if ($isEditor) {
            $this->createAddItemsForm($playlistUuid, $playlistService);
        }
    }

    /**
     * Create the form to add files and weblinks to a playlist.
     * @param string $playlistUuid The UUID of the playlist.
     * @param PlaylistService $playlistService The service to read the selectable files and weblinks.
     * @return void
     * @throws Exception
     */
    private function createAddItemsForm(string $playlistUuid, PlaylistService $playlistService): void
    {
        global $gL10n, $gCurrentSession;

        $files = $playlistService->findSelectableFiles();
        $weblinks = $playlistService->findSelectableWeblinks();

        $form = new FormPresenter(
            'adm_media_player_items_form',
            'plugin.media-player.items.tpl',
            SecurityUtils::encodeUrl(MediaPlayer::getPageUrl(), array('mode' => 'add_items', 'playlist_uuid' => $playlistUuid)),
            $this
        );
        $form->addSelectBox(
            'media_player_files',
            $gL10n->get('SYS_DOCUMENTS_FILES'),
            $files,
            array('multiselect' => true, 'search' => true, 'placeholder' => $gL10n->get('PLG_MEDIA_PLAYER_CHOOSE_FILES'),
                'helpTextId' => 'PLG_MEDIA_PLAYER_CHOOSE_FILES_DESC', 'maximumSelectionNumber' => max(1, count($files)))
        );
        $form->addSelectBox(
            'media_player_weblinks',
            $gL10n->get('SYS_WEBLINKS'),
            $weblinks,
            array('multiselect' => true, 'search' => true, 'placeholder' => $gL10n->get('PLG_MEDIA_PLAYER_CHOOSE_WEBLINKS'),
                'helpTextId' => 'PLG_MEDIA_PLAYER_CHOOSE_WEBLINKS_DESC', 'maximumSelectionNumber' => max(1, count($weblinks)))
        );
        $form->addSubmitButton('adm_button_add_items', $gL10n->get('PLG_MEDIA_PLAYER_ADD_ITEMS'), array('icon' => 'bi-plus-circle'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
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

        $playlist = (new PlaylistService($gDb))->getEditablePlaylist($playlistUuid);

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
        $form->addSubmitButton('adm_button_save', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

        $this->assignSmartyVariable('userCreatedName', $playlist->getNameOfCreatingUser());
        $this->assignSmartyVariable('userCreatedTimestamp', $playlist->getValue('mpl_timestamp_create'));
        $this->assignSmartyVariable('lastUserEditedName', $playlist->getNameOfLastEditingUser());
        $this->assignSmartyVariable('lastUserEditedTimestamp', $playlist->getValue('mpl_timestamp_change'));
        $form->addToHtmlPage();
        $gCurrentSession->addFormObject($form);
    }
}
