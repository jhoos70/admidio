-- Table: adm_media_items
CREATE TABLE IF NOT EXISTS adm_media_items (
    med_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    med_uuid VARCHAR(36) NOT NULL UNIQUE,
    med_type VARCHAR(20) NOT NULL,
    med_title VARCHAR(255) NOT NULL,
    med_artist VARCHAR(255) NULL,
    med_category VARCHAR(100) DEFAULT 'Gesamt',
    med_description TEXT NULL,
    med_source VARCHAR(1000) NOT NULL,
    med_filename VARCHAR(255) NULL,
    med_file_size BIGINT UNSIGNED DEFAULT 0,
    med_mime_type VARCHAR(100) NULL,
    med_duration INT UNSIGNED DEFAULT 0,
    med_play_count INT UNSIGNED DEFAULT 0,
    med_usr_id_create INT UNSIGNED NULL,
    med_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    med_timestamp_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_media_cat (med_category),
    INDEX idx_media_type (med_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: adm_media_playlists
CREATE TABLE IF NOT EXISTS adm_media_playlists (
    mpl_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mpl_uuid VARCHAR(36) NOT NULL UNIQUE,
    mpl_name VARCHAR(255) NOT NULL,
    mpl_description TEXT NULL,
    mpl_is_public TINYINT(1) DEFAULT 1,
    mpl_usr_id_create INT UNSIGNED NULL,
    mpl_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mpl_timestamp_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: adm_media_playlist_items
CREATE TABLE IF NOT EXISTS adm_media_playlist_items (
    mpi_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mpi_mpl_id INT UNSIGNED NOT NULL,
    mpi_med_id INT UNSIGNED NOT NULL,
    mpi_sequence INT UNSIGNED DEFAULT 1,
    mpi_timestamp_create TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mpi_playlist FOREIGN KEY (mpi_mpl_id) REFERENCES adm_media_playlists(mpl_id) ON DELETE CASCADE,
    CONSTRAINT fk_mpi_media FOREIGN KEY (mpi_med_id) REFERENCES adm_media_items(med_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
