<?php

namespace MediaPlayer\classes\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use MediaPlayer\classes\MediaPlayer;

/**
 ***********************************************************************************************
 * A playlist of the media player plugin
 *
 * A playlist belongs to an organization and contains items that reference files of the
 * module documents & files or weblinks.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class Playlist extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table media_playlists.
     * If the id is set than the specific playlist will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $mplId The recordset of the playlist with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $mplId = 0)
    {
        parent::__construct($database, MediaPlayer::TABLE_PLAYLISTS, 'mpl', $mplId);
    }

    /**
     * @return string|null Returns the hook ID of this entity.
     * @see Entity::getHookId()
     */
    public function getHookId(): ?string
    {
        return 'media_playlist';
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * Only playlists of the current organization could be read.
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

    /**
     * Save all changed columns of the recordset in table of database. A new playlist will be assigned to the
     * current organization.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord) {
            $this->setValue('mpl_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }
}
