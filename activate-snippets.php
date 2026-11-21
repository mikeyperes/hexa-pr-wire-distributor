<?php namespace hpr_distributor;

activate_snippets();

function activate_snippets() {
    $resolve = __NAMESPACE__ . '\\is_settings_snippet_enabled';
    \Hexa\PluginCore\SnippetRegistry\LegacySnippetActivator::activate(
        get_settings_snippets(),
        __NAMESPACE__,
        __NAMESPACE__ . '\\write_log',
        function_exists( $resolve ) ? $resolve : null
    );
}
