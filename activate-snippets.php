<?php namespace hpr_distributor;

activate_snippets();

function activate_snippets() {

    $settings_snippets = get_settings_snippets();
    foreach ($settings_snippets as $snippet) {
        $snippet_id = $snippet['id'];
        $function_to_call = $snippet['function'];

        // Check if the snippet is enabled
        $is_enabled = get_option($snippet_id, false);
 
        // Log snippet information
        write_log("Processing snippet: {$snippet['name']} (ID: $snippet_id)", false);

        if ($is_enabled) {
            write_log("Snippet $snippet_id is enabled. Preparing to activate.");
            
            // Adjust function name for correct namespace
            $function_to_call = '\\' . __NAMESPACE__ . '\\' . $function_to_call;
            
            if (function_exists($function_to_call)) {
                // Call the function to activate the snippet
                call_user_func($function_to_call);
                write_log("✅ Snippet $snippet_id activated by calling $function_to_call.", false);
            } else {
                write_log("🚫 Function $function_to_call does not exist for snippet $snippet_id.", true);
            }
        } else {
            write_log("🚫 Snippet $snippet_id is not enabled.", false);
        }
    }
}