<div id="adm_media_player" class="admidio-media-player"
    data-label-play="{$l10n->get('PLG_MEDIA_PLAYER_PLAY')}" data-label-pause="{$l10n->get('PLG_MEDIA_PLAYER_PAUSE')}"
    data-label-download="{$l10n->get('SYS_DOWNLOAD_FILE')}" data-label-open="{$l10n->get('PLG_MEDIA_PLAYER_OPEN_EXTERNAL')}"
    data-label-external="{$l10n->get('PLG_MEDIA_PLAYER_EXTERNAL_NOTICE')}"
    data-label-repeat-off="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_OFF')}" data-label-repeat-all="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ALL')}"
    data-label-repeat-one="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ONE')}"
    data-label-shuffle-off="{$l10n->get('PLG_MEDIA_PLAYER_SHUFFLE_OFF')}" data-label-shuffle-on="{$l10n->get('PLG_MEDIA_PLAYER_SHUFFLE_ON')}"
    data-label-minutes="{$l10n->get('PLG_MEDIA_PLAYER_MINUTES')}">

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
            {/if}
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-link admidio-media-player-toggle" data-bs-toggle="dropdown" aria-expanded="false"
                    title="{$l10n->get('SYS_MORE')}" aria-label="{$l10n->get('SYS_MORE')}">
                    <i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    {if count($items) > 0}
                        <li><a id="menu_item_media_player_zip" class="dropdown-item" href="{$zipUrl}">
                            <i class="bi bi-file-earmark-zip me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_DOWNLOAD_ZIP')}</a></li>
                    {/if}
                    {if $isEditor}
                        <li><a id="menu_item_media_player_edit" class="dropdown-item" href="{$editUrl}">
                            <i class="bi bi-pencil-square me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_EDIT_PLAYLIST')}</a></li>
                        <li><a class="dropdown-item" href="#adm_media_player_items_form">
                            <i class="bi bi-plus-circle me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_ADD_ITEMS')}</a></li>
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
                        data-download="{$item.downloadUrl|escape:'html'}" data-title="{$item.title|escape:'html'}" data-origin="{$item.origin|escape:'html'}">
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
                            {if $isEditor}
                                <a class="admidio-icon-link admidio-media-item-move" href="javascript:void(0)" data-uuid="{$item.uuid}" data-direction="UP" data-target="mpi_{$item.uuid}"><i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('PLG_MEDIA_PLAYER_ITEM'))}"></i></a>
                                <a class="admidio-icon-link admidio-media-item-move" href="javascript:void(0)" data-uuid="{$item.uuid}" data-direction="DOWN" data-target="mpi_{$item.uuid}"><i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('PLG_MEDIA_PLAYER_ITEM'))}"></i></a>
                                <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                    data-message="{$l10n->get('PLG_MEDIA_PLAYER_WANT_REMOVE_ITEM', array($item.title|escape:'html'))}"
                                    data-href="callUrlHideElement('mpi_{$item.uuid}', '{$item.removeUrl}', '{$csrfToken}')"><i class="bi bi-x-circle" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_REMOVE_ITEM')}"></i></a>
                            {/if}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    {/if}

    {* the player bar is fixed at the bottom of the window and is shown after the first item was started *}
    <div id="adm_media_player_bar" class="admidio-media-player-bar" hidden>
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
            <div class="admidio-media-player-bar-progress">
                <span id="adm_media_player_time" class="admidio-media-player-time">0:00</span>
                <input type="range" id="adm_media_player_seek" class="form-range" min="0" max="0" step="0.1" value="0"
                    aria-label="{$l10n->get('PLG_MEDIA_PLAYER_POSITION')}">
                <span id="adm_media_player_duration" class="admidio-media-player-time">0:00</span>
            </div>
        </div>
        <div class="admidio-media-player-bar-extra">
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

{if $isEditor}
    <h3 class="mt-5">{$l10n->get('PLG_MEDIA_PLAYER_ADD_ITEMS')}</h3>
{/if}
