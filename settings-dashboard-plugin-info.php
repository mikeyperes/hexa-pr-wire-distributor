<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Plugin Info Dashboard Tab
 * 
 * Displays:
 * - Plugin version information
 * - GitHub version comparison
 * - Update controls
 * - Version history download
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the Plugin Info tab content
 */
function display_plugin_info() {
    $plugin_data = hpr_get_plugin_data();
    $current_version = $plugin_data['Version'];
    $github_version = hpr_get_github_version_cached();
    
    $update_available = version_compare( $github_version, $current_version, '>' );
    $version_class = $update_available ? 'status-bad' : 'status-ok';
    $github_class = $update_available ? 'status-ok' : '';
    
    ?>
    <div class="hpr-panel">
        <div class="hpr-panel-header">Plugin Information</div>
        <div class="hpr-panel-body">
            
            <table class="hpr-table" style="max-width: 600px;">
                <tr>
                    <td style="width: 180px;"><strong>Plugin Name:</strong></td>
                    <td><?php echo esc_html( $plugin_data['Name'] ); ?></td>
                </tr>
                <tr>
                    <td><strong>Installed Version:</strong></td>
                    <td>
                        <span class="<?php echo $version_class; ?>" style="font-weight: bold;">
                            <?php echo esc_html( $current_version ); ?>
                        </span>
                        <?php if ( $update_available ) : ?>
                            <span class="status-bad" style="margin-left: 10px;">⚠ Update Available</span>
                        <?php else : ?>
                            <span class="status-ok" style="margin-left: 10px;">✓ Up to date</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>GitHub Version:</strong></td>
                    <td>
                        <span class="<?php echo $github_class; ?>" style="font-weight: bold;">
                            <?php echo esc_html( $github_version ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Author:</strong></td>
                    <td><?php echo wp_kses_post( $plugin_data['Author'] ); ?></td>
                </tr>
                <tr>
                    <td><strong>GitHub Repository:</strong></td>
                    <td>
                        <a href="https://github.com/<?php echo esc_attr( Config::$github_repo ); ?>" target="_blank">
                            <?php echo esc_html( Config::$github_repo ); ?>
                        </a>
                    </td>
                </tr>
            </table>
            
            <h3 style="margin-top: 25px;">Actions</h3>
            
            <p style="margin: 15px 0;">
                <button type="button" id="hpr-force-update-check" class="hpr-btn hpr-btn-secondary">
                    🔄 Force Update Check
                </button>
                <span id="hpr-update-check-status" style="margin-left: 10px;"></span>
            </p>
            
            <p style="margin: 15px 0;">
                <button type="button" id="hpr-update-now" class="hpr-btn hpr-btn-primary" <?php echo $update_available ? '' : 'disabled'; ?>>
                    ⬆️ Update Now from GitHub
                </button>
                <span id="hpr-update-status" style="margin-left: 10px;"></span>
            </p>
            
            <p style="margin: 15px 0;">
                <button type="button" id="hpr-download-current" class="hpr-btn hpr-btn-secondary">
                    📥 Download Current Version as ZIP
                </button>
                <span id="hpr-download-status" style="margin-left: 10px;"></span>
            </p>
            
            <h3 style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">Version History</h3>
            
            <p style="margin: 15px 0;">
                <select id="hpr-version-select" style="min-width: 200px; padding: 5px;">
                    <option value="">-- Select Version --</option>
                </select>
                <button type="button" id="hpr-load-versions" class="hpr-btn hpr-btn-secondary">
                    Load Versions
                </button>
                <button type="button" id="hpr-download-version" class="hpr-btn hpr-btn-secondary" disabled>
                    Download Selected
                </button>
                <span id="hpr-version-status" style="margin-left: 10px;"></span>
            </p>
            
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        
        // Force update check
        $('#hpr-force-update-check').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-update-check-status');
            
            $btn.prop('disabled', true);
            $status.text('Checking...').css('color', '#666');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hpr_force_update_check', nonce: hprNonce },
                timeout: 60000,
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                        if (response.data.update_available) {
                            $('#hpr-update-now').prop('disabled', false);
                            $status.append(' - v' + response.data.github_version + ' available');
                        }
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $status.text('✗ ' + response.data).css('color', '#d63638');
                    }
                },
                error: function() {
                    $status.text('✗ Request failed').css('color', '#d63638');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Update now
        $('#hpr-update-now').on('click', function() {
            if (!confirm('Update plugin from GitHub? Current version will be backed up.')) return;
            
            var $btn = $(this);
            var $status = $('#hpr-update-status');
            
            $btn.prop('disabled', true);
            $status.text('Updating...').css('color', '#666');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hpr_direct_update_plugin', nonce: hprNonce },
                timeout: 120000,
                success: function(response) {
                    if (response.success) {
                        $status.text('✓ ' + response.data.message).css('color', '#00a32a');
                        if (response.data.reload) {
                            setTimeout(function() { location.reload(); }, 2000);
                        }
                    } else {
                        $status.text('✗ ' + response.data).css('color', '#d63638');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('✗ Request failed').css('color', '#d63638');
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Download current version
        $('#hpr-download-current').on('click', function() {
            var $btn = $(this);
            var $status = $('#hpr-download-status');
            
            $btn.prop('disabled', true);
            $status.text('Creating ZIP...').css('color', '#666');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hpr_download_plugin_zip', nonce: hprNonce },
                timeout: 60000,
                success: function(response) {
                    if (response.success) {
                        $status.html('✓ <a href="' + response.data.download_url + '">' + response.data.filename + '</a>').css('color', '#00a32a');
                        window.location.href = response.data.download_url;
                    } else {
                        $status.text('✗ ' + response.data).css('color', '#d63638');
                    }
                },
                error: function() {
                    $status.text('✗ Request failed').css('color', '#d63638');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Load versions
        $('#hpr-load-versions').on('click', function() {
            var $btn = $(this);
            var $select = $('#hpr-version-select');
            var $status = $('#hpr-version-status');
            
            $btn.prop('disabled', true);
            $status.text('Loading...').css('color', '#666');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hpr_load_github_versions', nonce: hprNonce },
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        $select.empty().append('<option value="">-- Select Version --</option>');
                        $.each(response.data, function(i, v) {
                            $select.append('<option value="' + v.zipball_url + '">' + v.name + '</option>');
                        });
                        $status.text('✓ Loaded ' + response.data.length + ' versions').css('color', '#00a32a');
                    } else {
                        $status.text('✗ ' + response.data).css('color', '#d63638');
                    }
                },
                error: function() {
                    $status.text('✗ Request failed').css('color', '#d63638');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
        
        // Enable download button on selection
        $('#hpr-version-select').on('change', function() {
            $('#hpr-download-version').prop('disabled', !$(this).val());
        });
        
        // Download specific version
        $('#hpr-download-version').on('click', function() {
            var $btn = $(this);
            var $select = $('#hpr-version-select');
            var $status = $('#hpr-version-status');
            
            var zipUrl = $select.val();
            var version = $select.find('option:selected').text();
            
            if (!zipUrl) return;
            
            $btn.prop('disabled', true);
            $status.text('Downloading...').css('color', '#666');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hpr_download_specific_version',
                    version: version,
                    zip_url: zipUrl,
                    nonce: hprNonce
                },
                timeout: 120000,
                success: function(response) {
                    if (response.success) {
                        $status.html('✓ <a href="' + response.data.download_url + '">' + response.data.filename + '</a>').css('color', '#00a32a');
                        window.location.href = response.data.download_url;
                    } else {
                        $status.text('✗ ' + response.data).css('color', '#d63638');
                    }
                },
                error: function() {
                    $status.text('✗ Request failed').css('color', '#d63638');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Get GitHub version with caching
 */
function hpr_get_github_version_cached() {
    $cache_key = 'hpr_github_ver_' . md5( Config::$github_repo . Config::$github_branch );
    $cached = get_site_transient( $cache_key );
    
    if ( $cached !== false ) {
        return $cached;
    }
    
    $version = hpr_get_github_version_fresh();
    
    // Cache for 30 minutes
    set_site_transient( $cache_key, $version, 30 * MINUTE_IN_SECONDS );
    
    return $version;
}
