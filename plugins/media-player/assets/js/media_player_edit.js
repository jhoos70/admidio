/**
 ***********************************************************************************************
 * Edit mode of a playlist of the media player plugin
 *
 * The playlist is shown on the left side. Its items could be reordered by drag and drop or with the
 * arrow buttons and removed. The media browser on the right side shows the audio and video files of
 * the module documents & files as a tree of their folders and the weblinks. Files are added with the
 * checkboxes and the add button or by dragging them into the playlist. On small screens the media
 * browser opens as a panel.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$(function() {
    const editor = document.getElementById("adm_media_player_editor");
    if (!editor) {
        return;
    }

    const labels = editor.dataset;
    const list = document.getElementById("adm_media_player_edit_items");
    const treeElement = document.getElementById("adm_media_player_tree");
    const searchInput = document.getElementById("adm_media_player_search");
    const addButton = document.getElementById("adm_media_player_add_selected");
    const expandedKey = "admidio-media-player-tree-expanded";
    // files are dragged with the drag and drop of the browser, it only works with a mouse
    const canDrag = window.matchMedia("(pointer: fine)").matches;
    let expanded = readExpanded();

    /**
     * Read the folders that were expanded the last time.
     * @returns {Set<string>}
     */
    function readExpanded() {
        try {
            return new Set(JSON.parse(window.sessionStorage.getItem(expandedKey) || "[]"));
        } catch (exception) {
            return new Set();
        }
    }

    /**
     * Remember the expanded folders for the current browser session.
     */
    function writeExpanded() {
        try {
            window.sessionStorage.setItem(expandedKey, JSON.stringify(Array.from(expanded)));
        } catch (exception) {
            // the folders are only remembered for the current page
        }
    }

    /**
     * Send a request to the plugin and show an error message if it fails.
     * @param {string} url
     * @param {object} data
     * @returns {Promise<object>}
     */
    function post(url, data) {
        return new Promise(function(resolve, reject) {
            $.post(url, $.param(Object.assign({adm_csrf_token: labels.csrfToken}, data)), null, "json")
                .done(function(response) {
                    if (response && response.status === "success") {
                        resolve(response);
                    } else {
                        messageBox(response && response.message ? response.message : "Error", "", "error");
                        reject(response);
                    }
                })
                .fail(function(xhr) {
                    messageBox(xhr.responseText || xhr.statusText, "", "error");
                    reject(xhr);
                });
        });
    }

    /**
     * Get all items of the playlist.
     * @returns {HTMLElement[]}
     */
    function getItems() {
        return Array.from(list.querySelectorAll(".admidio-media-player-edit-item"));
    }

    /**
     * Update the numbers of the items, the state of the arrow buttons and the marks in the media browser.
     */
    function refreshItems() {
        const items = getItems();
        items.forEach(function(item, index) {
            item.querySelector(".admidio-media-player-edit-number").textContent = index + 1;
            item.querySelector(".admidio-media-player-move-up").disabled = (index === 0);
            item.querySelector(".admidio-media-player-move-down").disabled = (index === items.length - 1);
        });
        editor.classList.toggle("admidio-media-player-editor-empty", items.length === 0);
        markItemsInPlaylist();
    }

    /**
     * Save the current order of the items.
     */
    function saveOrder() {
        refreshItems();
        post(labels.reorderUrl, {"item_uuids[]": getItems().map(function(item) { return item.dataset.uuid; })})
            .catch(function() {
                window.location.reload();
            });
    }

    /**
     * Create an element of the playlist for an item that was added.
     * @param {object} item The data of the item from the server.
     * @returns {HTMLElement}
     */
    function createItemElement(item) {
        const template = list.querySelector(".admidio-media-player-edit-item");
        let element;
        if (template) {
            element = template.cloneNode(true);
        } else {
            // the playlist was empty, so the element is created from scratch
            element = document.createElement("li");
            element.innerHTML = '<span class="admidio-media-player-drag-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>' +
                '<span class="admidio-media-player-edit-number"></span>' +
                '<span class="admidio-media-player-edit-track"><span class="admidio-media-player-edit-title text-break"></span>' +
                '<span class="small text-body-secondary text-truncate d-block"></span></span>' +
                '<span class="admidio-media-player-edit-actions text-nowrap">' +
                '<button type="button" class="btn admidio-media-player-toggle admidio-media-player-move-up"><i class="bi bi-arrow-up"></i></button>' +
                '<button type="button" class="btn admidio-media-player-toggle admidio-media-player-move-down"><i class="bi bi-arrow-down"></i></button>' +
                '<button type="button" class="btn admidio-media-player-toggle admidio-media-player-remove"><i class="bi bi-x-lg"></i></button></span>';
            element.querySelector(".admidio-media-player-drag-handle").title = labels.labelDrag;
            [["move-up", labels.labelMoveUp], ["move-down", labels.labelMoveDown], ["remove", labels.labelRemove]].forEach(function(entry) {
                const button = element.querySelector(".admidio-media-player-" + entry[0]);
                button.title = entry[1];
                button.setAttribute("aria-label", entry[1]);
            });
        }
        element.className = "admidio-media-player-edit-item admidio-media-player-edit-new";
        element.dataset.uuid = item.uuid;
        element.dataset.fileUuid = item.fileUuid;
        element.dataset.weblinkUuid = item.weblinkUuid;
        element.dataset.kind = item.kind;
        element.dataset.removeUrl = item.removeUrl;
        element.querySelector(".admidio-media-player-edit-title").textContent = item.title;
        const origin = element.querySelector(".admidio-media-player-edit-track .small");
        origin.replaceChildren(createIcon(item.type, item.kind), document.createTextNode(item.origin));
        return element;
    }

    /**
     * Create the icon for the type of a file or weblink.
     * @param {string} type
     * @param {string} kind
     * @returns {HTMLElement}
     */
    function createIcon(type, kind) {
        const icon = document.createElement("i");
        if (type === "video" || type === "youtube" || type === "vimeo") {
            icon.className = "bi bi-camera-video me-1";
        } else if (kind === "weblink") {
            icon.className = "bi bi-link-45deg me-1";
        } else {
            icon.className = "bi bi-music-note me-1";
        }
        return icon;
    }

    /**
     * Add files and weblinks to the playlist.
     * @param {Array<{kind: string, uuid: string}>} media
     * @param {HTMLElement|null} beforeItem The new items are added before this item or at the end.
     * @returns {Promise}
     */
    function addMedia(media, beforeItem) {
        if (media.length === 0) {
            return Promise.resolve();
        }
        const data = {
            "file_uuids[]": media.filter(function(entry) { return entry.kind === "file"; }).map(function(entry) { return entry.uuid; }),
            "weblink_uuids[]": media.filter(function(entry) { return entry.kind === "weblink"; }).map(function(entry) { return entry.uuid; })
        };
        if (beforeItem) {
            data.before_item_uuid = beforeItem.dataset.uuid;
        }
        return post(labels.addUrl, data).then(function(response) {
            const elements = response.items.map(createItemElement);
            elements.forEach(function(element) {
                list.insertBefore(element, beforeItem);
            });
            refreshItems();
            if (elements.length > 0) {
                elements[0].scrollIntoView({behavior: "smooth", block: "nearest"});
            }
            window.setTimeout(function() {
                elements.forEach(function(element) {
                    element.classList.remove("admidio-media-player-edit-new");
                });
            }, 2000);
        });
    }

    // ----------------------------------------------------------------- media browser

    /**
     * Create the element of a folder of the media browser.
     * @param {object} folder
     * @param {string} path The names of the parent folders, used as key for the expanded state.
     * @returns {HTMLElement}
     */
    function createFolderElement(folder, path) {
        const key = path + "/" + folder.name;
        const element = document.createElement("li");
        element.className = "admidio-media-player-tree-folder";
        element.dataset.key = key;

        const line = document.createElement("div");
        line.className = "admidio-media-player-tree-line";
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "btn admidio-media-player-tree-toggle";
        toggle.setAttribute("aria-expanded", expanded.has(key).toString());
        toggle.innerHTML = '<i class="bi bi-chevron-right"></i>';
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "form-check-input admidio-media-player-tree-folder-check";
        checkbox.title = labels.labelSelectFolder;
        checkbox.setAttribute("aria-label", labels.labelSelectFolder + ": " + folder.name);
        const name = document.createElement("button");
        name.type = "button";
        name.className = "btn admidio-media-player-tree-name";
        name.innerHTML = '<i class="bi bi-folder2 me-2"></i>';
        name.appendChild(document.createTextNode(folder.name));
        toggle.setAttribute("aria-label", folder.name);
        line.append(toggle, checkbox, name);

        const children = document.createElement("ul");
        children.className = "admidio-media-player-tree-children list-unstyled";
        children.hidden = !expanded.has(key);
        folder.folders.forEach(function(child) {
            children.appendChild(createFolderElement(child, key));
        });
        folder.files.forEach(function(file) {
            children.appendChild(createFileElement(file, key));
        });

        element.append(line, children);
        return element;
    }

    /**
     * Create the element of a file or weblink of the media browser.
     * @param {object} file
     * @param {string} path
     * @returns {HTMLElement}
     */
    function createFileElement(file, path) {
        const element = document.createElement("li");
        element.className = "admidio-media-player-tree-file";
        element.dataset.kind = file.kind || "file";
        element.dataset.uuid = file.uuid;
        element.dataset.search = (path + "/" + file.name).toLowerCase();

        const label = document.createElement("label");
        label.className = "admidio-media-player-tree-line";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.className = "form-check-input admidio-media-player-tree-file-check";
        const name = document.createElement("span");
        name.className = "admidio-media-player-tree-file-name";
        name.textContent = file.name;
        const mark = document.createElement("i");
        mark.className = "bi bi-check2 admidio-media-player-tree-in-playlist";
        mark.title = labels.labelInPlaylist;
        mark.setAttribute("aria-label", labels.labelInPlaylist);
        label.append(checkbox, createIcon(file.type, element.dataset.kind), name, mark);
        element.appendChild(label);

        if (canDrag) {
            element.draggable = true;
        }
        return element;
    }

    /**
     * Show all files and weblinks in the media browser.
     * @param {object} tree The folders and weblinks from the server.
     */
    function renderTree(tree) {
        const root = document.createElement("ul");
        root.className = "list-unstyled mb-0";
        tree.folders.forEach(function(folder) {
            root.appendChild(createFolderElement(folder, ""));
        });
        if (tree.weblinks.length > 0) {
            root.appendChild(createFolderElement({
                name: labels.labelWeblinks,
                folders: tree.weblinks.map(function(category) {
                    return {name: category.name, folders: [], files: category.links.map(function(link) {
                        return Object.assign({kind: "weblink"}, link);
                    })};
                }),
                files: []
            }, ""));
        }
        const empty = document.createElement("p");
        empty.className = "admidio-media-player-tree-empty text-body-secondary px-3 py-2";
        empty.textContent = labels.labelNoMedia;
        empty.hidden = root.children.length > 0;
        treeElement.replaceChildren(root, empty);
        markItemsInPlaylist();
        updateSelection();
    }

    /**
     * Mark the files and weblinks of the media browser that are already in the playlist.
     */
    function markItemsInPlaylist() {
        const files = new Set();
        const weblinks = new Set();
        getItems().forEach(function(item) {
            if (item.dataset.fileUuid) {
                files.add(item.dataset.fileUuid);
            }
            if (item.dataset.weblinkUuid) {
                weblinks.add(item.dataset.weblinkUuid);
            }
        });
        treeElement.querySelectorAll(".admidio-media-player-tree-file").forEach(function(element) {
            const inPlaylist = (element.dataset.kind === "weblink" ? weblinks : files).has(element.dataset.uuid);
            element.classList.toggle("admidio-media-player-tree-file-in-playlist", inPlaylist);
        });
    }

    /**
     * Get the selected files and weblinks of the media browser.
     * @returns {Array<{kind: string, uuid: string}>}
     */
    function getSelectedMedia() {
        return Array.from(treeElement.querySelectorAll(".admidio-media-player-tree-file-check:checked")).map(function(checkbox) {
            const element = checkbox.closest(".admidio-media-player-tree-file");
            return {kind: element.dataset.kind, uuid: element.dataset.uuid};
        });
    }

    /**
     * Show the number of selected files at the add button and the state of the folder checkboxes.
     */
    function updateSelection() {
        const count = getSelectedMedia().length;
        addButton.disabled = (count === 0);
        addButton.textContent = labels.labelAdd.replace("#COUNT#", count);
        treeElement.querySelectorAll(".admidio-media-player-tree-folder").forEach(function(folder) {
            const boxes = Array.from(folder.querySelectorAll(".admidio-media-player-tree-file:not([hidden]) .admidio-media-player-tree-file-check"));
            const checked = boxes.filter(function(box) { return box.checked; }).length;
            const folderBox = folder.querySelector(":scope > .admidio-media-player-tree-line .admidio-media-player-tree-folder-check");
            folderBox.checked = boxes.length > 0 && checked === boxes.length;
            folderBox.indeterminate = checked > 0 && checked < boxes.length;
        });
    }

    /**
     * Expand or collapse a folder of the media browser.
     * @param {HTMLElement} folder
     * @param {boolean} open
     */
    function setExpanded(folder, open) {
        folder.querySelector(":scope > .admidio-media-player-tree-line .admidio-media-player-tree-toggle").setAttribute("aria-expanded", open.toString());
        folder.querySelector(":scope > .admidio-media-player-tree-children").hidden = !open;
        if (open) {
            expanded.add(folder.dataset.key);
        } else {
            expanded.delete(folder.dataset.key);
        }
        writeExpanded();
    }

    /**
     * Show only the files and weblinks that contain all words of the search field. The folders with
     * matches are opened.
     */
    function filterTree() {
        const words = searchInput.value.toLowerCase().split(/\s+/).filter(function(word) { return word !== ""; });
        treeElement.querySelectorAll(".admidio-media-player-tree-file").forEach(function(element) {
            element.hidden = !words.every(function(word) { return element.dataset.search.includes(word); });
        });
        // folders from the bottom up, so a folder knows if one of its subfolders has matches
        Array.from(treeElement.querySelectorAll(".admidio-media-player-tree-folder")).reverse().forEach(function(folder) {
            const children = folder.querySelector(":scope > .admidio-media-player-tree-children");
            const visible = Array.from(children.children).some(function(child) { return !child.hidden; });
            folder.hidden = !visible;
            const open = words.length > 0 ? visible : expanded.has(folder.dataset.key);
            folder.querySelector(":scope > .admidio-media-player-tree-line .admidio-media-player-tree-toggle").setAttribute("aria-expanded", open.toString());
            children.hidden = !open;
        });
        const empty = treeElement.querySelector(".admidio-media-player-tree-empty");
        if (empty) {
            empty.hidden = Array.from(treeElement.querySelectorAll(".admidio-media-player-tree-folder")).some(function(folder) {
                return !folder.hidden && folder.parentElement.closest(".admidio-media-player-tree-folder") === null;
            });
        }
        updateSelection();
    }

    // ----------------------------------------------------------------- drag and drop into the playlist

    let draggedMedia = null;
    const dropIndicator = document.createElement("li");
    dropIndicator.className = "admidio-media-player-drop-indicator";
    const dropZone = list.parentElement;

    /**
     * Get the files that are dragged: all selected files if the dragged file is selected, otherwise only the file.
     * @param {HTMLElement} element
     * @returns {Array<{kind: string, uuid: string, name: string}>}
     */
    function getDraggedMedia(element) {
        const checkbox = element.querySelector(".admidio-media-player-tree-file-check");
        const elements = checkbox.checked
            ? Array.from(treeElement.querySelectorAll(".admidio-media-player-tree-file-check:checked")).map(function(box) {
                return box.closest(".admidio-media-player-tree-file");
            })
            : [element];
        return elements.map(function(file) {
            return {kind: file.dataset.kind, uuid: file.dataset.uuid, name: file.querySelector(".admidio-media-player-tree-file-name").textContent};
        });
    }

    /**
     * Get the item of the playlist before which the dragged files would be added.
     * @param {number} clientY
     * @returns {HTMLElement|null}
     */
    function getDropTarget(clientY) {
        const items = getItems();
        for (let i = 0; i < items.length; i++) {
            const box = items[i].getBoundingClientRect();
            if (clientY < box.top + box.height / 2) {
                return items[i];
            }
        }
        return null;
    }

    /**
     * End the dragging and remove the drop indicator.
     */
    function endDrag() {
        draggedMedia = null;
        dropIndicator.remove();
        editor.classList.remove("admidio-media-player-dragging", "admidio-media-player-drop-active");
    }

    treeElement.addEventListener("dragstart", function(event) {
        const file = event.target.closest && event.target.closest(".admidio-media-player-tree-file");
        if (!file) {
            return;
        }
        draggedMedia = getDraggedMedia(file);
        event.dataTransfer.effectAllowed = "copy";
        event.dataTransfer.setData("text/plain", draggedMedia.map(function(media) { return media.name; }).join("\n"));
        // the drag image shows the name of the file and the number of further files
        const helper = document.createElement("div");
        helper.className = "admidio-media-player-drag-helper";
        helper.textContent = draggedMedia.length === 1 ? draggedMedia[0].name : draggedMedia[0].name + " +" + (draggedMedia.length - 1);
        document.body.appendChild(helper);
        event.dataTransfer.setDragImage(helper, 12, 12);
        window.setTimeout(function() {
            helper.remove();
        }, 0);
        editor.classList.add("admidio-media-player-dragging");
    });
    treeElement.addEventListener("dragend", endDrag);

    dropZone.addEventListener("dragover", function(event) {
        if (draggedMedia === null) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = "copy";
        const target = getDropTarget(event.clientY);
        if (dropIndicator.nextElementSibling !== target || dropIndicator.parentElement !== list) {
            list.insertBefore(dropIndicator, target);
        }
        editor.classList.add("admidio-media-player-drop-active");
    });
    dropZone.addEventListener("dragleave", function(event) {
        if (draggedMedia !== null && !dropZone.contains(event.relatedTarget)) {
            dropIndicator.remove();
            editor.classList.remove("admidio-media-player-drop-active");
        }
    });
    dropZone.addEventListener("drop", function(event) {
        if (draggedMedia === null) {
            return;
        }
        event.preventDefault();
        const media = draggedMedia;
        const before = dropIndicator.parentElement === list ? dropIndicator.nextElementSibling : getDropTarget(event.clientY);
        endDrag();
        addMedia(media, before).then(clearSelection);
    });

    /**
     * Remove the selection of the media browser.
     */
    function clearSelection() {
        treeElement.querySelectorAll(".admidio-media-player-tree-file-check:checked").forEach(function(checkbox) {
            checkbox.checked = false;
        });
        updateSelection();
    }

    // ----------------------------------------------------------------- events

    treeElement.addEventListener("click", function(event) {
        const toggle = event.target.closest(".admidio-media-player-tree-toggle, .admidio-media-player-tree-name");
        if (toggle) {
            const folder = toggle.closest(".admidio-media-player-tree-folder");
            setExpanded(folder, folder.querySelector(":scope > .admidio-media-player-tree-children").hidden);
        }
    });
    treeElement.addEventListener("change", function(event) {
        if (event.target.classList.contains("admidio-media-player-tree-folder-check")) {
            const folder = event.target.closest(".admidio-media-player-tree-folder");
            folder.querySelectorAll(".admidio-media-player-tree-file:not([hidden]) .admidio-media-player-tree-file-check").forEach(function(checkbox) {
                checkbox.checked = event.target.checked;
            });
            if (event.target.checked) {
                setExpanded(folder, true);
            }
        }
        updateSelection();
    });

    let searchTimer = null;
    searchInput.addEventListener("input", function() {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(filterTree, 150);
    });

    addButton.addEventListener("click", function() {
        addButton.disabled = true;
        addMedia(getSelectedMedia(), null).then(function() {
            clearSelection();
            // on small screens the panel is closed, so the user sees the new items
            const panel = document.getElementById("adm_media_player_browser");
            if (window.bootstrap && bootstrap.Offcanvas && panel.classList.contains("show")) {
                bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
            }
        }).catch(updateSelection);
    });

    list.addEventListener("click", function(event) {
        const button = event.target.closest("button");
        if (!button) {
            return;
        }
        const item = button.closest(".admidio-media-player-edit-item");
        if (button.classList.contains("admidio-media-player-remove")) {
            button.disabled = true;
            post(item.dataset.removeUrl, {}).then(function() {
                $(item).fadeOut(200, function() {
                    item.remove();
                    refreshItems();
                });
            }).catch(function() {
                button.disabled = false;
            });
        } else if (button.classList.contains("admidio-media-player-move-up") && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
            saveOrder();
            button.focus();
        } else if (button.classList.contains("admidio-media-player-move-down") && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
            saveOrder();
            button.focus();
        }
    });

    if (typeof $.fn.sortable === "function") {
        $(list).sortable({
            items: "> .admidio-media-player-edit-item",
            handle: ".admidio-media-player-drag-handle",
            axis: "y",
            placeholder: "admidio-media-player-sort-placeholder",
            forcePlaceholderSize: true,
            update: saveOrder
        });
    }

    $.get(labels.treeUrl, null, null, "json")
        .done(renderTree)
        .fail(function(xhr) {
            treeElement.textContent = xhr.responseText || xhr.statusText;
        });
    refreshItems();
});
