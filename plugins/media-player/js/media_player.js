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
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        const match = url.match(regExp);
        return (match && match[2].length === 11) ? match[2] : url;
    }

    function extractVimeoId(url) {
        if (!url) return null;
        const match = url.match(/(?:www\.|player\.)?vimeo.com\/(?:channels\/(?:\w+\/)?|groups\/(?:[^\/]*)\/videos\/|album\/(?:\d+)\/video\/|video\/|)(\d+)(?:[a-zA-Z0-9_\-]+)?/);
        return match ? match[1] : url;
    }

    function initOrUpdatePlayer(type, sourceConfig) {
        // Destroy existing player if needed to switch container between audio and video
        if (player) {
            player.destroy();
            player = null;
        }

        playerContainer.innerHTML = '';

        let targetEl;
        if (type === 'audio') {
            playerContainer.className = 'admidio-player-wrapper audio-mode';
            targetEl = document.createElement('audio');
            targetEl.id = 'active_plyr';
            targetEl.controls = true;
            playerContainer.appendChild(targetEl);
        } else {
            playerContainer.className = 'admidio-player-wrapper';
            targetEl = document.createElement('div');
            targetEl.id = 'active_plyr';
            playerContainer.appendChild(targetEl);
        }

        player = new Plyr(targetEl, {
            controls: [
                'play-large', 'restart', 'rewind', 'play', 'fast-forward',
                'progress', 'current-time', 'duration', 'mute', 'volume',
                'captions', 'settings', 'pip', 'airplay', 'fullscreen'
            ],
            seekTime: 10,
            speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
            keyboard: { focused: true, global: false }
        });

        player.source = sourceConfig;

        player.on('ready', function () {
            player.play().catch(function (e) {
                console.log('Autoplay deferred:', e);
            });
        });

        player.on('ended', function () {
            playNextTrack();
        });
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
        const type = row.dataset.type;
        const streamUrl = row.dataset.streamUrl;
        const rawSource = row.dataset.source;
        const title = row.dataset.title || 'Ohne Titel';
        const artist = row.dataset.artist || '';
        const category = row.dataset.category || '';
        const mime = row.dataset.mime || (type === 'audio' ? 'audio/mpeg' : 'video/mp4');

        if (titleEl) {
            titleEl.textContent = title;
        }
        if (metaEl) {
            let meta = [];
            if (artist) meta.push(artist);
            if (category) meta.push(category);
            metaEl.textContent = meta.join(' • ');
        }

        if (type === 'audio') {
            initOrUpdatePlayer('audio', {
                type: 'audio',
                title: title,
                sources: [{ src: streamUrl, type: mime }]
            });
        } else if (type === 'video') {
            initOrUpdatePlayer('video', {
                type: 'video',
                title: title,
                sources: [{ src: streamUrl, type: mime }]
            });
        } else if (type === 'youtube') {
            const ytId = extractYouTubeId(rawSource);
            initOrUpdatePlayer('video', {
                type: 'video',
                sources: [{ src: ytId, provider: 'youtube' }]
            });
        } else if (type === 'vimeo') {
            const vimeoId = extractVimeoId(rawSource);
            initOrUpdatePlayer('video', {
                type: 'video',
                sources: [{ src: vimeoId, provider: 'vimeo' }]
            });
        } else {
            // General external URL
            initOrUpdatePlayer('audio', {
                type: 'audio',
                title: title,
                sources: [{ src: rawSource }]
            });
        }

        // Scroll player into view if on mobile
        if (window.innerWidth < 768) {
            playerContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
            // Ignore click if user clicked an action link (e.g. download or delete)
            if (e.target.closest('.media-action-btn') || e.target.closest('a')) {
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
