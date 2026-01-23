<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Snippets Dashboard Tab
 * 
 * Displays all available snippets with toggle switches for enabling/disabling.
 * Uses abstract toggle switch components.
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Display the Snippets tab content
 */
function display_settings_snippets() {
    // Get all snippets
    $snippets = get_settings_snippets();
    
    // Group snippets by category
    $categories = [
        'core'        => [ 'label' => '🔧 Core', 'snippets' => [] ],
        'acf'         => [ 'label' => '📋 ACF Fields', 'snippets' => [] ],
        'display'     => [ 'label' => '👁️ Display', 'snippets' => [] ],
        'automation'  => [ 'label' => '🤖 Automation', 'snippets' => [] ],
        'performance' => [ 'label' => '⚡ Performance', 'snippets' => [] ],
    ];
    
    // Sort snippets into categories
    foreach ( $snippets as $snippet ) {
        $cat = isset( $snippet['category'] ) ? $snippet['category'] : 'core';
        if ( isset( $categories[ $cat ] ) ) {
            $categories[ $cat ]['snippets'][] = $snippet;
        } else {
            $categories['core']['snippets'][] = $snippet;
        }
    }
    
    ?>
    <div class="hpr-panel">
        <div class="hpr-panel-header">Snippets</div>
        <div class="hpr-panel-body">
            <p>Enable or disable plugin features using the toggle switches below. Changes take effect immediately.</p>
            
            <?php foreach ( $categories as $cat_id => $category ) : ?>
                <?php if ( ! empty( $category['snippets'] ) ) : ?>
                    <h3 style="margin-top: 25px; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee;">
                        <?php echo esc_html( $category['label'] ); ?>
                    </h3>
                    
                    <?php foreach ( $category['snippets'] as $snippet ) : ?>
                        <?php
                        $is_enabled = get_option( $snippet['id'], false );
                        $toggle_html = render_toggle_switch(
                            'toggle-' . $snippet['id'],
                            '',
                            $is_enabled,
                            'hprToggleSnippet(\'' . esc_js( $snippet['id'] ) . '\')'
                        );
                        ?>
                        <div class="hpr-snippet-item" data-snippet-id="<?php echo esc_attr( $snippet['id'] ); ?>">
                            <div class="hpr-snippet-toggle">
                                <?php echo $toggle_html; ?>
                            </div>
                            <div class="hpr-snippet-content">
                                <div class="hpr-snippet-header">
                                    <code class="hpr-snippet-id"><?php echo esc_html( $snippet['id'] ); ?></code>
                                    <span class="hpr-snippet-name"><?php echo esc_html( $snippet['name'] ); ?></span>
                                    <span class="hpr-snippet-category"><?php echo esc_html( $cat_id ); ?></span>
                                </div>
                                <?php if ( ! empty( $snippet['description'] ) ) : ?>
                                    <div class="hpr-snippet-description"><?php echo esc_html( $snippet['description'] ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            
        </div>
    </div>
    
    <script>
    function hprToggleSnippet(snippetId) {
        var isChecked = jQuery('#toggle-' + snippetId).prop('checked');
        var $item = jQuery('[data-snippet-id="' + snippetId + '"]');
        
        $item.css('opacity', '0.5');
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'hpr_distributor_toggle_snippet',
                snippet_id: snippetId,
                enable: isChecked ? 1 : 0,
                nonce: hprNonce
            },
            success: function(response) {
                $item.css('opacity', '1');
                if (response.success) {
                    // Visual feedback
                    $item.css('border-left', isChecked ? '3px solid #00a32a' : '');
                    setTimeout(function() {
                        $item.css('border-left', '');
                    }, 1000);
                } else {
                    alert('Error: ' + response.data);
                    // Revert toggle
                    jQuery('#toggle-' + snippetId).prop('checked', !isChecked);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $item.css('opacity', '1');
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('AJAX error occurred');
                jQuery('#toggle-' + snippetId).prop('checked', !isChecked);
            }
        });
    }
    </script>
    <?php
}
