<div class="admidio-documents-layout">
    <aside id="adm_media_player_tree_pane" class="admidio-folder-sidebar collapsed">
        <div class="admidio-folder-sidebar-header">
            <span class="admidio-folder-sidebar-title"><i class="bi bi-diagram-3-fill"></i> {$l10n->get('SYS_FOLDER_TREE')}</span>
            <button type="button" id="adm_media_player_tree_collapse" class="admidio-folder-sidebar-collapse"
                    aria-expanded="false" aria-controls="adm_media_player_tree_nav" title="{$l10n->get('SYS_FOLDER_TREE')}"
                    aria-label="{$l10n->get('SYS_FOLDER_TREE')}"><i class="bi bi-chevron-bar-left"></i><span class="admidio-folder-sidebar-collapse-label">{$l10n->get('SYS_FOLDER_TREE')}</span></button>
        </div>
        <nav id="adm_media_player_tree_nav" class="admidio-folder-tree-pane" aria-label="{$l10n->get('SYS_FOLDER_TREE')}">
            {foreach $treeRoots as $treeRoot}
                <ul class="admidio-tree adm-media-player-tree" role="tree" aria-label="{$l10n->get('SYS_FOLDER_TREE')}" data-url="{$treeUrl}"
                    data-root-uuid="{$treeRoot}" data-root-label="{if $treeRoot === 'private'}{$l10n->get('PLG_MEDIA_PLAYER_MY_PLAYLISTS')}{else}{$l10n->get('PLG_MEDIA_PLAYER_NAME')}{/if}"
                    data-current-uuid="{$treeCurrentUuid}" data-path="{$treePath|escape}" data-error-text="{$l10n->get('SYS_REQUEST_FAILED')}"></ul>
            {/foreach}
        </nav>
    </aside>
    <div id="adm_media_player_list_pane" class="admidio-documents-list">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                {foreach $breadcrumb as $crumb}
                    {if $crumb.current}
                        <li class="breadcrumb-item active" aria-current="page">{$crumb.name|escape:'html'}</li>
                    {else}
                        <li class="breadcrumb-item" data-drop-folder="{$crumb.uuid}" data-private="{if $crumb.private}1{else}0{/if}"><a href="{$crumb.url}">{$crumb.name|escape:'html'}</a></li>
                    {/if}
                {/foreach}
            </ol>
        </nav>

        <div id="adm_media_player_playlists_list">
            {if count($playlists) === 0 && count($folders) === 0 && $privateRoot === null}
                <p>{$l10n->get('PLG_MEDIA_PLAYER_NO_PLAYLISTS')}</p>
            {else}
                <div class="row admidio-margin-bottom">
                    {if $privateRoot !== null}
                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="card admidio-card" data-drop-folder="private" data-private="1">
                                <div class="card-body">
                                    <h5 class="card-title mb-0">
                                        <i class="bi bi-lock-fill text-body-secondary"></i>
                                        <a href="{$privateRoot.url}">{$l10n->get('PLG_MEDIA_PLAYER_MY_PLAYLISTS')}</a>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    {/if}
                    {foreach $folders as $folder}
                        <div id="mpf_{$folder.uuid}" class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="card admidio-card" data-drop-folder="{$folder.uuid}" data-private="{if $folder.private}1{else}0{/if}">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">
                                        <i class="bi bi-folder-fill text-body-secondary"></i>
                                        <a href="{$folder.url}">{$folder.name|escape:'html'}</a>
                                    </h5>
                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                        <small class="text-body-secondary">{$l10n->get('PLG_MEDIA_PLAYER_FOLDER_CONTENT', array($folder.folderCount, $folder.playlistCount))}</small>
                                        {if $folder.editable}
                                            <div>
                                                <a class="admidio-icon-link" href="{$folder.editUrl}"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT')}"></i></a>
                                                <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                                    data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($folder.name|escape:'html'))}"
                                                    data-href="callUrlHideElement('mpf_{$folder.uuid}', '{$folder.deleteUrl}', '{$csrfToken}')"><i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE')}"></i></a>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/foreach}
                    {foreach $playlists as $playlist}
                        <div id="mpl_{$playlist.uuid}" class="col-sm-6 col-lg-4 col-xl-3">
                            <div class="card admidio-card"{if $playlist.editable} draggable="true" data-move-url="{$playlist.moveUrl}" data-private="{if $playlist.private}1{else}0{/if}" title="{$dragTitle}"{/if}>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">
                                        <a href="{$playlist.url}">{$playlist.name|escape:'html'}</a>
                                        {if $playlist.private}
                                            <i class="bi bi-lock-fill text-body-secondary" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_PRIVATE_PLAYLIST')}"></i>
                                        {/if}
                                    </h5>
                                    {if $playlist.description !== ''}
                                        <div class="text-break mb-3">{$playlist.description|escape:'html'|nl2br}</div>
                                    {/if}
                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                        <small class="text-body-secondary">{$l10n->get('PLG_MEDIA_PLAYER_NUMBER_OF_ITEMS', array($playlist.itemCount))}</small>
                                        <div>
                                            {if $playlist.itemCount > 0}
                                                <a class="admidio-icon-link" href="{$playlist.zipUrl}"><i class="bi bi-file-earmark-zip" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_DOWNLOAD_ZIP')}"></i></a>
                                            {/if}
                                            {if $playlist.editable}
                                                <a class="admidio-icon-link" href="{$playlist.editUrl}"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT')}"></i></a>
                                                <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                                    data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($playlist.name|escape:'html'))}"
                                                    data-href="callUrlHideElement('mpl_{$playlist.uuid}', '{$playlist.deleteUrl}', '{$csrfToken}')"><i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE')}"></i></a>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/foreach}
                </div>
                {if count($playlists) === 0 && count($folders) === 0}
                    <p>{$l10n->get('PLG_MEDIA_PLAYER_NO_PLAYLISTS')}</p>
                {/if}
            {/if}
        </div>
    </div>
</div>

{literal}
<script>
// The folder tree is a sidebar that is collapsed at the start. The browser remembers the choice. The subfolders
// are loaded when a folder is expanded, only the folders from the root to the current folder are open at the start.
document.addEventListener("DOMContentLoaded", function () {
    const pane = document.getElementById("adm_media_player_tree_pane");
    const collapseButton = document.getElementById("adm_media_player_tree_collapse");
    if (!pane || !collapseButton || typeof AdmTreeView === "undefined") return;

    const storageKey = "admidio.media_player.folder_tree";
    const trees = Array.from(pane.querySelectorAll(".adm-media-player-tree")).map(function (tree) {
        const path = JSON.parse(tree.dataset.path);
        const rootUrl = new URL(window.location.href);
        rootUrl.search = "";
        rootUrl.hash = "";
        if (tree.dataset.rootUuid !== "shared") {
            rootUrl.searchParams.set("folder_uuid", tree.dataset.rootUuid);
        }
        // only the tree of the current place is opened down to the current folder
        const isCurrentTree = path[0] === tree.dataset.rootUuid;
        return new AdmTreeView(tree, {
            url: tree.dataset.url,
            idParam: "folder_uuid",
            root: {id: tree.dataset.rootUuid, name: tree.dataset.rootLabel, url: rootUrl.href},
            currentId: isCurrentTree ? tree.dataset.currentUuid : "",
            path: isCurrentTree ? path : [tree.dataset.rootUuid],
            errorText: tree.dataset.errorText
        });
    });

    const readState = function () {
        try {
            return window.localStorage.getItem(storageKey) === "1" ? "1" : "2";
        } catch (e) {
            return "2";
        }
    };

    const setState = function (state) {
        const open = state === "1";
        pane.classList.toggle("collapsed", !open);
        collapseButton.setAttribute("aria-expanded", open ? "true" : "false");
        if (open) {
            trees.forEach(function (tree) { tree.build(); });
        }
        try {
            window.localStorage.setItem(storageKey, state);
        } catch (e) {
            // the choice is only not remembered
        }
    };

    collapseButton.addEventListener("click", function () {
        setState(pane.classList.contains("collapsed") ? "1" : "2");
    });
    setState(readState());
});

// Drag a playlist onto a folder (tile, tree or breadcrumb) to move it. A playlist can only be moved into a folder of
// the same kind (shared or private). Without a mouse the folder is changed in the form of the playlist.
document.addEventListener("DOMContentLoaded", function () {
    const csrfToken = {/literal}{$csrfToken|json_encode}{literal};
    const pane = document.getElementById("adm_media_player_list_pane");
    if (!pane) return;
    let dragged = null;

    // the element that is the drop target at an event target and the kind of its folder
    const findTarget = function (element) {
        if (!(element instanceof Element)) return null;
        const direct = element.closest("[data-drop-folder]");
        if (direct) {
            return {element: direct, uuid: direct.dataset.dropFolder, isPrivate: direct.dataset.private === "1"};
        }
        const line = element.closest(".admidio-tree-line");
        const item = line ? line.closest("li.admidio-tree-node") : null;
        const tree = item ? item.closest("ul.adm-media-player-tree") : null;
        if (item && tree) {
            return {element: line, uuid: item.dataset.id, isPrivate: tree.dataset.rootUuid === "private"};
        }
        return null;
    };

    const clear = function () {
        document.querySelectorAll(".admidio-media-drop-over").forEach(function (e) { e.classList.remove("admidio-media-drop-over"); });
    };

    document.addEventListener("dragstart", function (event) {
        const card = event.target instanceof Element ? event.target.closest("[data-move-url]") : null;
        if (!card) return;
        dragged = card;
        card.classList.add("admidio-media-dragging");
        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", card.id);
    });

    document.addEventListener("dragend", function () {
        if (dragged) dragged.classList.remove("admidio-media-dragging");
        dragged = null;
        clear();
    });

    document.addEventListener("dragover", function (event) {
        if (!dragged) return;
        const target = findTarget(event.target);
        clear();
        if (!target || target.isPrivate !== (dragged.dataset.private === "1")) {
            event.dataTransfer.dropEffect = "none";
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
        target.element.classList.add("admidio-media-drop-over");
    });

    document.addEventListener("drop", function (event) {
        if (!dragged) return;
        const target = findTarget(event.target);
        const card = dragged;
        clear();
        if (!target || target.isPrivate !== (card.dataset.private === "1")) return;
        event.preventDefault();

        card.classList.add("admidio-media-moving");
        fetch(card.dataset.moveUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "adm_csrf_token=" + encodeURIComponent(csrfToken) + "&folder_uuid=" + encodeURIComponent(target.uuid)
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data.status === "success") {
                window.location.reload();
                return;
            }
            card.classList.remove("admidio-media-moving");
            alert(data.message ? data.message.replace(/<[^>]*>/g, "") : "");
        }).catch(function () {
            card.classList.remove("admidio-media-moving");
        });
    });
});
</script>
{/literal}
