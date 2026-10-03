<div id="adm_media_player_playlists_list">
    {if count($playlists) === 0}
        <p>{$l10n->get('PLG_MEDIA_PLAYER_NO_PLAYLISTS')}</p>
    {else}
        <div class="row admidio-margin-bottom">
            {foreach $playlists as $playlist}
                <div id="mpl_{$playlist.uuid}" class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="card admidio-card">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">
                                <a href="{$playlist.url}">{$playlist.name|escape:'html'}</a>
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
                                    {if $isEditor}
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
    {/if}
</div>
