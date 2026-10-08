/**
 ***********************************************************************************************
 * Database tables of the media player plugin
 *
 * A playlist contains items that reference files of the module documents & files or weblinks.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*==============================================================*/
/* Table: media_playlists                                       */
/*==============================================================*/
CREATE TABLE %PREFIX%_media_playlists
(
    mpl_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    mpl_uuid                    varchar(36)         NOT NULL,
    mpl_org_id                  integer unsigned    NOT NULL,
    mpl_usr_id_owner            integer unsigned,
    mpl_name                    varchar(255)        NOT NULL,
    mpl_description             text,
    mpl_usr_id_create           integer unsigned,
    mpl_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    mpl_usr_id_change           integer unsigned,
    mpl_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (mpl_id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_mpl_uuid ON %PREFIX%_media_playlists (mpl_uuid);

/*==============================================================*/
/* Table: media_playlist_items                                  */
/*==============================================================*/
CREATE TABLE %PREFIX%_media_playlist_items
(
    mpi_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    mpi_uuid                    varchar(36)         NOT NULL,
    mpi_mpl_id                  integer unsigned    NOT NULL,
    mpi_fil_id                  integer unsigned,
    mpi_lnk_id                  integer unsigned,
    mpi_sequence                smallint            NOT NULL,
    mpi_usr_id_create           integer unsigned,
    mpi_timestamp_create        timestamp           NOT NULL    DEFAULT CURRENT_TIMESTAMP,
    mpi_usr_id_change           integer unsigned,
    mpi_timestamp_change        timestamp           NULL        DEFAULT NULL,
    PRIMARY KEY (mpi_id),
    CONSTRAINT %PREFIX%_chk_mpi_source CHECK ((mpi_fil_id IS NOT NULL AND mpi_lnk_id IS NULL) OR (mpi_fil_id IS NULL AND mpi_lnk_id IS NOT NULL))
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE UNIQUE INDEX %PREFIX%_idx_mpi_uuid ON %PREFIX%_media_playlist_items (mpi_uuid);

/*==============================================================*/
/* Constraints                                                  */
/*==============================================================*/
ALTER TABLE %PREFIX%_media_playlists
    ADD CONSTRAINT %PREFIX%_fk_mpl_org         FOREIGN KEY (mpl_org_id)         REFERENCES %PREFIX%_organizations (org_id)  ON DELETE RESTRICT ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpl_usr_owner   FOREIGN KEY (mpl_usr_id_owner)   REFERENCES %PREFIX%_users (usr_id)          ON DELETE CASCADE ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpl_usr_create  FOREIGN KEY (mpl_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)          ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpl_usr_change  FOREIGN KEY (mpl_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)          ON DELETE SET NULL ON UPDATE RESTRICT;

-- items are removed together with their playlist, file or weblink
ALTER TABLE %PREFIX%_media_playlist_items
    ADD CONSTRAINT %PREFIX%_fk_mpi_mpl         FOREIGN KEY (mpi_mpl_id)         REFERENCES %PREFIX%_media_playlists (mpl_id) ON DELETE CASCADE ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpi_fil         FOREIGN KEY (mpi_fil_id)         REFERENCES %PREFIX%_files (fil_id)           ON DELETE CASCADE ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpi_lnk         FOREIGN KEY (mpi_lnk_id)         REFERENCES %PREFIX%_links (lnk_id)           ON DELETE CASCADE ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpi_usr_create  FOREIGN KEY (mpi_usr_id_create)  REFERENCES %PREFIX%_users (usr_id)           ON DELETE SET NULL ON UPDATE RESTRICT,
    ADD CONSTRAINT %PREFIX%_fk_mpi_usr_change  FOREIGN KEY (mpi_usr_id_change)  REFERENCES %PREFIX%_users (usr_id)           ON DELETE SET NULL ON UPDATE RESTRICT;
