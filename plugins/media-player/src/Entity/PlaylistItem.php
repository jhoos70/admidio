<?php

namespace AdmidioPlugin\MediaPlayer\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

/**
 ***********************************************************************************************
 * An item of a playlist of the media player plugin
 *
 * An item references either a file of the module documents & files or a weblink.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class PlaylistItem extends Entity
{

    /**
     * Constructor that will create an object of a recordset of the table media_playlist_items.
     * If the id is set than the specific item will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $mpiId The recordset of the item with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $mpiId = 0)
    {
        // read also data of the assigned playlist to check the organization
        $this->connectAdditionalTable(MediaPlayer::TABLE_PLAYLISTS, 'mpl_id', 'mpi_mpl_id');

        parent::__construct($database, MediaPlayer::TABLE_PLAYLIST_ITEMS, 'mpi', $mpiId);
    }

    /**
     * @return string|null Returns the hook ID of this entity.
     * @see Entity::getHookId()
     */
    public function getHookId(): ?string
    {
        return 'media_playlist_item';
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * Only items of playlists of the current organization could be read.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            if ((int)$this->getValue('mpl_org_id') !== $GLOBALS['gCurrentOrgId']) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            return true;
        }

        return false;
    }
}
