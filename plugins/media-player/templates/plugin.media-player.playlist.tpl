<div id="adm_media_player" class="admidio-media-player"
    data-label-play="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}" data-label-pause="{$l10n->get('PLG_MEDIA_PLAYER_PAUSE')}"
    data-label-download="{$l10n->get('SYS_DOWNLOAD_FILE')}" data-label-open="{$l10n->get('PLG_MEDIA_PLAYER_OPEN_EXTERNAL')}"
    data-label-external="{$l10n->get('PLG_MEDIA_PLAYER_EXTERNAL_NOTICE')}"
    data-label-repeat-off="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_OFF')}" data-label-repeat-all="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ALL')}"
    data-label-repeat-one="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ONE')}"
    data-label-ab-set-start="{$l10n->get('PLG_MEDIA_PLAYER_AB_SET_START')}" data-label-ab-set-end="{$l10n->get('PLG_MEDIA_PLAYER_AB_SET_END')}"
    data-label-ab-clear="{$l10n->get('PLG_MEDIA_PLAYER_AB_CLEAR')}"
    data-label-ab-hint-a="{$l10n->get('PLG_MEDIA_PLAYER_AB_HINT_A')}" data-label-ab-hint-b="{$l10n->get('PLG_MEDIA_PLAYER_AB_HINT_B')}"
    data-label-ab-hint-clear="{$l10n->get('PLG_MEDIA_PLAYER_AB_HINT_CLEAR')}"
    data-label-shuffle-off="{$l10n->get('PLG_MEDIA_PLAYER_SHUFFLE_OFF')}" data-label-shuffle-on="{$l10n->get('PLG_MEDIA_PLAYER_SHUFFLE_ON')}"
    data-label-sound-only-off="{$l10n->get('PLG_MEDIA_PLAYER_SOUND_ONLY_OFF')}" data-label-sound-only-on="{$l10n->get('PLG_MEDIA_PLAYER_SOUND_ONLY_ON')}"
    data-label-minutes="{$l10n->get('PLG_MEDIA_PLAYER_MINUTES')}"
    data-label-buffering="{$l10n->get('PLG_MEDIA_PLAYER_BUFFERING')}"
    data-label-markers="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS')}"
    data-label-markers-show="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS_SHOW')}"
    data-label-markers-hide="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS_HIDE')}"
    data-label-marker-add="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD')}"
    data-label-marker-add-form="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD_FORM')}"
    data-label-marker-name="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NAME')}"
    data-label-marker-bar="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_BAR')}" data-label-marker-time="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_TIME')}"
    data-label-marker-now="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NOW')}"
    data-label-marker-shared="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_SHARED')}"
    data-label-marker-shared-info="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_SHARED_INFO')}"
    data-label-marker-previous="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_PREVIOUS')}"
    data-label-marker-next="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NEXT')}"
    data-label-marker-loop="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_LOOP')}"
    data-label-marker-empty="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_EMPTY')}"
    data-label-marker-no-item="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NO_ITEM')}"
    data-label-marker-unavailable="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_UNAVAILABLE')}"
    data-label-marker-time-invalid="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_TIME_INVALID')}"
    data-label-marker-error="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ERROR')}"
    data-label-marker-delete-confirm="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_DELETE_CONFIRM')}"
    data-label-marker-jump="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_JUMP')}"
    data-label-save="{$l10n->get('SYS_SAVE')}" data-label-cancel="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_CANCEL')}" data-label-edit="{$l10n->get('SYS_EDIT')}" data-label-delete="{$l10n->get('SYS_DELETE')}"
    data-valid-login="{if $validLogin}1{else}0{/if}" data-markers-url="{$markersUrl}" data-marker-save-url="{$markerSaveUrl}" data-marker-delete-url="{$markerDeleteUrl}" data-can-share-markers="{if $canShareMarkers}1{else}0{/if}"
    data-artwork="{$artwork|escape:'html'}"
    data-durations-url="{$durationsUrl}" data-debug-log-url="{$debugLogUrl}" data-debug-user="{if $debugUser}1{else}0{/if}" data-link-duration-url="{$linkDurationUrl}" data-csrf-token="{$csrfToken}">

    <div class="admidio-media-player-main">
    <div class="admidio-media-player-hero">
        {if $playlistDescription !== ''}
            <p class="admidio-media-player-description">{$playlistDescription|escape:'html'|nl2br}</p>
        {/if}
        <p class="admidio-media-player-meta text-body-secondary">
            {$l10n->get('PLG_MEDIA_PLAYER_NUMBER_OF_ITEMS', array(count($items)))}<span id="adm_media_player_total_duration"></span>
        </p>
        <div class="admidio-media-player-hero-actions">
            {if count($items) > 0}
                <button type="button" id="adm_media_player_play_all" class="btn btn-primary rounded-circle admidio-media-player-main-button"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}">
                    <i class="bi bi-play-fill"></i></button>
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-shuffle" aria-pressed="false">
                    <i class="bi bi-shuffle"></i></button>
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-repeat" aria-pressed="false">
                    <i class="bi bi-repeat"></i></button>
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-sound-only" aria-pressed="false">
                    <i class="bi bi-soundwave"></i></button>
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-markers-toggle" aria-pressed="false">
                    <i class="bi bi-flag"></i></button>
            {/if}
            {if $isEditor}
                <a id="adm_media_player_edit_items" class="btn admidio-media-player-edit-button ms-auto" href="{$itemsUrl}">
                    <i class="bi bi-pencil-square"></i>{$l10n->get('SYS_EDIT')}</a>
            {/if}
            <div class="dropdown{if !$isEditor} ms-auto{/if}">
                <button type="button" class="btn btn-link admidio-media-player-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                    title="{$l10n->get('SYS_MORE')}" aria-label="{$l10n->get('SYS_MORE')}">
                    <i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    {if count($items) > 0}
                        <li><a id="menu_item_media_player_zip" class="dropdown-item" href="{$zipUrl}">
                            <i class="bi bi-file-earmark-zip me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_DOWNLOAD_ZIP')}</a></li>
                    {/if}
                    {if $validLogin}
                        <li><a id="menu_item_media_player_copy" class="dropdown-item" href="javascript:void(0);"
                            data-url="{$copyUrl}" data-csrf-token="{$csrfToken}">
                            <i class="bi bi-copy me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_COPY_PLAYLIST')}</a></li>
                    {/if}
                    {if $isEditor}
                        <li><a id="menu_item_media_player_edit" class="dropdown-item" href="{$editUrl}">
                            <i class="bi bi-card-text me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_EDIT_DETAILS')}</a></li>
                    {/if}
                </ul>
            </div>
        </div>
    </div>

    {* video and embedded players are shown here, audio files play without a visible element *}
    <div id="adm_media_player_stage" class="admidio-media-player-stage d-none"></div>

    {if count($items) === 0}
        <p>{$l10n->get('PLG_MEDIA_PLAYER_NO_ITEMS')}</p>
    {else}
        <table id="adm_media_player_items" class="table align-middle admidio-media-player-items">
            <tbody>
                {foreach $items as $item}
                    <tr id="mpi_{$item.uuid}" class="admidio-media-player-item"
                        data-type="{$item.type}" data-source="{$item.source|escape:'html'}" data-mime="{$item.mime|escape:'html'}"
                        data-download="{$item.downloadUrl|escape:'html'}" data-title="{$item.title|escape:'html'}" data-origin="{$item.origin|escape:'html'}"
                        data-file-uuid="{$item.fileUuid}" data-weblink-uuid="{$item.weblinkUuid}" data-duration="{$item.duration}">
                        <td class="admidio-media-player-number">
                            <span class="admidio-media-player-index">{$item@iteration}</span>
                            <button type="button" class="btn btn-link p-0 admidio-media-player-play-button"
                                aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PLAY_TRACK', array($item.title|escape:'html'))}">
                                <i class="bi bi-play-fill"></i></button>
                            <span class="admidio-media-player-equalizer" aria-hidden="true"><span></span><span></span><span></span></span>
                        </td>
                        <td class="admidio-media-player-track">
                            <div class="admidio-media-player-track-title text-break">{$item.title|escape:'html'}</div>
                            <div class="small text-body-secondary text-truncate">
                                {if $item.type === 'video' || $item.type === 'youtube' || $item.type === 'vimeo'}<i class="bi bi-camera-video me-1"></i>{elseif $item.kind === 'weblink'}<i class="bi bi-link-45deg me-1"></i>{/if}{$item.origin|escape:'html'}
                            </div>
                            {if $item.description !== ''}
                                <div class="small fst-italic text-body-secondary text-break">{$item.description|escape:'html'}</div>
                            {/if}
                        </td>
                        <td class="admidio-media-player-duration text-body-secondary text-end"></td>
                        <td class="admidio-media-player-row-actions text-end text-nowrap">
                            {if $item.downloadUrl !== ''}
                                <a class="admidio-icon-link" href="{$item.downloadUrl}"><i class="bi bi-download" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DOWNLOAD_FILE')}"></i></a>
                            {else}
                                <a class="admidio-icon-link" href="{$item.source|escape:'html'}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_OPEN_EXTERNAL')}"></i></a>
                            {/if}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
            {* the sum of the lengths, filled in by the script as soon as the lengths are known *}
            <tfoot id="adm_media_player_total_row" hidden>
                <tr>
                    <td></td>
                    <td class="admidio-media-player-total-label text-end">{$l10n->get('PLG_MEDIA_PLAYER_TOTAL_DURATION')}</td>
                    <td id="adm_media_player_total" class="admidio-media-player-duration admidio-media-player-total text-end"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    {/if}

    </div>

    {* markers of the current item: panel at the right on desktop, sheet above the player bar on phones; only used when the user has switched markers on *}
    <aside id="adm_media_player_markers" class="admidio-media-player-markers" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS')}" hidden>
        <div class="admidio-media-player-markers-header">
            <div class="admidio-media-player-markers-title">
                <strong>{$l10n->get('PLG_MEDIA_PLAYER_MARKERS')}</strong>
                <span id="adm_media_player_markers_track" class="small text-body-secondary text-truncate"></span>
            </div>
            <button type="button" id="adm_media_player_marker_previous" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-small"
                title="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_PREVIOUS')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_PREVIOUS')}">
                <i class="bi bi-chevron-bar-left"></i></button>
            <button type="button" id="adm_media_player_marker_next" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-small"
                title="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NEXT')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_NEXT')}">
                <i class="bi bi-chevron-bar-right"></i></button>
            {if $validLogin}
                <button type="button" id="adm_media_player_marker_add_form" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-small"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD_FORM')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD_FORM')}">
                    <i class="bi bi-plus-lg"></i></button>
            {/if}
            <button type="button" id="adm_media_player_markers_close" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-small"
                title="{$l10n->get('SYS_CLOSE')}" aria-label="{$l10n->get('SYS_CLOSE')}">
                <i class="bi bi-x-lg"></i></button>
        </div>
        <p id="adm_media_player_markers_message" class="admidio-media-player-markers-message text-body-secondary small"></p>
        <ul id="adm_media_player_markers_list" class="admidio-media-player-markers-list list-unstyled"></ul>
    </aside>

    {* the player bar is fixed at the bottom of the window and is shown after the first item was started *}
    <div id="adm_media_player_bar" class="admidio-media-player-bar" hidden>
        <span class="admidio-media-player-grip" aria-hidden="true"></span>
        <div class="admidio-media-player-bar-track">
            <div id="adm_media_player_title" class="admidio-media-player-bar-title text-truncate"></div>
            <div id="adm_media_player_origin" class="small text-body-secondary text-truncate"></div>
        </div>
        <div class="admidio-media-player-bar-center">
            <div class="admidio-media-player-bar-controls">
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-shuffle admidio-media-player-desktop-only" aria-pressed="false">
                    <i class="bi bi-shuffle"></i></button>
                <button type="button" id="adm_media_player_previous" class="btn btn-link admidio-media-player-toggle"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_PREVIOUS_TRACK')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PREVIOUS_TRACK')}">
                    <i class="bi bi-skip-start-fill"></i></button>
                <button type="button" id="adm_media_player_play_pause" class="btn btn-primary rounded-circle admidio-media-player-bar-play"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}">
                    <i class="bi bi-play-fill"></i></button>
                <button type="button" id="adm_media_player_next" class="btn btn-link admidio-media-player-toggle"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_NEXT_TRACK')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_NEXT_TRACK')}">
                    <i class="bi bi-skip-end-fill"></i></button>
                <button type="button" class="btn btn-link admidio-media-player-toggle admidio-media-player-repeat admidio-media-player-desktop-only" aria-pressed="false">
                    <i class="bi bi-repeat"></i></button>
            </div>
            <div id="adm_media_player_ab_hint" class="admidio-media-player-ab-hint" role="status" aria-live="polite" hidden></div>
            <div class="admidio-media-player-bar-progress">
                <span id="adm_media_player_time" class="admidio-media-player-time">0:00</span>
                <div class="admidio-media-player-seek">
                    <div id="adm_media_player_ab_region" class="admidio-media-player-ab-region" hidden></div>
                    <div id="adm_media_player_ticks" class="admidio-media-player-ticks" aria-hidden="true"></div>
                    <span id="adm_media_player_ab_flag_a" class="admidio-media-player-ab-flag" hidden>A</span>
                    <span id="adm_media_player_ab_flag_b" class="admidio-media-player-ab-flag" hidden>B</span>
                    <input type="range" id="adm_media_player_seek" class="form-range" min="0" max="0" step="0.1" value="0"
                        aria-label="{$l10n->get('PLG_MEDIA_PLAYER_POSITION')}">
                </div>
                <span id="adm_media_player_duration" class="admidio-media-player-time">0:00</span>
                <button type="button" id="adm_media_player_ab" class="btn btn-link admidio-media-player-toggle admidio-media-player-ab" aria-pressed="false"
                    title="{$l10n->get('PLG_MEDIA_PLAYER_AB_SET_START')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_AB_SET_START')}">
                    <i class="bi bi-repeat"></i><span class="admidio-media-player-ab-letters">AB</span></button>
                <button type="button" id="adm_media_player_marker_panel" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-only admidio-media-player-marker-small" aria-pressed="false" hidden
                    title="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKERS')}">
                    <i class="bi bi-flag"></i></button>
                {if $validLogin}
                    <button type="button" id="adm_media_player_marker_add" class="btn btn-link admidio-media-player-toggle admidio-media-player-marker-only admidio-media-player-marker-small" hidden
                        title="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MARKER_ADD')}">
                        <i class="bi bi-bookmark-plus"></i></button>
                {/if}
            </div>
        </div>
        <div class="admidio-media-player-bar-extra">
            <button type="button" id="adm_media_player_mute" class="btn btn-link admidio-media-player-toggle" aria-pressed="false"
                title="{$l10n->get('PLG_MEDIA_PLAYER_MUTE')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_MUTE')}"
                data-label-mute="{$l10n->get('PLG_MEDIA_PLAYER_MUTE')}" data-label-unmute="{$l10n->get('PLG_MEDIA_PLAYER_UNMUTE')}">
                <i class="bi bi-volume-up-fill"></i></button>
            <select id="adm_media_player_speed" class="form-select form-select-sm" title="{$l10n->get('PLG_MEDIA_PLAYER_SPEED')}"
                aria-label="{$l10n->get('PLG_MEDIA_PLAYER_SPEED')}">
                <option value="0.5"></option>
                <option value="0.75"></option>
                <option value="0.9"></option>
                <option value="1" selected></option>
                <option value="1.25"></option>
                <option value="1.5"></option>
            </select>
            <span id="adm_media_player_actions"></span>
        </div>
    </div>
</div>
