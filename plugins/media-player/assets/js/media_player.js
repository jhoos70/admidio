/**
 ***********************************************************************************************
 * Player of the media player plugin
 *
 * Plays the items of a playlist one after the other. The player bar at the bottom of the window
 * controls all items. Audio files are played by audio elements without a visible player, so the
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
    const muteElement = document.getElementById("adm_media_player_mute");
    const playButtons = [document.getElementById("adm_media_player_play_all"), document.getElementById("adm_media_player_play_pause")];
    const repeatModes = ["off", "all", "one"];
    const storageKeys = {repeat: "admidio-media-player-repeat", shuffle: "admidio-media-player-shuffle", soundOnly: "admidio-media-player-sound-only"};

    // Two audio elements: the active one plays the current audio file, the other one loads the next file, which
    // starts shortly before the current file ends. So there is no moment without playback; otherwise Chrome on
    // Android stops its media service in the background and is not allowed to start it again for the next file.
    const createAudioElement = function() {
        const element = document.createElement("audio");
        element.preload = "metadata";
        bar.appendChild(element);
        return element;
    };
    let audio = createAudioElement();
    audio.dataset.active = "1";
    let nextAudio = createAudioElement();
    // the item whose file is loaded in the second element
    let nextAudioRow = null;
    // seconds before the end of a file when the next file starts
    const handoffTime = 0.6;
    // set during the handoff, then the former file plays its last part; any other change of the item stops it
    let handingOver = false;
    // the picture of the current video file: Plyr player and video element, its sound plays in the audio element
    let picture = null;

    let media = null;
    let currentRow = null;
    let order = [];
    let repeatMode = readSetting(storageKeys.repeat, repeatModes, "off");
    let shuffle = readSetting(storageKeys.shuffle, ["0", "1"], "0") === "1";
    // play only the sound of video files, without loading the picture (saves data, no stuttering on weak phones)
    let soundOnlyMode = readSetting(storageKeys.soundOnly, ["0", "1"], "0") === "1";
    let seeking = false;
    // A-B repeat of the current item: start (A) and end (B) in seconds, null while not set
    let loopStart = null;
    let loopEnd = null;
    let loopTimer = null;
    // seconds of audio that must be loaded in advance before the playback continues after the browser has run out
    // of data, so a slow connection gives one longer pause instead of a series of short stops
    const rebufferSeconds = 20;
    // give up the pause after this long without more loaded data, or after this long at all
    const rebufferNoProgressMs = 4000;
    const rebufferMaxWaitMs = 30000;
    // no new pause for loading for this long after one that gave up
    const rebufferBlockMs = 30000;
    // set while the playback waits for data: {element, media, text, timer}
    let rebuffer = null;
    // after a pause for loading that gave up, the next one waits until then (milliseconds since 1970)
    let rebufferBlockedUntil = 0;
    // cover image of the media controls of phones: the icons of the web app manifest or the Admidio logo
    let artwork = [];
    try {
        artwork = JSON.parse(labels.artwork || "[]");
    } catch (exception) {
        // without a cover image the phone shows its default
    }
    let lastPositionUpdate = 0;
    // stopped from the lock screen: the media session stays ended although the player only pauses
    let sessionStopped = false;
    // set while a video is replaced by its sound, the removed player must not start the switch again
    let switchingToBackground = false;

    // Diagnostic mode for problems with the playback in the background, e.g. on a phone with the screen off. It is
    // switched on for this browser with the parameter media_debug=1 and off with media_debug=0, or on the server for
    // the users in adm_my_files/media_player_debug_users.txt. The events of the player are sent to the server, which
    // writes them to a log file.
    const debugEnabled = (function() {
        const key = "admidio.media_player.debug";
        try {
            const parameter = new URLSearchParams(window.location.search).get("media_debug");
            if (parameter === "1") {
                window.localStorage.setItem(key, "1");
            } else if (parameter === "0") {
                window.localStorage.removeItem(key);
            }
            return (labels.debugUser === "1" || window.localStorage.getItem(key) === "1") && typeof navigator.sendBeacon === "function";
        } catch (exception) {
            return false;
        }
    })();

    /**
     * Send an event of the player to the server log in the diagnostic mode.
     * @param {string} event
     * @param {object} [details]
     */
    function debug(event, details) {
        if (!debugEnabled) {
            return;
        }
        const entry = Object.assign({
            time: new Date().toISOString(),
            event: event,
            visibility: document.visibilityState,
            item: currentRow !== null ? currentRow.dataset.title : "",
            type: currentRow !== null ? currentRow.dataset.type : "",
            player: media === null ? "none" : (media.isAudio ? (media.soundOnly ? "audio (sound of video)" : "audio") : "video"),
            position: media !== null ? Math.round(media.currentTime() * 10) / 10 : null
        }, details || {});
        const data = new FormData();
        data.append("adm_csrf_token", labels.csrfToken);
        data.append("entry", JSON.stringify(entry));
        navigator.sendBeacon(labels.debugLogUrl, data);
    }

    if (debugEnabled) {
        ["play", "playing", "pause", "waiting", "stalled", "suspend", "ended", "error", "emptied", "loadstart", "canplay"].forEach(function(name) {
            [audio, nextAudio].forEach(function(element) {
                element.addEventListener(name, function() {
                    debug("audio " + name, Object.assign({element: element === audio ? "active" : "next"},
                        name === "error" && element.error ? {error: element.error.code + " " + element.error.message} : {readyState: element.readyState, networkState: element.networkState}));
                });
            });
        });
        document.addEventListener("visibilitychange", function() {
            debug("visibility");
        });
        window.addEventListener("pagehide", function() {
            debug("pagehide");
        });
        document.addEventListener("freeze", function() {
            debug("freeze");
        });
        document.addEventListener("resume", function() {
            debug("resume");
        });
        debug("debug mode on", {userAgent: navigator.userAgent});
    }

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
     * Show the state of the A-B repeat at the button and mark the range at the seek bar.
     */
    function showLoopState() {
        const button = document.getElementById("adm_media_player_ab");
        const region = document.getElementById("adm_media_player_ab_region");
        const flagA = document.getElementById("adm_media_player_ab_flag_a");
        const flagB = document.getElementById("adm_media_player_ab_flag_b");
        let label = labels.labelAbSetStart;
        let letters = "AB";
        let state = "off";
        if (loopEnd !== null) {
            label = labels.labelAbClear + " (" + formatTime(loopStart) + " \u2013 " + formatTime(loopEnd) + ")";
            state = "on";
        } else if (loopStart !== null) {
            label = labels.labelAbSetEnd + " (A " + formatTime(loopStart) + ")";
            letters = "A";
            state = "a";
        }
        button.querySelector(".admidio-media-player-ab-letters").textContent = letters;
        button.dataset.state = state;
        button.setAttribute("aria-pressed", (state === "on").toString());
        button.title = label;
        button.setAttribute("aria-label", label);
        // items that can't be played within the page (external weblinks) have no position to repeat
        button.hidden = media === null;
        const duration = (media !== null) ? media.duration() : 0;
        const visible = loopStart !== null && duration > 0 && isFinite(duration);
        flagA.hidden = !visible;
        flagB.hidden = !(visible && loopEnd !== null);
        region.hidden = !(visible && loopEnd !== null);
        if (visible) {
            flagA.style.left = seekOffset(loopStart / duration);
            if (loopEnd !== null) {
                flagB.style.left = seekOffset(loopEnd / duration);
                region.style.left = seekOffset(loopStart / duration);
                region.style.width = seekLength((loopEnd - loopStart) / duration);
            }
        }
    }

    let hintTimer = null;

    /**
     * The thumb of the seek bar moves between half a thumb width from the left edge and half a thumb width from the
     * right edge, so marks at the seek bar use the same scale.
     * @param {number} ratio Position in the item from 0 to 1.
     * @returns {string} A CSS length for the left edge of a mark.
     */
    function seekOffset(ratio) {
        return "calc(var(--admidio-media-player-thumb, 1rem) / 2 + (100% - var(--admidio-media-player-thumb, 1rem)) * " + ratio + ")";
    }

    /**
     * @param {number} ratio Length of a range as part of the item from 0 to 1.
     * @returns {string} A CSS length for the width of a range at the seek bar.
     */
    function seekLength(ratio) {
        return "calc((100% - var(--admidio-media-player-thumb, 1rem)) * " + ratio + ")";
    }

    /**
     * Show a short explanation of the next step above the progress bar.
     * @param {string} text
     */
    function showLoopHint(text) {
        const hint = document.getElementById("adm_media_player_ab_hint");
        hint.textContent = text;
        hint.hidden = false;
        window.clearTimeout(hintTimer);
        hintTimer = window.setTimeout(function() {
            hint.hidden = true;
        }, 4000);
    }

    /**
     * Remove the A-B repeat, e.g. when another item starts.
     */
    function clearLoop() {
        loopStart = null;
        loopEnd = null;
        if (loopTimer !== null) {
            window.clearInterval(loopTimer);
            loopTimer = null;
        }
        showLoopState();
    }

    /**
     * Jump back to the start of the A-B range when its end has been reached.
     */
    function checkLoop() {
        if (loopEnd === null || media === null) {
            return;
        }
        const position = media.currentTime();
        // a position far behind the end was chosen by the user with the seek bar, then the item plays on
        if (position >= loopEnd && position < loopEnd + 1.5) {
            if (picture !== null) {
                // the picture of a video follows the sound at once, not only after its pause between two corrections
                picture.lastSeek = 0;
            }
            media.currentTime(loopStart);
        }
    }

    /**
     * Switch the A-B repeat: first click sets A, second click sets B and starts the repeat, third click removes it.
     */
    function toggleLoop() {
        if (media === null) {
            return;
        }
        if (loopEnd !== null) {
            clearLoop();
            showLoopHint(labels.labelAbHintClear);
            return;
        }
        const position = media.currentTime();
        if (loopStart === null) {
            loopStart = position;
            showLoopHint(labels.labelAbHintA.replace("#VAR1#", formatTime(position)));
        } else if (position - loopStart < 0.5) {
            // the range would be empty: start again at this position
            loopStart = position;
        } else {
            loopEnd = position;
            showLoopHint(labels.labelAbHintB.replace("#VAR1#", formatTime(loopStart)).replace("#VAR2#", formatTime(loopEnd)));
            media.currentTime(loopStart);
            if (loopTimer === null) {
                loopTimer = window.setInterval(checkLoop, 50);
            }
        }
        showLoopState();
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
     * Show the sound only mode at all sound only buttons.
     */
    function showSoundOnlyMode() {
        const label = soundOnlyMode ? labels.labelSoundOnlyOn : labels.labelSoundOnlyOff;
        document.querySelectorAll(".admidio-media-player-sound-only").forEach(function(button) {
            button.setAttribute("aria-pressed", soundOnlyMode.toString());
            button.title = label;
            button.setAttribute("aria-label", label);
        });
    }

    /**
     * Show if the player is playing at the play buttons and the current item.
     */
    function showPlayState() {
        const playing = isPlaying();
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
            if (playing) {
                sessionStopped = false;
            }
            navigator.mediaSession.playbackState = (media === null || sessionStopped) ? "none" : (playing ? "playing" : "paused");
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
        showActiveMarker();
        // the lock screen needs the position only now and then, it moves the progress bar itself
        if (Date.now() - lastPositionUpdate > 1000) {
            updatePositionState();
        }
    }

    /**
     * Tell the phone the position, the length and the speed of the item, so the lock screen and the
     * notification show a progress bar that could be moved.
     */
    function updatePositionState() {
        if (!("mediaSession" in navigator) || typeof navigator.mediaSession.setPositionState !== "function" || media === null) {
            return;
        }
        const duration = media.duration();
        if (!isFinite(duration) || duration <= 0) {
            return;
        }
        lastPositionUpdate = Date.now();
        try {
            navigator.mediaSession.setPositionState({
                duration: duration,
                playbackRate: parseFloat(speedElement.value) || 1,
                position: Math.min(Math.max(media.currentTime(), 0), duration)
            });
        } catch (exception) {
            // some browsers refuse the state while the media is loading
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
                cancelRebuffer(false);
                const promise = player.play();
                if (promise && typeof promise.catch === "function") {
                    // the browser could prevent the autoplay, then the user starts the playback
                    promise.catch(function(error) {
                        debug("play rejected", {error: String(error && error.name) + ": " + String(error && error.message)});
                        showPlayState();
                    });
                }
            },
            pause: function() {
                cancelRebuffer(false);
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
            isAudio: isElement,
            /**
             * @param {boolean} keepPlaying For the audio element: only remove the events, the element keeps
             *                              playing until the next file is set.
             */
            destroy: function(keepPlaying) {
                if (isElement) {
                    handlers.forEach(function(entry) {
                        player.removeEventListener(entry[0], entry[1]);
                    });
                    if (keepPlaying) {
                        return;
                    }
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
    function stop(keepAudio) {
        hidePicture();
        if (keepAudio !== true) {
            nextAudio.pause();
        }
        if (media !== null) {
            media.destroy(keepAudio === true);
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
    /**
     * Check if the item plays or is about to continue: while the player waits for data it is paused, but it
     * continues without the user, so it counts as playing.
     * @returns {boolean}
     */
    function isPlaying() {
        return media !== null && (!media.paused() || (rebuffer !== null && rebuffer.media === media));
    }

    /**
     * Get the seconds of an audio element that are loaded from the current position on.
     * @param {HTMLAudioElement} element
     * @returns {number}
     */
    function getBufferedAhead(element) {
        const position = element.currentTime;
        for (let i = 0; i < element.buffered.length; i++) {
            if (element.buffered.start(i) <= position + 0.3 && element.buffered.end(i) >= position) {
                return element.buffered.end(i) - position;
            }
        }
        return 0;
    }

    /**
     * End the pause for loading data.
     * @param {boolean} resume **true** to continue the playback, **false** if the user or another action has taken over.
     */
    function cancelRebuffer(resume) {
        if (rebuffer === null) {
            return;
        }
        const waiting = rebuffer;
        rebuffer = null;
        window.clearInterval(waiting.timer);
        bar.classList.remove("admidio-media-player-buffering");
        if (originElement.textContent === labels.labelBuffering) {
            originElement.textContent = waiting.text;
        }
        debug("rebuffer end", {resume: resume, ahead: Math.round(getBufferedAhead(waiting.element))});
        if (resume && media === waiting.media) {
            media.play();
        }
        showPlayState();
    }

    /**
     * Pause the playback until enough of the file is loaded, if the browser has run out of data. Browsers continue
     * as soon as a few seconds are there and stop again shortly afterwards when the connection is slow.
     * Not used in the background: the browser may not allow to start the playback there again.
     * @param {object} current The media object of the item.
     * @param {HTMLAudioElement} element The audio element of the item.
     */
    function startRebuffer(current, element) {
        // Only if the playback has really started: the first seconds of a file are the normal start, the browser
        // continues by itself as soon as it has data. Not shortly after a pause that gave up.
        if (rebuffer !== null || media !== current || element.paused || element.seeking || seeking
            || document.visibilityState !== "visible" || element.currentTime < 1 || Date.now() < rebufferBlockedUntil) {
            return;
        }
        const remaining = (isFinite(element.duration) ? element.duration : Infinity) - element.currentTime;
        const needed = Math.min(rebufferSeconds, remaining - 0.5);
        if (needed <= 0 || getBufferedAhead(element) >= needed) {
            return;
        }

        const startedAt = Date.now();
        let bestAhead = getBufferedAhead(element);
        let lastProgress = startedAt;
        const check = function() {
            if (rebuffer === null || rebuffer.element !== element) {
                return;
            }
            const ahead = getBufferedAhead(element);
            const stillNeeded = Math.min(rebufferSeconds, (isFinite(element.duration) ? element.duration : Infinity) - element.currentTime - 0.5);
            if (media !== rebuffer.media || document.visibilityState !== "visible" || ahead >= stillNeeded) {
                cancelRebuffer(media === rebuffer.media);
                return;
            }
            if (ahead > bestAhead + 0.2) {
                bestAhead = ahead;
                lastProgress = Date.now();
            }
            // Some browsers stop loading a paused file after a few seconds, or the connection is gone. Then the
            // playback must not wait forever: it continues, and a new pause for loading must wait a while.
            if (Date.now() - lastProgress > rebufferNoProgressMs || Date.now() - startedAt > rebufferMaxWaitMs) {
                debug("rebuffer gave up", {ahead: Math.round(ahead)});
                rebufferBlockedUntil = Date.now() + rebufferBlockMs;
                cancelRebuffer(true);
            }
        };
        element.pause();
        rebuffer = {element: element, media: current, text: originElement.textContent, timer: window.setInterval(check, 500)};
        originElement.textContent = labels.labelBuffering;
        bar.classList.add("admidio-media-player-buffering");
        debug("rebuffer start", {ahead: Math.round(bestAhead), needed: Math.round(needed)});
        showPlayState();
    }

    function connectMedia() {
        media.on("timeupdate", showProgress);
        media.on("timeupdate", checkLoop);
        media.on("loadedmetadata", showProgress);
        media.on("durationchange", showProgress);
        media.on("durationchange", showLoopState);
        media.on("durationchange", renderTicks);
        media.on("loadedmetadata", renderTicks);
        media.on("play", showPlayState);
        media.on("playing", showPlayState);
        media.on("pause", showPlayState);
        media.on("pause", function() {
            // the browser paused the video because the page went to the background
            if (document.visibilityState === "hidden") {
                continueInBackground();
            }
        });
        media.on("ended", playAfterEnd);
        if (media.isAudio) {
            const currentAudio = media;
            const currentElement = audio;
            // a paused element with preload="metadata" stops loading the file, so a pause for loading would never end
            currentElement.preload = "auto";
            media.on("waiting", function() {
                startRebuffer(currentAudio, currentElement);
            });
            media.on("play", syncPicture);
            media.on("pause", syncPicture);
            media.on("seeked", syncPicture);
            media.on("timeupdate", syncPicture);

            // Chrome doesn't load a file again if its first request fails, e.g. when a phone changes from WLAN to the
            // mobile network. So the file is loaded again up to three times and continues at the same position.
            const current = media;
            let attempts = 0;
            media.on("error", function() {
                const error = audio.error;
                if (media !== current || error === null || error.code === MediaError.MEDIA_ERR_ABORTED || attempts >= 3) {
                    return;
                }
                attempts++;
                const source = audio.getAttribute("src");
                const position = audio.currentTime;
                debug("retry after error", {attempt: attempts, error: error.code});
                window.setTimeout(function() {
                    if (media !== current) {
                        return;
                    }
                    audio.src = source;
                    if (position > 0) {
                        audio.addEventListener("loadedmetadata", function() {
                            audio.currentTime = position;
                        }, {once: true});
                    }
                    media.play();
                }, 1000 * attempts);
            });

            // the next file starts shortly before the end of the current file, the end event is the fallback
            let handedOver = false;
            media.on("timeupdate", function() {
                const remaining = audio.duration - audio.currentTime;
                if (handedOver || media !== current || audio.paused || repeatMode === "one" || loopEnd !== null || nextAudioRow === null
                    || !(remaining > 0 && remaining <= handoffTime)) {
                    return;
                }
                handedOver = true;
                debug("handoff", {file: nextAudioRow.dataset.title, readyState: nextAudio.readyState});
                if (nextAudio.readyState >= 3) {
                    handingOver = true;
                    try {
                        play(nextAudioRow);
                    } finally {
                        handingOver = false;
                    }
                }
            });
        }
        media.setSpeed(parseFloat(speedElement.value));
        media.setLoop(repeatMode === "one");
        showLoopState();
    }

    /**
     * Show the picture of a video file in the stage. Its sound plays in the audio element, so the playback goes on
     * when the page goes to the background, where browsers pause videos. The picture is muted and follows the sound;
     * play, pause and seeking in the video player control the sound. The sound is muted with the button in the bar.
     * @param {HTMLElement} row
     */
    function showPicture(row) {
        hidePicture();
        const video = document.createElement("video");
        video.preload = "auto";
        video.muted = true;
        video.setAttribute("playsinline", "");
        const sourceElement = document.createElement("source");
        sourceElement.src = row.dataset.source;
        if (row.dataset.mime !== "") {
            sourceElement.type = row.dataset.mime;
        }
        video.appendChild(sourceElement);
        stage.className = "admidio-media-player-stage";
        stage.appendChild(video);

        const player = new Plyr(video, {
            seekTime: 10,
            controls: ["play-large", "play", "progress", "current-time", "fullscreen"],
            clickToPlay: true,
            muted: true
        });
        // ownSeek: the position was set by syncPicture(), lastSeek: time of that change
        const current = {player: player, video: video, ownSeek: false, lastSeek: 0};
        picture = current;
        const isActive = function() {
            return picture === current && media !== null && media.isAudio;
        };
        video.addEventListener("loadedmetadata", function() {
            syncPicture();
        });
        video.addEventListener("play", function() {
            if (isActive() && audio.paused) {
                media.play();
            }
        });
        // the browser pauses the video in the background, the sound goes on
        video.addEventListener("pause", function() {
            if (isActive() && document.visibilityState === "visible" && !audio.paused && !video.ended) {
                media.pause();
            }
        });
        // only a new position that the user selected in the video player is taken over by the sound
        video.addEventListener("seeked", function() {
            if (current.ownSeek) {
                current.ownSeek = false;
                return;
            }
            if (isActive() && Math.abs(video.currentTime - audio.currentTime) > 0.5) {
                audio.currentTime = video.currentTime;
            }
        });
        // the picture never plays its own sound, e.g. after the user changed it in the fullscreen of the browser
        video.addEventListener("volumechange", function() {
            if (!video.muted) {
                video.muted = true;
            }
        });
        if (debugEnabled) {
            ["play", "pause", "waiting", "ended"].forEach(function(name) {
                video.addEventListener(name, function() {
                    debug("picture " + name, {pictureTime: Math.round(video.currentTime * 10) / 10});
                });
            });
        }
        // on phones the video should be visible when it starts
        if (window.innerWidth < 992) {
            stage.scrollIntoView({behavior: "smooth", block: "start"});
        }
    }

    /**
     * Remove the picture of a video file from the stage.
     */
    function hidePicture() {
        if (picture !== null) {
            const former = picture;
            picture = null;
            try {
                former.player.destroy();
            } catch (exception) {
                // the player is removed together with the stage content
            }
        }
    }

    /**
     * The picture of a video follows the sound in the audio element: play, pause, position and speed. A jump makes
     * the picture wait for the decoder, so small differences are evened out with the speed of the picture (up to 10
     * percent faster or slower); only differences over one second, e.g. after the background or at the start, are a
     * jump, at most every three seconds.
     */
    function syncPicture() {
        if (picture === null || media === null || !media.isAudio || document.visibilityState !== "visible") {
            return;
        }
        const video = picture.video;
        const difference = audio.currentTime - video.currentTime;
        if (video.readyState >= 1 && !video.seeking && Math.abs(difference) > 1 && Date.now() - picture.lastSeek > 3000) {
            picture.ownSeek = true;
            picture.lastSeek = Date.now();
            video.currentTime = audio.currentTime;
        }
        const correction = Math.abs(difference) < 0.1 ? 0 : Math.max(-0.1, Math.min(0.1, difference * 0.25));
        video.playbackRate = audio.playbackRate * (1 + correction);
        if (audio.paused && !video.paused) {
            video.pause();
        } else if (!audio.paused && video.paused && !video.ended) {
            const promise = video.play();
            if (promise && typeof promise.catch === "function") {
                promise.catch(function() {
                    // the picture starts with the next synchronisation
                });
            }
        }
    }

    /**
     * Create a Plyr player for a video in the stage.
     * @param {HTMLElement} element
     * @param {number} startTime Position in seconds where the video starts.
     * @param {boolean} autoplay Start the playback when the player is ready.
     */
    function startVideo(element, startTime, autoplay) {
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
        if (debugEnabled) {
            ["play", "playing", "pause", "waiting", "stalled", "ended", "error"].forEach(function(name) {
                player.on(name, function() {
                    debug("video " + name);
                });
            });
        }
        if (startTime > 0) {
            player.once("loadedmetadata", function() {
                player.currentTime = startTime;
            });
        }
        player.on("ready", function() {
            if (autoplay) {
                media.play();
            }
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
            album: headline ? headline.textContent.trim() : "",
            artwork: artwork
        });
    }

    /**
     * Get the url of the sound track of a video. Browsers don't play a video file reliably while the page is in the
     * background: they don't load further parts of the file, and Chrome on Android stops the playback when it loses
     * the video decoder. So the server sends only the sound track as audio file, which plays like other audio files.
     * @param {HTMLElement} row
     * @returns {string}
     */
    function getSoundUrl(row) {
        return row.dataset.source + (row.dataset.source.includes("?") ? "&" : "?") + "sound=1";
    }

    /**
     * Load the file of the next item in the second audio element, if it is an audio file or, in the background, the
     * sound of a video. The file starts shortly before the current file ends, see connectMedia().
     */
    function preloadNextAudio() {
        nextAudioRow = null;
        if (currentRow === null || order.length === 0 || repeatMode === "one" || media === null || !media.isAudio) {
            return;
        }
        const index = order.indexOf(currentRow);
        if (index === order.length - 1 && repeatMode !== "all") {
            return;
        }
        const background = document.visibilityState === "hidden";
        let next = order[(index + 1) % order.length];
        if (background) {
            next = getPlayableInBackground(next);
        }
        if (next === null || next === currentRow) {
            return;
        }
        let source = "";
        if (next.dataset.type === "audio") {
            source = next.dataset.source;
        } else if (next.dataset.type === "video") {
            source = getSoundUrl(next);
        } else {
            return;
        }
        if (nextAudio.getAttribute("src") !== source) {
            nextAudio.preload = "auto";
            nextAudio.src = source;
        }
        nextAudioRow = next;
        debug("preload next", {file: next.dataset.title});
    }

    /**
     * Get the next item that can be played while the page is in the background, starting with the item itself.
     * Links can't be played there, e.g. a YouTube video while the screen of the phone is off.
     * @param {HTMLElement} row
     * @returns {HTMLElement|null}
     */
    function getPlayableInBackground(row) {
        const start = order.indexOf(row);
        for (let step = 0; step < order.length; step++) {
            if (start + step >= order.length && repeatMode !== "all") {
                return null;
            }
            const item = order[(start + step) % order.length];
            if (item.dataset.type === "audio" || item.dataset.type === "video") {
                return item;
            }
        }
        return null;
    }

    /**
     * Play an item of the playlist.
     * @param {HTMLElement} row
     * @param {number} [startTime] Position in seconds where the item starts.
     * @param {boolean} [autoplay] Start the playback, default true.
     */
    function play(row, startTime, autoplay) {
        startTime = startTime || 0;
        autoplay = autoplay !== false;
        if (!order.includes(row)) {
            createOrder();
        }

        // In the background (e.g. the screen of the phone is off) browsers don't start videos: links are skipped.
        // The sound of video files always plays in the audio element, the picture is shown when the page is visible.
        const background = document.visibilityState === "hidden";
        if (background && row.dataset.type !== "audio" && row.dataset.type !== "video") {
            const playable = getPlayableInBackground(row);
            if (playable !== null) {
                play(playable);
            } else {
                showPlayState();
            }
            return;
        }
        const type = row.dataset.type;
        const source = row.dataset.source;
        const isFile = type === "audio" || type === "video";
        debug("play item", {next: row.dataset.title, nextType: type, background: background, startTime: startTime, autoplay: autoplay});

        // From one audio file to the next the audio element is not stopped and emptied. Otherwise Chrome on
        // Android could refuse to start the next file while the page is in the background.
        stop(isFile && media !== null && media.isAudio);
        getRows().forEach(function(item) {
            item.classList.remove("admidio-media-player-active", "admidio-media-player-playing");
        });
        row.classList.add("admidio-media-player-active");
        currentRow = row;
        clearLoop();
        markers = [];
        markersLoaded = false;
        markerEditing = null;

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

        let previousAudio = null;
        if (isFile) {
            const audioSource = type === "video" ? getSoundUrl(row) : source;
            if (row === nextAudioRow && nextAudio.getAttribute("src") === audioSource && nextAudio.error === null && nextAudio.readyState >= 2) {
                // the file is loaded in the second element: it becomes the active one, the former element plays
                // the last part of its file
                previousAudio = audio;
                audio = nextAudio;
                nextAudio = previousAudio;
                delete previousAudio.dataset.active;
                audio.dataset.active = "1";
                if (!handingOver) {
                    // the user selected another item: the former file stops at once
                    previousAudio.pause();
                }
            } else {
                audio.src = audioSource;
            }
            nextAudioRow = null;
            media = createMedia(audio, true);
            media.soundOnly = type === "video";
            connectMedia();
            if (startTime > 0) {
                audio.addEventListener("loadedmetadata", function() {
                    audio.currentTime = startTime;
                }, {once: true});
            }
            if (autoplay) {
                media.play();
            }
            if (type === "video" && !background && !soundOnlyMode) {
                showPicture(row);
            }
        } else if ((type === "youtube" && getYouTubeId(source) !== null) || (type === "vimeo" && getVimeoId(source) !== null)) {
            const embed = document.createElement("div");
            embed.dataset.plyrProvider = type;
            embed.dataset.plyrEmbedId = (type === "youtube") ? getYouTubeId(source) : getVimeoId(source);
            startVideo(embed, 0, true);
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
        markersItemChanged();
        if (previousAudio === null) {
            preloadNextAudio();
        } else {
            // the next file is loaded when the former element has played the end of its file
            const tail = previousAudio;
            const preload = function() {
                if (tail === nextAudio && media !== null && media.isAudio) {
                    preloadNextAudio();
                }
            };
            tail.addEventListener("ended", preload, {once: true});
            tail.addEventListener("pause", preload, {once: true});
        }
    }

    /**
     * Continue after the end of an item depending on the repeat mode.
     */
    function playAfterEnd() {
        if (loopEnd !== null) {
            // the end of the A-B range is the end of the item
            media.currentTime(loopStart);
            media.play();
        } else if (repeatMode === "one") {
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
            if (isPlaying()) {
                media.pause();
            } else {
                media.play();
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

    // play an item with a click on its row, links of the row have their own function; the handler is bound to
    // the table and not to the document, because Safari on the iPhone only turns a tap on a table row into a click
    // if the row or one of its parents up to the table listens to clicks
    $("#adm_media_player_items").on("click", ".admidio-media-player-item", function(event) {
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
    applyMarkersMode();
    });
    // ---- markers: named positions of the current item, only active when the user has switched them on
    const markerKeys = {mode: "admidio-media-player-markers"};
    const markersElement = document.getElementById("adm_media_player_markers");
    const markersListElement = document.getElementById("adm_media_player_markers_list");
    const markersMessageElement = document.getElementById("adm_media_player_markers_message");
    const ticksElement = document.getElementById("adm_media_player_ticks");
    const markerBarButtons = document.querySelectorAll(".admidio-media-player-marker-only");
    const validLogin = labels.validLogin === "1";
    let markersMode = readSetting(markerKeys.mode, ["0", "1"], "0") === "1";
    let markers = [];
    let markersCanShare = false;
    // true after the markers of the current item have been loaded
    let markersLoaded = false;
    // UUID of the marker that is edited, "new" for the form of a new marker
    let markerEditing = null;
    let activeMarkerUuid = null;

    /**
     * The identifier of the current item for the requests of the markers, or null if the item has no markers.
     * @returns {object|null}
     */
    function getMarkerTarget() {
        if (currentRow === null || media === null) {
            return null;
        }
        if (currentRow.dataset.fileUuid) {
            return {file_uuid: currentRow.dataset.fileUuid};
        }
        if (currentRow.dataset.weblinkUuid) {
            return {weblink_uuid: currentRow.dataset.weblinkUuid};
        }
        return null;
    }

    /**
     * Read a time like 83, 1:23, 1:23.5 or 1:02:03 as seconds.
     * @param {string} text
     * @returns {number|null} The seconds or null if the text is no time.
     */
    function parseMarkerTime(text) {
        text = text.trim().replace(",", ".");
        if (!/^\d+(:\d{1,2}){0,2}(\.\d+)?$/.test(text)) {
            return null;
        }
        const parts = text.split(":").map(parseFloat);
        let seconds = 0;
        parts.forEach(function(part) {
            seconds = seconds * 60 + part;
        });
        return (parts.length > 1 && parts.slice(1).some(function(part) { return part >= 60; })) ? null : Math.round(seconds * 10) / 10;
    }

    function sendMarkerRequest(url, data) {
        return $.post(url, $.param(Object.assign({adm_csrf_token: labels.csrfToken}, data)), null, "json").then(function(response) {
            if (response.status !== "success") {
                return $.Deferred().reject(response.message).promise();
            }
            return response;
        }, function(xhr) {
            return $.Deferred().reject(xhr.responseText || xhr.statusText).promise();
        });
    }

    function showMarkerError(message) {
        messageBox(message || labels.labelMarkerError, "", "error");
    }

    let stickyFrame = null;
    let sheetScroll = null;

    /**
     * Heights of the player parts that stand above the list of a phone: the picture of a video and the bar.
     */
    function updateSheetMetrics() {
        const sheet = document.body.classList.contains("admidio-media-player-markers-sheet");
        root.style.setProperty("--admidio-media-player-bar-height", bar.hidden ? "0px" : bar.offsetHeight + "px");
        root.style.setProperty("--admidio-media-player-stage-height", (sheet && !stage.classList.contains("d-none")) ? stage.offsetHeight + "px" : "0px");
    }

    if (window.ResizeObserver) {
        const sheetObserver = new ResizeObserver(function() {
            if (markersMode) {
                updateSheetMetrics();
            }
        });
        sheetObserver.observe(bar);
        sheetObserver.observe(stage);
    }

    /**
     * Some themes have a header that stays at the top of the window. The sticky panel must stand below it, otherwise
     * its upper part is covered. The height of such a header is read from the elements at the top of the window.
     */
    function updateStickyOffset() {
        stickyFrame = null;
        if (!markersMode) {
            return;
        }
        let offset = 0;
        [0.25, 0.5, 0.75].forEach(function(fraction) {
            document.elementsFromPoint(Math.round(window.innerWidth * fraction), 3).forEach(function(element) {
                if (markersElement.contains(element) || bar.contains(element) || stage.contains(element)) {
                    return;
                }
                const position = window.getComputedStyle(element).position;
                if (position === "fixed" || position === "sticky") {
                    offset = Math.max(offset, element.getBoundingClientRect().bottom);
                }
            });
        });
        root.style.setProperty("--admidio-media-player-sticky-top", Math.max(0, Math.round(offset)) + "px");
    }

    function requestStickyOffset() {
        if (stickyFrame === null && markersMode) {
            stickyFrame = window.requestAnimationFrame(updateStickyOffset);
        }
    }

    /**
     * Show or hide everything of the markers: panel, buttons of the bar and ticks at the seek bar.
     */
    function applyMarkersMode() {
        const open = markersMode;
        root.classList.toggle("admidio-media-player-markers-open", open);
        // with the panel the page uses the full width of the window like the edit mode, not the default 1000 px
        const content = document.getElementById("adm_content");
        if (content !== null) {
            content.classList.toggle("admidio-max-content", open);
        }
        markersElement.hidden = !open;
        const target = getMarkerTarget() !== null;
        markerBarButtons.forEach(function(button) {
            button.hidden = !markersMode || (button.id === "adm_media_player_marker_add" && !target);
        });
        const panelButton = document.getElementById("adm_media_player_marker_panel");
        panelButton.setAttribute("aria-pressed", open.toString());
        panelButton.title = labels.labelMarkersHide;
        panelButton.setAttribute("aria-label", labels.labelMarkersHide);
        panelButton.querySelector("i").className = "bi bi-flag-fill";
        document.querySelectorAll(".admidio-media-player-markers-toggle").forEach(function(button) {
            const label = markersMode ? labels.labelMarkersHide : labels.labelMarkersShow;
            button.setAttribute("aria-pressed", markersMode.toString());
            button.title = label;
            button.setAttribute("aria-label", label);
            button.querySelector("i").className = markersMode ? "bi bi-flag-fill" : "bi bi-flag";
        });
        document.getElementById("adm_media_player_markers_track").textContent = (currentRow !== null) ? currentRow.dataset.title : "";
        ticksElement.hidden = !markersMode;
        // on phones the player (picture, bar) moves to the top and the list stands below it
        const sheet = open && !bar.hidden;
        const sheetWas = document.body.classList.contains("admidio-media-player-markers-sheet");
        if (sheet && !sheetWas) {
            // the picture leaves the page flow, so the browser may shorten the page and move its scroll position
            sheetScroll = window.scrollY;
        }
        document.body.classList.toggle("admidio-media-player-markers-sheet", sheet);
        document.documentElement.classList.toggle("admidio-media-player-markers-sheet", sheet);
        if (!sheet && sheetWas && sheetScroll !== null) {
            window.scrollTo(0, sheetScroll);
            sheetScroll = null;
        }
        updateSheetMetrics();
        requestStickyOffset();
        const addForm = document.getElementById("adm_media_player_marker_add_form");
        if (addForm) {
            addForm.hidden = !target;
        }
        let message = "";
        if (currentRow === null) {
            message = labels.labelMarkerNoItem;
        } else if (!target) {
            message = labels.labelMarkerUnavailable;
        } else if (markersLoaded && markers.length === 0 && markerEditing === null) {
            message = labels.labelMarkerEmpty;
        }
        markersMessageElement.textContent = message;
        markersMessageElement.hidden = message === "";
    }

    function createIconButton(icon, label, handler, extraClass) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-link admidio-media-player-marker-action" + (extraClass ? " " + extraClass : "");
        button.title = label;
        button.setAttribute("aria-label", label);
        const i = document.createElement("i");
        i.className = "bi " + icon;
        button.appendChild(i);
        button.addEventListener("click", handler);
        return button;
    }

    /**
     * Create the row with the form for a new or an existing marker.
     * @param {object|null} marker The marker that is edited or null for a new marker.
     * @returns {HTMLLIElement}
     */
    function createMarkerForm(marker) {
        const item = document.createElement("li");
        item.className = "admidio-media-player-marker admidio-media-player-marker-form";
        const form = document.createElement("form");
        form.noValidate = true;

        const time = document.createElement("input");
        time.type = "text";
        time.className = "form-control form-control-sm admidio-media-player-marker-time-input";
        time.inputMode = "decimal";
        time.autocomplete = "off";
        time.setAttribute("aria-label", labels.labelMarkerTime);
        time.value = formatTime(marker !== null ? marker.position : (media !== null ? media.currentTime() : 0));
        const now = createIconButton("bi-clock", labels.labelMarkerNow, function() {
            if (media !== null) {
                time.value = formatTime(media.currentTime());
            }
        });
        const name = document.createElement("input");
        name.type = "text";
        name.className = "form-control form-control-sm";
        name.maxLength = 100;
        name.autocomplete = "off";
        name.placeholder = labels.labelMarkerName;
        name.setAttribute("aria-label", labels.labelMarkerName);
        name.value = marker !== null ? marker.name : "";

        const first = document.createElement("div");
        first.className = "admidio-media-player-marker-form-row";
        first.append(time, now, name);
        form.appendChild(first);

        // bar number or rehearsal letter in the sheet music
        const bar = document.createElement("input");
        bar.type = "text";
        bar.className = "form-control form-control-sm admidio-media-player-marker-bar-input";
        bar.maxLength = 20;
        bar.autocomplete = "off";
        bar.placeholder = labels.labelMarkerBar;
        bar.setAttribute("aria-label", labels.labelMarkerBar);
        bar.value = marker !== null ? marker.bar : "";
        const second = document.createElement("div");
        second.className = "admidio-media-player-marker-form-row admidio-media-player-marker-form-second";
        second.appendChild(bar);
        form.appendChild(second);

        let shared = null;
        if (labels.canShareMarkers === "1" || (marker !== null && marker.shared)) {
            const check = document.createElement("div");
            check.className = "form-check admidio-media-player-marker-shared-check";
            shared = document.createElement("input");
            shared.type = "checkbox";
            shared.className = "form-check-input";
            shared.checked = marker !== null && marker.shared;
            shared.id = "adm_media_player_marker_shared";
            shared.disabled = labels.canShareMarkers !== "1" && !(marker !== null && marker.shared);
            const sharedLabel = document.createElement("label");
            sharedLabel.className = "form-check-label small";
            sharedLabel.htmlFor = shared.id;
            sharedLabel.textContent = labels.labelMarkerShared;
            check.append(shared, sharedLabel);
            form.appendChild(check);
        }

        const buttons = document.createElement("div");
        buttons.className = "admidio-media-player-marker-form-buttons";
        const save = document.createElement("button");
        save.type = "submit";
        save.className = "btn btn-primary btn-sm";
        save.textContent = labels.labelSave;
        const cancel = document.createElement("button");
        cancel.type = "button";
        cancel.className = "btn btn-secondary btn-sm";
        cancel.textContent = labels.labelCancel;
        cancel.addEventListener("click", function() {
            markerEditing = null;
            renderMarkers();
        });
        buttons.append(save, cancel);
        form.appendChild(buttons);

        form.addEventListener("keydown", function(event) {
            if (event.key === "Escape") {
                cancel.click();
            }
        });
        form.addEventListener("submit", function(event) {
            event.preventDefault();
            const position = parseMarkerTime(time.value);
            if (position === null) {
                showMarkerError(labels.labelMarkerTimeInvalid);
                time.focus();
                return;
            }
            const data = {position: position, name: name.value, bar: bar.value, shared: (shared !== null && shared.checked) ? 1 : 0};
            let url = labels.markerSaveUrl;
            if (marker !== null) {
                url += "&marker_uuid=" + encodeURIComponent(marker.uuid);
            } else {
                Object.assign(data, getMarkerTarget());
            }
            save.disabled = true;
            sendMarkerRequest(url, data).done(function(response) {
                markerEditing = null;
                markers = markers.filter(function(entry) { return entry.uuid !== response.marker.uuid; });
                markers.push(response.marker);
                sortMarkers();
                renderMarkers();
                renderTicks();
            }).fail(function(message) {
                save.disabled = false;
                showMarkerError(message);
            });
        });
        item.appendChild(form);
        // the form is filled in after it is in the page, so the name could get the focus
        item.focusName = function() {
            name.focus();
            name.select();
        };
        return item;
    }

    function sortMarkers() {
        markers.sort(function(a, b) {
            return (a.position - b.position) || a.name.localeCompare(b.name);
        });
    }

    /**
     * Jump to a marker. A stopped playback starts there.
     */
    function jumpToMarker(marker) {
        if (media === null) {
            return;
        }
        if (loopEnd !== null && (marker.position < loopStart || marker.position >= loopEnd)) {
            // a marker outside of the repeated range ends the repeat, otherwise the end of the range (often the next marker)
            // would send the playback straight back to the start of the range
            clearLoop();
            showLoopHint(labels.labelAbHintClear);
        }
        if (picture !== null) {
            picture.lastSeek = 0;
        }
        media.currentTime(marker.position);
        if (media.paused()) {
            media.play();
        }
        showProgress();
    }

    /**
     * Repeat the part from a marker to the next marker or to the end of the item.
     */
    function loopFromMarker(marker) {
        if (media === null) {
            return;
        }
        const next = markers.find(function(entry) { return entry.position > marker.position + 0.4; });
        const end = next ? next.position : media.duration();
        if (!isFinite(end) || end - marker.position < 0.5) {
            return;
        }
        loopStart = marker.position;
        loopEnd = end;
        if (loopTimer === null) {
            loopTimer = window.setInterval(checkLoop, 50);
        }
        showLoopState();
        showLoopHint(labels.labelAbHintB.replace("#VAR1#", formatTime(loopStart)).replace("#VAR2#", formatTime(loopEnd)));
        jumpToMarker({position: loopStart});
    }

    function createMarkerRow(marker) {
        if (markerEditing === marker.uuid) {
            return createMarkerForm(marker);
        }
        const item = document.createElement("li");
        item.className = "admidio-media-player-marker";
        item.dataset.uuid = marker.uuid;
        const jump = document.createElement("button");
        jump.type = "button";
        jump.className = "admidio-media-player-marker-jump";
        jump.title = labels.labelMarkerJump;
        const time = document.createElement("span");
        time.className = "admidio-media-player-marker-time";
        time.textContent = formatTime(marker.position);
        const name = document.createElement("span");
        name.className = "admidio-media-player-marker-name";
        name.textContent = marker.name;
        jump.append(time, name);
        // the bar and the shared icon stand behind the name in fixed columns, so the names are aligned
        const bar = document.createElement("span");
        bar.className = "admidio-media-player-marker-bar-slot";
        if (marker.bar !== "") {
            const badge = document.createElement("span");
            badge.className = "admidio-media-player-marker-bar";
            badge.textContent = marker.bar;
            badge.title = labels.labelMarkerBar + ": " + marker.bar;
            bar.appendChild(badge);
        }
        const sharedSlot = document.createElement("span");
        sharedSlot.className = "admidio-media-player-marker-shared-slot";
        if (marker.shared) {
            const icon = document.createElement("i");
            icon.className = "bi bi-people-fill admidio-media-player-marker-shared";
            icon.title = labels.labelMarkerSharedInfo;
            icon.setAttribute("aria-label", labels.labelMarkerSharedInfo);
            sharedSlot.appendChild(icon);
        }
        jump.append(bar, sharedSlot);
        jump.addEventListener("click", function() {
            jumpToMarker(marker);
        });
        item.appendChild(jump);
        const actions = document.createElement("span");
        actions.className = "admidio-media-player-marker-actions";
        actions.appendChild(createIconButton("bi-repeat", labels.labelMarkerLoop, function() {
            loopFromMarker(marker);
        }, "admidio-media-player-marker-loop"));
        if (marker.canEdit) {
            actions.appendChild(createIconButton("bi-pencil", labels.labelEdit, function() {
                markerEditing = marker.uuid;
                renderMarkers();
            }, "admidio-media-player-marker-edit"));
            actions.appendChild(createIconButton("bi-trash", labels.labelDelete, function() {
                if (!window.confirm(labels.labelMarkerDeleteConfirm.replace("#VAR1#", marker.name))) {
                    return;
                }
                sendMarkerRequest(labels.markerDeleteUrl + "&marker_uuid=" + encodeURIComponent(marker.uuid), {}).done(function() {
                    markers = markers.filter(function(entry) { return entry.uuid !== marker.uuid; });
                    renderMarkers();
                    renderTicks();
                }).fail(showMarkerError);
            }, "admidio-media-player-marker-delete"));
        }
        item.appendChild(actions);
        return item;
    }

    /**
     * Fill the list of the panel.
     */
    function renderMarkers() {
        activeMarkerUuid = null;
        markersListElement.replaceChildren();
        let form = null;
        if (markerEditing === "new") {
            form = createMarkerForm(null);
            markersListElement.appendChild(form);
        }
        markers.forEach(function(marker) {
            const row = createMarkerRow(marker);
            markersListElement.appendChild(row);
            if (markerEditing === marker.uuid) {
                form = row;
            }
        });
        applyMarkersMode();
        showActiveMarker();
        markersElement.classList.toggle("admidio-media-player-markers-editing", markerEditing !== null);
        if (form !== null && form.focusName) {
            form.focusName();
            // the whole form must be visible, the focus alone scrolls only to the name field
            form.scrollIntoView({block: "nearest"});
        }
    }

    /**
     * Mark the ticks of the markers at the seek bar.
     */
    function renderTicks() {
        ticksElement.replaceChildren();
        const duration = (media !== null) ? media.duration() : 0;
        if (!isFinite(duration) || duration <= 0) {
            return;
        }
        markers.forEach(function(marker) {
            if (marker.position > duration) {
                return;
            }
            const tick = document.createElement("span");
            tick.className = "admidio-media-player-tick" + (marker.shared ? " admidio-media-player-tick-shared" : "");
            tick.style.left = seekOffset(marker.position / duration);
            tick.title = formatTime(marker.position) + (marker.bar !== "" ? " [" + marker.bar + "]" : "") + " " + marker.name;
            ticksElement.appendChild(tick);
        });
    }

    /**
     * Highlight the marker the playback has passed last.
     */
    function showActiveMarker() {
        if (!markersMode || markers.length === 0 || media === null) {
            return;
        }
        const position = media.currentTime() + 0.05;
        let active = null;
        markers.forEach(function(marker) {
            if (marker.position <= position) {
                active = marker;
            }
        });
        const uuid = active !== null ? active.uuid : null;
        if (uuid === activeMarkerUuid) {
            return;
        }
        activeMarkerUuid = uuid;
        markersListElement.querySelectorAll(".admidio-media-player-marker").forEach(function(row) {
            row.classList.toggle("admidio-media-player-marker-active", row.dataset.uuid === uuid);
        });
    }

    /**
     * Load the markers of the current item. Without markers mode nothing is requested.
     */
    function loadMarkers() {
        markers = [];
        markersLoaded = false;
        markerEditing = null;
        renderTicks();
        const target = getMarkerTarget();
        if (!markersMode || target === null) {
            renderMarkers();
            return;
        }
        renderMarkers();
        const row = currentRow;
        $.getJSON(labels.markersUrl + "&" + $.param(target)).done(function(response) {
            if (row !== currentRow || response.status !== "success") {
                return;
            }
            markers = response.markers;
            markersCanShare = response.canShare;
            markersLoaded = true;
            sortMarkers();
            renderMarkers();
            renderTicks();
        }).fail(function() {
            if (row === currentRow) {
                markersLoaded = true;
                renderMarkers();
            }
        });
    }

    /**
     * A new item was started or has loaded its metadata, the markers of the item are loaded.
     */
    function markersItemChanged() {
        loadMarkers();
    }

    /**
     * Create a marker at the current position and open it for naming.
     */
    function addMarkerNow() {
        const target = getMarkerTarget();
        if (target === null || media === null) {
            return;
        }
        const position = Math.round(media.currentTime() * 10) / 10;
        sendMarkerRequest(labels.markerSaveUrl, Object.assign({position: position, name: "", shared: 0}, target)).done(function(response) {
            markers.push(response.marker);
            sortMarkers();
            markersLoaded = true;
            markerEditing = response.marker.uuid;
            renderMarkers();
            renderTicks();
        }).fail(showMarkerError);
    }

    function previousNextMarker(direction) {
        if (media === null || markers.length === 0) {
            return;
        }
        const position = media.currentTime();
        let target = null;
        if (direction > 0) {
            target = markers.find(function(marker) { return marker.position > position + 0.5; });
        } else {
            markers.forEach(function(marker) {
                if (marker.position < position - 1.5) {
                    target = marker;
                }
            });
            if (target === null) {
                target = {position: 0};
            }
        }
        if (target !== null) {
            jumpToMarker(target);
        }
    }

    /**
     * Switch the markers on or off. All flags (header of the playlist, player bar, close button of the panel) do the same.
     */
    function setMarkersMode(on) {
        markersMode = on;
        writeSetting(markerKeys.mode, markersMode ? "1" : "0");
        if (markersMode) {
            loadMarkers();
        } else {
            markers = [];
            markerEditing = null;
            renderTicks();
            applyMarkersMode();
        }
    }

    const phoneQuery = window.matchMedia("(max-width: 991.98px)");
    const reducedMotionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
    let sheetAnimating = false;

    function sheetIsOpen() {
        return document.body.classList.contains("admidio-media-player-markers-sheet");
    }

    /**
     * Switch the markers on. On phones the player moves up and the list slides in below it.
     */
    function openMarkers() {
        if (markersMode) {
            return;
        }
        const barTop = bar.hidden ? 0 : bar.getBoundingClientRect().top;
        setMarkersMode(true);
        if (!sheetIsOpen() || !phoneQuery.matches || reducedMotionQuery.matches) {
            return;
        }
        root.style.setProperty("--admidio-media-player-bar-rise", Math.round(barTop - bar.getBoundingClientRect().top) + "px");
        document.body.classList.add("admidio-media-player-markers-opening");
        window.setTimeout(function() {
            document.body.classList.remove("admidio-media-player-markers-opening");
        }, 300);
    }

    /**
     * Switch the markers off. On phones the list slides down and the player returns to the bottom first.
     */
    function closeMarkers() {
        if (!markersMode || sheetAnimating) {
            return;
        }
        if (!sheetIsOpen() || !phoneQuery.matches || reducedMotionQuery.matches) {
            setMarkersMode(false);
            return;
        }
        sheetAnimating = true;
        root.style.setProperty("--admidio-media-player-bar-drop", Math.round(window.innerHeight - bar.getBoundingClientRect().bottom) + "px");
        document.body.classList.add("admidio-media-player-markers-closing");
        window.setTimeout(function() {
            document.body.classList.remove("admidio-media-player-markers-closing");
            sheetAnimating = false;
            setMarkersMode(false);
        }, 220);
    }

    /**
     * Swipe gestures of a touch screen: a vertical swipe (distance or speed high enough) calls up or down.
     * options.ignore: selector of elements that keep their own gesture, options.scroller: element that must stand at its top for "down".
     */
    function attachSwipe(element, options) {
        let startX = 0;
        let startY = 0;
        let startTime = 0;
        let tracking = false;
        let startedAtTop = true;
        element.addEventListener("touchstart", function(event) {
            tracking = false;
            if (event.touches.length !== 1 || !phoneQuery.matches || (options.ignore && event.target.closest(options.ignore))) {
                return;
            }
            startX = event.touches[0].clientX;
            startY = event.touches[0].clientY;
            startTime = Date.now();
            startedAtTop = !options.scroller || options.scroller.scrollTop <= 0;
            tracking = true;
        }, {passive: true});
        element.addEventListener("touchcancel", function() {
            tracking = false;
        }, {passive: true});
        element.addEventListener("touchend", function(event) {
            if (!tracking) {
                return;
            }
            tracking = false;
            const touch = event.changedTouches[0];
            const deltaY = touch.clientY - startY;
            const distance = Math.abs(deltaY);
            const speed = distance / Math.max(1, Date.now() - startTime);
            if (distance < 40 || Math.abs(touch.clientX - startX) > distance * 0.7 || (distance < 70 && speed < 0.4)) {
                return;
            }
            if (deltaY < 0) {
                if (options.up) {
                    options.up();
                }
            } else if (options.down && startedAtTop && (!options.scroller || options.scroller.scrollTop <= 0)) {
                options.down();
            }
        }, {passive: true});
    }

    // swipe up at the player bar opens the markers, swipe down at the bar, the picture or the list closes them
    attachSwipe(bar, {
        ignore: ".admidio-media-player-seek, select, input, textarea",
        up: function() {
            if (!sheetIsOpen()) {
                openMarkers();
            }
        },
        down: function() {
            if (sheetIsOpen()) {
                closeMarkers();
            }
        }
    });
    attachSwipe(stage, {
        ignore: ".plyr__controls",
        down: function() {
            if (sheetIsOpen()) {
                closeMarkers();
            }
        }
    });
    attachSwipe(markersElement, {
        ignore: "input, select, textarea",
        scroller: markersElement,
        down: closeMarkers
    });

    $(document).on("click", ".admidio-media-player-markers-toggle", function() {
        if (markersMode) {
            closeMarkers();
        } else {
            openMarkers();
        }
    });
    document.getElementById("adm_media_player_marker_panel").addEventListener("click", closeMarkers);
    document.getElementById("adm_media_player_markers_close").addEventListener("click", closeMarkers);
    document.getElementById("adm_media_player_marker_previous").addEventListener("click", function() {
        previousNextMarker(-1);
    });
    document.getElementById("adm_media_player_marker_next").addEventListener("click", function() {
        previousNextMarker(1);
    });
    const markerAddButton = document.getElementById("adm_media_player_marker_add");
    if (markerAddButton) {
        markerAddButton.addEventListener("click", addMarkerNow);
    }
    const markerAddFormButton = document.getElementById("adm_media_player_marker_add_form");
    if (markerAddFormButton) {
        markerAddFormButton.addEventListener("click", function() {
            if (getMarkerTarget() === null) {
                return;
            }
            markerEditing = "new";
            renderMarkers();
        });
    }
    window.addEventListener("resize", applyMarkersMode);
    window.addEventListener("scroll", requestStickyOffset, {passive: true});

    document.getElementById("adm_media_player_ab").addEventListener("click", toggleLoop);
    $(document).on("click", ".admidio-media-player-shuffle", function() {
        shuffle = !shuffle;
        writeSetting(storageKeys.shuffle, shuffle ? "1" : "0");
        createOrder();
        showShuffleMode();
    });

    $(document).on("click", ".admidio-media-player-sound-only", function() {
        soundOnlyMode = !soundOnlyMode;
        writeSetting(storageKeys.soundOnly, soundOnlyMode ? "1" : "0");
        showSoundOnlyMode();
        if (media !== null && media.soundOnly && currentRow !== null) {
            if (soundOnlyMode) {
                // the sound goes on, the picture is removed and not loaded any more
                hidePicture();
                stage.replaceChildren();
                stage.className = "admidio-media-player-stage d-none";
            } else if (document.visibilityState === "visible") {
                showPicture(currentRow);
            }
        }
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
    // the sound of all items is muted and switched on again with the button in the bar
    muteElement.addEventListener("click", function() {
        const muted = !audio.muted;
        audio.muted = muted;
        nextAudio.muted = muted;
        muteElement.setAttribute("aria-pressed", muted ? "true" : "false");
        const label = muted ? muteElement.dataset.labelUnmute : muteElement.dataset.labelMute;
        muteElement.title = label;
        muteElement.setAttribute("aria-label", label);
        muteElement.querySelector("i").className = "bi " + (muted ? "bi-volume-mute-fill" : "bi-volume-up-fill");
    });

    speedElement.addEventListener("change", function() {
        if (media !== null) {
            media.setSpeed(parseFloat(speedElement.value));
            updatePositionState();
        }
    });

    // the space key starts and pauses the playback if no form element has the focus
    document.addEventListener("keydown", function(event) {
        if (event.code === "Space" && media !== null && !event.target.closest("input, textarea, select, button, a, [contenteditable]")) {
            event.preventDefault();
            togglePlay();
        }
    });

    /**
     * Browsers pause videos while the page is in the background, e.g. when the screen of the phone is turned off.
     * A video file continues as sound at the same position, a YouTube or Vimeo video continues with the next item
     * that can be played in the background.
     */
    function continueInBackground() {
        if (switchingToBackground || media === null || media.isAudio || currentRow === null) {
            return;
        }
        switchingToBackground = true;
        debug("continue in background");
        try {
            play(currentRow, currentRow.dataset.type === "video" ? media.currentTime() : 0);
        } finally {
            switchingToBackground = false;
        }
    }

    // a playing video continues in the background, a video that was played as sound there is shown again at the
    // same position when the page is visible
    document.addEventListener("visibilitychange", function() {
        if (document.visibilityState === "hidden") {
            // in the background the browser may not allow to start the playback again, so it continues now
            cancelRebuffer(true);
        }
        if (document.visibilityState === "hidden" && media !== null && !media.paused()) {
            continueInBackground();
            preloadNextAudio();
        } else if (document.visibilityState === "visible" && media !== null && media.soundOnly && currentRow !== null && !soundOnlyMode) {
            // the sound goes on, the picture follows it
            if (picture === null) {
                showPicture(currentRow);
            } else {
                syncPicture();
            }
        }
    });

    // controls on the lock screen of phones and in the media keys of the keyboard
    if ("mediaSession" in navigator) {
        const seekBy = function(seconds) {
            if (media !== null) {
                media.currentTime(Math.min(Math.max(media.currentTime() + seconds, 0), media.duration() || 0));
                updatePositionState();
            }
        };
        const actions = {
            play: function() { if (media !== null) { media.play(); } else { togglePlay(); } },
            pause: function() { if (media !== null) { media.pause(); } },
            stop: function() {
                sessionStopped = true;
                if (media !== null) {
                    media.pause();
                    media.currentTime(0);
                }
                navigator.mediaSession.playbackState = "none";
            },
            previoustrack: playPrevious,
            nexttrack: playNext,
            seekto: function(details) {
                if (media !== null) {
                    media.currentTime(details.seekTime);
                    updatePositionState();
                }
            }
        };
        // the iPhone shows either the skip buttons or previous/next on the lock screen, so the skip buttons
        // are only offered to the other devices, which show both
        const isApple = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
        if (!isApple) {
            actions.seekbackward = function(details) { seekBy(-((details && details.seekOffset) || 10)); };
            actions.seekforward = function(details) { seekBy((details && details.seekOffset) || 10); };
        }
        Object.keys(actions).forEach(function(action) {
            try {
                navigator.mediaSession.setActionHandler(action, function(details) {
                    debug("media session " + action);
                    actions[action](details);
                });
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

    // the icons of the web app manifest (e.g. the logo of the organization) are the cover image
    const manifestLink = document.querySelector("link[rel=manifest]");
    if (manifestLink && "mediaSession" in navigator && typeof fetch === "function") {
        fetch(manifestLink.href, {credentials: "same-origin"})
            .then(function(response) { return response.json(); })
            .then(function(manifest) {
                const icons = (manifest.icons || []).filter(function(icon) {
                    return icon.src && (!icon.purpose || icon.purpose.split(" ").includes("any"));
                }).map(function(icon) {
                    return {src: new URL(icon.src, manifestLink.href).href, sizes: icon.sizes || "", type: icon.type || "image/png"};
                });
                // only icons that could be loaded are used, otherwise the phone would show an empty cover
                return Promise.all(icons.map(function(icon) {
                    return new Promise(function(resolve) {
                        const image = new Image();
                        image.onload = function() { resolve(icon); };
                        image.onerror = function() { resolve(null); };
                        image.src = icon.src;
                    });
                }));
            })
            .then(function(icons) {
                icons = icons.filter(function(icon) { return icon !== null; });
                if (icons.length > 0) {
                    artwork = icons;
                    if (currentRow !== null) {
                        updateMediaSession(currentRow);
                    }
                }
            })
            .catch(function() {
                // the Admidio logo stays the cover image
            });
    }

    showRepeatMode();
    showShuffleMode();
    showSoundOnlyMode();
    applyMarkersMode();
    showPlayState();
    loadDurations();
});
