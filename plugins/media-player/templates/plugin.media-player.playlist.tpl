{if $playlistDescription !== ''}
    <p class="lead">{$playlistDescription|nl2br}</p>
{/if}

<div class="row g-4 admidio-margin-bottom">
    <div class="col-lg-5 order-lg-2">
        <div class="admidio-media-player-sticky">
            <div class="admidio-media-player-card" id="adm_media_player">
                <div class="admidio-media-player-header">
                    <button type="button" id="adm_media_player_previous" class="btn btn-sm btn-primary" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PREVIOUS_TRACK')}">
                        <i class="bi bi-skip-backward-fill" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_PREVIOUS_TRACK')}"></i></button>
                    <button type="button" id="adm_media_player_next" class="btn btn-sm btn-primary" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_NEXT_TRACK')}">
                        <i class="bi bi-skip-forward-fill" data-bs-toggle="tooltip" title="{$l10n->get('PLG_MEDIA_PLAYER_NEXT_TRACK')}"></i></button>
                    <button type="button" id="adm_media_player_repeat" class="btn btn-sm btn-outline-primary" aria-pressed="false"
                        data-label-off="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_OFF')}" data-label-all="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ALL')}"
                        data-label-one="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_ONE')}"
                        title="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_OFF')}" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_REPEAT_OFF')}">
                        <i class="bi bi-repeat"></i></button>
                    <div class="admidio-media-player-now text-truncate">
                        <div id="adm_media_player_title" class="fw-bold text-truncate">{$l10n->get('PLG_MEDIA_PLAYER_CHOOSE_TRACK')}</div>
                        <div id="adm_media_player_origin" class="small text-body-secondary text-truncate"></div>
                    </div>
                    <div id="adm_media_player_actions"></div>
                </div>
                <div id="adm_media_player_stage" class="admidio-media-player-stage"
                    data-label-download="{$l10n->get('SYS_DOWNLOAD_FILE')}"
                    data-label-open="{$l10n->get('PLG_MEDIA_PLAYER_OPEN_EXTERNAL')}"
                    data-label-external="{$l10n->get('PLG_MEDIA_PLAYER_EXTERNAL_NOTICE')}">
                    <i class="bi bi-music-note-beamed me-2"></i>{$l10n->get('PLG_MEDIA_PLAYER_CHOOSE_TRACK')}
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 order-lg-1">
        {if count($items) === 0}
            <p>{$l10n->get('PLG_MEDIA_PLAYER_NO_ITEMS')}</p>
        {else}
            <div class="table-responsive">
                <table id="adm_media_player_items" class="table table-hover align-middle admidio-media-player-items">
                    <tbody>
                        {foreach $items as $item}
                            <tr id="mpi_{$item.uuid}" class="admidio-media-player-item"
                                data-type="{$item.type}" data-source="{$item.source|escape:'html'}" data-mime="{$item.mime|escape:'html'}"
                                data-download="{$item.downloadUrl|escape:'html'}" data-title="{$item.title|escape:'html'}" data-origin="{$item.origin|escape:'html'}">
                                <td class="admidio-media-player-play">
                                    <button type="button" class="btn btn-primary btn-sm rounded-circle admidio-media-player-play-button" aria-label="{$l10n->get('PLG_MEDIA_PLAYER_PLAY_TRACK', array($item.title|escape:'html'))}">
                                        <i class="bi bi-play-fill"></i></button>
                                </td>
                                <td>
                                    <div class="fw-bold text-break">{$item.title|escape:'html'}</div>
                                    <div class="small text-body-secondary">
                                        {if $item.kind === 'file'}<i class="bi bi-folder2 me-1"></i>{else}<i class="bi bi-link-45deg me-1"></i>{/if}{$item.origin|escape:'html'}
                                    </div>
                                    {if $item.description !== ''}
                                        <div class="small fst-italic text-break">{$item.description|escape:'html'}</div>
                                    {/if}
                                </td>
                                <td class="text-end text-nowrap">
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
            </div>
        {/if}
    </div>
</div>

{if $isEditor}
    <h3>{$l10n->get('PLG_MEDIA_PLAYER_ADD_ITEMS')}</h3>
{/if}
