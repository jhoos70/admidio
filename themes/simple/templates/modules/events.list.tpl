<style>
#adm_events_table th,
#adm_events_table td {
    vertical-align: middle;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
    line-height: 1.25;
}
#adm_events_table th:last-child,
#adm_events_table td:last-child,
#adm_events_table th:first-child,
#adm_events_table td:first-child {
    white-space: nowrap !important;
}
#adm_events_table .btn {
    padding: 0.15rem 0.4rem;
    font-size: 0.875rem;
    line-height: 1.25;
}
</style>
<table id="adm_events_table" class="{$classTable}" style="max-width: 100%;">
    <thead>
        <tr>
            {foreach $headers as $key => $header}
                <th{if isset($columnClass[$key]) && $columnClass[$key] !== ''} class="{$columnClass[$key]}"{/if} style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
    {foreach $rows as $row}
        <tr id="{$row.id}" class="{$row.class}">
        {foreach $row.data as $key => $cell}
            <td{if isset($columnClass[$key]) && $columnClass[$key] !== ''} class="{$columnClass[$key]}"{/if} style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$cell}</td>
        {/foreach}
        </tr>
    {/foreach}
    </tbody>
</table>