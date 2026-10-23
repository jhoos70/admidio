<div id="adm_media_player_editor" class="admidio-media-player-editor"
    data-tree-url="{$mediaTreeUrl}" data-add-url="{$addUrl}" data-reorder-url="{$reorderUrl}" data-csrf-token="{$csrfToken}"
    data-label-add="{$l10n->get('PLG_MEDIA_PLAYER_ADD_SELECTED', array('#COUNT#'))}"
    data-label-in-playlist="{$l10n->get('PLG_MEDIA_PLAYER_IN_PLAYLIST')}"
    data-label-no-media="{$l10n->get('PLG_MEDIA_PLAYER_NO_MEDIA_FOUND')}"
    data-label-weblinks="{$l10n->get('SYS_WEBLINKS')}"
    data-label-remove="{$l10n->get('PLG_MEDIA_PLAYER_REMOVE_ITEM')}"
    data-label-move-up="{$l10n->get('SYS_MOVE_UP', array('PLG_MEDIA_PLAYER_ITEM'))}"
    data-label-move-down="{$l10n->get('SYS_MOVE_DOWN', array('PLG_MEDIA_PLAYER_ITEM'))}"
    data-label-drag="{$l10n->get('PLG_MEDIA_PLAYER_DRAG')}"
    data-label-select-folder="{$l10n->get('PLG_MEDIA_PLAYER_SELECT_FOLDER')}">

    <div class="admidio-media-player-editor-toolbar">
        <a id="adm_media_player_done" class="btn btn-primary" href="{$playlistUrl}"><i class="bi bi-check-lg"></i>{$l10n->get('PLG_MEDIA_PLAYER_DONE')}</a>
        <a class="btn admidio-media-player-edit-button" href="{$editUrl}"><i class="bi bi-card-text"></i>{$l10n->get('PLG_MEDIA_PLAYER_EDIT_DETAILS')}</a>
        <button type="button" class="btn admidio-media-player-edit-button d-lg-none ms-auto" data-bs-toggle="offcanvas" data-bs-target="#adm_media_player_browser"
            aria-controls="adm_media_player_browser"><i class="bi bi-plus-circle"></i>{$l10n->get('PLG_MEDIA_PLAYER_ADD_MEDIA')}</button>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <ol id="adm_media_player_edit_items" class="admidio-media-player-edit-items list-unstyled">
                {foreach $items as $item}
                    <li class="admidio-media-player-edit-item" data-uuid="{$item.uuid}" data-file-uuid="{$item.fileUuid}" data-weblink-uuid="{$item.weblinkUuid}"
                        data-source="{$item.source|escape:'html'}" data-kind="{$item.kind}" data-remove-url="{$item.removeUrl}">
                        <span class="admidio-media-player-drag-handle" title="{$l10n->get('PLG_MEDIA_PLAYER_DRAG')}" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                        <span class="admidio-media-player-edit-number">{$item@iteration}</span>
                        <span class="admidio-media-player-edit-track">
                            <span class="admidio-media-player-edit-title text-break">{$item.title|escape:'html'}</span>
                            <span class="small text-body-secondary text-truncate d-block">
                                {if $item.type === 'video' || $item.type === 'youtube' || $item.type === 'vimeo'}<i class="bi bi-camera-video me-1"></i>{elseif $item.kind === 'weblink'}<i class="bi bi-link-45deg me-1"></i>{else}<i class="bi bi-music-note me-1"></i>{/if}{$item.origin|escape:'html'}
                            </span>
                        </span>
                        <span class="admidio-media-player-edit-actions text-nowrap">
                            <button type="button" class="btn admidio-media-player-toggle admidio-media-player-move-up" title="{$l10n->get('SYS_MOVE_UP', array('PLG_MEDIA_PLAYER_ITEM'))}" aria-label="{$l10n->get('SYS_MOVE_UP', array('PLG_MEDIA_PLAYER_ITEM'))}"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn admidio-media-player-toggle admidio-media-player-move-down" title="{$l10n->get('SYS_MOVE_DOWN', array('PLG_MEDIA_PLAYER_ITEM'))}" aria-label="{$l10n->get('SYS_MOVE_DOWN', array('PLG_MEDIA_PLAYER_ITEM'))}"><i class="bi bi-arrow-down"></i></button>
                            <button type="button" class="btn admidio-media-player-toggle admidio-media-player-remove" title="{$l10n->get('PLG_MEDIA_PLAYER_REMOVE_ITEM')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_REMOVE_ITEM')}"><i class="bi bi-x-lg"></i></button>
                        </span>
                    </li>
                {/foreach}
            </ol>
            <p class="admidio-media-player-drop-hint text-body-secondary">
                <i class="bi bi-arrow-left-right me-2 d-none d-lg-inline"></i><span class="d-none d-lg-inline">{$l10n->get('PLG_MEDIA_PLAYER_DROP_HINT')}</span><span class="d-lg-none">{$l10n->get('PLG_MEDIA_PLAYER_ADD_HINT_PHONE')}</span>
            </p>
        </div>

        <div class="col-lg-5">
            {* on large screens the browser is shown next to the playlist, on small screens it opens as a panel *}
            <div id="adm_media_player_browser" class="offcanvas-lg offcanvas-end admidio-media-player-browser" tabindex="-1" aria-labelledby="adm_media_player_browser_title">
                <div class="offcanvas-header admidio-media-player-browser-header">
                    <h5 id="adm_media_player_browser_title" class="mb-0">{$l10n->get('PLG_MEDIA_PLAYER_MEDIA')}</h5>
                    <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#adm_media_player_browser" aria-label="{$l10n->get('SYS_CLOSE')}"></button>
                </div>
                <div class="admidio-media-player-browser-search">
                    <input type="search" id="adm_media_player_search" class="form-control" placeholder="{$l10n->get('PLG_MEDIA_PLAYER_SEARCH_MEDIA')}"
                        aria-label="{$l10n->get('PLG_MEDIA_PLAYER_SEARCH_MEDIA')}" autocomplete="off">
                </div>
                <div id="adm_media_player_tree" class="admidio-media-player-tree" role="group" aria-labelledby="adm_media_player_browser_title">
                    <div class="text-center text-body-secondary py-4"><span class="spinner-border spinner-border-sm"></span></div>
                </div>
                <div class="admidio-media-player-browser-footer">
                    <button type="button" id="adm_media_player_add_selected" class="btn btn-primary w-100" disabled></button>
                </div>
            </div>
        </div>
    </div>
</div>
