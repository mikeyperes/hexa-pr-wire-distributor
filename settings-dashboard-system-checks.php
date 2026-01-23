<?php namespace hpr_distributor;



function hws_ct_get_settings_system_checks()
{
    
    $system_checks = [
        'Check Hexa PR Wire Auto Delete Status' => [
    'id' => 'hexa-pr-wire-auto-delete-cron',
    'value' => hws_ct_highlight_based_on_criteria(check_hexa_pr_wire_purge_status()) 
],
        'Create Hexa PR Wire User' => [
            'id' => 'wp-main-email',
            'value' => hws_ct_highlight_based_on_criteria(check_if_user_hexa_pr_wire_exists())
        ],  
         'Check FIFU Setup' => [
            'id' => 'fifu-setup',
            'value' => hws_ct_highlight_based_on_criteria(check_fifu_setup())
        ],
        'Check Press Release post type active' => [
            'id' => 'fifu-setup',
            'value' => hws_ct_highlight_based_on_criteria(check_press_release_post_type_enabled())
        ],
        'Check Category Status' => [
            'id' => 'fifu-setup',
            'value' => hws_ct_highlight_based_on_criteria(check_press_release_categories_tags())
        ],

        
        


    ];

        return $system_checks;
   
    $system_checks = [
        /*
        'WordPress Comments' => [
    'id' => 'wp-comments',
    'value' => hws_ct_highlight_based_on_criteria(
        perform_comments_system_check() // Calls the function that checks the comment statuses
    ),
],*/ 


'Press Release Auto Delete Status' => [
    'id' => 'press-release-auto-delete',
    'value' => hws_ct_highlight_based_on_criteria(check_hexa_pr_wire_purge_status())
],

        'WordPress Admin Email' => [
            'id' => 'wp-main-email',
            'value' => hws_ct_highlight_based_on_criteria(check_wordpress_main_email()) . 
            ' <a target="_blank" href="' . esc_url(admin_url('options-general.php')) . '">modify</a>'
        ],
        'Imagick Library Available' => [
            'id' => 'imagick-available',
            'value' => hws_ct_highlight_based_on_criteria(check_imagick_available())
        ],
        /*
        'CloudLinux Configurations Enabled' => [
            'id' => 'cloudlinux-config',
            'value' => hws_ct_highlight_if_essential_setting_failed([
                'status' => check_cloudlinux_config(),
                'details' => check_cloudlinux_config() ? 'Enabled' : 'Not Enabled'
            ]),
        ],*/
        'WordPress Auto Updates Enabled' => [
            'id' => 'wp-auto-updates',
            'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_AUTO_UPDATE_CORE', check_wp_config_constant_status('WP_AUTO_UPDATE_CORE'), ['listed_values' => ['false', false]]))

        ],
        'Cloudflare Active' => [
            'id' => 'cloudflare-active',
            'value' => hws_ct_highlight_based_on_criteria(check_cloudflare_active())
        ],   
        'PHP Type' => [
            'id' => 'php-type',
            'value' => hws_ct_highlight_based_on_criteria(check_php_type())
        ],
        'PHP Handler' => [
            'id' => 'php-handler',
            'value' => hws_ct_highlight_based_on_criteria(check_php_handler())
        ],
        'Plugin Auto Updates' => [
            'id' => 'plugin-auto-updates',
            'value' => hws_ct_highlight_based_on_criteria(
                [
                    'status' => has_filter('auto_update_plugin', '__return_true'),
                    'raw_value' => has_filter('auto_update_plugin', '__return_true') ? 'Enabled' : 'Disabled'
                ]
            ),
        ],
                    // Add for theme auto-updates
        'Theme Auto Updates' => [
            'id' => 'theme-auto-updates',
            'value' => hws_ct_highlight_based_on_criteria(
                [
                    'status' => has_filter('auto_update_theme', '__return_true'),
                    'raw_value' => has_filter('auto_update_theme', '__return_true') ? 'Enabled' : 'Disabled'
                ]  ),
        ],
        'Database Table Prefix' => [
            'id' => 'database-table-prefix',
            'value' => hws_ct_highlight_based_on_criteria(get_database_table_prefix())
            ],
            'MyISAM Tables' => [
    'id' => 'myisam-tables',
'value' => hws_ct_highlight_based_on_criteria(check_myisam_tables()) . ' - <a target=_blank href="' . esc_url(admin_url('admin.php?page=litespeed-db_optm')) . '">View More</a>'

],
/*
'Additional WordPress Installs Detected' => [
    'id' => 'additional-wp-installs',
    'raw_value' => hws_ct_highlight_based_on_criteria(detect_additional_wp_installs())
],*/

'WordFence Notification Email' => [
    'id' => 'wf-email',
    'value' => hws_ct_highlight_based_on_criteria(check_wordfence_notification_email())
],
'LSCWP_OBJECT_CACHE State' => [
    'id' => 'wp-ls-cache',
        'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('LSCWP_OBJECT_CACHE', check_wp_config_constant_status('LSCWP_OBJECT_CACHE'), ['listed_values' => ['false', false]]))

],
'display_errors State' => [
    'id' => 'display-errors',
    'value' => hws_ct_highlight_based_on_criteria(
            perform_php_ini_check('display_errors',  // Call perform_php_ini_check to get the current status
            [1, "1", "On", "on"],  // ON values passed directly
            [0, "0", "Off", "off"],  // OFF values passed directly
            [true,1, "1", "On", "on"] // mark as fail (red text) if these value
        ))
],
/*
'display_errors State' => [
    'id' => 'display-errors-delete',
    'value' => hws_ct_highlight_based_on_criteria(
        hws_ct_package_constant_value_for_checks(
            'display_errors', 
            check_php_ini_status('display_errors'), 
            ['listed_values' => ['Off', 'On']]
        )
    )
],*/
'error_reporting State' => [
    'id' => 'error-reporting',
    'value' => hws_ct_highlight_based_on_criteria(
        hws_ct_package_constant_value_for_checks(
            'error_reporting', 
            check_php_ini_status('error_reporting'), 
            ['listed_values' => [E_ALL]]
        )
    )
        ],
        'WP_DEBUG State' => [
    'id' => 'wp-debug',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG', check_wp_config_constant_status('WP_DEBUG'), ['listed_values' => ['true', true]]))

],
'WP_DEBUG_DISPLAY State' => [
    'id' => 'wp-debug-display',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG_DISPLAY', check_wp_config_constant_status('WP_DEBUG_DISPLAY'), ['listed_values' => ['true', true]]))
],
'WP_DEBUG_LOG State' => [
    'id' => 'wp-debug-log',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_DEBUG_LOG', check_wp_config_constant_status('WP_DEBUG_LOG'), ['listed_values' => ['true', true]]))
],
'Debug Log Size' => [
    'id' => 'debug-log',
    'value' => hws_ct_highlight_based_on_criteria(check_log_file_sizes()['debug_log'])
],

'Error Log Size' => [
    'id' => 'error-log',
    'value' => hws_ct_highlight_based_on_criteria(check_log_file_sizes()['error_log'])
],
            'SMTP Authentication Enabled' => [
                'id' => 'smtp-auth',
                'value' => hws_ct_highlight_based_on_criteria(check_smtp_auth_status_and_mailer()).'<a href="' . esc_url(admin_url('admin.php?page=wp-mail-smtp')) . '" target="_blank" class="button">Edit</a>'
            ],
          /* 'SMTP Mailer Service' => [
                'id' => 'smtp-mailer',
                'value' => hws_ct_highlight_based_on_criteria([
                    'status' => !empty(check_smtp_auth_status_and_mailer()['mailer']),
                    'value' => check_smtp_auth_status_and_mailer()['mailer']
                ])
            ],
 'SMTP Authenticated Domain' => [
    'id' => 'smtp-domain',
    'value' => hws_ct_highlight_based_on_criteria([
        'status' => !empty(check_smtp_auth_status_and_mailer()['details']),
        'details' => check_smtp_auth_status_and_mailer()['details']
    ]) . (check_smtp_auth_status_and_mailer()['details'] ?  : '')
],*/

'REDIS Active' => [
    'id' => 'redis-active',
'value' => hws_ct_highlight_based_on_criteria(check_redis_active()) . ' <a href="' . esc_url(admin_url('admin.php?page=litespeed-cache')) . '" target="_blank">View More</a>'

],
'Caching Source' => [
                'id' => 'caching-source',
                'value' => hws_ct_highlight_based_on_criteria(check_caching_source())
            ],
            'PHP Version' => [
                'id' => 'php-version',
                'value' => hws_ct_highlight_based_on_criteria(check_php_version())
               
                
            ],
'WordPress RAM' => [
    'id' => 'wp-ram',
    'value' => hws_ct_highlight_based_on_criteria(
        check_wordpress_memory_limit() ),
],

'WP_CACHE State' => [
    'id' => 'wp-cache',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('WP_CACHE', check_wp_config_constant_status('WP_CACHE'), ['listed_values' => ['false', false,1]]))
    ],

            'Server RAM' => [
                'id' => 'server-ram',
                'value' => hws_ct_highlight_based_on_criteria(check_server_memory_limit())
            ],
            'Number of Processors' => [
                'id' => 'num-processors',
                'value' => hws_ct_highlight_based_on_criteria(check_server_specs()),
            ],
'post_max_size' => [
    'id' => 'php-post-max-size',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('post_max_size', check_php_ini_status('post_max_size'), ['listed_values' => ['false', false]]))
],
'upload_max_filesize' => [
    'id' => 'php-upload-max-filesize',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('upload_max_filesize', check_php_ini_status('upload_max_filesize'), ['listed_values' => ['false', false]]))
],
/*,
'php_version' => [
    'id' => 'php-version',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('php_version', phpversion(), ['listed_values' => ['false', false]]))
]



'cloud_linux_info' => [
    'id' => 'cloud-linux-info',
    'value' => hws_ct_highlight_based_on_criteria(hws_ct_package_constant_value_for_checks('cloud_linux_info', (defined('CLOUDLINUX_VERSION') ? CLOUDLINUX_VERSION : 'Not CloudLinux'), ['listed_values' => ['false', false]]))
],
*/
    ];
    return $system_checks;

}

/*,
            
         
            // Add for plugin auto-updates
    


   


           
        
       
         

          
            */

function display_settings_system_checks()
{

    ?>

    <style>
        .block{display:block !important}
</style>
        <!-- System Checks Panel -->
    <div class="panel">
     <h2 class="panel-title">System Checks</h2>
     <small><a href="<?php echo admin_url('site-health.php'); ?>" target="_blank">View WordPress Site Health</a></small>
        <div class="panel-content">
            <?php
            $system_checks = hws_ct_get_settings_system_checks();










             foreach ($system_checks as $label => $setting): ?>

                <p id="<?php echo $setting['id']; ?>"><strong><?php echo $label; ?>:</strong> <?php echo nl2br($setting['value']); ?></p>
                
                <?php if ($setting['id'] === 'wp-ram'): ?>
                    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
                        <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="4000M" data-target="wp-ram">Add Memory Limit</button>
                    <?php endif; ?>
                    <button class="button modify-wp-config" data-constant="WP_MEMORY_LIMIT" data-value="512M" data-target="wp-ram">Remove WP_MEMORY_LIMIT</button>
                <?php endif; ?>
                
                <?php if ($setting['id'] === 'wp-cache'):

$wp_cache_status = check_wp_config_constant_status('WP_CACHE'); 
if ($wp_cache_status === 'true' || $wp_cache_status === true): ?>
    <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="false" data-target="wp-cache">Disable WP_CACHE</button>
<?php else: ?>
    <button class="button modify-wp-config" data-constant="WP_CACHE" data-value="true" data-target="wp-cache">Enable WP_CACHE</button>
<?php endif; ?>

<?php endif; ?>



               
                <?php /* if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()): ?>
                    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="true" data-target="wp-auto-updates">Enable Auto Updates</button>
                    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="false" data-target="wp-auto-updates">Disable Auto Updates</button>
                <?php endif */ ?>

                <?php if ($setting['id'] === 'wp-auto-updates' && !check_wp_core_auto_update_status()): ?>
    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="true" data-target="wp-auto-updates">Enable Auto Updates</button>
<?php elseif ($setting['id'] === 'wp-auto-updates' && check_wp_core_auto_update_status()): ?>
    <button class="button modify-wp-config" data-constant="WP_AUTO_UPDATE_CORE" data-value="false" data-target="wp-auto-updates">Disable Auto Updates</button>
<?php endif; ?>
                
            
                <?php if ($setting['id'] === 'plugin-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_plugins" data-action="enable">Enable Plugin Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_plugins" data-action="disable">Disable Plugin Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>

<?php if ($setting['id'] === 'wf-email'):
    $wordfence_url = admin_url('admin.php?page=WordfenceOptions'); 
?>
    <a target=_blank href="<?php echo esc_url($wordfence_url); ?>" class="button">Go to Wordfence Options</a>
<?php endif; ?>



<?php if ($setting['id'] === 'theme-auto-updates'): ?>
    <?php if (strpos($setting['value'], 'color: red') !== false): ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_themes" data-action="enable">Enable Theme Auto Updates</button>
    <?php else: ?>
        <button class="button modify-snippet-via-button" data-snippet-id="enable_auto_update_themes" data-action="disable">Disable Theme Auto Updates</button>
    <?php endif; ?>
<?php endif; ?>
                

            <?php endforeach;




            ?>
        </div>
    </div>

    
<?php

//add_action('wp_ajax_hws_ct_update_wp_config', 'hws_ct_update_wp_config');


?>


  
    <?php
    

}






/*
if (!function_exists('toggle_wordpress_comments')) {
    function toggle_wordpress_comments($state) {
        // Determine the comment status based on the state
        $comment_status = ($state === 'enable') ? 'open' : 'closed';
        $ping_status = ($state === 'enable') ? 'open' : 'closed';

        // Toggle comments for future posts
        add_filter('wp_insert_post_data', function($data) use ($comment_status, $ping_status) {
            if ($data['post_type'] === 'post' || $data['post_type'] === 'page') {
                $data['comment_status'] = $comment_status;
                $data['ping_status'] = $ping_status;
            }
            return $data;
        });

        // Toggle comments for prior posts
        $posts = get_posts([
            'post_status' => 'publish',
            'numberposts' => -1 // Fetch all posts
        ]);

        if (!empty($posts)) {
            foreach ($posts as $post) {
                wp_update_post([
                    'ID' => $post->ID,
                    'comment_status' => $comment_status,
                    'ping_status' => $ping_status
                ]);
            }
            write_log("Comments successfully {$state}d on prior posts.", true);
        } else {
            write_log("No published posts found to {$state} comments.", true);
        }

        return true; // Return true to indicate success
    }
} else {
    write_log("Warning: toggle_wordpress_comments function is already declared", true);
}







// Check if Comments Are Disabled for Prior Posts
if (!function_exists('are_comments_disabled_for_prior_posts')) {
    function are_comments_disabled_for_prior_posts() {
        // Query for any published posts with open comments
        $posts_with_open_comments = get_posts([
            'post_status' => 'publish',
            'numberposts' => 1, // We only need one result to verify
            'comment_status' => 'open'
        ]);

        // Return true if no posts have open comments (i.e., comments are disabled)
        return empty($posts_with_open_comments);
    }
} else write_log("Warning: are_comments_disabled_for_prior_posts function is already declared", true);



// Check if Users Must Be Registered to Comment
if (!function_exists('check_users_must_be_registered_to_comment')) {
    function check_users_must_be_registered_to_comment() {
        return get_option('comment_registration') ? true : false;
    }
} else write_log("Warning: check_users_must_be_registered_to_comment function is already declared", true);

// Check if Email Notifications for Comments Are Enabled
if (!function_exists('check_email_notifications_for_comments')) {
    function check_email_notifications_for_comments() {
        return get_option('comments_notify') ? true : false;
    }
} else write_log("Warning: check_email_notifications_for_comments function is already declared", true);




// Check if Comments Are Disabled for Future Posts
if (!function_exists('are_comments_disabled_for_future_posts')) {
    function are_comments_disabled_for_future_posts() {
        // Retrieve the WordPress option that controls default comment status for new posts
        $default_comment_status = get_option('default_comment_status'); 

        // If the default comment status is 'closed', comments are disabled for future posts
        return $default_comment_status === 'closed';
    }
} else write_log("Warning: are_comments_disabled_for_future_posts function is already declared", true);
*/


// Toggle Users Must Be Registered to Comment
if (!function_exists('toggle_users_must_be_registered_to_comment')) {
    function toggle_users_must_be_registered_to_comment($action) {
        if ($action === 'enable') {
            update_option('comment_registration', 1); // Enable user registration requirement
        } else if ($action === 'disable') {
            update_option('comment_registration', 0); // Disable user registration requirement
        }
        return true; // Return true to indicate success
    }
} else write_log("Warning: toggle_users_must_be_registered_to_comment function is already declared", true);


/*
if (!function_exists('perform_comments_system_check')) {
    function perform_comments_system_check() {
        // Step 1: Perform the checks and get values
        $future_comments_status = are_comments_disabled_for_future_posts() ? "true" : "false"; // Future comments disabled check
        $prior_comments_status = are_comments_disabled_for_prior_posts() ? "true" : "false"; // Prior comments disabled check
        $users_must_be_registered = get_option('comment_registration') ? "true" : "false"; // User registration check
        $email_notifications = get_option('comments_notify') ? "true" : "false"; // Email notifications check

        // Step 2: Get the number of approved, pending, total, and spam comments
        $approved_comments = get_comments(['status' => 'approve', 'count' => true]);
        $pending_comments = get_comments(['status' => 'hold', 'count' => true]);
        $spam_comments = get_comments(['status' => 'spam', 'count' => true]);
        $total_comments = wp_count_comments()->total_comments;

        // Step 3: Determine the status for highlighting (fail if there are pending, spam, or approved comments)
        $status = ($approved_comments > 0 || $pending_comments > 0 || $spam_comments > 0) ? 'fail' : 'pass';

        // Step 4: Format the report with proper red text for entire rows on failure
        $report = "<br>";

        // Approved comments (no condition to make this red)
        $report .= "Approved Comments: $approved_comments<br>";

// Pending comments, red if pending
$report .= ($pending_comments > 0 
            ? "<span style='color:red;'>Pending Comments: $pending_comments</span>
               <button class='button execute-function block' data-method='delete_pending_comments' data-loader='true'>Delete Pending Comments</button>"
            : "Pending Comments: $pending_comments<br>");

// Spam comments, red if spam exists
$report .= ($spam_comments > 0 
            ? "<span style='color:red;'>Spam Comments: $spam_comments</span>
               <button class='button execute-function block' data-method='delete_spam_comments' data-loader='true'>Delete Spam Comments</button>"
            : "Spam Comments: $spam_comments<br>");

// Total comments (no condition to make this red)
$report .= "Total Comments: $total_comments
            <button class='button execute-function block' data-method='delete_all_comments' data-loader='true'>Delete All Comments</button>";

// Comments Disabled for Future Posts, red if false
$report .= ($future_comments_status === 'false' 
            ? "<span style='color:red;'>Comments Disabled for Future Posts: false</span>
               <button class='button execute-function block' data-method='enable_comments_future' data-loader='true'>Enable Comments on Future Posts</button>"
            : "Comments Disabled for Future Posts: true
               <button class='button execute-function block' data-method='disable_comments_future' data-loader='true'>Disable Comments on Future Posts</button>");

// Comments Disabled for Prior Posts, red if false
$report .= ($prior_comments_status === 'false' 
            ? "<span style='color:red;'>Comments Disabled for Prior Posts: false</span>
               <button class='button execute-function block' data-method='disable_comments_prior' data-loader='true'>Disable Comments on Prior Posts</button>"
            : "Comments Disabled for Prior Posts: true
               <button class='button execute-function block' data-method='enable_comments_prior' data-loader='true'>Enable Comments on Prior Posts</button>");

// Users Must Be Registered to Comment, red if false
$report .= ($users_must_be_registered === 'false' 
            ? "<span style='color:red;'>Users Must Be Registered to Comment: false</span>
               <button class='button execute-function block' data-method='enable_user_registration' data-loader='true'>Require User Registration to Comment</button>"
            : "Users Must Be Registered to Comment: true
               <button class='button execute-function block' data-method='disable_user_registration' data-loader='true'>Disable User Registration for Comments</button>");
        // Step 5: Log the report (without the HTML tags for logging purposes)
        write_log(strip_tags($report), false);

        // Step 6: Return an array with the report, variables, and the overall status
        return [
            'function' => 'perform_comments_system_check',
            'status' => $status,
            'raw_value' => $report, // The full report with line breaks and red text in 'raw_value',
            'variables' => [ // Add the variables to a secondary array
                'future_comments_status' => $future_comments_status,
                'prior_comments_status' => $prior_comments_status,
                'users_must_be_registered' => $users_must_be_registered,
                'email_notifications' => $email_notifications,
            ]
        ];
    }
} else write_log("Warning: perform_comments_system_check function is already declared", true);
*/

