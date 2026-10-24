<?php

namespace AdmidioPlugin\MediaPlayer;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\Roles\Service\RolesService;
use AdmidioPlugin\MediaPlayer\Presenter\MediaPlayerPreferencesPresenter;

/**
 ***********************************************************************************************
 * Media player
 *
 * Playlists with audio and video files of the module documents & files and with weblinks.
 * The plugin has its own page and is not shown on the overview page.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
final class MediaPlayer
{
    public const PLUGIN_ID = 'media-player';

    public const TABLE_PLAYLISTS = TABLE_PREFIX . '_media_playlists';
    public const TABLE_PLAYLIST_ITEMS = TABLE_PREFIX . '_media_playlist_items';
    public const TABLE_DURATIONS = TABLE_PREFIX . '_media_durations';

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Register what the plugin contributes. The playlists are a page of their own, so only the
     * preferences panel has to be registered.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        Hooks::addFilter(
            PluginPanel::HOOK,
            static function (array $panels) use ($plugin): array {
                global $gL10n;

                $panels[] = array(
                    'id' => PluginPanel::normalizeId($plugin->id),
                    'title' => $gL10n->get($plugin->name),
                    'icon' => $plugin->icon,
                    'create' => array(MediaPlayerPreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * Get the plugin object of the media player.
     * @return Plugin
     * @throws Exception
     */
    public static function getPlugin(): Plugin
    {
        return PluginRegistry::requireEnabled(self::PLUGIN_ID);
    }

    /**
     * Get the URL of the page of the plugin.
     * @return string
     * @throws Exception
     */
    public static function getPageUrl(): string
    {
        return self::getPlugin()->getUrl('index.php');
    }

    /**
     * Get the active roles of the current organization for the preferences.
     * @return array Returns an array with arrays of role id and role name.
     * @throws Exception
     */
    public static function getAvailableRoles(): array
    {
        global $gDb;

        $roles = array();
        $rolesService = new RolesService($gDb);

        foreach ($rolesService->findAll(1) as $row) {
            $roles[] = array($row['rol_id'], $row['rol_name']);
        }
        return $roles;
    }

    /**
     * Check if the current user may create shared playlists and edit the shared playlists the user has created.
     * Administrators and playlist managers may always do this, other users must be a member of one of the
     * roles that are set in the preferences.
     * @return bool Returns **true** if the current user may create shared playlists.
     * @throws Exception
     */
    public static function isPlaylistEditor(): bool
    {
        return self::isPlaylistManager() || self::isMemberOfSettingRoles('media_player_roles_edit');
    }

    /**
     * Check if the current user may edit and delete all shared playlists. Administrators may always do this,
     * other users must be a member of one of the roles that are set in the preferences.
     * @return bool Returns **true** if the current user may manage all shared playlists.
     * @throws Exception
     */
    public static function isPlaylistManager(): bool
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }
        return $gCurrentUser->isAdministrator() || self::isMemberOfSettingRoles('media_player_roles_manage');
    }

    /**
     * Check if the current user may change a playlist. A private playlist could only be changed by its owner.
     * A shared playlist could be changed by playlist managers and by the playlist editor who has created it.
     * @param int|null $ownerId The id of the user who owns the playlist or **null** for a shared playlist.
     * @param int|null $creatorId The id of the user who has created the playlist.
     * @return bool Returns **true** if the current user may change the playlist.
     * @throws Exception
     */
    public static function canEditPlaylist(?int $ownerId, ?int $creatorId): bool
    {
        global $gCurrentUserId, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }
        if ($ownerId !== null) {
            return $ownerId === $gCurrentUserId;
        }
        return self::isPlaylistManager() || (self::isPlaylistEditor() && $creatorId === $gCurrentUserId);
    }

    /**
     * Check if the current user is a member of one of the roles of a role setting of the plugin.
     * @param string $setting The name of the setting with the role ids.
     * @return bool Returns **true** if the current user is a member of one of the roles.
     * @throws Exception
     */
    private static function isMemberOfSettingRoles(string $setting): bool
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }

        $config = self::getPlugin()->getSettingValues();
        $roles = array_map('intval', (array)($config[$setting] ?? array()));

        return count(array_intersect($roles, $gCurrentUser->getRoleMemberships())) > 0;
    }

    /**
     * Throws an exception if the plugin is not visible for the current user. Admidio already refuses the page
     * if the plugin is not installed or not enabled. If the plugin is only available for registered users and
     * the user is not logged in, the login page will be shown.
     * @return void
     * @throws Exception
     */
    public static function checkAccess(): void
    {
        global $gValidLogin;

        $config = self::getPlugin()->getSettingValues();
        $access = (int)$config['media_player_plugin_enabled'];

        if ($access === PluginWidget::ACCESS_NOBODY) {
            throw new Exception('SYS_MODULE_DISABLED');
        }
        if ($access === PluginWidget::ACCESS_REGISTERED_USERS && !$gValidLogin) {
            require(ADMIDIO_PATH . '/system/login_valid.php');
        }
    }
}
