/**
 ***********************************************************************************************
 * Player of the media player plugin
 *
 * Plays the items of a playlist one after the other. The player bar at the bottom of the window
 * controls all items. Audio files are played by one audio element without a visible player, so the
 * playback continues with the next item even if the screen of a phone is locked. Video files and
 * YouTube and Vimeo videos are shown with the Plyr player above the list; embedded players are only
 * loaded after the user started an item. All other weblinks are opened in a new window.
 * The repeat button switches between no repeat, repeat of the playlist and repeat of the current item,
 * the shuffle button plays the items in a random order. Both are remembered in the browser.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$(function() {
    const root = document.getElementById("adm_media_player");
    if (!root) {
        return;
    }

    const labels = root.dataset;
    const stage = document.getElementById("adm_media_player_stage");
    const bar = document.getElementById("adm_media_player_bar");
    const titleElement = document.getElementById("adm_media_player_title");
    const originElement = document.getElementById("adm_media_player_origin");
    const actionsElement = document.getElementById("adm_media_player_actions");
    const timeElement = document.getElementById("adm_media_player_time");
    const durationElement = document.getElementById("adm_media_player_duration");
    const seekElement = document.getElementById("adm_media_player_seek");
    const speedElement = document.getElementById("adm_media_player_speed");
    const playButtons = [document.getElementById("adm_media_player_play_all"), document.getElementById("adm_media_player_play_pause")];
    const repeatModes = ["off", "all", "one"];
    const storageKeys = {repeat: "admidio-media-player-repeat", shuffle: "admidio-media-player-shuffle"};

    // one audio element for all audio files, so mobile browsers continue the playback in the background
    const audio = document.createElement("audio");
    audio.preload = "metadata";
    bar.appendChild(audio);

    let media = null;
    let currentRow = null;
    let order = [];
    let repeatMode = readSetting(storageKeys.repeat, repeatModes, "off");
    let shuffle = readSetting(storageKeys.shuffle, ["0", "1"], "0") === "1";
    let seeking = false;

    /**
     * Read a setting that is remembered in the browser.
     * @param {string} key
     * @param {string[]} validValues
     * @param {string} defaultValue
     * @returns {string}
     */
    function readSetting(key, validValues, defaultValue) {
        try {
            const value = window.localStorage.getItem(key);
            if (validValues.includes(value)) {
                return value;
            }
        } catch (exception) {
            // the browser doesn't allow the storage, then the default is used
        }
        return defaultValue;
    }

    /**
     * Remember a setting in the browser.
     * @param {string} key
     * @param {string} value
     */
    function writeSetting(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (exception) {
            // the setting is only used for the current page
        }
    }

    /**
     * Format seconds as m:ss or h:mm:ss.
     * @param {number} seconds
     * @returns {string}
     */
    function formatTime(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            return "0:00";
        }
        seconds = Math.floor(seconds);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const rest = String(seconds % 60).padStart(2, "0");
        if (hours > 0) {
            return hours + ":" + String(minutes).padStart(2, "0") + ":" + rest;
        }
        return minutes + ":" + rest;
    }

    /**
     * Get all items of the playlist in their current order.
     * @returns {HTMLElement[]}
     */
    function getRows() {
        return Array.from(document.querySelectorAll(".admidio-media-player-item"));
    }

    /**
     * Create the order in which the items are played. With shuffle the current item stays the first one.
     */
    function createOrder() {
        order = getRows();
        if (shuffle) {
            for (let i = order.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [order[i], order[j]] = [order[j], order[i]];
            }
            if (currentRow !== null) {
                order.splice(order.indexOf(currentRow), 1);
                order.unshift(currentRow);
            }
        }
    }

    /**
     * Show the current repeat mode at all repeat buttons.
     */
    function showRepeatMode() {
        const label = labels["labelRepeat" + repeatMode.charAt(0).toUpperCase() + repeatMode.slice(1)];
        document.querySelectorAll(".admidio-media-player-repeat").forEach(function(button) {
            button.querySelector("i").className = (repeatMode === "one") ? "bi bi-repeat-1" : "bi bi-repeat";
            button.setAttribute("aria-pressed", (repeatMode !== "off").toString());
            button.title = label;
            button.setAttribute("aria-label", label);
        });
    }

    /**
     * Show the current shuffle mode at all shuffle buttons.
     */
    function showShuffleMode() {
        const label = shuffle ? labels.labelShuffleOn : labels.labelShuffleOff;
        document.querySelectorAll(".admidio-media-player-shuffle").forEach(function(button) {
            button.setAttribute("aria-pressed", shuffle.toString());
            button.title = label;
            button.setAttribute("aria-label", label);
        });
    }

    /**
     * Show if the player is playing at the play buttons and the current item.
     */
    function showPlayState() {
        const playing = media !== null && !media.paused();
        playButtons.forEach(function(button) {
            if (button !== null) {
                button.querySelector("i").className = playing ? "bi bi-pause-fill" : "bi bi-play-fill";
                button.title = playing ? labels.labelPause : labels.labelPlay;
                button.setAttribute("aria-label", button.title);
            }
        });
        if (currentRow !== null) {
            currentRow.classList.toggle("admidio-media-player-playing", playing);
        }
        if ("mediaSession" in navigator) {
            navigator.mediaSession.playbackState = media === null ? "none" : (playing ? "playing" : "paused");
        }
    }

    /**
     * Show the current position and the length of the item in the player bar.
     */
    function showProgress() {
        if (media === null) {
            return;
        }
        const duration = media.duration();
        const position = media.currentTime();
        durationElement.textContent = formatTime(duration);
        if (!seeking) {
            timeElement.textContent = formatTime(position);
            seekElement.max = isFinite(duration) ? duration : 0;
            seekElement.value = position;
        }
    }

    /**
     * Create a uniform interface for the audio element and for a Plyr player.
     * @param {HTMLMediaElement|Plyr} player
     * @param {boolean} isElement **true** for the audio element, **false** for a Plyr player.
     * @returns {object}
     */
    function createMedia(player, isElement) {
        const handlers = [];
        const on = function(event, handler) {
            if (isElement) {
                player.addEventListener(event, handler);
            } else {
                player.on(event, handler);
            }
            handlers.push([event, handler]);
        };
        return {
            play: function() {
                const promise = player.play();
                if (promise && typeof promise.catch === "function") {
                    // the browser could prevent the autoplay, then the user starts the playback
                    promise.catch(function() {
                        showPlayState();
                    });
                }
            },
            pause: function() {
                player.pause();
            },
            paused: function() {
                return player.paused;
            },
            currentTime: function(value) {
                if (value !== undefined) {
                    player.currentTime = value;
                }
                return player.currentTime || 0;
            },
            duration: function() {
                return player.duration || 0;
            },
            setSpeed: function(value) {
                if (isElement) {
                    player.playbackRate = value;
                } else {
                    player.speed = value;
                }
            },
            setLoop: function(value) {
                player.loop = value;
            },
            on: on,
            destroy: function() {
                if (isElement) {
                    handlers.forEach(function(entry) {
                        player.removeEventListener(entry[0], entry[1]);
                    });
                    player.pause();
                    player.removeAttribute("src");
                    player.load();
                } else {
                    try {
                        player.destroy();
                    } catch (exception) {
                        // the player is removed together with the stage content
                    }
                }
            }
        };
    }

    /**
     * Get the id of a YouTube video from its url.
     * @param {string} url
     * @returns {string|null}
     */
    function getYouTubeId(url) {
        const match = url.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|shorts\/|live\/|watch\?(?:.*&)?v=))([\w-]{11})/);
        return match ? match[1] : null;
    }

    /**
     * Get the id of a Vimeo video from its url.
     * @param {string} url
     * @returns {string|null}
     */
    function getVimeoId(url) {
        const match = url.match(/vimeo\.com\/(?:.*\/)?(\d+)/);
        return match ? match[1] : null;
    }

    /**
     * Stop the current item and remove its player.
     */
    function stop() {
        if (media !== null) {
            media.destroy();
            media = null;
        }
        stage.replaceChildren();
        stage.className = "admidio-media-player-stage d-none";
        actionsElement.replaceChildren();
    }

    /**
     * Create a link button for the player bar.
     * @param {string} url
     * @param {string} label
     * @param {string} icon
     * @param {boolean} newWindow
     * @returns {HTMLAnchorElement}
     */
    function createActionLink(url, label, icon, newWindow) {
        const link = document.createElement("a");
        link.className = "admidio-icon-link";
        link.href = url;
        link.title = label;
        link.setAttribute("aria-label", label);
        if (newWindow) {
            link.target = "_blank";
            link.rel = "noopener noreferrer";
        }
        const iconElement = document.createElement("i");
        iconElement.className = "bi " + icon;
        link.appendChild(iconElement);
        return link;
    }

    /**
     * Connect the events of the current item with the player bar and start the playback.
     */
    function connectMedia() {
        media.on("timeupdate", showProgress);
        media.on("loadedmetadata", showProgress);
        media.on("durationchange", showProgress);
        media.on("play", showPlayState);
        media.on("playing", showPlayState);
        media.on("pause", showPlayState);
        media.on("ended", playAfterEnd);
        media.setSpeed(parseFloat(speedElement.value));
        media.setLoop(repeatMode === "one");
    }

    /**
     * Create a Plyr player for a video in the stage.
     * @param {HTMLElement} element
     */
    function startVideo(element) {
        stage.className = "admidio-media-player-stage";
        stage.appendChild(element);
        const player = new Plyr(element, {
            seekTime: 10,
            speed: {selected: parseFloat(speedElement.value), options: [0.5, 0.75, 0.9, 1, 1.25, 1.5]},
            youtube: {noCookie: true, rel: 0, modestbranding: 1},
            vimeo: {dnt: true},
            loop: {active: repeatMode === "one"}
        });
        media = createMedia(player, false);
        connectMedia();
        player.on("ready", function() {
            media.play();
        });
        // on phones the video should be visible when it starts
        if (window.innerWidth < 992) {
            stage.scrollIntoView({behavior: "smooth", block: "start"});
        }
    }

    /**
     * Show the item in the media session of the browser, e.g. on the lock screen of a phone.
     * @param {HTMLElement} row
     */
    function updateMediaSession(row) {
        if (!("mediaSession" in navigator) || typeof MediaMetadata === "undefined") {
            return;
        }
        const headline = document.querySelector("h1");
        navigator.mediaSession.metadata = new MediaMetadata({
            title: row.dataset.title,
            artist: row.dataset.origin,
            album: headline ? headline.textContent.trim() : ""
        });
    }

    /**
     * Play an item of the playlist.
     * @param {HTMLElement} row
     */
    function play(row) {
        const type = row.dataset.type;
        const source = row.dataset.source;

        stop();
        getRows().forEach(function(item) {
            item.classList.remove("admidio-media-player-active", "admidio-media-player-playing");
        });
        row.classList.add("admidio-media-player-active");
        currentRow = row;
        if (!order.includes(row)) {
            createOrder();
        }

        bar.hidden = false;
        document.body.classList.add("admidio-media-player-bar-visible");
        titleElement.textContent = row.dataset.title;
        originElement.textContent = row.dataset.origin;
        timeElement.textContent = "0:00";
        durationElement.textContent = row.dataset.duration ? formatTime(parseFloat(row.dataset.duration)) : "0:00";
        seekElement.value = 0;
        if (row.dataset.download !== "") {
            actionsElement.appendChild(createActionLink(row.dataset.download, labels.labelDownload, "bi-download", false));
        } else {
            actionsElement.appendChild(createActionLink(source, labels.labelOpen, "bi-box-arrow-up-right", true));
        }
        updateMediaSession(row);

        if (type === "audio") {
            audio.src = source;
            media = createMedia(audio, true);
            connectMedia();
            media.play();
        } else if (type === "video") {
            const video = document.createElement("video");
            video.preload = "metadata";
            video.setAttribute("playsinline", "");
            const sourceElement = document.createElement("source");
            sourceElement.src = source;
            if (row.dataset.mime !== "") {
                sourceElement.type = row.dataset.mime;
            }
            video.appendChild(sourceElement);
            startVideo(video);
        } else if ((type === "youtube" && getYouTubeId(source) !== null) || (type === "vimeo" && getVimeoId(source) !== null)) {
            const embed = document.createElement("div");
            embed.dataset.plyrProvider = type;
            embed.dataset.plyrEmbedId = (type === "youtube") ? getYouTubeId(source) : getVimeoId(source);
            startVideo(embed);
        } else {
            // other weblinks could not be played within the page
            stage.className = "admidio-media-player-stage admidio-media-player-external";
            const notice = document.createElement("p");
            notice.textContent = labels.labelExternal;
            const link = document.createElement("a");
            link.className = "btn btn-primary";
            link.href = source;
            link.target = "_blank";
            link.rel = "noopener noreferrer";
            link.textContent = labels.labelOpen;
            stage.append(notice, link);
        }
        showPlayState();
    }

    /**
     * Continue after the end of an item depending on the repeat mode.
     */
    function playAfterEnd() {
        if (repeatMode === "one") {
            // the embedded players don't always support the loop, so start the item again
            media.currentTime(0);
            media.play();
        } else if (repeatMode === "all" || order.indexOf(currentRow) < order.length - 1) {
            playNext();
        } else {
            showPlayState();
        }
    }

    /**
     * Play the next item of the playlist.
     */
    function playNext() {
        if (order.length === 0) {
            createOrder();
        }
        if (order.length > 0) {
            play(order[(order.indexOf(currentRow) + 1) % order.length]);
        }
    }

    /**
     * Play the previous item of the playlist. Within the first seconds of an item the item starts again.
     */
    function playPrevious() {
        if (media !== null && media.currentTime() > 3) {
            media.currentTime(0);
            return;
        }
        if (order.length === 0) {
            createOrder();
        }
        if (order.length > 0) {
            play(order[(order.indexOf(currentRow) - 1 + order.length) % order.length]);
        }
    }

    /**
     * Start the playlist or pause and continue the current item.
     */
    function togglePlay() {
        if (media !== null) {
            if (media.paused()) {
                media.play();
            } else {
                media.pause();
            }
        } else if (currentRow !== null && currentRow.dataset.type !== "audio" && currentRow.dataset.type !== "video"
            && currentRow.dataset.type !== "youtube" && currentRow.dataset.type !== "vimeo") {
            playNext();
        } else {
            currentRow = null;
            createOrder();
            if (order.length > 0) {
                play(order[0]);
            }
        }
    }

    /**
     * Show the length of the items and the total length of the playlist. Stored lengths come with the page.
     * Files without a stored length are measured one after the other by loading only their metadata, and
     * the lengths are sent to the server, so the next visitors don't have to load the files. The server
     * reads the length of YouTube and Vimeo videos.
     */
    function loadDurations() {
        const rows = getRows();
        const totalElement = document.getElementById("adm_media_player_total_duration");
        const measured = {};

        const showDuration = function(row, seconds) {
            row.dataset.duration = seconds;
            row.querySelector(".admidio-media-player-duration").textContent = formatTime(seconds);
        };
        const totalRow = document.getElementById("adm_media_player_total_row");
        const showTotal = function() {
            const total = rows.reduce(function(sum, row) {
                return sum + (Math.floor(parseFloat(row.dataset.duration)) || 0);
            }, 0);
            if (total > 0 && totalElement) {
                totalElement.textContent = " · " + Math.max(1, Math.round(total / 60)) + " " + labels.labelMinutes;
            }
            // the sum below the lengths of the items
            if (total > 0 && totalRow) {
                document.getElementById("adm_media_player_total").textContent = formatTime(total);
                totalRow.hidden = false;
            }
        };
        const post = function(url, data) {
            return $.post(url, $.param(Object.assign({adm_csrf_token: labels.csrfToken}, data)), null, "json");
        };

        rows.forEach(function(row) {
            if (row.dataset.duration !== "") {
                showDuration(row, parseFloat(row.dataset.duration));
            }
        });
        showTotal();

        const files = rows.filter(function(row) {
            return row.dataset.duration === "" && (row.dataset.type === "audio" || row.dataset.type === "video") && row.dataset.fileUuid !== "";
        });
        const links = rows.filter(function(row) {
            return row.dataset.duration === "" && (row.dataset.type === "youtube" || row.dataset.type === "vimeo") && row.dataset.weblinkUuid !== "";
        });

        const measureNext = function(index) {
            if (index >= files.length) {
                if (Object.keys(measured).length > 0) {
                    const data = {};
                    Object.keys(measured).forEach(function(uuid) {
                        data["durations[" + uuid + "]"] = measured[uuid];
                    });
                    post(labels.durationsUrl, data);
                }
                return;
            }
            const row = files[index];
            const probe = document.createElement(row.dataset.type);
            probe.preload = "metadata";
            const finish = function() {
                if (isFinite(probe.duration) && probe.duration > 0) {
                    showDuration(row, probe.duration);
                    measured[row.dataset.fileUuid] = Math.floor(probe.duration);
                    showTotal();
                }
                probe.removeAttribute("src");
                probe.load();
                measureNext(index + 1);
            };
            probe.addEventListener("loadedmetadata", finish, {once: true});
            probe.addEventListener("error", finish, {once: true});
            probe.src = row.dataset.source;
        };
        const askNext = function(index) {
            if (index >= links.length) {
                return;
            }
            post(labels.linkDurationUrl, {weblink_uuid: links[index].dataset.weblinkUuid})
                .done(function(response) {
                    if (response && response.status === "success" && response.duration) {
                        showDuration(links[index], response.duration);
                        showTotal();
                    }
                })
                .always(function() {
                    askNext(index + 1);
                });
        };
        measureNext(0);
        askNext(0);
    }

    // play an item with a click on its row, links of the row have their own function
    $(document).on("click", ".admidio-media-player-item", function(event) {
        if ($(event.target).closest("a").length > 0) {
            return;
        }
        if (this === currentRow && media !== null) {
            togglePlay();
        } else {
            play(this);
        }
    });
    playButtons.forEach(function(button) {
        if (button !== null) {
            button.addEventListener("click", togglePlay);
        }
    });
    document.getElementById("adm_media_player_next").addEventListener("click", playNext);
    document.getElementById("adm_media_player_previous").addEventListener("click", playPrevious);
    $(document).on("click", ".admidio-media-player-repeat", function() {
        repeatMode = repeatModes[(repeatModes.indexOf(repeatMode) + 1) % repeatModes.length];
        writeSetting(storageKeys.repeat, repeatMode);
        if (media !== null) {
            media.setLoop(repeatMode === "one");
        }
        showRepeatMode();
    });
    $(document).on("click", ".admidio-media-player-shuffle", function() {
        shuffle = !shuffle;
        writeSetting(storageKeys.shuffle, shuffle ? "1" : "0");
        createOrder();
        showShuffleMode();
    });

    // the position slider: while the user drags it, the time shows the new position
    seekElement.addEventListener("input", function() {
        seeking = true;
        timeElement.textContent = formatTime(parseFloat(seekElement.value));
    });
    seekElement.addEventListener("change", function() {
        if (media !== null) {
            media.currentTime(parseFloat(seekElement.value));
        }
        seeking = false;
    });

    // the speed is shown in the format of the language of the page
    Array.from(speedElement.options).forEach(function(option) {
        option.textContent = parseFloat(option.value).toLocaleString(document.documentElement.lang || undefined) + "×";
    });
    speedElement.addEventListener("change", function() {
        if (media !== null) {
            media.setSpeed(parseFloat(speedElement.value));
        }
    });

    // the space key starts and pauses the playback if no form element has the focus
    document.addEventListener("keydown", function(event) {
        if (event.code === "Space" && media !== null && !event.target.closest("input, textarea, select, button, a, [contenteditable]")) {
            event.preventDefault();
            togglePlay();
        }
    });

    // controls on the lock screen of phones and in the media keys of the keyboard
    if ("mediaSession" in navigator) {
        const actions = {
            play: function() { if (media !== null) { media.play(); } else { togglePlay(); } },
            pause: function() { if (media !== null) { media.pause(); } },
            previoustrack: playPrevious,
            nexttrack: playNext,
            seekto: function(details) { if (media !== null) { media.currentTime(details.seekTime); } }
        };
        Object.keys(actions).forEach(function(action) {
            try {
                navigator.mediaSession.setActionHandler(action, actions[action]);
            } catch (exception) {
                // the browser doesn't support this action
            }
        });
    }

    // a private copy of the playlist is created and opened in the edit mode
    $("#menu_item_media_player_copy").click(function() {
        const link = this;
        $.post(link.dataset.url, {adm_csrf_token: link.dataset.csrfToken}, null, "json")
            .done(function(response) {
                if (response.status === "success") {
                    window.location.href = response.url;
                } else {
                    messageBox(response.message, "", "error");
                }
            })
            .fail(function(xhr) {
                messageBox(xhr.responseText || xhr.statusText, "", "error");
            });
    });

    showRepeatMode();
    showShuffleMode();
    showPlayState();
    loadDurations();
});
