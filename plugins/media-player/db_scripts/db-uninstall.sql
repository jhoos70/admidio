/**
 ***********************************************************************************************
 * Remove the database tables of the media player plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

DROP TABLE IF EXISTS %PREFIX%_media_playlist_items;
DROP TABLE IF EXISTS %PREFIX%_media_playlists;
