<?php

namespace MediaPlayer\classes\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use MediaPlayer\classes\MediaPlayer;

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
    public const MOVE_UP = 'UP';
    public const MOVE_DOWN = 'DOWN';

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
     * Change the sequence of the item within its playlist. The item will be swapped with the previous
     * or the next item.
     * @param string $mode The direction of the move, **MOVE_UP** or **MOVE_DOWN**.
     * @return bool Returns **true** if the sequence was changed.
     * @throws Exception
     */
    public function moveSequence(string $mode): bool
    {
        $sequence = (int)$this->getValue('mpi_sequence');
        if ($mode === self::MOVE_UP) {
            $sql = 'SELECT mpi_id, mpi_sequence FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . '
                     WHERE mpi_mpl_id = ? AND mpi_sequence < ?
                     ORDER BY mpi_sequence DESC';
        } else {
            $sql = 'SELECT mpi_id, mpi_sequence FROM ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . '
                     WHERE mpi_mpl_id = ? AND mpi_sequence > ?
                     ORDER BY mpi_sequence ASC';
        }
        $neighbour = $this->db->queryPrepared($sql, array((int)$this->getValue('mpi_mpl_id'), $sequence))->fetch();

        if ($neighbour === false) {
            return false;
        }

        $sql = 'UPDATE ' . MediaPlayer::TABLE_PLAYLIST_ITEMS . ' SET mpi_sequence = ? WHERE mpi_id = ?';
        $this->db->queryPrepared($sql, array($sequence, (int)$neighbour['mpi_id']));
        $this->setValue('mpi_sequence', (int)$neighbour['mpi_sequence']);

        return $this->save();
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
