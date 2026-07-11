<?php namespace hpr_distributor;

/**
 * Enable auto-delete functionality for HexaPR Wire.
 * This function sets up various WordPress hooks to schedule and handle 
 * the automatic deletion of press releases.
 */
function enable_hpr_auto_deletes() {
    // Keep manual actions authenticated and bind the actual cron callback.
    add_action( 'wp_ajax_view_crons', __NAMESPACE__ . '\hexaprwire_display_crons' );
    add_action( 'wp_ajax_hexaprwire_process_deletes', __NAMESPACE__ . '\hexaprwire_process_deletes' );
    add_action( 'hexaprwire_process_deletes', __NAMESPACE__ . '\hexaprwire_process_deletes' );

    // Schedule the deletion process to run hourly if not already scheduled
    if (!wp_next_scheduled('hexaprwire_process_deletes')) {
        wp_schedule_event(time(), 'hourly', 'hexaprwire_process_deletes');
    }
}



/**
 * Check the status of the 'hexaprwire_process_deletes' cron job and related purge functionality.
 * The function returns detailed information about the cron status, including whether it's functioning, 
 * when it last ran, and provides links to manually trigger it if needed.
 */
function check_hexa_pr_wire_purge_status() {
    $cron_slug = 'hexaprwire_process_deletes';
    $base_url = site_url();

    // Get the list of scheduled cron jobs
    $crons = _get_cron_array();
    $cron_found = false;
    $cron_last_ran = 'N/A';
    $report = '';

    // Check if the cron job is scheduled
    foreach ($crons as $timestamp => $cron_jobs) {
        if (isset($cron_jobs[$cron_slug])) {
            $cron_found = true;
            $cron_last_ran = date('F d, Y H:i:s', $timestamp);
            break;
        }
    }

    // If the cron job is found, build the report
    if ($cron_found) {
        // Generate links to view and manually trigger the cron
        $view_crons_url = esc_url( wp_nonce_url( admin_url( "admin-ajax.php?action=view_crons" ), Config::AJAX_NONCE, "nonce" ) );
        $process_deletes_url = esc_url( wp_nonce_url( admin_url( "admin-ajax.php?action=hexaprwire_process_deletes" ), Config::AJAX_NONCE, "nonce" ) );
        
        $report .= "<br /><strong>Cron Job Found:</strong> '$cron_slug'<br>";
        $report .= "Last Ran: $cron_last_ran<br>";
        $report .= "<a href='$view_crons_url' target='_blank'>View Crons - $view_crons_url</a><br>";
        $report .= "<a href='$process_deletes_url' target='_blank'>Process Press Release Purge List - $process_deletes_url</a><br>";
        
        write_log("Hexa PR Wire cron job '$cron_slug' is active and was last run on $cron_last_ran", false);
        
        return [
            'function' => 'check_hexa_pr_wire_purge_status',
            'status' => true, // Cron is active and functioning
            'raw_value' => $report, // Display the cron information
            'variables' => [
                'cron_slug' => $cron_slug,
                'last_ran' => $cron_last_ran
            ]
        ];
    } else {
        // If the cron job isn't found, provide a link to manually trigger it and show an error
        $trigger_cron_url = esc_url( wp_nonce_url( admin_url( "admin-ajax.php?action=hexaprwire_process_deletes" ), Config::AJAX_NONCE, "nonce" ) );
        
        $report .= "Cron job '$cron_slug' is not active or functioning.<br>";
        $report .= "<a href='$trigger_cron_url' target='_blank'>Manually Trigger Cron</a><br>";

        write_log("Hexa PR Wire cron job '$cron_slug' is not functioning or scheduled.", false);

        return [
            'function' => 'check_hexa_pr_wire_purge_status',
            'status' => false, // Cron is not active or functioning
            'raw_value' => $report, // Display the failure information
            'variables' => [
                'cron_slug' => $cron_slug,
                'last_ran' => $cron_last_ran
            ]
        ];
    }
}


/**
 * Process the deletion of press releases based on slugs fetched from HexaPR Wire.
 * This function sends a request to the HexaPR Wire API, retrieves a list of slugs,
 * and deletes the corresponding posts if found.
 */
function hexaprwire_process_deletes() {
    if ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) {
        guard_ajax_request( "manage_options" );
    }
    // URL for fetching the list of slugs to delete
    $ch = curl_init("https://hexaprwire.com/wp-admin/admin-ajax.php?action=purge_release_list"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $data = curl_exec($ch);
    curl_close($ch);

    // If no data is returned, stop processing
    if (empty($data)) {
        return;
    }

    // Explode the comma-separated slugs returned from the API
    $slugs = explode(",", $data);
    $log = "";  // Initialize log for tracking deleted or missing posts

    foreach ($slugs as $slug) {
        // Search for the post with the given slug
        $args = [
            'name'        => $slug,
            'post_type'   => 'press-release',
            'post_status' => 'publish',
            'numberposts' => 1
        ];

        // Get the post based on the slug
        $my_posts = get_posts($args);

        // If a post is found, delete it
        if (sizeof($my_posts) > 0) {
            $post = $my_posts[0];
            $log .= "found-{$slug} with {$post->ID}; ";
            wp_trash_post($post->ID); // Move the post to trash
        } else {
            $log .= "not-found-{$slug}; ";
        }
    }

    // Uncomment below line to log the results (optional logging)
    // write_log('hexaprwire.com process deletes log:'.$log);
    return; // End the process
}

/**
 * Display the list of scheduled cron jobs in a human-readable format.
 * This is used in an AJAX action to output the current cron schedule.
 */
function hexaprwire_display_crons() {
    guard_ajax_request( "manage_options" );
    echo '<pre>';
    print_r(_get_cron_array());  // Display the cron array
    echo '</pre>';
    return; // Terminate after displaying the crons
}