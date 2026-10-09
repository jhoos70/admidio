<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['media_player_plugin_enabled']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['media_player_roles_edit']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_media_player']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}
