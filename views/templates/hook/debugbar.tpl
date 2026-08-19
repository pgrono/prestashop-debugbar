<aside id="psoft-debugbar" class="psoft-debugbar" aria-label="{l s='Debug bar' mod='psoft_debugbar'}">
    <div class="psoft-debugbar__summary">
        <button class="psoft-debugbar__brand" type="button" data-debugbar-action="details" aria-expanded="false">
            <span class="psoft-debugbar__mark">DEV</span>
            <span>Debug Bar</span>
        </button>

        {if $psoft_debugbar.show_performance}
            <span class="psoft-debugbar__metric" title="{l s='Page generation time' mod='psoft_debugbar'}">
                <strong>{$psoft_debugbar.duration|escape:'html':'UTF-8'} ms</strong>
            </span>
            <span class="psoft-debugbar__metric" title="{l s='Memory usage / peak memory usage' mod='psoft_debugbar'}">
                {$psoft_debugbar.memory|escape:'html':'UTF-8'} / {$psoft_debugbar.peak_memory|escape:'html':'UTF-8'}
            </span>
        {/if}

        {if $psoft_debugbar.show_queries}
            <button class="psoft-debugbar__metric psoft-debugbar__metric--button" type="button" data-debugbar-section="queries">
                <strong>{if $psoft_debugbar.query_count !== null}{$psoft_debugbar.query_count|intval}{else}—{/if}</strong> SQL
            </button>
        {/if}

        {if $psoft_debugbar.show_hooks}
            <button class="psoft-debugbar__metric psoft-debugbar__metric--button" type="button" data-debugbar-section="hooks">
                <strong>{$psoft_debugbar.hook_count|intval}</strong> {l s='hooks' mod='psoft_debugbar'}
            </button>
        {/if}

        {if $psoft_debugbar.show_templates}
            <button class="psoft-debugbar__metric psoft-debugbar__metric--button" type="button" data-debugbar-section="templates">
                <strong>{$psoft_debugbar.template_count|intval}</strong> TPL
            </button>
        {/if}

        {if $psoft_debugbar.show_context}
            <span class="psoft-debugbar__metric psoft-debugbar__metric--context">
                {$psoft_debugbar.page|default:$psoft_debugbar.controller|escape:'html':'UTF-8'}
            </span>
        {/if}

        <span class="psoft-debugbar__spacer"></span>
        <button class="psoft-debugbar__info-button" type="button" data-debugbar-section="author" aria-label="{l s='About the author' mod='psoft_debugbar'}" title="{l s='About the author' mod='psoft_debugbar'}">i</button>
        <button class="psoft-debugbar__details-button" type="button" data-debugbar-action="details" aria-expanded="false">
            {l s='Details' mod='psoft_debugbar'}
        </button>
        <button class="psoft-debugbar__collapse" type="button" data-debugbar-action="collapse" aria-label="{l s='Collapse the debug bar' mod='psoft_debugbar'}">×</button>
    </div>

    <div class="psoft-debugbar__panel" hidden>
        <div class="psoft-debugbar__panel-header">
            <strong>{l s='Current request diagnostics' mod='psoft_debugbar'}</strong>
            <button type="button" data-debugbar-action="close-details" aria-label="{l s='Close details' mod='psoft_debugbar'}">×</button>
        </div>

        <div class="psoft-debugbar__tabs" role="tablist">
            {if $psoft_debugbar.show_context}<button type="button" data-debugbar-tab="context">{l s='Context' mod='psoft_debugbar'}</button>{/if}
            {if $psoft_debugbar.show_queries}<button type="button" data-debugbar-tab="queries">SQL ({$psoft_debugbar.query_count|intval})</button>{/if}
            {if $psoft_debugbar.show_hooks}<button type="button" data-debugbar-tab="hooks">{l s='Hooks' mod='psoft_debugbar'} ({$psoft_debugbar.hook_count|intval})</button>{/if}
            {if $psoft_debugbar.show_templates}<button type="button" data-debugbar-tab="templates">TPL ({$psoft_debugbar.template_count|intval})</button>{/if}
            <button type="button" data-debugbar-tab="author">{l s='Author' mod='psoft_debugbar'}</button>
        </div>

        {if $psoft_debugbar.show_context}
            <section class="psoft-debugbar__section" data-debugbar-panel="context">
                <dl class="psoft-debugbar__context-grid">
                    <div><dt>PrestaShop</dt><dd>{$psoft_debugbar.prestashop|escape:'html':'UTF-8'}</dd></div>
                    <div><dt>PHP</dt><dd>{$psoft_debugbar.php|escape:'html':'UTF-8'}</dd></div>
                    <div><dt>{l s='Controller' mod='psoft_debugbar'}</dt><dd>{$psoft_debugbar.controller|escape:'html':'UTF-8'}</dd></div>
                    <div><dt>{l s='Page' mod='psoft_debugbar'}</dt><dd>{$psoft_debugbar.page|escape:'html':'UTF-8'}</dd></div>
                    <div><dt>{l s='Shop' mod='psoft_debugbar'}</dt><dd>{$psoft_debugbar.shop|escape:'html':'UTF-8'}</dd></div>
                    <div><dt>{l s='Language' mod='psoft_debugbar'}</dt><dd>{$psoft_debugbar.language|escape:'html':'UTF-8'}</dd></div>
                </dl>
            </section>
        {/if}

        {if $psoft_debugbar.show_queries}
            <section class="psoft-debugbar__section" data-debugbar-panel="queries" hidden>
                {if $psoft_debugbar.queries|@count}
                    <ol class="psoft-debugbar__query-list">
                        {foreach from=$psoft_debugbar.queries item=query}
                            <li class="{if $query.slow}psoft-debugbar__query--slow{/if}">
                                <span class="psoft-debugbar__query-time">{$query.duration|escape:'html':'UTF-8'} ms</span>
                                <code>{$query.sql|escape:'html':'UTF-8'}</code>
                            </li>
                        {/foreach}
                    </ol>
                    {if $psoft_debugbar.queries_truncated}
                        <p>+ {$psoft_debugbar.queries_truncated|intval} {l s='more entries' mod='psoft_debugbar'}</p>
                    {/if}
                {else}
                    <p>{l s='No SQL queries were recorded after the controller started.' mod='psoft_debugbar'}</p>
                {/if}
            </section>
        {/if}

        {if $psoft_debugbar.show_hooks}
            <section class="psoft-debugbar__section" data-debugbar-panel="hooks" hidden>
                {if $psoft_debugbar.hooks|@count}
                    <ol class="psoft-debugbar__list psoft-debugbar__hook-list">
                        {foreach from=$psoft_debugbar.hooks item=hook}
                            <li>
                                <code>{$hook.name|escape:'html':'UTF-8'}</code>
                                {if $psoft_debugbar.show_hook_timing}
                                    <span class="psoft-debugbar__hook-time">
                                        {if $hook.timed}<strong>{$hook.duration|escape:'html':'UTF-8'}</strong> ms{else}—{/if}
                                        {if $hook.calls > 1}<small>×{$hook.calls|intval}</small>{/if}
                                    </span>
                                {/if}
                            </li>
                        {/foreach}
                    </ol>
                    {if $psoft_debugbar.hooks_truncated}
                        <p>+ {$psoft_debugbar.hooks_truncated|intval} {l s='more entries' mod='psoft_debugbar'}</p>
                    {/if}
                {else}
                    <p>{l s='No executed hooks were recorded.' mod='psoft_debugbar'}</p>
                {/if}
            </section>
        {/if}

        {if $psoft_debugbar.show_templates}
            <section class="psoft-debugbar__section" data-debugbar-panel="templates" hidden>
                {if $psoft_debugbar.templates|@count}
                    <ol class="psoft-debugbar__list">
                        {foreach from=$psoft_debugbar.templates item=templatePath}
                            <li><code>{$templatePath|escape:'html':'UTF-8'}</code></li>
                        {/foreach}
                    </ol>
                    {if $psoft_debugbar.templates_truncated}
                        <p>+ {$psoft_debugbar.templates_truncated|intval} {l s='more entries' mod='psoft_debugbar'}</p>
                    {/if}
                {else}
                    <p>{l s='No loaded TPL templates were detected.' mod='psoft_debugbar'}</p>
                {/if}
            </section>
        {/if}

        <section class="psoft-debugbar__section psoft-debugbar__author" data-debugbar-panel="author" hidden>
            <strong class="psoft-debugbar__author-title">{$psoft_debugbar.author_text.title|escape:'html':'UTF-8'}</strong>
            <p>{$psoft_debugbar.author_text.description|escape:'html':'UTF-8'}</p>
            <div class="psoft-debugbar__author-links">
                {if $psoft_debugbar.author_is_polish}
                    <a href="http://prestasoft.pl" target="_blank" rel="noopener noreferrer">{$psoft_debugbar.author_text.site|escape:'html':'UTF-8'}</a>
                {else}
                    <a href="https://prestaaddons.com" target="_blank" rel="noopener noreferrer">{$psoft_debugbar.author_text.site|escape:'html':'UTF-8'}</a>
                {/if}
                <a class="psoft-debugbar__coffee-link" href="https://buymeacoffee.com/pgrono" target="_blank" rel="noopener noreferrer">{$psoft_debugbar.author_text.coffee|escape:'html':'UTF-8'}</a>
                <a href="https://github.com/pgrono/prestashop-debugbar" target="_blank" rel="noopener noreferrer">GitHub</a>
            </div>
            <p class="psoft-debugbar__author-thanks">{$psoft_debugbar.author_text.thanks|escape:'html':'UTF-8'}</p>
        </section>
    </div>
</aside>
