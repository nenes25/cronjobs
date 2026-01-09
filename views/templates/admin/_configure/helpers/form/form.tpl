{**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *}

{extends file="helpers/form/form.tpl"}

{block name="defaultForm"}

    {if (isset($form_errors)) && (count($form_errors) > 0)}
        <div class="alert alert-danger">
            <h4>{l s='Error!' mod='cronjobs'}</h4>
            <ul class="list-unstyled">
            {foreach from=$form_errors item='message'}
                <li>{$message|escape:'htmlall':'UTF-8'}</li>
            {/foreach}
            </ul>
        </div>
    {/if}

    {if (isset($form_infos)) && (count($form_infos) > 0)}
        <div class="alert alert-warning">
            <h4>{l s='Warning!' mod='cronjobs'}</h4>
            <ul class="list-unstyled">
            {foreach from=$form_infos item='message'}
                <li>{$message|escape:'htmlall':'UTF-8'}</li>
            {/foreach}
            </ul>
        </div>
    {/if}

    {if (isset($form_successes)) && (count($form_successes) > 0)}
        <div class="alert alert-success">
            <h4>{l s='Success!' mod='cronjobs'}</h4>
            <ul class="list-unstyled">
            {foreach from=$form_successes item='message'}
                <li>{$message|escape:'htmlall':'UTF-8'}</li>
            {/foreach}
            </ul>
        </div>
    {/if}

    {$smarty.block.parent}
{/block}
