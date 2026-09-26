/**
 * Admidio Media Player Plugin Client Logic
 */

document.addEventListener('DOMContentLoaded', function () {
    let player = null;
    let currentTrackIndex = -1;
    let visibleTracks = [];

    const playerContainer = document.getElementById('admidio_player_wrapper');
    const titleEl = document.getElementById('player_current_title');
    const metaEl = document.getElementById('player_current_meta');
    const externalActionsEl = document.getElementById('player_external_actions');
    const searchInput = document.getElementById('media_search_input');
    const categoryBadges = document.querySelectorAll('.admidio-category-badge');
    const mediaRows = document.querySelectorAll('.media-item-row');
    const prevBtn = document.getElementById('btn_player_prev');
    const nextBtn = document.getElementById('btn_player_next');

    function updateVisibleTracks() {
        visibleTracks = Array.from(document.querySelectorAll('.media-item-row:not(.d-none)'));
    }

    function extractYouTubeId(url) {
        if (!url) return null;
        url = url.trim();
        const regExp = /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=|shorts\/|live\/))([\w-]{11})/;
        const match = url.match(regExp);
        if (match && match[1]) {
            return match[1];
        }
        if (/^[\w-]{11}$/.test(url)) {
            return url;
        }
        return null;
    }

    function extractVimeoId(url) {
        if (!url) return null;
        url = url.trim();
        const match = url.match(/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)/);
        return match ? match[1] : null;
    }

    function initOrUpdatePlayer(type, trackData) {
        // Destroy existing player if needed to switch cleanly
        if (player) {
            try {
                player.destroy();
            } catch (e) {
                console.warn('Error destroying player:', e);
            }
            player = null;
        }

        playerContainer.innerHTML = '';

        const plyrOptions = {
            controls: [
                'play-large', 'restart', 'rewind', 'play', 'fast-forward',
                'progress', 'current-time', 'duration', 'mute', 'volume',
                'captions', 'settings', 'pip', 'airplay', 'fullscreen'
            ],
            seekTime: 10,
            speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
            keyboard: { focused: true, global: false }
        };

        if (type === 'audio') {
            playerContainer.className = 'admidio-player-wrapper audio-mode';
            const audioEl = document.createElement('audio');
            audioEl.id = 'active_plyr';
            audioEl.controls = true;
            audioEl.preload = 'auto';

            const src = trackData.streamUrl || trackData.source;
            if (src) {
                const sourceEl = document.createElement('source');
                sourceEl.src = src;
                if (trackData.mime) {
                    sourceEl.type = trackData.mime;
                }
                audioEl.appendChild(sourceEl);
            }
            playerContainer.appendChild(audioEl);
            player = new Plyr(audioEl, plyrOptions);

        } else if (type === 'video') {
            playerContainer.className = 'admidio-player-wrapper video-mode';
            const videoEl = document.createElement('video');
            videoEl.id = 'active_plyr';
            videoEl.controls = true;
            videoEl.playsInline = true;
            videoEl.preload = 'auto';

            const src = trackData.streamUrl || trackData.source;
            if (src) {
                const sourceEl = document.createElement('source');
                sourceEl.src = src;
                if (trackData.mime) {
                    sourceEl.type = trackData.mime;
                }
                videoEl.appendChild(sourceEl);
            }
            playerContainer.appendChild(videoEl);
            player = new Plyr(videoEl, plyrOptions);

        } else if (type === 'youtube') {
            playerContainer.className = 'admidio-player-wrapper video-mode';
            const embedDiv = document.createElement('div');
            embedDiv.className = 'plyr__video-embed';
            embedDiv.id = 'active_plyr';

            const origin = encodeURIComponent(window.location.origin);
            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube.com/embed/${trackData.ytId}?origin=${origin}&iv_load_policy=3&modestbranding=1&playsinline=1&showinfo=0&rel=0&enablejsapi=1`;
            iframe.allowFullscreen = true;
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
            iframe.setAttribute('allowtransparency', '');

            embedDiv.appendChild(iframe);
            playerContainer.appendChild(embedDiv);
            player = new Plyr(embedDiv, plyrOptions);

        } else if (type === 'vimeo') {
            playerContainer.className = 'admidio-player-wrapper video-mode';
            const embedDiv = document.createElement('div');
            embedDiv.className = 'plyr__video-embed';
            embedDiv.id = 'active_plyr';

            const iframe = document.createElement('iframe');
            iframe.src = `https://player.vimeo.com/video/${trackData.vimeoId}?loop=false&byline=false&portrait=false&title=false&speed=true&transparent=0&gesture=media`;
            iframe.allowFullscreen = true;
            iframe.allow = 'autoplay; fullscreen; picture-in-picture';
            iframe.setAttribute('allowtransparency', '');

            embedDiv.appendChild(iframe);
            playerContainer.appendChild(embedDiv);
            player = new Plyr(embedDiv, plyrOptions);

        } else {
            // General external URL
            playerContainer.className = 'admidio-player-wrapper audio-mode';
            const audioEl = document.createElement('audio');
            audioEl.id = 'active_plyr';
            audioEl.controls = true;
            if (trackData.source) {
                const sourceEl = document.createElement('source');
                sourceEl.src = trackData.source;
                audioEl.appendChild(sourceEl);
            }
            playerContainer.appendChild(audioEl);
            player = new Plyr(audioEl, plyrOptions);
        }

        if (player) {
            player.on('ready', function () {
                try {
                    const playPromise = player.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(function (e) {
                            console.log('Autoplay deferred or prevented by browser:', e);
                        });
                    }
                } catch (e) {
                    console.log('Play on ready error:', e);
                }
            });

            player.on('ended', function () {
                playNextTrack();
            });
        }
    }

    function playTrackByIndex(index) {
        updateVisibleTracks();
        if (index < 0 || index >= visibleTracks.length) return;

        currentTrackIndex = index;
        const row = visibleTracks[index];

        // Reset active state across all rows
        mediaRows.forEach(r => r.classList.remove('now-playing'));
        row.classList.add('now-playing');

        const uuid = row.dataset.uuid;
        const type = (row.dataset.type || '').toLowerCase();
        const streamUrl = row.dataset.streamUrl || '';
        const rawSource = row.dataset.source || '';
        const title = row.dataset.title || 'Ohne Titel';
        const artist = row.dataset.artist || '';
        const category = row.dataset.category || '';
        const mime = row.dataset.mime || '';

        if (titleEl) {
            titleEl.textContent = title;
        }
        if (metaEl) {
            let meta = [];
            if (artist) meta.push(artist);
            if (category) meta.push(category);
            metaEl.textContent = meta.join(' • ');
        }

        // Update header external actions
        if (externalActionsEl) {
            externalActionsEl.innerHTML = '';
        }

        if (type === 'youtube') {
            const ytId = extractYouTubeId(rawSource);
            if (ytId) {
                initOrUpdatePlayer('youtube', { ytId: ytId, title: title });

                if (externalActionsEl) {
                    const ytLink = document.createElement('a');
                    ytLink.href = rawSource.startsWith('http') ? rawSource : `https://www.youtube.com/watch?v=${ytId}`;
                    ytLink.target = '_blank';
                    ytLink.rel = 'noopener noreferrer';
                    ytLink.className = 'btn btn-sm btn-outline-danger';
                    ytLink.innerHTML = '<i class="bi bi-youtube me-1"></i> Auf YouTube öffnen';
                    ytLink.title = 'In neuem Tab auf YouTube öffnen';
                    externalActionsEl.appendChild(ytLink);
                }
            } else {
                playerContainer.className = 'admidio-player-wrapper audio-mode';
                playerContainer.innerHTML = '<div class="text-danger p-3"><i class="bi bi-exclamation-triangle me-2"></i>Ungültige YouTube-URL</div>';
            }
        } else if (type === 'vimeo') {
            const vimeoId = extractVimeoId(rawSource);
            if (vimeoId) {
                initOrUpdatePlayer('vimeo', { vimeoId: vimeoId, title: title });

                if (externalActionsEl) {
                    const vLink = document.createElement('a');
                    vLink.href = rawSource.startsWith('http') ? rawSource : `https://vimeo.com/${vimeoId}`;
                    vLink.target = '_blank';
                    vLink.rel = 'noopener noreferrer';
                    vLink.className = 'btn btn-sm btn-outline-info';
                    vLink.innerHTML = '<i class="bi bi-box-arrow-up-right me-1"></i> Auf Vimeo öffnen';
                    externalActionsEl.appendChild(vLink);
                }
            } else {
                playerContainer.className = 'admidio-player-wrapper audio-mode';
                playerContainer.innerHTML = '<div class="text-danger p-3"><i class="bi bi-exclamation-triangle me-2"></i>Ungültige Vimeo-URL</div>';
            }
        } else if (type === 'video') {
            initOrUpdatePlayer('video', {
                streamUrl: streamUrl,
                source: rawSource,
                mime: mime || 'video/mp4',
                title: title
            });
            if (externalActionsEl && streamUrl) {
                const dlLink = document.createElement('a');
                dlLink.href = streamUrl.replace('mode=stream', 'mode=download');
                dlLink.className = 'btn btn-sm btn-outline-secondary';
                dlLink.innerHTML = '<i class="bi bi-download me-1"></i> Download';
                externalActionsEl.appendChild(dlLink);
            }
        } else if (type === 'audio') {
            initOrUpdatePlayer('audio', {
                streamUrl: streamUrl,
                source: rawSource,
                mime: mime || 'audio/mpeg',
                title: title
            });
            if (externalActionsEl && streamUrl) {
                const dlLink = document.createElement('a');
                dlLink.href = streamUrl.replace('mode=stream', 'mode=download');
                dlLink.className = 'btn btn-sm btn-outline-secondary';
                dlLink.innerHTML = '<i class="bi bi-download me-1"></i> Download';
                externalActionsEl.appendChild(dlLink);
            }
        } else {
            // General external URL
            const possibleYt = extractYouTubeId(rawSource);
            if (possibleYt) {
                row.dataset.type = 'youtube';
                playTrackByIndex(index);
            } else {
                initOrUpdatePlayer('audio', { source: rawSource, title: title });
            }
        }

        // Scroll player into view if on mobile
        if (window.innerWidth < 992) {
            const playerCard = document.querySelector('.admidio-player-card');
            if (playerCard) {
                const navHeight = 70;
                const cardTop = playerCard.getBoundingClientRect().top + window.pageYOffset - navHeight;
                window.scrollTo({ top: Math.max(0, cardTop), behavior: 'smooth' });
            }
        }
    }

    function playNextTrack() {
        updateVisibleTracks();
        if (visibleTracks.length === 0) return;
        let nextIndex = currentTrackIndex + 1;
        if (nextIndex >= visibleTracks.length) {
            nextIndex = 0; // loop back to first track
        }
        playTrackByIndex(nextIndex);
    }

    function playPrevTrack() {
        updateVisibleTracks();
        if (visibleTracks.length === 0) return;
        let prevIndex = currentTrackIndex - 1;
        if (prevIndex < 0) {
            prevIndex = visibleTracks.length - 1;
        }
        playTrackByIndex(prevIndex);
    }

    // Row click listeners
    mediaRows.forEach((row, idx) => {
        row.addEventListener('click', function (e) {
            // Ignore click if user clicked an action link or non-play button
            if (e.target.closest('.media-action-btn') || e.target.closest('a') || e.target.closest('button:not(.media-play-btn)')) {
                return;
            }
            updateVisibleTracks();
            const visibleIdx = visibleTracks.indexOf(row);
            if (visibleIdx !== -1) {
                playTrackByIndex(visibleIdx);
            }
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', playPrevTrack);
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', playNextTrack);
    }

    // Category filtering
    categoryBadges.forEach(badge => {
        badge.addEventListener('click', function () {
            categoryBadges.forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const selectedCat = this.dataset.category;
            filterMedia();
        });
    });

    // Search input
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterMedia();
        });
    }

    function filterMedia() {
        const activeBadge = document.querySelector('.admidio-category-badge.active');
        const selectedCat = activeBadge ? activeBadge.dataset.category : 'all';
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

        mediaRows.forEach(row => {
            const rowCat = (row.dataset.category || '').toLowerCase();
            const rowTitle = (row.dataset.title || '').toLowerCase();
            const rowArtist = (row.dataset.artist || '').toLowerCase();

            const matchesCategory = (selectedCat === 'all' || rowCat === selectedCat.toLowerCase());
            const matchesSearch = (!searchTerm || rowTitle.includes(searchTerm) || rowArtist.includes(searchTerm) || rowCat.includes(searchTerm));

            if (matchesCategory && matchesSearch) {
                row.classList.remove('d-none');
            } else {
                row.classList.add('d-none');
            }
        });

        updateVisibleTracks();
    }

    // Initial setup
    updateVisibleTracks();
});
