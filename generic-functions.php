<?php namespace hpr_distributor; 



// Define the write_log function only if it isn't already defined
if (!function_exists(__NAMESPACE__ . '\\write_log')) {
    function write_log($log, $full_debug = false) {
        if (WP_DEBUG && WP_DEBUG_LOG && $full_debug) {
            // Get the backtrace
            $backtrace = debug_backtrace();
            
            // Extract the last function that called this one
            $caller = isset($backtrace[1]['function']) ? $backtrace[1]['function'] : 'N/A';
            
            // Extract the file and line number where the caller is located
            $caller_file = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'N/A';
            $caller_line = isset($backtrace[0]['line']) ? $backtrace[0]['line'] : 'N/A';
            
            // Prepare the log message
            $log_message = is_array($log) || is_object($log) ? print_r($log, true) : $log;
            $log_message .= "\n\n[Called by: $caller]\n[In file: $caller_file at line $caller_line]\n\n---\n";
            
            // Write to the log
            error_log($log_message);
        }
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\write_log function is already declared", true);



if (!function_exists(__NAMESPACE__ . '\\check_press_release_post_type_enabled')) {
    function check_press_release_post_type_enabled() {
        // Check if the press-release post type is registered
        if (!post_type_exists('press-release')) {
            write_log("Press-release post type is not enabled", true);
            return [
                'function' => 'check_press_release_post_type_enabled',
                'status' => false,
                'raw_value' => 'Press-release post type is not enabled.',
                'variables' => [
                    'post_type' => 'press-release',
                    'is_enabled' => false
                ]
            ];
        }

        // Post type exists
        write_log("Press-release post type is enabled", false);
        return [
            'function' => 'check_press_release_post_type_enabled',
            'status' => true,
            'raw_value' => 'Press-release post type is enabled.',
            'variables' => [
                'post_type' => 'press-release',
                'is_enabled' => true
            ]
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_press_release_post_type_enabled function is already declared", true);






if (!function_exists(__NAMESPACE__ . '\\is_plugin_auto_update_enabled')) {
    function is_plugin_auto_update_enabled($plugin_id) {
        // Check if site-wide auto-updates are enabled
        if (has_filter('auto_update_plugin', '__return_true') !== false) {
            return true;
        }
 
        // Get the list of plugins with auto-updates enabled
        $auto_update_plugins = get_site_option('auto_update_plugins', []);

        // Check if the specific plugin has auto-updates enabled
        return in_array($plugin_id, $auto_update_plugins);
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\is_plugin_auto_update_enabled function is already declared", true);


if (!function_exists(__NAMESPACE__ . '\\check_fifu_setup')) {
    function check_fifu_setup() {
        // Check if the FIFU plugin is installed and active
        $plugin_status = check_plugin_status('featured-image-from-url/featured-image-from-url.php');
        list($is_installed, $is_active, $is_auto_update_enabled) = $plugin_status;

        if (!$is_installed || !$is_active) {
            write_log("FIFU plugin is either not installed or not active", false);
            return [
                'function' => 'check_fifu_setup',
                'status' => false,
                'raw_value' => 'FIFU Plugin is not properly installed or activated.',
                'variables' => [
                    'is_installed' => $is_installed,
                    'is_active' => $is_active
                ]
            ];
        }

        // Fetch last 10 press-release posts
        $args = [
            'post_type' => 'press-release',
            'posts_per_page' => 10
        ];
        $press_releases = new \WP_Query($args);

        if (!$press_releases->have_posts()) {
            write_log("No press releases found to check FIFU setup", false);
            return [
                'function' => 'check_fifu_setup',
                'status' => false,
                'raw_value' => 'No press releases found.',
                'variables' => []
            ];
        }

        // Check each post for featured image setup
        $report = "<br />";
        $site_base_url = get_site_url();
        $status = true;

        while ($press_releases->have_posts()) {
            $press_releases->the_post();
            $post_id = get_the_ID();
            $post_title = get_the_title();
            $post_date = get_the_date(); // Get the publish date
            $featured_image_url = get_the_post_thumbnail_url($post_id);
            $featured_image_id = get_post_thumbnail_id($post_id); // Get the featured image ID
            $media_link = admin_url('post.php?post=' . $featured_image_id . '&action=edit'); // Link to edit media in WP
            $report .= "<br />";
            // Check if featured image URL is internal (indicating FIFU is not set up properly)
            if ($featured_image_url && strpos($featured_image_url, $site_base_url) !== false) {
                $status = false; // Set status to false if internal URL found
                $report .= "Post: $post_title (Published on $post_date) - <a href='" . get_edit_post_link($post_id) . "' target='_blank'>Edit Post</a><br>";
                $report .= "Featured Image URL: <a href='$featured_image_url' target='_blank'><span style='color: red;'>$featured_image_url</span></a><br>";
                $report .= "WP Media: <a href='$media_link' target='_blank'>Edit Media</a><br>";
            } else {
                $report .= "Post: $post_title (Published on $post_date) - <a href='" . get_edit_post_link($post_id) . "' target='_blank'>Edit Post</a><br>";
                $report .= "Featured Image URL: <a href='$featured_image_url' target='_blank'>" . ($featured_image_url ?: "No featured image") . "</a><br>";
                if ($featured_image_url) {
                    $report .= "WP Media: <a href='$media_link' target='_blank'>Edit Media</a><br>";
                }
            }
        }

        // Reset post data
        wp_reset_postdata();

        return [
            'function' => 'check_fifu_setup',
            'status' => $status,
            'raw_value' => $report ?: 'No report generated.',
            'variables' => [
                'is_installed' => $is_installed,
                'is_active' => $is_active,
                'site_base_url' => $site_base_url
            ]
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_fifu_setup function is already declared", true);


if (!function_exists(__NAMESPACE__ . '\\create_category_for_post_type')) {
    function create_category_for_post_type($name,$slug, $post_type, $post_id = 0) {
        if (empty($slug) || empty($post_type)) {
            write_log('Slug or post type is empty', true);
            return new WP_Error('empty_fields', 'Both slug and post type are required.');
        }

        // Check if the post type exists
        if (!post_type_exists($post_type)) {
            write_log("Post type '$post_type' does not exist", false);
            return new WP_Error('invalid_post_type', "The post type '$post_type' does not exist.");
        }

        // Check if categories are supported by the post type
        if (!is_object_in_taxonomy($post_type, 'category')) {
            write_log("Post type '$post_type' does not support categories", false);
            return new WP_Error('taxonomy_not_supported', "The post type '$post_type' does not support categories.");
        }

        // Check if the category already exists
        $category_exists = get_category_by_slug($slug);
        if ($category_exists) {
            write_log("Category with slug '$slug' already exists", false);
            return new WP_Error('category_exists', "Category with slug '$slug' already exists.");
        }

        // Create the category
        $category_data = wp_insert_term(
            $name,   // Category name (same as slug)
            'category',
            [
                'slug' => $slug,
                'description' => "Category for $post_type"
            ]
        );

        if (is_wp_error($category_data)) {
            write_log('Error creating category: ' . $category_data->get_error_message(), true);
            return $category_data;
        }

        // Optionally assign the category to a post
        if ($post_id) {
            wp_set_post_categories($post_id, [$category_data['term_id']], true);
            write_log("Assigned category '$slug' to post ID $post_id for post type '$post_type'", false);
        }

        write_log("Category with slug '$slug' for post type '$post_type' created successfully", false);
        return $category_data;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\create_category_for_post_type function is already declared", true);




if (!function_exists(__NAMESPACE__ . '\\check_press_release_categories_tags')) {
    function check_press_release_categories_tags() {
        // Check if press-release post type exists
        if (!post_type_exists('press-release')) {
            write_log("press-release post type does not exist", false);
            return [
                'function' => 'check_press_release_categories_tags',
                'status' => false,
                'raw_value' => 'press-release post type does not exist.',
                'variables' => []
            ];
        }

        // Check if categories and tags are enabled
        $categories_enabled = is_object_in_taxonomy('press-release', 'category');
        $tags_enabled = is_object_in_taxonomy('press-release', 'post_tag');
        
        // Check if the "press-release" category exists
        $category_exists = get_category_by_slug('press-release');
        $create_button = '';

        // If the "press-release" category doesn't exist, create a button
        if (!$category_exists) {
        
            $create_button = "<button class='button execute-function' data-method='create_category_for_post_type' data-name='Press Release' data-slug='press-release' data-post_type='press-release' data-state='1'>Create Press-Release Category</button><br>";
        }

        // Generate report
        $report = "Press-release categories enabled: " . ($categories_enabled ? "Yes" : "No") . "<br>";
        $report .= "press-release tags enabled: " . ($tags_enabled ? "Yes" : "No") . "<br>";
        $report .= "Category with slug 'press-release': " . ($category_exists ? "Exists" : "Does not exist") . "<br>";
        $report .= $create_button;

        // Determine final status
        $status = $categories_enabled && $tags_enabled && $category_exists ? true : false;

        return [
            'function' => 'check_press_release_categories_tags',
            'status' => $status,
            'raw_value' => $report,
            'variables' => [
                'categories_enabled' => $categories_enabled,
                'tags_enabled' => $tags_enabled,
                'category_exists' => $category_exists ? true : false
            ]
        ];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_press_release_categories_tags function is already declared", true);



if (!function_exists(__NAMESPACE__ . '\\check_user_exists_by_slug')) {
    function check_user_exists_by_slug($slug) {
        if (empty($slug)) {
            write_log('Slug is empty', false);
            return false;
        }
        
        $user = get_user_by('slug', $slug);
        $exists = ($user) ? true : false;
        
        write_log('Checking if user exists by slug: ' . $slug . ' - ' . ($exists ? 'Exists' : 'Does not exist'), false);
        
        return $exists;
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_user_exists_by_slug function is already declared", true);


function create_hexa_pr_wire_user() {
    $result = \hpr_distributor\Setup\HexaPrWireAuthor::provision();

    return is_wp_error( $result ) ? $result : (int) $result["user_id"];
}

function check_if_user_hexa_pr_wire_exists(): array {
    $status = \hpr_distributor\Setup\HexaPrWireAuthor::status();
    $ready = ! empty( $status["exists"] )
        && ! empty( $status["profile_correct"] )
        && ! empty( $status["avatar_exists"] )
        && ! empty( $status["urls_complete"] );

    return [
        "function"  => "check_if_user_hexa_pr_wire_exists",
        "status"    => $ready,
        "raw_value" => $ready
            ? "Hexa PR Wire author, profile URLs, role, and avatar are ready."
            : "Hexa PR Wire author setup is incomplete. Run the Going Live author action.",
        "variables" => $status,
    ];
}




if (!function_exists(__NAMESPACE__ . '\\check_plugin_status')) {
    function check_plugin_status($plugin_slug) {
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug);
        $is_active = $is_installed && is_plugin_active($plugin_slug);

        // Initialize auto-update as not enabled since it's meaningless if not installed
        $is_auto_update_enabled = false;

        if ($is_installed) { 
            // Check global auto-update setting first
            $global_auto_update_enabled = apply_filters('auto_update_plugin', false, (object) array('plugin' => $plugin_slug));
 
            // If globally enabled, set auto-update to true
            if ($global_auto_update_enabled) {
                $is_auto_update_enabled = true;
            } else {
                // Get the current list of plugins with auto-updates enabled
                $auto_update_plugins = get_option('auto_update_plugins', []);

                // Check if this specific plugin is in the list
                $is_auto_update_enabled = in_array($plugin_slug, $auto_update_plugins);

                // If not in the auto-update plugins list, apply the global filter
                if (!$is_auto_update_enabled) {
                    $update_plugins = get_site_transient('update_plugins');

                    // Check the transient data for this specific plugin
                    if (isset($update_plugins->no_update[$plugin_slug])) {
                        $plugin_data = $update_plugins->no_update[$plugin_slug];
                    } elseif (isset($update_plugins->response[$plugin_slug])) {
                        $plugin_data = $update_plugins->response[$plugin_slug];
                    }

                    // Apply the auto_update_plugin filter with both arguments
                    if (isset($plugin_data)) {
                        $is_auto_update_enabled = apply_filters('auto_update_plugin', false, $plugin_data);
                    }
                }
            }
        }

        // Log the final auto-update status for debugging
        write_log("Plugin Slug: $plugin_slug - Installed: " . ($is_installed ? 'Yes' : 'No') . " - Auto-Update Enabled: " . ($is_auto_update_enabled ? 'Yes' : 'No'),false);

        return [$is_installed, $is_active, $is_auto_update_enabled];
    }
} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\check_plugin_status function is already declared", true);

if (!function_exists(__NAMESPACE__ . '\\set_default_press_release_category_on_new')) {
    function set_default_press_release_category_on_new($post_ID) {
        // Only apply when a new post is being created and it's of type 'press-release'
        if (did_action('edit_form_after_title') || get_post_status($post_ID) !== 'auto-draft' || get_post_type($post_ID) !== 'press-release') {
            return;
        }

        // Get "press-release" category
        $press_release_category = get_category_by_slug('press-release');
        if (!$press_release_category) {
            write_log("Category with slug 'press-release' does not exist.", false);
            return;
        }

        // Get "Uncategorized" category
        $uncategorized_id = get_cat_ID('Uncategorized');

        // Preselect "press-release" and remove "Uncategorized"
        wp_set_post_categories($post_ID, [$press_release_category->term_id], false);
        wp_remove_object_terms($post_ID, $uncategorized_id, 'category');

        write_log("Preselected 'press-release' category and removed 'Uncategorized' for post ID $post_ID.", false);
    }

}

if (!function_exists(__NAMESPACE__ . '\\add_post_type_to_archive')) {
function add_post_type_to_archive( $post_type ) {
    add_action( 'pre_get_posts', function( $query ) use ( $post_type ) {
        if ( ! is_admin() && $query->is_main_query() && is_author() ) {
            // Get the current post types in the query
            $post_types = $query->get('post_type');
            
            // Default to 'post' if no post type is set
            if ( empty( $post_types ) ) {
                $post_types = array( 'post' );
            }

            // Ensure post types is an array
            if ( ! is_array( $post_types ) ) {
                $post_types = array( $post_types );
            }

            // Add the custom post type
            $post_types[] = $post_type;
            $query->set( 'post_type', $post_types );
        }
    });
}} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\add_post_type_to_archive function is already declared", true);





if (!function_exists(__NAMESPACE__ . '\\add_press_release_to_author_page')) {
function add_press_release_to_author_page(){
add_post_type_to_archive( 'press-release' );
}} else write_log("⚠️ Warning: " . __NAMESPACE__ . "\\add_press_release_to_author_page function is already declared", true);


?>