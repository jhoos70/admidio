<?php

namespace MediaPlayer\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\Entity\RolesRights;

/**
 ***********************************************************************************************
 * Access checks of the media player plugin
 *
 * Playlist items reference files of the module documents & files and weblinks. A user only gets the
 * items that the user could also see in these modules. The checks are the same as in File::getFileForDownload()
 * and in the weblinks module, but without exceptions and with a cache for lists of items.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class MediaAccessService
{
    public const AUDIO_EXTENSIONS = array('mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'opus', 'flac');
    public const VIDEO_EXTENSIONS = array('mp4', 'm4v', 'mov', 'webm', 'ogv');
    /**
     * Mime types of the audio and video files for the browser player. The mime types of FileSystemUtils
     * couldn't be used because some of them are not supported by the browsers (e.g. audio/mpeg3 for mp3).
     * QuickTime videos mostly contain H.264, so they are announced as mp4 that browsers could play.
     */
    private const MIME_TYPES = array(
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'flac' => 'audio/flac',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mov' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg'
    );

    /**
     * @var array<int,bool> Cache of the view right of each folder for the current user.
     */
    private array $folderViewRights = array();
    /**
     * @var array<int,int>|null Cache of the weblink categories the current user could see.
     */
    private ?array $visibleLinkCategories = null;

    /**
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Check if the module documents & files could be used by the current user.
     * @return bool Returns **true** if the module is enabled for the current user.
     * @throws Exception
     */
    public function isDocumentsModuleAvailable(): bool
    {
        global $gSettingsManager, $gValidLogin;

        $enabled = $gSettingsManager->getInt('documents_files_module_enabled');
        return $enabled === 1 || ($enabled === 2 && $gValidLogin);
    }

    /**
     * Check if the module weblinks could be used by the current user.
     * @return bool Returns **true** if the module is enabled for the current user.
     * @throws Exception
     */
    public function isWeblinksModuleAvailable(): bool
    {
        global $gSettingsManager, $gValidLogin;

        $enabled = $gSettingsManager->getInt('weblinks_module_enabled');
        return $enabled === 1 || ($enabled === 2 && $gValidLogin);
    }

    /**
     * Check if the current user could view a file. The array must contain the columns of the
     * tables files and folders.
     * @param array<string,mixed> $row The columns of the file and its folder.
     * @return bool Returns **true** if the user could view the file.
     * @throws Exception
     */
    public function canViewFile(array $row): bool
    {
        global $gCurrentUser, $gCurrentOrgId;

        if (!$this->isDocumentsModuleAvailable() || (int)$row['fol_org_id'] !== $gCurrentOrgId) {
            return false;
        }
        if ($gCurrentUser->isAdministratorDocumentsFiles()) {
            return true;
        }
        if ((bool)$row['fil_locked']) {
            return false;
        }
        if ((bool)$row['fol_public']) {
            return true;
        }

        $folderId = (int)$row['fol_id'];
        if (!array_key_exists($folderId, $this->folderViewRights)) {
            $folderViewRoles = new RolesRights($this->db, 'folder_view', $folderId);
            $this->folderViewRights[$folderId] = $folderViewRoles->hasRight($gCurrentUser->getRoleMemberships());
        }
        return $this->folderViewRights[$folderId];
    }

    /**
     * Check if the current user could view a weblink. The array must contain the column lnk_cat_id.
     * @param array<string,mixed> $row The columns of the weblink.
     * @return bool Returns **true** if the user could view the weblink.
     * @throws Exception
     */
    public function canViewWeblink(array $row): bool
    {
        if (!$this->isWeblinksModuleAvailable()) {
            return false;
        }
        return in_array((int)$row['lnk_cat_id'], $this->getVisibleLinkCategories(), true);
    }

    /**
     * Get the ids of all weblink categories the current user could see.
     * @return array<int,int> Returns the ids of the categories.
     * @throws Exception
     */
    public function getVisibleLinkCategories(): array
    {
        global $gCurrentUser;

        if ($this->visibleLinkCategories === null) {
            $this->visibleLinkCategories = array_map('intval', $gCurrentUser->getAllVisibleCategories('LNK'));
        }
        return $this->visibleLinkCategories;
    }

    /**
     * Get the media type of file by its name.
     * @param string $fileName The name of the file with extension.
     * @return string Returns **audio**, **video** or an empty string if the file could not be played.
     */
    public static function getFileMediaType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }
        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }
        return '';
    }

    /**
     * Get the media type of weblink by its url. Links to YouTube and Vimeo are played in embedded players,
     * links to audio and video files in the browser player and all other links are opened in a new window.
     * @param string $url The url of the weblink.
     * @return string Returns **youtube**, **vimeo**, **audio**, **video** or **external**.
     */
    public static function getLinkMediaType(string $url): string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $path = (string)parse_url($url, PHP_URL_PATH);

        if (preg_match('/(^|\.)(youtube\.com|youtube-nocookie\.com|youtu\.be)$/', $host) === 1) {
            return 'youtube';
        }
        if (preg_match('/(^|\.)vimeo\.com$/', $host) === 1) {
            return 'vimeo';
        }

        $mediaType = self::getFileMediaType($path);
        if ($mediaType !== '') {
            return $mediaType;
        }
        return 'external';
    }

    /**
     * Get the mime type of file that could be used for the browser player.
     * @param string $fileName The name of the file with extension.
     * @return string Returns the mime type e.g. **audio/mpeg** or **application/octet-stream** if the file could not be played.
     */
    public static function getFileMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }
}
