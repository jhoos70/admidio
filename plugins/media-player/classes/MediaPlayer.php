<?php

namespace MediaPlayer\classes;

use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\UI\Presenter\PagePresenter;
use Exception;

/**
 * Class MediaPlayer
 *
 * Media Player Plugin for Admidio (Audio, Video, Playlists, Offline ZIP Download).
 * Fully aligned and compatible with Admidio's PluginManager (PluginAbstract / PluginInterface).
 *
 * @copyright Antigravity & Querbeat Chor
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
if (class_exists('Admidio\Infrastructure\Plugins\PluginAbstract')) {
    class MediaPlayer extends PluginAbstract
    {
        /**
         * Standalone module plugin, not an overview dashboard widget.
         * Returning false directly ensures compatibility across Admidio 5.0.x and 5.1+.
         *
         * @return bool
         */
        public static function isOverviewPlugin(): bool
        {
            return false;
        }

        /**
         * @param PagePresenter|null $page
         * @return bool
         */
        public static function doRender(?PagePresenter $page = null): bool
        {
            return true;
        }

        /**
         * Check if the plugin is installed. Checks both adm_components and standalone tables.
         *
         * @return bool
         */
        public static function isInstalled(): bool
        {
            global $gDb;
            if (parent::isInstalled()) {
                return true;
            }
            // Standalone mode check
            return $gDb->tableExists('adm_media_items');
        }

        /**
         * Check if the plugin is activated
         *
         * @return bool
         */
        public static function isActivated(): bool
        {
            return self::isInstalled();
        }

        /**
         * Visibility check for menu / navigation
         *
         * @return bool
         */
        public static function isVisible(): bool
        {
            global $gValidLogin;
            return (bool)$gValidLogin;
        }

        /**
         * Custom installation tasks
         *
         * @param bool $addMenuEntry
         * @return bool
         * @throws Exception
         */
        public static function doInstall(bool $addMenuEntry = true): bool
        {
            $installed = parent::doInstall($addMenuEntry);

            if ($installed) {
                // Ensure storage directory exists
                $storagePath = ADMIDIO_PATH . FOLDER_DATA . '/media';
                if (!is_dir($storagePath)) {
                    @mkdir($storagePath, 0775, true);
                }
                if (!is_dir($storagePath . '/tmp')) {
                    @mkdir($storagePath . '/tmp', 0775, true);
                }
            }

            return $installed;
        }

        /**
         * Custom uninstallation tasks
         *
         * @param bool $removeMenuEntry
         * @param array $options
         * @return bool
         * @throws Exception
         */
        public static function doUninstall(bool $removeMenuEntry = true, array $options = array()): bool
        {
            return parent::doUninstall($removeMenuEntry, $options);
        }
    }
} else {
    // Fallback stub for older Admidio releases (prior to PluginManager)
    class MediaPlayer
    {
        public static function getInstance(): self
        {
            static $instance = null;
            if ($instance === null) {
                $instance = new self();
            }
            return $instance;
        }

        public static function doRender(?PagePresenter $page = null): bool
        {
            return true;
        }

        public static function isOverviewPlugin(): bool
        {
            return false;
        }

        public static function isInstalled(): bool
        {
            return true;
        }

        public static function isActivated(): bool
        {
            return true;
        }

        public static function isVisible(): bool
        {
            return true;
        }
    }
}
