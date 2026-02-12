<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Overview Dashboard Tab
 * 
 * Clean, comprehensive dashboard with:
 * - Status cards for quick overview
 * - User status check (hexaprwire)
 * - Category/CPT verification with create buttons
 * - Press release stats with FIFU verification
 * - Cron job status and management
 * - RSS feed URLs
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the publication slug from site URL
 */
function get_publication_slug() {
    $site_url = get_site_url();
    $parsed = parse_url( $site_url );
    $host = isset( $parsed['host'] ) ? $parsed['host'] : '';
    
    $host = preg_replace( '/^www\./', '', $host );
    $parts = explode( '.', $host );
    if ( count( $parts ) > 1 ) {
        array_pop( $parts );
    }
    $slug = implode( '-', $parts );
    
    return sanitize_title( $slug );
}

/**
 * Get the Hexa PR Wire RSS feed URL
 */
function get_hexa_rss_url() {
    $publication = get_publication_slug();
    return 'https://hexaprwire.com/?feed=rss_publication&publication=' . urlencode( $publication ) . '&v=' . time();
}

/**
 * Check if hexaprwire user exists
 */
function check_hexaprwire_user() {
    $user = get_user_by( 'slug', 'hexaprwire' );
    return [
        'exists' => $user !== false,
        'user'   => $user,
    ];
}

/**
 * Check if press-release category exists
 */
function check_press_release_category() {
    $category = get_term_by( 'slug', 'press-release', 'category' );
    return [
        'exists'   => $category !== false,
        'category' => $category,
    ];
}

/**
 * Check if press-release post type exists
 */
function check_press_release_cpt() {
    return post_type_exists( 'press-release' );
}

/**
 * Get press release statistics
 */
function get_press_release_stats() {
    if ( ! post_type_exists( 'press-release' ) ) {
        return null;
    }
    
    $counts = wp_count_posts( 'press-release' );
    $total = isset( $counts->publish ) ? $counts->publish : 0;
    
    // Get recent posts with FIFU check
    $recent_posts = get_posts([
        'post_type'      => 'press-release',
        'posts_per_page' => 10,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    
    $fifu_stats = [
        'total'       => count( $recent_posts ),
        'using_fifu'  => 0,
        'local_media' => 0,
        'no_image'    => 0,
        'posts'       => [],
    ];
    
    foreach ( $recent_posts as $post ) {
        $thumbnail_id = get_post_thumbnail_id( $post->ID );
        $fifu_url = get_post_meta( $post->ID, 'fifu_image_url', true );
        
        $post_data = [
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'date'       => get_the_date( 'F j, Y', $post->ID ),
            'edit_url'   => get_edit_post_link( $post->ID ),
            'view_url'   => get_permalink( $post->ID ),
            'image_type' => 'none',
            'image_url'  => '',
        ];
        
        if ( $fifu_url ) {
            $fifu_stats['using_fifu']++;
            $post_data['image_type'] = 'fifu';
            $post_data['image_url'] = $fifu_url;
        } elseif ( $thumbnail_id ) {
            $fifu_stats['local_media']++;
            $post_data['image_type'] = 'local';
            $post_data['image_url'] = wp_get_attachment_url( $thumbnail_id );
        } else {
            $fifu_stats['no_image']++;
        }
        
        $fifu_stats['posts'][] = $post_data;
    }
    
    return [
        'total'      => $total,
        'draft'      => isset( $counts->draft ) ? $counts->draft : 0,
        'fifu_stats' => $fifu_stats,
    ];
}

/**
 * Check cron job status
 */
function get_cron_status() {
    $crons = [
        'hexaprwire_daily_purge_check' => [
            'name'        => 'Daily Purge Check',
            'description' => 'Checks Hexa PR Wire for posts to delete',
        ],
        'hexaprwire_process_deletes' => [
            'name'        => 'Process Deletes',
            'description' => 'Processes the purge list',
        ],
    ];
    
    $status = [];
    foreach ( $crons as $hook => $info ) {
        $next = wp_next_scheduled( $hook );
        $status[ $hook ] = [
            'name'        => $info['name'],
            'description' => $info['description'],
            'scheduled'   => $next !== false,
            'next_run'    => $next ? date( 'Y-m-d H:i:s', $next ) : null,
        ];
    }
    
    return $status;
}

/**
 * Display the Overview tab content
 */
function display_settings_overview() {
    $site_url = get_site_url();
    $publication = get_publication_slug();
    $local_rss_url = $site_url . '/feed/internal-rss';
    $hexa_rss_url = get_hexa_rss_url();
    
    // Get all status checks
    $user_check = check_hexaprwire_user();
    $category_check = check_press_release_category();
    $cpt_exists = check_press_release_cpt();
    $pr_stats = get_press_release_stats();
    $cron_status = get_cron_status();
    $auto_delete_enabled = get_option( 'enable_hpr_auto_deletes', false );
    $rss_cache_disabled = get_option( 'disable_rss_caching', false );
    
    // Count issues
    $issues = 0;
    if ( ! $user_check['exists'] ) $issues++;
    if ( ! $category_check['exists'] ) $issues++;
    if ( ! $cpt_exists ) $issues++;
    if ( ! $auto_delete_enabled ) $issues++;
    if ( $pr_stats && $pr_stats['fifu_stats']['local_media'] > 0 ) $issues++;
    
    ?>
    
    <!-- Quick Status Cards -->
    <div class="hpr-status-grid">
        <?php
        // User Status
        $user_status = $user_check['exists'] ? 'good' : 'bad';
        render_status_card( $user_check['exists'] ? '✓' : '✗', 'User: hexaprwire', $user_status );
        
        // Category Status
        $cat_status = $category_check['exists'] ? 'good' : 'bad';
        render_status_card( $category_check['exists'] ? '✓' : '✗', 'Category', $cat_status );
        
        // CPT Status
        $cpt_status = $cpt_exists ? 'good' : 'bad';
        render_status_card( $cpt_exists ? '✓' : '✗', 'Post Type', $cpt_status );
        
        // Auto Delete
        $auto_status = $auto_delete_enabled ? 'good' : 'warn';
        render_status_card( $auto_delete_enabled ? '✓' : '⚠', 'Auto Delete', $auto_status );
        
        // RSS Cache
        $cache_status = $rss_cache_disabled ? 'good' : 'warn';
        render_status_card( $rss_cache_disabled ? '✓' : '⚠', 'RSS Cache Off', $cache_status );
        
        // Post Count
        if ( $pr_stats ) {
            render_status_card( $pr_stats['total'], 'Press Releases', 'good' );
        }
        ?>
    </div>
    
    <?php if ( $issues > 0 ) : ?>
    <div class="hpr-info-box warning">
        <strong>⚠ <?php echo $issues; ?> issue(s) detected.</strong> Review the sections below for details.
    </div>
    <?php endif; ?>
    
    <!-- User Check -->
    <div class="hpr-panel">
        <div class="hpr-panel-header">👤 Hexa PR Wire User</div>
        <div class="hpr-panel-body">
            <?php if ( $user_check['exists'] ) : ?>
                <p class="status-ok">✓ User <code>hexaprwire</code> exists</p>
                <p>
                    <strong>User ID:</strong> <?php echo $user_check['user']->ID; ?><br>
                    <strong>Display Name:</strong> <?php echo esc_html( $user_check['user']->display_name ); ?><br>
                    <strong>Email:</strong> <?php echo esc_html( $user_check['user']->user_email ); ?>
                </p>
                <a href="<?php echo admin_url( 'user-edit.php?user_id=' . $user_check['user']->ID ); ?>" class="hpr-btn hpr-btn-secondary">Edit User</a>
            <?php else : ?>
                <p class="status-bad">✗ User <code>hexaprwire</code> does not exist</p>
                <p>This user is required to properly attribute imported press releases.</p>
                <button type="button" class="hpr-btn hpr-btn-primary" id="hpr-create-user">Create hexaprwire User</button>
                <span id="hpr-create-user-status" style="margin-left: 10px;"></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Category Check -->
    <div class="hpr-panel">
        <div class="hpr-panel-header">📁 Press Release Category</div>
        <div class="hpr-panel-body">
            <?php if ( $category_check['exists'] ) : ?>
                <p class="status-ok">✓ Category <code>press-release</code> exists</p>
                <p>
                    <strong>Name:</strong> <?php echo esc_html( $category_check['category']->name ); ?><br>
                    <strong>Slug:</strong> <?php echo esc_html( $category_check['category']->slug ); ?><br>
                    <strong>Post Count:</strong> <?php echo $category_check['category']->count; ?>
                </p>
                <a href="<?php echo admin_url( 'term.php?taxonomy=category&tag_ID=' . $category_check['category']->term_id ); ?>" class="hpr-btn hpr-btn-secondary">Edit Category</a>
            <?php else : ?>
                <p class="status-bad">✗ Category <code>press-release</code> does not exist</p>
                <p>This category is required for organizing press releases.</p>
                <button type="button" class="hpr-btn hpr-btn-primary" id="hpr-create-category">Create "Press Release" Category</button>
                <span id="hpr-create-category-status" style="margin-left: 10px;"></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- CPT Check -->
    <div class="hpr-panel">
        <div class="hpr-panel-header">📝 Press Release Post Type</div>
        <div class="hpr-panel-body">
            <?php if ( $cpt_exists ) : ?>
                <p class="status-ok">✓ Post type <code>press-release</code> is registered</p>
                
                <?php if ( $pr_stats ) : ?>
                <div class="hpr-status-grid" style="margin: 15px 0;">
                    <div class="hpr-status-card good">
                        <div class="value"><?php echo $pr_stats['total']; ?></div>
                        <div class="label">Published</div>
                    </div>
                    <div class="hpr-status-card">
                        <div class="value"><?php echo $pr_stats['draft']; ?></div>
                        <div class="label">Drafts</div>
                    </div>
                    <div class="hpr-status-card <?php echo $pr_stats['fifu_stats']['using_fifu'] > 0 ? 'good' : ''; ?>">
                        <div class="value"><?php echo $pr_stats['fifu_stats']['using_fifu']; ?></div>
                        <div class="label">Using FIFU</div>
                    </div>
                    <div class="hpr-status-card <?php echo $pr_stats['fifu_stats']['local_media'] > 0 ? 'bad' : 'good'; ?>">
                        <div class="value"><?php echo $pr_stats['fifu_stats']['local_media']; ?></div>
                        <div class="label">Local Media</div>
                    </div>
                </div>
                
                <?php if ( $pr_stats['fifu_stats']['local_media'] > 0 ) : ?>
                <div class="hpr-info-box warning">
                    <strong>⚠ Warning:</strong> <?php echo $pr_stats['fifu_stats']['local_media']; ?> post(s) have locally hosted images instead of FIFU. This uses unnecessary disk space.
                </div>
                <?php endif; ?>
                
                <!-- Recent Posts Table -->
                <h4 style="margin-top: 20px;">Recent Press Releases</h4>
                <table class="hpr-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Title</th>
                            <th>Date</th>
                            <th>Image</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pr_stats['fifu_stats']['posts'] as $post ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( wp_trim_words( $post['title'], 10 ) ); ?></strong>
                            </td>
                            <td><?php echo esc_html( $post['date'] ); ?></td>
                            <td>
                                <?php if ( $post['image_type'] === 'fifu' ) : ?>
                                    <span class="status-ok" title="<?php echo esc_attr( $post['image_url'] ); ?>">✓ FIFU</span>
                                <?php elseif ( $post['image_type'] === 'local' ) : ?>
                                    <span class="status-bad" title="<?php echo esc_attr( $post['image_url'] ); ?>">⚠ Local</span>
                                <?php else : ?>
                                    <span class="status-warn">No image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $post['edit_url'] ); ?>" target="_blank">Edit</a> |
                                <a href="<?php echo esc_url( $post['view_url'] ); ?>" target="_blank">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <p style="margin-top: 15px;">
                    <a href="<?php echo admin_url( 'edit.php?post_type=press-release' ); ?>" class="hpr-btn hpr-btn-secondary">View All Press Releases</a>
                    <a href="<?php echo admin_url( 'post-new.php?post_type=press-release' ); ?>" class="hpr-btn hpr-btn-secondary">Add New</a>
                </p>
                
            <?php else : ?>
                <p class="status-bad">✗ Post type <code>press-release</code> is not registered</p>
                <p>Enable the "Enable Press Release Post Type" snippet in the Snippets tab.</p>
                <a href="#" onclick="jQuery('.hpr-tab-btn[data-tab=snippets]').click(); return false;" class="hpr-btn hpr-btn-primary">Go to Snippets</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cron Status -->
    <div class="hpr-panel">
        <div class="hpr-panel-header">⏰ Cron Jobs & Auto Delete</div>
        <div class="hpr-panel-body">
            
            <div class="hpr-info-box <?php echo $auto_delete_enabled ? 'success' : 'warning'; ?>">
                <strong>Auto Delete:</strong>
                <?php if ( $auto_delete_enabled ) : ?>
                    <span class="status-ok">✓ Enabled</span>
                <?php else : ?>
                    <span class="status-warn">⚠ Disabled</span> - 
                    <a href="#" onclick="jQuery('.hpr-tab-btn[data-tab=snippets]').click(); return false;">Enable in Snippets</a>
                <?php endif; ?>
            </div>
            
            <table class="hpr-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Cron Job</th>
                        <th>Status</th>
                        <th>Next Run</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $cron_status as $hook => $cron ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $cron['name'] ); ?></strong><br>
                            <small><?php echo esc_html( $cron['description'] ); ?></small>
                        </td>
                        <td>
                            <?php if ( $cron['scheduled'] ) : ?>
                                <span class="status-ok">✓ Scheduled</span>
                            <?php else : ?>
                                <span class="status-bad">✗ Not Scheduled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $cron['next_run'] ? esc_html( $cron['next_run'] ) : '—'; ?>
                        </td>
                        <td>
                            <button type="button" class="hpr-btn hpr-btn-secondary hpr-schedule-cron" data-hook="<?php echo esc_attr( $hook ); ?>">
                                <?php echo $cron['scheduled'] ? 'Reschedule' : 'Schedule'; ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h4 style="margin-top: 20px;">Quick Actions</h4>
            <div class="hpr-quick-links">
                <a href="https://hexaprwire.com/wp-admin/admin-ajax.php?action=purge_release_list" target="_blank">📋 View Purge List</a>
                <a href="<?php echo admin_url( 'admin-ajax.php?action=view_crons' ); ?>" target="_blank">⏰ View All Crons</a>
                <button type="button" class="hpr-btn hpr-btn-secondary" id="hpr-run-purge-now">🗑️ Run Purge Now</button>
            </div>
            <span id="hpr-purge-status" style="margin-left: 10px;"></span>
        </div>
    </div>
    
    <!-- RSS Feeds -->
    <div class="hpr-panel">
        <div class="hpr-panel-header">📡 RSS Feeds</div>
        <div class="hpr-panel-body">
            
            <div class="hpr-info-box <?php echo $rss_cache_disabled ? 'success' : 'warning'; ?>">
                <strong>RSS Caching:</strong>
                <?php if ( $rss_cache_disabled ) : ?>
                    <span class="status-ok">✓ Disabled</span> - Feeds always return fresh data
                <?php else : ?>
                    <span class="status-warn">⚠ May be cached</span> - 
                    <a href="#" onclick="jQuery('.hpr-tab-btn[data-tab=snippets]').click(); return false;">Disable in Snippets</a>
                <?php endif; ?>
            </div>
            
            <h4 style="margin-top: 20px;">Local RSS Feed</h4>
            <p>
                <a href="<?php echo esc_url( $local_rss_url ); ?>" target="_blank" style="word-break: break-all;">
                    <?php echo esc_url( $local_rss_url ); ?>
                </a>
            </p>
            
            <h4 style="margin-top: 20px;">Hexa PR Wire Feed</h4>
            <p><strong>Publication:</strong> <code><?php echo esc_html( $publication ); ?></code></p>
            <p>
                <a href="<?php echo esc_url( $hexa_rss_url ); ?>" target="_blank" style="word-break: break-all;">
                    <?php echo esc_html( $hexa_rss_url ); ?>
                </a>
            </p>
            
        </div>
    </div>
    
    <?php
    // SEO Settings section
    if ( function_exists( __NAMESPACE__ . '\\display_seo_settings' ) ) {
        display_seo_settings();
    }
    ?>
    
    <script>
    jQuery(document).ready(function($) {
        
        // Create User
        $('#hpr-create-user').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-create-user-status');
            
            $btn.prop('disabled', true);
            $status.text('Creating...').css('color', '#666');
            
            $.post(ajaxurl, {
                action: 'hpr_create_user',
                nonce: hprNonce
            }, function(response) {
                if (response.success) {
                    $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $status.text('✗ ' + response.data).css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Create Category
        $('#hpr-create-category').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-create-category-status');
            
            $btn.prop('disabled', true);
            $status.text('Creating...').css('color', '#666');
            
            $.post(ajaxurl, {
                action: 'hpr_create_category',
                nonce: hprNonce
            }, function(response) {
                if (response.success) {
                    $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $status.text('✗ ' + response.data).css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Schedule Cron
        $('.hpr-schedule-cron').on('click', function() {
            var $btn = $(this);
            var hook = $btn.data('hook');
            
            $btn.prop('disabled', true).text('Scheduling...');
            
            $.post(ajaxurl, {
                action: 'hpr_schedule_cron',
                hook: hook,
                nonce: hprNonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('Schedule');
                }
            });
        });
        
        // Run Purge Now
        $('#hpr-run-purge-now').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-purge-status');
            
            $btn.prop('disabled', true);
            $status.text('Running purge check...').css('color', '#666');
            
            $.post(ajaxurl, {
                action: 'hpr_run_purge_now',
                nonce: hprNonce
            }, function(response) {
                if (response.success) {
                    $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                } else {
                    $status.text('✗ ' + response.data).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
            });
        });
        
    });
    </script>
    <?php
}
