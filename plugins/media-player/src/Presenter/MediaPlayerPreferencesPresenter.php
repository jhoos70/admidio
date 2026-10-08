<?php

namespace AdmidioPlugin\MediaPlayer\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PreferencesPresenter;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

/**
 ***********************************************************************************************
 * Preferences panel of the media player plugin
 *
 * The roles that may edit playlists can't be described by the manifest alone, so the plugin
 * creates the form of its preferences itself.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class MediaPlayerPreferencesPresenter
{
    /**
     * The template that shows the form. It lives in the templates directory of the plugin and can be
     * overridden by a theme.
     */
    private const TEMPLATE = 'preferences.plugin.media-player.tpl';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Create the form with the preferences of the plugin.
     * @param PreferencesPresenter $page The preferences page the panel is shown in.
     * @return string Returns the html of the form.
     * @throws Exception|\Smarty\Exception
     */
    public static function createForm(PreferencesPresenter $page): string
    {
        global $gL10n, $gCurrentSession;

        $plugin = MediaPlayer::getPlugin();
        $settings = $plugin->settings;
        $values = $plugin->getSettingValues();

        $form = new FormPresenter(
            'adm_preferences_form_media_player',
            self::TEMPLATE,
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/preferences.php', array('mode' => 'save', 'panel' => 'media_player')),
            null,
            array('class' => 'form-preferences')
        );
        $form->addSelectBox(
            'media_player_plugin_enabled',
            Language::translateIfTranslationStrId($settings['media_player_plugin_enabled']['label']),
            array(
                '0' => $gL10n->get('SYS_DISABLED'),
                '1' => $gL10n->get('SYS_ENABLED'),
                '2' => $gL10n->get('ORG_ONLY_FOR_REGISTERED_USER')
            ),
            array('defaultValue' => $values['media_player_plugin_enabled'], 'showContextDependentFirstEntry' => false,
                'helpTextId' => $settings['media_player_plugin_enabled']['description'])
        );

        $roles = MediaPlayer::getAvailableRoles();
        $form->addSelectBox(
            'media_player_roles_edit',
            Language::translateIfTranslationStrId($settings['media_player_roles_edit']['label']),
            $roles,
            array('defaultValue' => $values['media_player_roles_edit'], 'showContextDependentFirstEntry' => false,
                'helpTextId' => $settings['media_player_roles_edit']['description'], 'multiselect' => true,
                'maximumSelectionNumber' => max(1, count($roles)))
        );
        $form->addSubmitButton(
            'adm_button_save_media_player',
            $gL10n->get('SYS_SAVE'),
            array('icon' => 'bi-check-lg', 'class' => 'offset-sm-3')
        );

        $form->addToSmarty($page->getSmartyTemplate());
        $gCurrentSession->addFormObject($form);

        return $plugin->renderTemplate($page, self::TEMPLATE);
    }
}
