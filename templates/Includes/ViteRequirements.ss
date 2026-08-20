<% if $ModulePreloads %><% loop $ModulePreloads %>
<link rel="modulepreload" href="{$Asset}"<% if $Up.Nonce %> nonce="{$Up.Nonce}"<% end_if %>>
<% end_loop %><% end_if %>
<script type="module" <% if $Nonce %>nonce="{$Nonce}"<% end_if %>><% loop $JSModules %>
    import '{$Asset}';<% end_loop %>
</script>
