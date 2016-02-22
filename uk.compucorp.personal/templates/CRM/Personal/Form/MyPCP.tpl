
<hr>

{if $rows}
<div id="ltype">
<p></p>
{include file="CRM/common/pager.tpl" location="top"}
 {include file="CRM/common/jsortable.tpl"}
{strip}
<table id="options" class="display">
  <thead>
     <tr>
    <th>{ts}Page Title{/ts}</th>
    <th>{ts}No. of Contributions{/ts}</th>
    <th>{ts}Contribution Page / Event{/ts}</th>
    <th>{ts}Raised Amount{/ts}</th>
    <th>{ts}Target Amount{/ts}</th>
    <th>{ts}Status{/ts}</th>
    <th></th>
    </tr>
  </thead>
  <tbody>
  {foreach from=$rows item=row}
  <tr id="row_{$row.id}" class="{$row.class}">
    <td><a href="{crmURL p='civicrm/pcp/info' q="reset=1&id=`$row.id`" fe='true'}" title="{ts}View Personal Campaign Page{/ts}" target="_blank">{$row.title}</a></td>
    <td>{$row.no_of_contributors}</td>
    <td><a href="{$row.page_url}" title="{ts}View page{/ts}" target="_blank">{$row.page_title}</td>
    <td>{$row.raised_amount}</td>
    <td>{$row.target_amount}</td>
    <td>{$row.status_id}</td>
    <td id={$row.id}>{$row.action|replace:'xx':$row.id}</td>
  </tr>
  {/foreach}
  </tbody>
</table>
{/strip}
</div>
{else}
<div class="messages status no-popup">
<div class="icon inform-icon"></div>
    {if $isSearch}
        {ts}There are no Personal Campaign Pages which match your search criteria.{/ts}
    {else}
        {ts}There are currently no Personal Campaign Pages.{/ts}
    {/if}
</div>
{/if}

