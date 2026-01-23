<?php
namespace hpr_distributor;

/**
 * Hexa PR Wire - Reusable Dashboard Components
 * 
 * Contains abstract UI components used throughout the plugin:
 * - Toggle switches
 * - Panel containers
 * - Status cards
 * - Styled tables
 * 
 * @since 2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render a toggle switch
 * 
 * @param string $id         Unique identifier
 * @param string $label      Label text (optional)
 * @param bool   $checked    Whether toggle is on
 * @param string $onclick    JavaScript onclick handler
 * @param string $extra_class Additional CSS class
 * @return string HTML
 */
function render_toggle_switch( $id, $label = '', $checked = false, $onclick = '', $extra_class = '' ) {
    $checked_attr = $checked ? 'checked' : '';
    $onclick_attr = $onclick ? ' onclick="' . esc_attr( $onclick ) . '"' : '';
    
    $html = '<label class="hpr-toggle-switch ' . esc_attr( $extra_class ) . '">';
    $html .= '<input type="checkbox" id="' . esc_attr( $id ) . '" ' . $checked_attr . $onclick_attr . '>';
    $html .= '<span class="hpr-toggle-slider"></span>';
    if ( $label ) {
        $html .= '<span class="hpr-toggle-label">' . esc_html( $label ) . '</span>';
    }
    $html .= '</label>';
    
    return $html;
}

/**
 * Output all dashboard styles
 */
function output_dashboard_styles() {
    ?>
    <style>
        /* === HPR Dashboard Global Styles === */
        #hpr-dashboard { max-width: 1400px; }
        #hpr-dashboard * { box-sizing: border-box; }
        
        /* === Tabs === */
        .hpr-tabs-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            border-bottom: 2px solid #c3c4c7;
            margin-bottom: 0;
            background: #f0f0f1;
            padding: 10px 10px 0;
        }
        .hpr-tab-btn {
            padding: 12px 20px;
            text-decoration: none;
            color: #50575e;
            font-weight: 500;
            font-size: 14px;
            border: 1px solid transparent;
            border-bottom: none;
            background: transparent;
            margin-bottom: -2px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hpr-tab-btn:hover { color: #2271b1; background: #fff; }
        .hpr-tab-btn.active {
            color: #1d2327;
            background: #fff;
            border-color: #c3c4c7;
            border-bottom-color: #fff;
            border-radius: 4px 4px 0 0;
        }
        .hpr-tab-content {
            display: none;
            background: #fff;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 20px;
        }
        .hpr-tab-content.active { display: block; }
        
        /* === Toggle Switch === */
        .hpr-toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        .hpr-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        .hpr-toggle-slider {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            background-color: #ccc;
            border-radius: 24px;
            transition: background-color 0.3s ease;
        }
        .hpr-toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            top: 3px;
            background-color: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .hpr-toggle-switch input:checked + .hpr-toggle-slider {
            background-color: #00a32a;
        }
        .hpr-toggle-switch input:checked + .hpr-toggle-slider::before {
            transform: translateX(20px);
        }
        .hpr-toggle-switch input:focus + .hpr-toggle-slider {
            box-shadow: 0 0 0 2px rgba(0, 163, 42, 0.2);
        }
        .hpr-toggle-label {
            margin-left: 10px;
            font-size: 13px;
            color: #1d2327;
        }
        
        /* === Panels === */
        .hpr-panel {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            background: #fff;
        }
        .hpr-panel-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            background: #f9f9f9;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px 6px 0 0;
        }
        .hpr-panel-body { padding: 20px; }
        
        /* === Status Cards === */
        .hpr-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .hpr-status-card {
            background: #f6f7f7;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid #c3c4c7;
        }
        .hpr-status-card.good { border-left-color: #00a32a; }
        .hpr-status-card.bad { border-left-color: #d63638; }
        .hpr-status-card.warn { border-left-color: #dba617; }
        .hpr-status-card .value { font-size: 20px; font-weight: 600; color: #1d2327; }
        .hpr-status-card .label { font-size: 11px; color: #646970; text-transform: uppercase; margin-top: 4px; }
        
        /* === Snippet Items === */
        .hpr-snippet-item {
            display: flex;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 10px;
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .hpr-snippet-item:hover {
            border-color: #2271b1;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .hpr-snippet-toggle {
            flex-shrink: 0;
            margin-right: 15px;
            margin-top: 2px;
        }
        .hpr-snippet-content { flex: 1; min-width: 0; }
        .hpr-snippet-header {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 4px;
        }
        .hpr-snippet-id {
            background: #e0e0e0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-family: monospace;
            color: #555;
        }
        .hpr-snippet-name {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
        }
        .hpr-snippet-description {
            font-size: 13px;
            color: #646970;
            margin-top: 4px;
        }
        .hpr-snippet-category {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            background: #e0e7ff;
            color: #3b5998;
        }
        
        /* === Tables === */
        .hpr-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hpr-table th,
        .hpr-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .hpr-table th { background: #f9f9f9; font-weight: 600; }
        .hpr-table tr:hover { background: #f9f9f9; }
        
        /* === Status Indicators === */
        .status-ok { color: #00a32a; }
        .status-bad { color: #d63638; }
        .status-warn { color: #dba617; }
        
        /* === Quick Links === */
        .hpr-quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }
        .hpr-quick-links a {
            display: inline-block;
            padding: 8px 15px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            text-decoration: none;
            color: #2271b1;
            font-size: 13px;
            transition: all 0.2s;
        }
        .hpr-quick-links a:hover {
            background: #2271b1;
            color: #fff;
            border-color: #2271b1;
        }
        
        /* === Buttons === */
        .hpr-btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hpr-btn-primary {
            background: #2271b1;
            color: #fff;
        }
        .hpr-btn-primary:hover { background: #135e96; color: #fff; }
        .hpr-btn-secondary {
            background: #f0f0f1;
            color: #2271b1;
            border: 1px solid #c3c4c7;
        }
        .hpr-btn-secondary:hover { background: #e0e0e0; }
        .hpr-btn-danger {
            background: #d63638;
            color: #fff;
        }
        .hpr-btn-danger:hover { background: #b32d2e; }
        
        /* === Cron/Auto Delete Section === */
        .hpr-info-box {
            background: #f0f6fc;
            border: 1px solid #c3c4c7;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 4px 4px 0;
        }
        .hpr-info-box.warning {
            background: #fcf9e8;
            border-left-color: #dba617;
        }
        .hpr-info-box.success {
            background: #edfaef;
            border-left-color: #00a32a;
        }
        .hpr-info-box.error {
            background: #fcf0f1;
            border-left-color: #d63638;
        }
    </style>
    <?php
}

/**
 * Render a panel
 * 
 * @param string $title   Panel title
 * @param string $content Panel body content
 * @param string $id      Optional ID
 */
function render_panel( $title, $content, $id = '' ) {
    $id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';
    ?>
    <div class="hpr-panel"<?php echo $id_attr; ?>>
        <div class="hpr-panel-header"><?php echo esc_html( $title ); ?></div>
        <div class="hpr-panel-body"><?php echo $content; ?></div>
    </div>
    <?php
}

/**
 * Render a status card
 * 
 * @param string $value  Display value
 * @param string $label  Label text
 * @param string $status Status class (good, bad, warn)
 */
function render_status_card( $value, $label, $status = '' ) {
    $class = $status ? ' ' . esc_attr( $status ) : '';
    ?>
    <div class="hpr-status-card<?php echo $class; ?>">
        <div class="value"><?php echo esc_html( $value ); ?></div>
        <div class="label"><?php echo esc_html( $label ); ?></div>
    </div>
    <?php
}
