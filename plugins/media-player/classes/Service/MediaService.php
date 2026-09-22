<?php
namespace MediaPlayer\classes\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Infrastructure\Exception;
use Ramsey\Uuid\Uuid;

/**
 * Service class for the Media Player Plugin
 */
class MediaService
{
    protected Database $db;
    protected string $storagePath = '';

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->storagePath = ADMIDIO_PATH . FOLDER_DATA . '/media';
        $this->ensureStorageDirectory();
        $this->ensureDatabaseTables();
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    protected function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }
    }

    public function ensureDatabaseTables(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS adm_media_items (
            med_id INT AUTO_INCREMENT PRIMARY KEY,
            med_uuid VARCHAR(36) NOT NULL UNIQUE,
            med_type VARCHAR(20) NOT NULL,
            med_title VARCHAR(255) NOT NULL,
            med_artist VARCHAR(255) NULL,
            med_category VARCHAR(100) NOT NULL DEFAULT 'Gesamt',
            med_description TEXT NULL,
            med_source TEXT NOT NULL,
            med_filename VARCHAR(255) NULL,
            med_file_size BIGINT NULL DEFAULT 0,
            med_mime_type VARCHAR(100) NULL,
            med_duration INT NULL DEFAULT 0,
            med_play_count INT NOT NULL DEFAULT 0,
            med_roles_view TEXT NULL,
            med_usr_id_create INT NULL,
            med_timestamp_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            med_timestamp_change DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_med_category (med_category),
            INDEX idx_med_type (med_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS adm_media_playlists (
            mpl_id INT AUTO_INCREMENT PRIMARY KEY,
            mpl_uuid VARCHAR(36) NOT NULL UNIQUE,
            mpl_name VARCHAR(255) NOT NULL,
            mpl_description TEXT NULL,
            mpl_usr_id_create INT NULL,
            mpl_timestamp_create DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            mpl_timestamp_change DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            mpl_is_public TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS adm_media_playlist_items (
            mpi_id INT AUTO_INCREMENT PRIMARY KEY,
            mpi_mpl_id INT NOT NULL,
            mpi_med_id INT NOT NULL,
            mpi_order INT NOT NULL DEFAULT 0,
            INDEX idx_mpi_playlist (mpi_mpl_id),
            INDEX idx_mpi_media (mpi_med_id),
            CONSTRAINT fk_mpi_playlist FOREIGN KEY (mpi_mpl_id) REFERENCES adm_media_playlists(mpl_id) ON DELETE CASCADE,
            CONSTRAINT fk_mpi_media FOREIGN KEY (mpi_med_id) REFERENCES adm_media_items(med_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            // Ignore if already created
        }
    }

    public function userCanManage(): bool
    {
        global $gCurrentUser, $gValidLogin;
        if (!$gValidLogin) {
            return false;
        }
        if ($gCurrentUser->isAdministrator()) {
            return true;
        }
        return false;
    }

    public function getAllMedia(?string $category = null, ?string $search = null): array
    {
        $params = array();
        $where = array('1=1');

        if (!empty($category) && $category !== 'all') {
            $where[] = 'med_category = ?';
            $params[] = $category;
        }

        if (!empty($search)) {
            $where[] = '(med_title LIKE ? OR med_artist LIKE ? OR med_description LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT * FROM adm_media_items WHERE ' . implode(' AND ', $where) . ' ORDER BY med_title ASC, med_id DESC';
        $stmt = $this->db->queryPrepared($sql, $params);
        return $stmt->fetchAll();
    }

    public function getAllCategories(): array
    {
        $sql = "SELECT DISTINCT med_category FROM adm_media_items WHERE med_category IS NOT NULL AND med_category != '' ORDER BY med_category ASC";
        $stmt = $this->db->query($sql);
        $cats = array();
        if ($stmt) {
            while ($row = $stmt->fetch()) {
                $cats[] = $row['med_category'];
            }
        }

        // Add standard choir categories if not present
        $defaults = array('Gesamt', 'Alt 1', 'Alt 2', 'Sopran 1', 'Sopran 2', 'Tenor', 'Bass', 'Probe', 'Konzert', 'Video');
        foreach ($defaults as $d) {
            if (!in_array($d, $cats, true)) {
                $cats[] = $d;
            }
        }
        return $cats;
    }

    public function getItemByUuid(string $uuid): ?array
    {
        $sql = 'SELECT * FROM adm_media_items WHERE med_uuid = ?';
        $stmt = $this->db->queryPrepared($sql, array($uuid));
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getItemById(int $id): ?array
    {
        $sql = 'SELECT * FROM adm_media_items WHERE med_id = ?';
        $stmt = $this->db->queryPrepared($sql, array($id));
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function createMediaItem(array $data): string
    {
        $uuid = (string)Uuid::uuid4();
        $sql = 'INSERT INTO adm_media_items 
            (med_uuid, med_type, med_title, med_artist, med_category, med_description, med_source, med_filename, med_file_size, med_mime_type, med_duration, med_usr_id_create, med_timestamp_create)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';
        
        $this->db->queryPrepared($sql, array(
            $uuid,
            $data['med_type'],
            $data['med_title'],
            $data['med_artist'] ?? null,
            $data['med_category'] ?? 'Gesamt',
            $data['med_description'] ?? null,
            $data['med_source'],
            $data['med_filename'] ?? null,
            (int)($data['med_file_size'] ?? 0),
            $data['med_mime_type'] ?? null,
            (int)($data['med_duration'] ?? 0),
            $data['med_usr_id_create'] ?? null
        ));

        return $uuid;
    }

    public function updateMediaItem(string $uuid, array $data): void
    {
        $fields = array();
        $params = array();

        foreach (array('med_title', 'med_artist', 'med_category', 'med_description') as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }

        if (!empty($fields)) {
            $params[] = $uuid;
            $sql = 'UPDATE adm_media_items SET ' . implode(', ', $fields) . ' WHERE med_uuid = ?';
            $this->db->queryPrepared($sql, $params);
        }
    }

    public function deleteMediaItem(string $uuid): void
    {
        $item = $this->getItemByUuid($uuid);
        if ($item) {
            if ($item['med_type'] === 'audio' || $item['med_type'] === 'video') {
                $filePath = $this->storagePath . '/' . $item['med_source'];
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            $sql = 'DELETE FROM adm_media_items WHERE med_uuid = ?';
            $this->db->queryPrepared($sql, array($uuid));
        }
    }

    public function handleFileUpload(array $filePost, string $title, ?string $artist, string $category, ?string $description): string
    {
        if (!isset($filePost['error']) || $filePost['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: code ' . ($filePost['error'] ?? 'unknown'));
        }

        $origName = $filePost['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedAudio = array('mp3', 'm4a', 'wav', 'ogg', 'aac', 'flac');
        $allowedVideo = array('mp4', 'webm', 'mov', 'm4v');

        $isAudio = in_array($ext, $allowedAudio, true);
        $isVideo = in_array($ext, $allowedVideo, true);

        if (!$isAudio && !$isVideo) {
            throw new Exception('Invalid file extension: ' . $ext);
        }

        $type = $isAudio ? 'audio' : 'video';
        $subDir = date('Y/m');
        $targetDir = $this->storagePath . '/' . $subDir;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $newFilename = (string)Uuid::uuid4() . '.' . $ext;
        $relPath = $subDir . '/' . $newFilename;
        $targetPath = $targetDir . '/' . $newFilename;

        if (!move_uploaded_file($filePost['tmp_name'], $targetPath)) {
            throw new Exception('Could not save uploaded file');
        }

        $mime = mime_content_type($targetPath) ?: ($isAudio ? 'audio/mpeg' : 'video/mp4');
        $size = filesize($targetPath);

        global $gCurrentUserId;
        return $this->createMediaItem(array(
            'med_type' => $type,
            'med_title' => !empty($title) ? $title : pathinfo($origName, PATHINFO_FILENAME),
            'med_artist' => $artist,
            'med_category' => !empty($category) ? $category : 'Gesamt',
            'med_description' => $description,
            'med_source' => $relPath,
            'med_filename' => $origName,
            'med_file_size' => $size,
            'med_mime_type' => $mime,
            'med_duration' => 0,
            'med_usr_id_create' => $gCurrentUserId
        ));
    }

    public function cleanupTempFiles(): void
    {
        $tempDir = $this->storagePath . '/tmp';
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*.part');
            $now = time();
            if ($files) {
                foreach ($files as $f) {
                    if ($now - filemtime($f) > 86400) {
                        @unlink($f);
                    }
                }
            }
        }
    }

    public function handleChunkUpload(array $postData, array $chunkFile): array
    {
        if (!isset($chunkFile['error']) || $chunkFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Chunk upload error: ' . ($chunkFile['error'] ?? 'unknown'));
        }

        $uploadId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $postData['upload_id'] ?? '');
        if (empty($uploadId)) {
            throw new Exception('Invalid upload ID');
        }

        $chunkIndex = (int)($postData['chunk_index'] ?? 0);
        $totalChunks = (int)($postData['total_chunks'] ?? 1);
        $origName = basename($postData['file_name'] ?? 'upload');
        $fileSize = (int)($postData['file_size'] ?? 0);

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedAudio = array('mp3', 'm4a', 'wav', 'ogg', 'aac', 'flac');
        $allowedVideo = array('mp4', 'webm', 'mov', 'm4v');

        $isAudio = in_array($ext, $allowedAudio, true);
        $isVideo = in_array($ext, $allowedVideo, true);

        if (!$isAudio && !$isVideo) {
            throw new Exception('Invalid file extension: ' . $ext);
        }

        $tempDir = $this->storagePath . '/tmp';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tempFilePath = $tempDir . '/' . $uploadId . '.part';

        // Append chunk
        $in = fopen($chunkFile['tmp_name'], 'rb');
        $out = fopen($tempFilePath, $chunkIndex === 0 ? 'wb' : 'ab');
        if (!$in || !$out) {
            if ($in) fclose($in);
            if ($out) fclose($out);
            throw new Exception('Could not write temporary upload chunk');
        }
        while ($buff = fread($in, 1048576)) {
            fwrite($out, $buff);
        }
        fclose($in);
        fclose($out);

        // If last chunk, finalize
        if ($chunkIndex + 1 >= $totalChunks) {
            $actualSize = filesize($tempFilePath);
            if ($fileSize > 0 && abs($actualSize - $fileSize) > 512) {
                @unlink($tempFilePath);
                throw new Exception("File size mismatch ($actualSize != $fileSize)");
            }

            $type = $isAudio ? 'audio' : 'video';
            $subDir = date('Y/m');
            $targetDir = $this->storagePath . '/' . $subDir;
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $newFilename = (string)Uuid::uuid4() . '.' . $ext;
            $relPath = $subDir . '/' . $newFilename;
            $targetPath = $targetDir . '/' . $newFilename;

            if (!rename($tempFilePath, $targetPath)) {
                if (!copy($tempFilePath, $targetPath)) {
                    @unlink($tempFilePath);
                    throw new Exception('Could not move temporary file to target directory');
                }
                @unlink($tempFilePath);
            }

            $mime = mime_content_type($targetPath) ?: ($isAudio ? 'audio/mpeg' : 'video/mp4');
            $title = trim($postData['med_title'] ?? '');
            $artist = trim($postData['med_artist'] ?? '');
            $category = trim($postData['med_category'] ?? 'Gesamt');
            $description = trim($postData['med_description'] ?? '');

            global $gCurrentUserId;
            $uuid = $this->createMediaItem(array(
                'med_type' => $type,
                'med_title' => !empty($title) ? $title : pathinfo($origName, PATHINFO_FILENAME),
                'med_artist' => $artist,
                'med_category' => !empty($category) ? $category : 'Gesamt',
                'med_description' => $description,
                'med_source' => $relPath,
                'med_filename' => $origName,
                'med_file_size' => $actualSize,
                'med_mime_type' => $mime,
                'med_duration' => 0,
                'med_usr_id_create' => $gCurrentUserId
            ));

            $this->cleanupTempFiles();

            return array(
                'status' => 'success',
                'completed' => true,
                'uuid' => $uuid
            );
        }

        return array(
            'status' => 'success',
            'completed' => false,
            'chunk' => $chunkIndex
        );
    }

    public function handleExternalLink(string $url, string $title, ?string $artist, string $category, ?string $description): string
    {
        $url = trim($url);
        $type = 'url';
        if (preg_match('/(youtube\.com|youtu\.be)/i', $url)) {
            $type = 'youtube';
        } elseif (preg_match('/vimeo\.com/i', $url)) {
            $type = 'vimeo';
        } elseif (preg_match('/\.(mp3|m4a|wav|ogg)(\?.*)?$/i', $url)) {
            $type = 'audio';
        } elseif (preg_match('/\.(mp4|webm|mov)(\?.*)?$/i', $url)) {
            $type = 'video';
        }

        global $gCurrentUserId;
        return $this->createMediaItem(array(
            'med_type' => $type,
            'med_title' => $title,
            'med_artist' => $artist,
            'med_category' => !empty($category) ? $category : 'Gesamt',
            'med_description' => $description,
            'med_source' => $url,
            'med_filename' => null,
            'med_file_size' => 0,
            'med_mime_type' => null,
            'med_duration' => 0,
            'med_usr_id_create' => $gCurrentUserId
        ));
    }

    public function streamMediaFile(string $uuid): void
    {
        $item = $this->getItemByUuid($uuid);
        if (!$item || $item['med_type'] === 'youtube' || $item['med_type'] === 'vimeo') {
            http_response_code(404);
            exit();
        }

        // Increment play count
        $this->db->queryPrepared('UPDATE adm_media_items SET med_play_count = med_play_count + 1 WHERE med_id = ?', array($item['med_id']));

        $filePath = $this->storagePath . '/' . $item['med_source'];
        if (!is_file($filePath)) {
            http_response_code(404);
            exit();
        }

        $fileSize = filesize($filePath);
        $mimeType = !empty($item['med_mime_type']) ? $item['med_mime_type'] : 'audio/mpeg';

        while (ob_get_level()) {
            ob_end_clean();
        }

        $fp = fopen($filePath, 'rb');
        $start = 0;
        $end = $fileSize - 1;

        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $mimeType);
        header('Cache-Control: private, max-age=86400');

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            if (preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
                if ($matches[1] !== '') {
                    $start = (int)$matches[1];
                }
                if ($matches[2] !== '') {
                    $end = (int)$matches[2];
                }
            }
            if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
                http_response_code(416);
                header('Content-Range: bytes */' . $fileSize);
                exit();
            }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$fileSize");
            header('Content-Length: ' . ($end - $start + 1));
        } else {
            http_response_code(200);
            header('Content-Length: ' . $fileSize);
        }

        fseek($fp, $start);
        $chunkSize = 1024 * 64;
        $bytesRemaining = ($end - $start) + 1;
        while (!feof($fp) && $bytesRemaining > 0 && connection_status() == 0) {
            $readBytes = min($chunkSize, $bytesRemaining);
            $data = fread($fp, $readBytes);
            echo $data;
            flush();
            $bytesRemaining -= strlen($data);
        }
        fclose($fp);
        exit();
    }

    public function downloadSingleFile(string $uuid): void
    {
        $item = $this->getItemByUuid($uuid);
        if (!$item || $item['med_type'] === 'youtube' || $item['med_type'] === 'vimeo') {
            http_response_code(404);
            exit();
        }

        $filePath = $this->storagePath . '/' . $item['med_source'];
        if (!is_file($filePath)) {
            http_response_code(404);
            exit();
        }

        $downloadFilename = $item['med_filename'] ?: ($item['med_title'] . '.' . pathinfo($item['med_source'], PATHINFO_EXTENSION));

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . ($item['med_mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadFilename) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        exit();
    }

    public function getAllPlaylists(): array
    {
        $sql = 'SELECT p.*, COUNT(i.mpi_id) AS item_count 
                FROM adm_media_playlists p 
                LEFT JOIN adm_media_playlist_items i ON i.mpi_mpl_id = p.mpl_id 
                GROUP BY p.mpl_id 
                ORDER BY p.mpl_name ASC';
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getPlaylistByUuid(string $uuid): ?array
    {
        $sql = 'SELECT * FROM adm_media_playlists WHERE mpl_uuid = ?';
        $stmt = $this->db->queryPrepared($sql, array($uuid));
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function createPlaylist(string $name, ?string $description): string
    {
        global $gCurrentUserId;
        $uuid = (string)Uuid::uuid4();
        $sql = 'INSERT INTO adm_media_playlists (mpl_uuid, mpl_name, mpl_description, mpl_usr_id_create, mpl_timestamp_create) VALUES (?, ?, ?, ?, NOW())';
        $this->db->queryPrepared($sql, array($uuid, $name, $description, $gCurrentUserId));
        return $uuid;
    }

    public function deletePlaylist(string $uuid): void
    {
        $pl = $this->getPlaylistByUuid($uuid);
        if ($pl) {
            $this->db->queryPrepared('DELETE FROM adm_media_playlists WHERE mpl_uuid = ?', array($uuid));
        }
    }

    public function getPlaylistItems(int $playlistId): array
    {
        $sql = 'SELECT m.*, i.mpi_id, i.mpi_order 
                FROM adm_media_playlist_items i 
                JOIN adm_media_items m ON m.med_id = i.mpi_med_id 
                WHERE i.mpi_mpl_id = ? 
                ORDER BY i.mpi_order ASC, i.mpi_id ASC';
        $stmt = $this->db->queryPrepared($sql, array($playlistId));
        return $stmt->fetchAll();
    }

    public function addItemToPlaylist(int $playlistId, int $mediaId): void
    {
        // Get max order
        $stmt = $this->db->queryPrepared('SELECT MAX(mpi_order) AS max_ord FROM adm_media_playlist_items WHERE mpi_mpl_id = ?', array($playlistId));
        $row = $stmt->fetch();
        $nextOrder = ($row && $row['max_ord'] !== null) ? ((int)$row['max_ord'] + 1) : 0;

        $sql = 'INSERT INTO adm_media_playlist_items (mpi_mpl_id, mpi_med_id, mpi_order) VALUES (?, ?, ?)';
        $this->db->queryPrepared($sql, array($playlistId, $mediaId, $nextOrder));
    }

    public function removeItemFromPlaylist(int $playlistId, int $mediaId): void
    {
        $sql = 'DELETE FROM adm_media_playlist_items WHERE mpi_mpl_id = ? AND mpi_med_id = ?';
        $this->db->queryPrepared($sql, array($playlistId, $mediaId));
    }

    public function downloadPlaylistZip(string $playlistUuid): void
    {
        $playlist = $this->getPlaylistByUuid($playlistUuid);
        if (!$playlist) {
            throw new Exception('Playlist not found');
        }

        $items = $this->getPlaylistItems($playlist['mpl_id']);
        if (empty($items)) {
            throw new Exception('Playlist is empty');
        }

        $safeName = preg_replace('/[^\w\s\d\-_~,;\[\]\(\).]/u', '_', $playlist['mpl_name']);
        $safeName = trim($safeName) ?: 'Playlist';
        $zipFilename = $safeName . '.zip';
        $tempZip = tempnam(sys_get_temp_dir(), 'mpl_zip_');

        $zip = new \ZipArchive();
        if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Could not create ZIP file');
        }

        $m3uContent = "#EXTM3U\n#PLAYLIST:" . $playlist['mpl_name'] . "\n\n";
        $externalLinks = "";
        $trackNum = 1;

        foreach ($items as $item) {
            $trackPrefix = sprintf('%02d - ', $trackNum);
            $title = $item['med_title'];
            $category = !empty($item['med_category']) ? " (" . $item['med_category'] . ")" : "";
            $artist = !empty($item['med_artist']) ? $item['med_artist'] . " - " : "";

            if ($item['med_type'] === 'audio' || $item['med_type'] === 'video') {
                $srcFile = $this->storagePath . '/' . $item['med_source'];
                if (is_file($srcFile)) {
                    $ext = pathinfo($item['med_filename'] ?: $item['med_source'], PATHINFO_EXTENSION) ?: 'mp3';
                    $destName = $trackPrefix . preg_replace('/[^\w\s\d\-_~,;\[\]\(\).]/u', '_', $title . $category) . '.' . $ext;
                    $zip->addFile($srcFile, $destName);

                    $duration = (int)($item['med_duration'] ?: -1);
                    $m3uContent .= "#EXTINF:$duration," . ($item['med_artist'] ? $item['med_artist'] . " - " : "") . $title . $category . "\n";
                    $m3uContent .= $destName . "\n\n";
                    $trackNum++;
                }
            } elseif ($item['med_type'] === 'youtube' || $item['med_type'] === 'vimeo' || $item['med_type'] === 'url') {
                $externalLinks .= $trackPrefix . $title . $category . ": " . $item['med_source'] . "\n";
                $m3uContent .= "#EXTINF:-1," . ($item['med_artist'] ? $item['med_artist'] . " - " : "") . $title . $category . "\n";
                $m3uContent .= $item['med_source'] . "\n\n";
                $trackNum++;
            }
        }

        $zip->addFromString($safeName . '.m3u8', $m3uContent);

        if (!empty($externalLinks)) {
            $zip->addFromString('Online_Links_Info.txt', "Online-Streams / Videos dieser Playlist:\n\n" . $externalLinks);
        }

        $zip->close();

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($tempZip));
        header('Cache-Control: private, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($tempZip);
        @unlink($tempZip);
        exit();
    }
}
