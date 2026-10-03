<?php

namespace MediaPlayer\classes;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Roles\Service\RolesService;
use Admidio\UI\Presenter\PagePresenter;

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
class MediaPlayer extends PluginAbstract
{
    public const TABLE_PLAYLISTS = TABLE_PREFIX . '_media_playlists';
    public const TABLE_PLAYLIST_ITEMS = TABLE_PREFIX . '_media_playlist_items';

    /**
     * The plugin has its own page, so nothing is rendered within other pages.
     * @param PagePresenter|null $page
     * @return bool
     */
    public static function doRender(?PagePresenter $page = null): bool
    {
        return true;
    }

    /**
     * Get the active roles of the current organization for the preferences.
     * @param bool $onlyIds If set to **true** only the ids of the roles are returned.
     * @return array Returns an array with the role ids or with arrays of role id and role name.
     * @throws Exception
     */
    public static function getAvailableRoles(bool $onlyIds = false): array
    {
        global $gDb;

        $roles = array();
        $rolesService = new RolesService($gDb);

        foreach ($rolesService->findAll(1) as $row) {
            if ($onlyIds) {
                $roles[] = (int)$row['rol_id'];
            } else {
                $roles[] = array($row['rol_id'], $row['rol_name']);
            }
        }
        return $roles;
    }

    /**
     * Check if the current user may create, edit and delete playlists. Administrators may always edit playlists,
     * other users must be a member of one of the roles that are set in the preferences.
     * @return bool Returns **true** if the current user may edit playlists.
     * @throws Exception
     */
    public static function isPlaylistEditor(): bool
    {
        global $gCurrentUser, $gValidLogin;

        if (!$gValidLogin) {
            return false;
        }
        if ($gCurrentUser->isAdministrator()) {
            return true;
        }

        $config = self::getPluginConfigValues();
        $editRoles = array_map('intval', (array)($config['media_player_roles_edit'] ?? array()));

        return count(array_intersect($editRoles, $gCurrentUser->getRoleMemberships())) > 0;
    }

    /**
     * Throws an exception if the plugin is not installed or not visible for the current user.
     * If the plugin is only available for registered users and the user is not logged in,
     * the login page will be shown.
     * @return void
     * @throws Exception
     */
    public static function checkAccess(): void
    {
        global $gValidLogin;

        if (!self::isInstalled()) {
            throw new Exception('SYS_PLUGIN_NOT_INSTALLED');
        }

        $config = self::getPluginConfigValues();
        $enabled = (int)($config['media_player_plugin_enabled'] ?? 0);

        if ($enabled === 0) {
            throw new Exception('SYS_MODULE_DISABLED');
        }
        if ($enabled === 2 && !$gValidLogin) {
            require(ADMIDIO_PATH . '/system/login_valid.php');
        }
    }
}
