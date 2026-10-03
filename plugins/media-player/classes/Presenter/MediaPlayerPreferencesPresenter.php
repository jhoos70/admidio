<?php

namespace MediaPlayer\classes\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use MediaPlayer\classes\MediaPlayer;
use Smarty\Smarty;

/**
 ***********************************************************************************************
 * Preferences panel of the media player plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class MediaPlayerPreferencesPresenter
{
    /**
     * Create the form with the preferences of the plugin.
     * @param Smarty $smarty The Smarty object of the preferences page.
     * @return string Returns the html of the form.
     * @throws Exception
     * @throws \Smarty\Exception
     */
    public static function createMediaPlayerForm(Smarty $smarty): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = MediaPlayer::getInstance();
        $formValues = $plugin::getPluginConfig();

        $form = new FormPresenter(
            'adm_preferences_form_media_player',
            $plugin::getPluginPath() . '/templates/preferences.plugin.media-player.tpl',
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'media_player')),
            null,
            array('class' => 'form-preferences')
        );

        $selectBoxEntries = array(
            '0' => $gL10n->get('SYS_DISABLED'),
            '1' => $gL10n->get('SYS_ENABLED'),
            '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
        );
        $form->addSelectBox(
            'media_player_plugin_enabled',
            Language::translateIfTranslationStrId($formValues['media_player_plugin_enabled']['name']),
            $selectBoxEntries,
            array('defaultValue' => $formValues['media_player_plugin_enabled']['value'], 'showContextDependentFirstEntry' => false,
                'helpTextId' => $formValues['media_player_plugin_enabled']['description'])
        );

        $roles = $plugin::getAvailableRoles();
        $form->addSelectBox(
            'media_player_roles_edit',
            Language::translateIfTranslationStrId($formValues['media_player_roles_edit']['name']),
            $roles,
            array('defaultValue' => $formValues['media_player_roles_edit']['value'], 'showContextDependentFirstEntry' => false,
                'helpTextId' => $formValues['media_player_roles_edit']['description'], 'multiselect' => true,
                'maximumSelectionNumber' => max(1, count($roles)))
        );
        $form->addSubmitButton(
            'adm_button_save_media_player',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $form->addToSmarty($smarty);
        $gCurrentSession->addFormObject($form);
        return $smarty->fetch($plugin::getPluginPath() . '/templates/preferences.plugin.media-player.tpl');
    }
}
