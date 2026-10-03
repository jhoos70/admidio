/**
 ***********************************************************************************************
 * Player of the media player plugin
 *
 * Plays the items of a playlist one after the other. Files and weblinks to audio or video files are
 * played with the browser player, YouTube and Vimeo videos in their embedded players. Embedded players
 * are only loaded after the user started an item. All other weblinks are opened in a new window.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$(function() {
    const stage = document.getElementById("adm_media_player_stage");
    if (!stage) {
        return;
    }

    const titleElement = document.getElementById("adm_media_player_title");
    const originElement = document.getElementById("adm_media_player_origin");
    const actionsElement = document.getElementById("adm_media_player_actions");
    let player = null;
    let currentRow = null;

    /**
     * Get all items of the playlist in their current order.
     * @returns {HTMLElement[]}
     */
    function getRows() {
        return Array.from(document.querySelectorAll(".admidio-media-player-item"));
    }

    /**
     * Get the id of a YouTube video from its url.
     * @param {string} url
     * @returns {string|null}
     */
    function getYouTubeId(url) {
        const match = url.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|v\/|shorts\/|live\/|watch\?(?:.*&)?v=))([\w-]{11})/);
        if (match) {
            return match[1];
        }
        return null;
    }

    /**
     * Get the id of a Vimeo video from its url.
     * @param {string} url
     * @returns {string|null}
     */
    function getVimeoId(url) {
        const match = url.match(/vimeo\.com\/(?:.*\/)?(\d+)/);
        if (match) {
            return match[1];
        }
        return null;
    }

    /**
     * Remove the current player and the content of the stage.
     */
    function clearStage() {
        if (player !== null) {
            try {
                player.destroy();
            } catch (exception) {
                // the player is removed together with the stage content
            }
            player = null;
        }
        stage.replaceChildren();
        actionsElement.replaceChildren();
    }

    /**
     * Create a link button for the header of the player.
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
     * Create a Plyr player for the element and start the playback.
     * @param {HTMLElement} element
     */
    function startPlayer(element) {
        player = new Plyr(element, {
            seekTime: 10,
            speed: {selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2]},
            youtube: {noCookie: true, rel: 0, modestbranding: 1},
            vimeo: {dnt: true}
        });
        player.on("ready", function() {
            const promise = player.play();
            if (promise && typeof promise.catch === "function") {
                // the browser could prevent the autoplay, then the user starts the playback
                promise.catch(function() {});
            }
        });
        player.on("ended", playNext);
    }

    /**
     * Play an item of the playlist.
     * @param {HTMLElement} row
     */
    function play(row) {
        const type = row.dataset.type;
        const source = row.dataset.source;

        clearStage();
        getRows().forEach(function(item) {
            item.classList.remove("admidio-media-player-active");
        });
        row.classList.add("admidio-media-player-active");
        currentRow = row;

        titleElement.textContent = row.dataset.title;
        originElement.textContent = row.dataset.origin;

        if (row.dataset.download !== "") {
            actionsElement.appendChild(createActionLink(row.dataset.download, stage.dataset.labelDownload, "bi-download", false));
        } else {
            actionsElement.appendChild(createActionLink(source, stage.dataset.labelOpen, "bi-box-arrow-up-right", true));
        }

        if (type === "audio" || type === "video") {
            stage.className = "admidio-media-player-stage admidio-media-player-" + type;
            const media = document.createElement(type);
            media.controls = true;
            media.preload = "metadata";
            media.setAttribute("playsinline", "");
            const sourceElement = document.createElement("source");
            sourceElement.src = source;
            if (row.dataset.mime !== "") {
                sourceElement.type = row.dataset.mime;
            }
            media.appendChild(sourceElement);
            stage.appendChild(media);
            startPlayer(media);
        } else if ((type === "youtube" && getYouTubeId(source) !== null) || (type === "vimeo" && getVimeoId(source) !== null)) {
            stage.className = "admidio-media-player-stage admidio-media-player-video";
            const embed = document.createElement("div");
            embed.dataset.plyrProvider = type;
            if (type === "youtube") {
                embed.dataset.plyrEmbedId = getYouTubeId(source);
            } else {
                embed.dataset.plyrEmbedId = getVimeoId(source);
            }
            stage.appendChild(embed);
            startPlayer(embed);
        } else {
            // other weblinks could not be played within the page
            stage.className = "admidio-media-player-stage admidio-media-player-external";
            const notice = document.createElement("p");
            notice.textContent = stage.dataset.labelExternal;
            const link = document.createElement("a");
            link.className = "btn btn-primary";
            link.href = source;
            link.target = "_blank";
            link.rel = "noopener noreferrer";
            link.textContent = stage.dataset.labelOpen;
            stage.append(notice, link);
        }

        // on small screens the player is above the list, so show it
        if (window.innerWidth < 992) {
            stage.scrollIntoView({behavior: "smooth", block: "center"});
        }
    }

    /**
     * Play the next item of the playlist.
     */
    function playNext() {
        const rows = getRows();
        if (rows.length === 0) {
            return;
        }
        let index = rows.indexOf(currentRow) + 1;
        if (index >= rows.length) {
            index = 0;
        }
        play(rows[index]);
    }

    /**
     * Play the previous item of the playlist.
     */
    function playPrevious() {
        const rows = getRows();
        if (rows.length === 0) {
            return;
        }
        let index = rows.indexOf(currentRow) - 1;
        if (index < 0) {
            index = rows.length - 1;
        }
        play(rows[index]);
    }

    $(document).on("click", ".admidio-media-player-item", function(event) {
        // links of the row have their own function
        if ($(event.target).closest("a").length > 0) {
            return;
        }
        play(this);
    });
    $("#adm_media_player_next").click(playNext);
    $("#adm_media_player_previous").click(playPrevious);
});
