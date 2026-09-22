<?php

namespace hpr_distributor;

use Hexa\PluginCore\WpAdminComponents\CoreUi;

defined( 'ABSPATH' ) || exit;

function output_dashboard_styles(): void {
    if ( class_exists( CoreUi::class ) ) {
        CoreUi::render_assets();
    }
    ?>
    <style>
        #hpr-dashboard{max-width:1500px}#hpr-dashboard *{box-sizing:border-box}
        .hpr-page-head{align-items:flex-start;display:flex;gap:16px;justify-content:space-between;margin:0 0 18px}.hpr-page-head h2{margin:0 0 5px}.hpr-page-head p{color:#5f6d80;margin:0;max-width:760px}
        .hpr-metric-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));margin:0 0 16px}.hpr-metric{background:#fbfcfe;border:1px solid #d9e0ea;border-radius:8px;padding:14px}.hpr-metric strong{display:block;font-size:22px;line-height:1.1}.hpr-metric span{color:#65758b;display:block;font-size:11px;font-weight:700;margin-top:5px;text-transform:uppercase}
        .hpr-table-wrap{max-width:100%;overflow:auto}.hpr-table{border-collapse:collapse;width:100%}.hpr-table th,.hpr-table td{border-bottom:1px solid #e4e9f0;padding:10px;text-align:left;vertical-align:top}.hpr-table th{background:#f7f9fc;color:#314056;font-size:12px}.hpr-table code{white-space:normal;word-break:break-word}
        .hpr-form-grid{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr))}.hpr-form-grid .wide{grid-column:1/-1}.hpr-check{align-items:flex-start;display:flex;gap:8px;margin:8px 0}.hpr-check input{margin-top:2px}.hpr-check span{display:block}.hpr-check small{color:#65758b;display:block;margin-top:2px}
        .hpr-inline-actions{align-items:center;display:flex;flex-wrap:wrap;gap:9px}.hpr-result{background:#f8fafc;border:1px solid #d9e0ea;border-radius:7px;margin-top:12px;max-height:420px;overflow:auto;padding:12px;white-space:pre-wrap}.hpr-result:empty{display:none}.hpr-result.is-error{background:#fff0f2;border-color:#ffd0d8;color:#8a1728}.hpr-result.is-success{background:#edf9f1;border-color:#ccefd7;color:#126b34}
        .hpr-notice,.hpr-info-box{background:#f8fbff;border:1px solid #cfe0ff;border-left:4px solid #3157d5;border-radius:7px;margin:0 0 14px;padding:12px 14px}.hpr-notice.warning,.hpr-info-box.warning{background:#fff8e8;border-color:#f0d58a;border-left-color:#9a6700}.hpr-notice.danger,.hpr-info-box.error{background:#fff0f2;border-color:#ffd0d8;border-left-color:#b42336}.hpr-notice.success,.hpr-info-box.success{background:#edf9f1;border-color:#ccefd7;border-left-color:#16803c}
        .hpr-muted{color:#65758b}.hpr-url{overflow-wrap:anywhere}.hpr-section{margin:0 0 16px}.hpr-section h3{margin-top:0}.hpr-button-row{align-items:center;display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}.hpr-button-row .spinner{float:none;margin:0}
        .hpr-btn{background:#3157d5;border:1px solid #3157d5;border-radius:6px;color:#fff;cursor:pointer;display:inline-flex;font-weight:700;line-height:1;padding:9px 12px;text-decoration:none}.hpr-btn-secondary{background:#fff;color:#3157d5}.hpr-btn-danger{background:#b42336;border-color:#b42336}.status-ok{color:#16803c}.status-bad{color:#b42336}.status-warn{color:#9a6700}
        @media(max-width:900px){.hpr-form-grid{grid-template-columns:1fr}.hpr-form-grid .wide{grid-column:auto}.hpr-page-head{display:block}.hpr-page-head .hpc-pill{margin-top:10px}}
    </style>
    <?php
}

function hpr_status_pill( string $label, string $tone = '' ): string {
    if ( class_exists( CoreUi::class ) ) {
        return CoreUi::pill( $label, $tone );
    }
    return '<span class="hpc-pill ' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
}

function hpr_card( string $title, string $body_html, string $meta_html = '' ): string {
    if ( class_exists( CoreUi::class ) ) {
        return CoreUi::card( [ 'title' => $title, 'body_html' => $body_html, 'meta_html' => $meta_html ] );
    }
    return '<section class="hpc-card"><h3>' . esc_html( $title ) . '</h3>' . $body_html . $meta_html . '</section>';
}

function render_toggle_switch( $id, $label = '', $checked = false, $onclick = '', $extra_class = '' ): string {
    $html = '<label class="hpr-check ' . esc_attr( (string) $extra_class ) . '">';
    $html .= '<input type="checkbox" id="' . esc_attr( (string) $id ) . '"' . checked( (bool) $checked, true, false );
    if ( '' !== (string) $onclick ) {
        $html .= ' onclick="' . esc_attr( (string) $onclick ) . '"';
    }
    $html .= '><span>' . esc_html( (string) $label ) . '</span></label>';
    return $html;
}

function render_panel( $title, $content, $id = '' ): void {
    echo hpr_card( (string) $title, (string) $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function render_status_card( $value, $label, $status = '' ): void {
    echo '<div class="hpr-metric"><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( (string) $label ) . '</span></div>';
}
