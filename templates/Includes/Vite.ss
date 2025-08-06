
<% if $IsDev %>
<% if $IsDevHot %>
<script type="module" <% if $Nonce %>nonce="{$Nonce}"<% end_if %>>
    const loadModule = async (modulePath) => {
        try {
            return await import(modulePath)
        } catch (e) {
            // delete everything in body
            document.body.innerHTML = ''
            // display an error that you're not running `ddev yarn watch`
            const error = document.createElement('div')
            error.style.position = 'fixed'
            error.style.top = '0'
            error.style.left = '0'
            error.style.right = '0'
            error.style.zIndex = '9999'
            error.style.width = '90vw'
            error.style.maxWidth = '600px'
            error.style.margin = '10vh auto'
            error.style.display = 'flex'
            error.style.padding = '10vw'
            error.style.border = '2px solid red'
            error.style.outline = '7px solid rgba(255, 0, 0, 0.05)'
            error.style.borderRadius = '4px'
            error.style.alignItems = 'flex-start'
            error.style.justifyContent = 'center'
            error.style.flexDirection = 'column'
            error.style.padding = '8px 16px'
            error.style.background = 'white'
            error.style.color = 'black'
            error.style.fontFamily = 'monospace'
            error.style.fontSize = '14px'
            error.style.lineHeight = '1.5'
            error.innerHTML = '<h1>Error loading module <code>' + modulePath + '</code>.</h1><p>Make sure you are running <code style="background: yellow; padding: 4px">ddev {$PackageManager} dev</code> in the project root.</p><p>If you want to avoid the hot server, set `SS_USE_VITE_DEV_SERVER="false"` in <strong>.ddev/.env</strong> and then restart <strong>ddev restart</strong>';

            document.body.appendChild(error)
            document.body.style.opacity = '1';
        }
    }

    await loadModule('{$ViteBaseHref}/@react-refresh');
</script>

<script type="module" <% if $Nonce %>nonce="{$Nonce}"<% end_if %>>
    import RefreshRuntime from '{$ViteBaseHref}/@react-refresh'

    if (RefreshRuntime) {
        RefreshRuntime.injectIntoGlobalHook(window);
        window.\$RefreshReg\$ = () => {}
        window.\$RefreshSig\$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true

        await import('{$ViteBaseHref}/@vite/client');
        await import('{$ViteBaseHref}/{$ViteEntryPoint}');
    }
</script>

<% if $HotAdditionalRequirements %><% loop $HotAdditionalRequirements %><!-- additional requirements -->
<script type="module" nonce="{$Up.Nonce}" src="{$Up.ViteBaseHref}/{$Asset}"></script>
<% end_loop %><% end_if %><% else %>
$IncludeViteBuiltRequirements.RAW<% end_if %>
<% else %>
$IncludeViteBuiltRequirements.RAW
<% end_if %>
