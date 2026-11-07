<?php

namespace AdmidioPlugin\MediaPlayer\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;
use AdmidioPlugin\MediaPlayer\MediaPlayer;

/**
 ***********************************************************************************************
 * A folder of the playlists of the media player plugin
 *
 * A folder belongs to an organization and contains playlists and other folders. It is shared with all users or
 * private for its owner, like the playlists in it.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class PlaylistFolder extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table media_playlist_folders.
     * If the id is set than the specific folder will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $mpfId The recordset of the folder with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $mpfId = 0)
    {
        parent::__construct($database, MediaPlayer::TABLE_FOLDERS, 'mpf', $mpfId);
    }

    /**
     * @return string|null Returns the hook ID of this entity.
     * @see Entity::getHookId()
     */
    public function getHookId(): ?string
    {
        return 'media_playlist_folder';
    }

    /**
     * Reads a record out of the table in database selected by the conditions of the param **$sqlWhereCondition** out of the table.
     * Only folders of the current organization could be read.
     * @param string $sqlWhereCondition Conditions for the table to select one record
     * @param array<int,mixed> $queryParams The query params for the prepared statement
     * @return bool Returns **true** if one record is found
     * @throws Exception
     */
    protected function readData(string $sqlWhereCondition, array $queryParams = array()): bool
    {
        if (parent::readData($sqlWhereCondition, $queryParams)) {
            if ((int)$this->getValue('mpf_org_id') !== $GLOBALS['gCurrentOrgId']) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            return true;
        }

        return false;
    }

    /**
     * Save all changed columns of the recordset in table of database. A new folder will be assigned to the
     * current organization.
     * @param bool $updateFingerPrint Default **true**. Will update the creator or editor of the recordset if table has columns like **usr_id_create** or **usr_id_changed**
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        if ($this->newRecord) {
            $this->setValue('mpf_org_id', $GLOBALS['gCurrentOrgId']);
        }

        return parent::save($updateFingerPrint);
    }
}
