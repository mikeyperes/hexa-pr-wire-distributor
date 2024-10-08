<?php namespace hpr_distributor;

use function hpr_distributor\disable_rankmath_sitemap_caching;
use function hpr_distributor\enable_auto_update_plugins;
use function hpr_distributor\enable_auto_update_themes;
use function hpr_distributor\custom_wp_admin_logo;
use function hpr_distributor\disable_litespeed_js_combine;
use function hpr_distributor\hws_ct_snippets_activate_author_social_acfs;
use function hpr_distributor\write_log;
use function hpr_distributor\toggle_snippet;
use function hpr_distributor\hws_ct_get_settings_snippets;
 

function enable_press_release_category_on_new_post()
{

    // Hook into 'wp_insert_post' to apply the changes when a new post is created
    add_action('wp_insert_post', __NAMESPACE__ . '\\set_default_press_release_category_on_new', 10, 1);
}


if (!function_exists('hpr_distributor\toggle_snippet')) {
    function toggle_snippet() {
        $settings_snippets = hws_ct_get_settings_snippets();

        // Retrieve the snippet ID and the enable/disable state from the AJAX request
        $snippet_id = sanitize_text_field($_POST['snippet_id']);
        $enable = filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN);

        write_log("Toggle snippet called with ID: {$snippet_id}, enable: " . ($enable ? 'true' : 'false'));

        // Find the corresponding snippet and function
        foreach ($settings_snippets as $snippet) {
            if ($snippet['id'] === $snippet_id) {
                // Get the current value from the database
                $current_value = get_option($snippet_id);
                write_log("Current value of '{$snippet_id}': " . var_export($current_value, true));

                // Ensure both current and new values are booleans for accurate comparison
                $current_value_bool = filter_var($current_value, FILTER_VALIDATE_BOOLEAN);

                // Only update if the value has actually changed
                if ($current_value_bool !== $enable) {
                    write_log("Attempting to update '{$snippet_id}' to " . ($enable ? 'true' : 'false'));

                    // Attempt the update
                    $updated = update_option($snippet_id, $enable);

                    // Log the result of the update attempt
                    if ($updated) {
                        write_log("Option '{$snippet_id}' updated successfully.");
                        wp_send_json_success("Option '{$snippet_id}' updated successfully.");
                    } else {
                        global $wpdb;
                        $db_error = $wpdb->last_error;
                        write_log("Failed to update option '{$snippet_id}'. Database error: {$db_error}");
                        wp_send_json_error("Failed to update option '{$snippet_id}'. Database error: {$db_error}");
                    }
                } else {
                    write_log("No update required for '{$snippet_id}'. Current value is the same as the new value.");
                    wp_send_json_error("No update required for '{$snippet_id}'. Current value is the same.");
                }

                exit; // Stop further processing once the correct snippet is found
            }
        }

        write_log("Invalid snippet ID: {$snippet_id}");
        wp_send_json_error("Invalid snippet ID: {$snippet_id}");

        wp_die(); // Ensure proper termination of the script
    }
} else {
    write_log("Warning: hpr_distributor/toggle_snippet function is already declared", true);
}



    add_action('wp_ajax_toggle_snippet', 'hpr_distributor\toggle_snippet');

    function display_settings_snippets() {
        add_action('admin_init', 'acf_form_init');
    
        function acf_form_init() {
            acf_form_head();
        }
        ?>
    

    <style>
        .panel-settings-snippets {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 20px;
            background-color: #f7f7f7;
            padding: 10px 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-size: 14px;
        }

        .panel-settings-snippets .panel-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .panel-settings-snippets .panel-content {
            padding: 10px 0;
        }

        .panel-settings-snippets ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .panel-settings-snippets li {
            padding: 1px 0;
            font-size: 12px;
            color: #888;
        }

        .panel-settings-snippets input[type="checkbox"] {
            margin-right: 10px;
        }

        .panel-settings-snippets label {
            font-size: 13px;
            color: #555;
        }

        .panel-settings-snippets small {
            display: block;
            margin-top: 3px;
            color: #777;
            font-size: 12px;
        }

        .snippet-item {
            margin-bottom: 12px;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #dcdcdc;
            background-color: #fff;
        }
    </style>
        <!-- Snippets Status Panel -->
        <div class="panel panel-settings-snippets">
            <h2 class="panel-title">Snippets</h2>
            <div class="panel-content">
                <h3>Active Snippets:</h3>
                <div style="margin-left: 15px; color: green;">
                    <?php
                    // Initialize an array to store active snippets
                    $active_snippets = [];
                    $settings_snippets = hws_ct_get_settings_snippets();
    
                    // Iterate through the snippets and check which ones are active
                    foreach ($settings_snippets as $snippet) {
                        $is_enabled = get_option($snippet['id'], false);
                        if ($is_enabled) {
                            $active_snippets[] = $snippet['name']; // Add active snippet names to the array
                        }
                    }
    
                        // Display active snippets or a message if none are found
                if (!empty($active_snippets)) {
                    echo "<ul>";
                    foreach ($active_snippets as $snippet_name) {
                        echo "<li>&#x2705; {$snippet_name}</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>No active snippets found.</p>";
                }
                    ?>
                </div>
    
                <!-- Snippet Actions and Status -->
                <div style="margin-bottom: 15px;">
                    <h3>Available Snippets:</h3>
                    <div style="margin-left: 15px;">
                        <?php
// Loop through all snippets and display them with a checkbox
foreach ($settings_snippets as $snippet) {
    // Get the current state of the option from the database
    $is_enabled = get_option($snippet['id'], false);

    // Debug printout to screen
  //  echo "<pre>Debug: Option '{$snippet['id']}' current value: " . var_export($is_enabled, true) . "</pre>";

    // Determine if the checkbox should be checked
    $checked = $is_enabled ? 'checked' : '';

    // Display the checkbox and label with the info field included
    echo "<div style='color: #555; margin-bottom: 10px;'>
            <input type='checkbox' id='{$snippet['id']}' onclick='toggleSnippet(\"{$snippet['id']}\")' $checked>
            <label for='{$snippet['id']}'>
                {$snippet['name']} - <em>{$snippet['description']}</em>
                <br>
                <small><strong>Details:</strong><br>{$snippet['info']}</small>
            </label>
          </div>";
}

                        ?>
                    </div>
                </div>
            </div>
        </div>



  
    
    <?php }
    
?>