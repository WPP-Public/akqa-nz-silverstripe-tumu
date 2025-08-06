<script type="module" <% if $Nonce %>nonce="{$Nonce}"<% end_if %>><% loop $JSModules %>
    import '{$Asset}';<% end_loop %>
</script>
