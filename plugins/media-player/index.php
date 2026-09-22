<?php
/**
 ***********************************************************************************************
 * Media Player Plugin for Admidio
 *
 * Audio & Video Mediathek with in-browser playback, YouTube support, playlists and offline ZIP export.
 *
 * @copyright Antigravity & Admidio Community
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use MediaPlayer\classes\MediaPlayer;
use MediaPlayer\classes\Service\MediaService;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/classes/MediaPlayer.php');
    require_once(__DIR__ . '/classes/Service/MediaService.php');

    global $gDb, $gCurrentUser, $gValidLogin, $gL10n, $gNavigation;

    // Access check
    if (!$gValidLogin) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $mediaService = new MediaService($gDb);
    $canManage = $mediaService->userCanManage();

    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'list'));
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'string');
    $getPlaylistUuid = admFuncVariableIsValid($_GET, 'playlist_uuid', 'string');

    $pluginUrl = ADMIDIO_URL . FOLDER_PLUGINS . '/media-player';

    // -------------------------------------------------------------------------
    // STREAMING & DOWNLOAD MODES (No HTML wrapper)
    // -------------------------------------------------------------------------
    if ($getMode === 'stream') {
        if (empty($getUuid)) {
            http_response_code(400);
            exit();
        }
        $mediaService->streamMediaFile($getUuid);
        exit();
    }

    if ($getMode === 'download') {
        if (empty($getUuid)) {
            http_response_code(400);
            exit();
        }
        $mediaService->downloadSingleFile($getUuid);
        exit();
    }

    if ($getMode === 'download_playlist') {
        if (empty($getPlaylistUuid)) {
            http_response_code(400);
            exit();
        }
        $mediaService->downloadPlaylistZip($getPlaylistUuid);
        exit();
    }

    // -------------------------------------------------------------------------
    // ACTION MODES (POST)
    // -------------------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token'] ?? '');

        if ($getMode === 'chunk_upload') {
            header('Content-Type: application/json');
            if (!$canManage) {
                http_response_code(403);
                echo json_encode(array('error' => 'SYS_NO_RIGHTS'));
                exit();
            }

            if (!isset($_FILES['chunk'])) {
                http_response_code(400);
                echo json_encode(array('error' => 'Kein Datei-Chunk empfangen'));
                exit();
            }

            try {
                $res = $mediaService->handleChunkUpload($_POST, $_FILES['chunk']);
                echo json_encode($res);
            } catch (\Throwable $e) {
                http_response_code(500);
                echo json_encode(array('error' => $e->getMessage()));
            }
            exit();
        }

        if ($getMode === 'save_media') {
            if (!$canManage) {
                throw new Exception('SYS_NO_RIGHTS');
            }

            $sourceType = $_POST['source_type'] ?? 'file';
            $title = trim($_POST['med_title'] ?? '');
            $artist = trim($_POST['med_artist'] ?? '');
            $category = trim($_POST['med_category'] ?? 'Gesamt');
            $description = trim($_POST['med_description'] ?? '');

            if ($sourceType === 'file' && isset($_FILES['media_file']) && !empty($_FILES['media_file']['name'])) {
                $mediaService->handleFileUpload($_FILES['media_file'], $title, $artist, $category, $description);
            } elseif ($sourceType === 'url') {
                $url = trim($_POST['med_url'] ?? '');
                if (!empty($url)) {
                    $mediaService->handleExternalLink($url, $title ?: $url, $artist, $category, $description);
                }
            }

            admRedirect($pluginUrl . '/index.php');
            exit();
        }

        if ($getMode === 'delete_media') {
            if (!$canManage) {
                throw new Exception('SYS_NO_RIGHTS');
            }
            if (!empty($getUuid)) {
                $mediaService->deleteMediaItem($getUuid);
            }
            admRedirect($pluginUrl . '/index.php');
            exit();
        }

        if ($getMode === 'create_playlist') {
            $name = trim($_POST['playlist_name'] ?? '');
            $description = trim($_POST['playlist_description'] ?? '');
            if (!empty($name)) {
                $mediaService->createPlaylist($name, $description);
            }
            admRedirect($pluginUrl . '/index.php?mode=playlists');
            exit();
        }

        if ($getMode === 'delete_playlist') {
            if (!empty($getPlaylistUuid)) {
                $mediaService->deletePlaylist($getPlaylistUuid);
            }
            admRedirect($pluginUrl . '/index.php?mode=playlists');
            exit();
        }

        if ($getMode === 'add_to_playlist') {
            $playlistId = (int)($_POST['target_playlist_id'] ?? 0);
            $mediaId = (int)($_POST['target_media_id'] ?? 0);
            if ($playlistId > 0 && $mediaId > 0) {
                $mediaService->addItemToPlaylist($playlistId, $mediaId);
            }
            admRedirect($pluginUrl . '/index.php');
            exit();
        }

        if ($getMode === 'remove_from_playlist') {
            $playlistId = (int)($_POST['target_playlist_id'] ?? 0);
            $mediaId = (int)($_POST['target_media_id'] ?? 0);
            if ($playlistId > 0 && $mediaId > 0) {
                $mediaService->removeItemFromPlaylist($playlistId, $mediaId);
            }
            admRedirect($pluginUrl . '/index.php?mode=playlist_detail&playlist_uuid=' . $getPlaylistUuid);
            exit();
        }
    }

    // -------------------------------------------------------------------------
    // HTML VIEW MODES
    // -------------------------------------------------------------------------
    $pageHeadline = 'Audio & Video';
    $gNavigation->addStartUrl(CURRENT_URL, $pageHeadline, 'bi-music-note-beamed');

    $page = PagePresenter::withHtmlIDAndHeadline('plg_media_player', $pageHeadline);
    $page->addCssFile($pluginUrl . '/libs/plyr/plyr.css');
    $page->addCssFile($pluginUrl . '/css/media_player.css');
    $page->addJavascriptFile($pluginUrl . '/libs/plyr/plyr.js');
    $page->addJavascriptFile($pluginUrl . '/js/media_player.js');

    // Page action buttons in header
    if ($canManage) {
        $page->addPageFunctionsMenuItem(
            'btn_add_media',
            'Medium hinzufügen',
            'javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modal_add_media',
            'bi-cloud-arrow-up-fill'
        );
    }
    $page->addPageFunctionsMenuItem(
        'btn_nav_playlists',
        'Wiedergabelisten',
        $pluginUrl . '/index.php?mode=playlists',
        'bi-collection-play-fill'
    );
    $page->addPageFunctionsMenuItem(
        'btn_nav_all_media',
        'Alle Medien',
        $pluginUrl . '/index.php',
        'bi-music-note-list'
    );

    ob_start();

    // -------------------------------------------------------------------------
    // RENDER: PLAYLIST DETAIL VIEW
    // -------------------------------------------------------------------------
    if ($getMode === 'playlist_detail' && !empty($getPlaylistUuid)) {
        $playlist = $mediaService->getPlaylistByUuid($getPlaylistUuid);
        if (!$playlist) {
            echo '<div class="alert alert-danger">Playlist nicht gefunden.</div>';
        } else {
            $items = $mediaService->getPlaylistItems($playlist['mpl_id']);
            ?>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2><i class="bi bi-collection-play me-2 text-primary"></i><?= htmlspecialchars($playlist['mpl_name']) ?></h2>
                    <?php if (!empty($playlist['mpl_description'])): ?>
                        <p class="text-muted mb-0"><?= htmlspecialchars($playlist['mpl_description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= $pluginUrl ?>/index.php?mode=download_playlist&playlist_uuid=<?= $playlist['mpl_uuid'] ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-zip-fill me-1"></i> Playlist herunterladen (ZIP)
                    </a>
                    <a href="<?= $pluginUrl ?>/index.php?mode=playlists" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Zurück
                    </a>
                </div>
            </div>

            <!-- Player Card -->
            <?php renderPlayerCard(); ?>

            <!-- Playlist Items Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle admidio-media-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>Titel</th>
                                    <th>Interpret / Arrangeur</th>
                                    <th>Stimmgruppe</th>
                                    <th>Typ</th>
                                    <th class="text-end" style="width: 140px;">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-music-note mb-2 d-block fs-3"></i>
                                            Diese Playlist enthält noch keine Titel.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $idx => $item): 
                                        $streamUrl = ($item['med_type'] === 'audio' || $item['med_type'] === 'video') ? ($pluginUrl . '/index.php?mode=stream&uuid=' . $item['med_uuid']) : '';
                                        $downloadUrl = ($item['med_type'] === 'audio' || $item['med_type'] === 'video') ? ($pluginUrl . '/index.php?mode=download&uuid=' . $item['med_uuid']) : '';
                                    ?>
                                        <tr class="media-item-row" 
                                            data-uuid="<?= htmlspecialchars($item['med_uuid']) ?>"
                                            data-type="<?= htmlspecialchars($item['med_type']) ?>"
                                            data-stream-url="<?= htmlspecialchars($streamUrl) ?>"
                                            data-source="<?= htmlspecialchars($item['med_source']) ?>"
                                            data-title="<?= htmlspecialchars($item['med_title']) ?>"
                                            data-artist="<?= htmlspecialchars($item['med_artist'] ?? '') ?>"
                                            data-category="<?= htmlspecialchars($item['med_category']) ?>"
                                            data-mime="<?= htmlspecialchars($item['med_mime_type'] ?? '') ?>">
                                            <td class="text-center">
                                                <button class="media-play-btn" title="Abspielen">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['med_title']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($item['med_artist'] ?? '-') ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($item['med_category']) ?></span></td>
                                            <td>
                                                <span class="media-type-badge media-type-<?= htmlspecialchars($item['med_type']) ?>">
                                                    <?= htmlspecialchars(strtoupper($item['med_type'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!empty($downloadUrl)): ?>
                                                        <a href="<?= $downloadUrl ?>" class="btn btn-outline-secondary media-action-btn" title="Herunterladen">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <form method="post" action="<?= $pluginUrl ?>/index.php?mode=remove_from_playlist&playlist_uuid=<?= $playlist['mpl_uuid'] ?>" class="d-inline" onsubmit="return confirm('Aus Playlist entfernen?');">
                                                        <input type="hidden" name="adm_csrf_token" value="<?= $gCurrentSession->getCsrfToken() ?>">
                                                        <input type="hidden" name="target_playlist_id" value="<?= $playlist['mpl_id'] ?>">
                                                        <input type="hidden" name="target_media_id" value="<?= $item['med_id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger media-action-btn" title="Entfernen">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        }
    } 
    // -------------------------------------------------------------------------
    // RENDER: PLAYLISTS OVERVIEW
    // -------------------------------------------------------------------------
    elseif ($getMode === 'playlists') {
        $playlists = $mediaService->getAllPlaylists();
        ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2><i class="bi bi-collection-play me-2 text-primary"></i>Wiedergabelisten</h2>
                <p class="text-muted mb-0">Eigene Playlists zusammenstellen, im Browser anhören oder als ZIP-Paket herunterladen.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_create_playlist">
                <i class="bi bi-plus-circle me-1"></i> Neue Playlist erstellen
            </button>
        </div>

        <div class="row g-4">
            <?php if (empty($playlists)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center py-4">
                        <i class="bi bi-collection-play fs-2 mb-2 d-block"></i>
                        Noch keine Playlists angelegt. Klicke auf „Neue Playlist erstellen“, um eine hinzuzufügen.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($playlists as $pl): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h5 class="card-title mb-0">
                                        <a href="<?= $pluginUrl ?>/index.php?mode=playlist_detail&playlist_uuid=<?= $pl['mpl_uuid'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($pl['mpl_name']) ?>
                                        </a>
                                    </h5>
                                    <span class="badge bg-primary rounded-pill"><?= (int)$pl['item_count'] ?> Titel</span>
                                </div>
                                <?php if (!empty($pl['mpl_description'])): ?>
                                    <p class="card-text text-muted small mb-3"><?= htmlspecialchars($pl['mpl_description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-light border-top-0 d-flex justify-content-between align-items-center">
                                <a href="<?= $pluginUrl ?>/index.php?mode=playlist_detail&playlist_uuid=<?= $pl['mpl_uuid'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-play-circle me-1"></i> Ansehen &amp; Abspielen
                                </a>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($pl['item_count'] > 0): ?>
                                        <a href="<?= $pluginUrl ?>/index.php?mode=download_playlist&playlist_uuid=<?= $pl['mpl_uuid'] ?>" class="btn btn-sm btn-success" title="Als ZIP herunterladen">
                                            <i class="bi bi-file-earmark-zip-fill"></i> ZIP
                                        </a>
                                    <?php endif; ?>
                                    <form method="post" action="<?= $pluginUrl ?>/index.php?mode=delete_playlist&playlist_uuid=<?= $pl['mpl_uuid'] ?>" class="d-inline" onsubmit="return confirm('Playlist wirklich löschen?');">
                                        <input type="hidden" name="adm_csrf_token" value="<?= $gCurrentSession->getCsrfToken() ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Playlist löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    } 
    // -------------------------------------------------------------------------
    // RENDER: MAIN MEDIATHEK VIEW
    // -------------------------------------------------------------------------
    else {
        $allMedia = $mediaService->getAllMedia();
        $categories = $mediaService->getAllCategories();
        $allPlaylists = $mediaService->getAllPlaylists();
        ?>
        <!-- Top Player Card -->
        <?php renderPlayerCard(); ?>

        <!-- Category Badges / Filter -->
        <div class="admidio-category-nav">
            <span class="admidio-category-badge active" data-category="all">
                <i class="bi bi-grid-fill me-1"></i> Alle
            </span>
            <?php foreach ($categories as $cat): ?>
                <span class="admidio-category-badge" data-category="<?= htmlspecialchars($cat) ?>">
                    <?= htmlspecialchars($cat) ?>
                </span>
            <?php endforeach; ?>
        </div>

        <!-- Search & Control Bar -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-6 mb-2 mb-md-0">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="media_search_input" class="form-control border-start-0" placeholder="Titel, Interpret oder Stimmgruppe suchen...">
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted small me-3"><?= count($allMedia) ?> Medien verfügbar</span>
                <a href="<?= $pluginUrl ?>/index.php?mode=playlists" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-collection-play me-1"></i> Zu den Playlists
                </a>
            </div>
        </div>

        <!-- Media Table -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle admidio-media-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;"></th>
                                <th>Titel</th>
                                <th>Interpret / Arrangeur</th>
                                <th>Stimmgruppe</th>
                                <th>Typ</th>
                                <th class="text-end" style="width: 140px;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allMedia)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-music-note mb-2 d-block fs-3"></i>
                                        Noch keine Medien vorhanden. Klicke oben auf „Medium hinzufügen“, um Audio, Video oder YouTube-Links einzustellen.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allMedia as $item): 
                                    $streamUrl = ($item['med_type'] === 'audio' || $item['med_type'] === 'video') ? ($pluginUrl . '/index.php?mode=stream&uuid=' . $item['med_uuid']) : '';
                                    $downloadUrl = ($item['med_type'] === 'audio' || $item['med_type'] === 'video') ? ($pluginUrl . '/index.php?mode=download&uuid=' . $item['med_uuid']) : '';
                                ?>
                                    <tr class="media-item-row" 
                                        data-uuid="<?= htmlspecialchars($item['med_uuid']) ?>"
                                        data-type="<?= htmlspecialchars($item['med_type']) ?>"
                                        data-stream-url="<?= htmlspecialchars($streamUrl) ?>"
                                        data-source="<?= htmlspecialchars($item['med_source']) ?>"
                                        data-title="<?= htmlspecialchars($item['med_title']) ?>"
                                        data-artist="<?= htmlspecialchars($item['med_artist'] ?? '') ?>"
                                        data-category="<?= htmlspecialchars($item['med_category']) ?>"
                                        data-mime="<?= htmlspecialchars($item['med_mime_type'] ?? '') ?>">
                                        <td class="text-center">
                                            <button class="media-play-btn" title="Abspielen">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['med_title']) ?></strong>
                                            <?php if (!empty($item['med_description'])): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($item['med_description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($item['med_artist'] ?? '-') ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($item['med_category']) ?></span></td>
                                        <td>
                                            <span class="media-type-badge media-type-<?= htmlspecialchars($item['med_type']) ?>">
                                                <?= htmlspecialchars(strtoupper($item['med_type'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <?php if (!empty($downloadUrl)): ?>
                                                    <a href="<?= $downloadUrl ?>" class="btn btn-outline-secondary media-action-btn" title="Herunterladen">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($allPlaylists)): ?>
                                                    <button type="button" class="btn btn-outline-secondary media-action-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#modal_add_to_playlist"
                                                            data-media-id="<?= $item['med_id'] ?>" data-media-title="<?= htmlspecialchars($item['med_title']) ?>"
                                                            title="Zu Playlist hinzufügen">
                                                        <i class="bi bi-plus-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($canManage): ?>
                                                    <form method="post" action="<?= $pluginUrl ?>/index.php?mode=delete_media&uuid=<?= $item['med_uuid'] ?>" class="d-inline" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                                        <input type="hidden" name="adm_csrf_token" value="<?= $gCurrentSession->getCsrfToken() ?>">
                                                        <button type="submit" class="btn btn-outline-danger media-action-btn" title="Löschen">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // MODALS
    // -------------------------------------------------------------------------
    renderModals($canManage, $pluginUrl, $gCurrentSession->getCsrfToken(), $mediaService->getAllCategories(), $mediaService->getAllPlaylists());

    $htmlContent = ob_get_clean();
    $page->addHtml($htmlContent);
    $page->show();

} catch (\Throwable $e) {
    echo $e->getMessage();
}

/**
 * Helper to render the unified player card
 */
function renderPlayerCard(): void
{
    ?>
    <div class="admidio-player-card">
        <div class="admidio-player-header">
            <div class="d-flex align-items-center gap-2">
                <button id="btn_player_prev" class="btn btn-sm btn-outline-secondary" title="Vorheriger Titel">
                    <i class="bi bi-skip-backward-fill"></i>
                </button>
                <button id="btn_player_next" class="btn btn-sm btn-outline-secondary" title="Nächster Titel">
                    <i class="bi bi-skip-forward-fill"></i>
                </button>
                <div class="ms-2">
                    <h5 id="player_current_title" class="admidio-player-title mb-0">Titel auswählen</h5>
                    <div id="player_current_meta" class="media-now-info text-muted">Klicke unten auf einen Titel zum Abspielen</div>
                </div>
            </div>
        </div>
        <div id="admidio_player_wrapper" class="admidio-player-wrapper audio-mode">
            <div class="text-muted small py-3"><i class="bi bi-music-note-beamed me-2"></i>Warte auf Wiedergabe...</div>
        </div>
    </div>
    <?php
}

/**
 * Helper to render modals for Upload, Add Link, and Playlist Creation
 */
function renderModals(bool $canManage, string $pluginUrl, string $csrfToken, array $categories, array $playlists): void
{
    if ($canManage): ?>
        <!-- Modal: Medium hinzufügen -->
        <div class="modal fade" id="modal_add_media" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="form_add_media" method="post" action="<?= $pluginUrl ?>/index.php?mode=save_media" enctype="multipart/form-data">
                    <input type="hidden" name="adm_csrf_token" value="<?= $csrfToken ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-cloud-arrow-up-fill me-2 text-primary"></i>Medium hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Art des Mediums</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="source_type" id="type_file" value="file" checked onchange="toggleSourceType()">
                                    <label class="btn btn-outline-primary" for="type_file"><i class="bi bi-upload me-1"></i> Datei hochladen (Audio/Video)</label>
                                    
                                    <input type="radio" class="btn-check" name="source_type" id="type_url" value="url" onchange="toggleSourceType()">
                                    <label class="btn btn-outline-primary" for="type_url"><i class="bi bi-youtube me-1"></i> Externer Link (YouTube / Vimeo / URL)</label>
                                </div>
                            </div>

                            <div id="group_file" class="mb-3">
                                <label class="form-label fw-bold">Audiodatei oder Videodatei</label>
                                <input type="file" name="media_file" id="media_file_input" class="form-control" accept="audio/*,video/*,.mp3,.m4a,.wav,.ogg,.aac,.flac,.mp4,.webm">
                                <div class="form-text">Unterstützt: MP3, M4A, WAV, OGG, AAC, MP4, WEBM (automatischer Chunk-Upload auch für sehr große Dateien)</div>
                            </div>

                            <div id="group_url" class="mb-3 d-none">
                                <label class="form-label fw-bold">YouTube / Vimeo / Web-URL</label>
                                <input type="url" name="med_url" id="med_url_input" class="form-control" placeholder="https://www.youtube.com/watch?v=... oder https://youtu.be/...">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Titel <span class="text-danger">*</span></label>
                                    <input type="text" name="med_title" id="med_title_input" class="form-control" required placeholder="z. B. Viva la Vida">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Interpret / Komponist / Arrangeur</label>
                                    <input type="text" name="med_artist" id="med_artist_input" class="form-control" placeholder="z. B. Coldplay / Arr. Mark Brymer">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Kategorie / Stimmgruppe</label>
                                <div class="input-group">
                                    <select class="form-select" id="select_category" onchange="if(this.value==='__custom__'){document.getElementById('input_custom_category').classList.remove('d-none');document.getElementById('input_custom_category').focus();}else{document.getElementById('input_custom_category').classList.add('d-none');}">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                        <?php endforeach; ?>
                                        <option value="__custom__">+ Eigene Kategorie eingeben...</option>
                                    </select>
                                    <input type="text" id="input_custom_category" class="form-control d-none" placeholder="Neue Stimmgruppe/Kategorie">
                                </div>
                                <input type="hidden" name="med_category" id="hidden_med_category" value="Gesamt">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung / Notizen für die Probe</label>
                                <textarea name="med_description" id="med_description_input" class="form-control" rows="2" placeholder="z. B. Takt 16-32 besonders beachten, Einsätze auf 3+"></textarea>
                            </div>

                            <!-- Live Chunk Upload Progress & Error Alerts -->
                            <div id="upload_progress_container" class="mb-3 d-none">
                                <label class="form-label fw-bold">Upload-Fortschritt</label>
                                <div class="progress" style="height: 24px;">
                                    <div id="upload_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%;">0%</div>
                                </div>
                                <div id="upload_status_text" class="small text-muted mt-1">Wird hochgeladen...</div>
                            </div>

                            <div id="upload_error_alert" class="alert alert-danger d-none mb-3"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="btn_cancel_media">Abbrechen</button>
                            <button type="submit" class="btn btn-primary" id="btn_submit_media"><i class="bi bi-check-lg me-1"></i> Speichern</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function toggleSourceType() {
            const isFile = document.getElementById('type_file').checked;
            document.getElementById('group_file').classList.toggle('d-none', !isFile);
            document.getElementById('group_url').classList.toggle('d-none', isFile);
        }
        function syncCategoryField() {
            const sel = document.getElementById('select_category');
            const custom = document.getElementById('input_custom_category');
            const hidden = document.getElementById('hidden_med_category');
            if (sel.value === '__custom__' && custom.value.trim() !== '') {
                hidden.value = custom.value.trim();
            } else {
                hidden.value = sel.value;
            }
        }

        // Chunked file upload handling
        document.getElementById('form_add_media').addEventListener('submit', async function(e) {
            const isFile = document.getElementById('type_file').checked;
            syncCategoryField();

            if (!isFile) {
                // External URL: standard submit
                return;
            }

            const fileInput = document.getElementById('media_file_input');
            if (!fileInput.files || fileInput.files.length === 0) {
                return; // HTML5 required check
            }

            e.preventDefault();

            const file = fileInput.files[0];
            const titleInput = document.getElementById('med_title_input');
            const artistInput = document.getElementById('med_artist_input');
            const categoryInput = document.getElementById('hidden_med_category');
            const descInput = document.getElementById('med_description_input');
            const csrfInput = document.querySelector('input[name="adm_csrf_token"]');

            const title = titleInput.value.trim() || file.name.replace(/\.[^/.]+$/, '');
            const artist = artistInput.value.trim();
            const category = categoryInput.value.trim() || 'Gesamt';
            const description = descInput.value.trim();
            const csrfToken = csrfInput.value;

            const progressContainer = document.getElementById('upload_progress_container');
            const progressBar = document.getElementById('upload_progress_bar');
            const statusText = document.getElementById('upload_status_text');
            const errorAlert = document.getElementById('upload_error_alert');
            const submitBtn = document.getElementById('btn_submit_media');
            const cancelBtn = document.getElementById('btn_cancel_media');

            errorAlert.classList.add('d-none');
            progressContainer.classList.remove('d-none');
            submitBtn.disabled = true;
            cancelBtn.disabled = true;

            const chunkSize = 5 * 1024 * 1024; // 5 MB chunks
            const totalChunks = Math.ceil(file.size / chunkSize);
            const uploadId = 'up_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);

            try {
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * chunkSize;
                    const end = Math.min(start + chunkSize, file.size);
                    const chunkBlob = file.slice(start, end);

                    const formData = new FormData();
                    formData.append('chunk', chunkBlob, file.name);
                    formData.append('upload_id', uploadId);
                    formData.append('chunk_index', chunkIndex);
                    formData.append('total_chunks', totalChunks);
                    formData.append('file_name', file.name);
                    formData.append('file_size', file.size);
                    formData.append('med_title', title);
                    formData.append('med_artist', artist);
                    formData.append('med_category', category);
                    formData.append('med_description', description);
                    formData.append('adm_csrf_token', csrfToken);

                    const percent = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                    statusText.textContent = `Teilstück ${chunkIndex + 1} von ${totalChunks} wird übertragen (${percent}%)...`;

                    const res = await fetch('<?= $pluginUrl ?>/index.php?mode=chunk_upload', {
                        method: 'POST',
                        body: formData
                    });

                    if (!res.ok) {
                        const errData = await res.json().catch(() => ({}));
                        throw new Error(errData.error || `HTTP-Status ${res.status}`);
                    }

                    const data = await res.json();
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    progressBar.style.width = percent + '%';
                    progressBar.textContent = percent + '%';
                }

                statusText.textContent = 'Datei vollständig hochgeladen! Aktualisiere...';
                setTimeout(() => {
                    window.location.reload();
                }, 400);
            } catch (err) {
                errorAlert.textContent = 'Fehler beim Upload: ' + err.message;
                errorAlert.classList.remove('d-none');
                progressContainer.classList.add('d-none');
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
            }
        });
        </script>
    <?php endif; ?>

    <!-- Modal: Neue Playlist erstellen -->
    <div class="modal fade" id="modal_create_playlist" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="<?= $pluginUrl ?>/index.php?mode=create_playlist">
                <input type="hidden" name="adm_csrf_token" value="<?= $csrfToken ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-collection-play-fill me-2 text-primary"></i>Neue Playlist erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Name der Playlist <span class="text-danger">*</span></label>
                            <input type="text" name="playlist_name" class="form-control" required placeholder="z. B. Konzertprogramm Herbst 2026">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Beschreibung</label>
                            <textarea name="playlist_description" class="form-control" rows="2" placeholder="z. B. Alle aktuellen Stücke zur Vorbereitung"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Erstellen</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Zu Playlist hinzufügen -->
    <?php if (!empty($playlists)): ?>
        <div class="modal fade" id="modal_add_to_playlist" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" action="<?= $pluginUrl ?>/index.php?mode=add_to_playlist">
                    <input type="hidden" name="adm_csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="target_media_id" id="modal_playlist_media_id" value="0">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-plus-square-fill me-2 text-primary"></i>Zu Playlist hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Titel: <strong id="modal_playlist_media_title">...</strong></p>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Playlist auswählen</label>
                                <select name="target_playlist_id" class="form-select" required>
                                    <?php foreach ($playlists as $pl): ?>
                                        <option value="<?= $pl['mpl_id'] ?>"><?= htmlspecialchars($pl['mpl_name']) ?> (<?= (int)$pl['item_count'] ?> Titel)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Hinzufügen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addModal = document.getElementById('modal_add_to_playlist');
            if (addModal) {
                addModal.addEventListener('show.bs.modal', function(e) {
                    const btn = e.relatedTarget;
                    const mediaId = btn.getAttribute('data-media-id');
                    const mediaTitle = btn.getAttribute('data-media-title');
                    document.getElementById('modal_playlist_media_id').value = mediaId;
                    document.getElementById('modal_playlist_media_title').textContent = mediaTitle;
                });
            }
        });
        </script>
    <?php endif;
}
