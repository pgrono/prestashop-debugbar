{if isset($ps_modules) && $ps_modules|@count > 0}
<div class="panel">
    <h3><i class="icon-puzzle-piece"></i> {l s='Other PrestaSoft.pl modules' mod='psoft_debugbar'}</h3>
    <div id="ps-modules-slider" class="keen-slider">
        {foreach from=$ps_modules item=module}
        <div class="keen-slider__slide" style="min-width:250px;max-width:300px;">
            {if isset($module.url) && $module.url}
            <a href="{$module.url|escape:'html':'UTF-8'}" target="_blank" rel="noopener" style="display:block;">
                {if isset($module.img) && $module.img}
                <img src="{$module.img|escape:'html':'UTF-8'}" alt="{$module.name|escape:'html':'UTF-8'}" style="width:100%;height:auto;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
                {/if}
            </a>
            {/if}
        </div>
        {/foreach}
    </div>
</div>
<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function () {
    if (typeof KeenSlider !== 'undefined') {
        new KeenSlider('#ps-modules-slider', {loop:true,mode:'free-snap',slides:{perView:'auto',spacing:15}});
    }
});
</script>
<form method="post" style="text-align:center;margin:10px 0 0;">
    <button type="submit" name="submitHideSlider" value="1" class="btn btn-default btn-sm" style="opacity:.5;">
        <i class="icon-eye-slash"></i> {l s='Hide modules carousel' mod='psoft_debugbar'}
    </button>
</form>
{/if}
