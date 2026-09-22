<?php

namespace hpr_distributor;

use Hexa\PluginCore\WpAdminComponents\CoreUi;
use Hexa\PluginCore\WpAdminComponents\DynamicButton;
use Hexa\PluginCore\WpAdminComponents\DynamicNotice;

defined( 'ABSPATH' ) || exit;

function output_dashboard_styles(): void {
    if ( class_exists( CoreUi::class ) ) {
        CoreUi::render_assets();
    }
    ?>
    <style>
        #hpr-dashboard{max-width:1500px}#hpr-dashboard *{box-sizing:border-box}
        .hpr-page-head{align-items:flex-start;display:flex;gap:16px;justify-content:space-between;margin:0 0 18px}.hpr-page-head h2{margin:0 0 5px}.hpr-page-head p{color:#5f6d80;margin:0;max-width:760px}
        .hpr-stack{display:grid;gap:16px}.hpr-section{margin:0}.hpr-section>h3{margin-top:0}.hpr-section-intro{color:#5f6d80;margin:-2px 0 14px;max-width:820px}
        .hpr-data-list,.hpr-record-list,.hpr-settings-list,.hpr-step-list{border:1px solid #d9e0ea;border-radius:8px;overflow:hidden}
        .hpr-data-row,.hpr-record,.hpr-setting-row,.hpr-step{background:#fff;border-bottom:1px solid #e4e9f0;padding:13px 15px}.hpr-data-row:last-child,.hpr-record:last-child,.hpr-setting-row:last-child,.hpr-step:last-child{border-bottom:0}
        .hpr-data-row{align-items:flex-start;display:flex;gap:18px}.hpr-data-label{color:#314056;flex:0 0 220px;font-size:12px;font-weight:800;text-transform:uppercase}.hpr-data-value{min-width:0;overflow-wrap:anywhere}.hpr-data-value code{white-space:normal;word-break:break-word}.hpr-data-description{color:#65758b;display:block;font-size:12px;line-height:1.45;margin-top:4px}
        .hpr-record-head{align-items:flex-start;display:flex;gap:12px;justify-content:space-between}.hpr-record-title{font-size:14px;font-weight:800;line-height:1.4;margin:0}.hpr-record-summary{color:#3f4d63;line-height:1.55;margin:5px 0 0}.hpr-record-actions{align-items:center;display:flex;flex:0 0 auto;flex-wrap:wrap;gap:8px}.hpr-record-media{align-items:flex-start;display:flex;gap:14px;margin-top:11px}.hpr-record-thumb{background:#f1f4f8;border:1px solid #d9e0ea;border-radius:7px;height:72px;object-fit:cover;width:112px}.hpr-record-meta{color:#65758b;font-size:12px;line-height:1.55;min-width:0;overflow-wrap:anywhere}
        .hpr-form-grid{display:block}.hpr-form-grid .hpc-field{margin-bottom:14px}.hpr-check{align-items:flex-start;display:flex;gap:9px;margin:0}.hpr-check input{margin-top:2px}.hpr-check span{display:block}.hpr-check small{color:#65758b;display:block;line-height:1.45;margin-top:3px}.hpr-setting-row{padding:14px 15px}
        .hpr-step{align-items:flex-start;display:flex;gap:13px}.hpr-step-number{align-items:center;background:#eef2ff;border:1px solid #dbe4ff;border-radius:999px;color:#2944ad;display:inline-flex;flex:0 0 28px;font-size:12px;font-weight:900;height:28px;justify-content:center}.hpr-step-main{min-width:0;width:100%}.hpr-step-main h4{font-size:14px;margin:3px 0 5px}.hpr-step-main>p{color:#5f6d80;margin:0 0 12px}
        .hpr-inline-actions{align-items:center;display:flex;flex-wrap:wrap;gap:9px}.hpr-result{background:#f8fafc;border:1px solid #d9e0ea;border-radius:7px;margin-top:12px;max-height:420px;overflow:auto;padding:12px;white-space:pre-wrap}.hpr-result:empty{display:none}.hpr-result.is-error{background:#fff0f2;border-color:#ffd0d8;color:#8a1728}.hpr-result.is-success{background:#edf9f1;border-color:#ccefd7;color:#126b34}
        .hpr-notice,.hpr-info-box{background:#f8fbff;border:1px solid #cfe0ff;border-left:4px solid #3157d5;border-radius:7px;margin:0 0 14px;padding:12px 14px}.hpr-notice.warning,.hpr-info-box.warning{background:#fff8e8;border-color:#f0d58a;border-left-color:#9a6700}.hpr-notice.danger,.hpr-info-box.error{background:#fff0f2;border-color:#ffd0d8;border-left-color:#b42336}.hpr-notice.success,.hpr-info-box.success{background:#edf9f1;border-color:#ccefd7;border-left-color:#16803c}
        .hpr-muted{color:#65758b}.hpr-url{overflow-wrap:anywhere}.hpr-button-row{align-items:center;display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}.hpr-button-row .spinner{float:none;margin:0}.hpr-secondary{border-top:1px solid #edf1f6;margin-top:12px;padding-top:8px}.hpr-secondary summary{color:#65758b;cursor:pointer;font-size:12px;font-weight:700}.hpr-secondary-body{padding-top:8px}.hpr-top-notice{margin-bottom:18px}.hpr-empty{color:#65758b;margin:0;padding:14px 15px}
        .hpr-btn{background:#3157d5;border:1px solid #3157d5;border-radius:6px;color:#fff;cursor:pointer;display:inline-flex;font-weight:700;line-height:1;padding:9px 12px;text-decoration:none}.hpr-btn-secondary{background:#fff;color:#3157d5}.hpr-btn-danger{background:#b42336;border-color:#b42336}.status-ok{color:#16803c}.status-bad{color:#b42336}.status-warn{color:#9a6700}
        #hpr-dashboard .hpc-grid,#hpr-dashboard .hpc-grid.two,#hpr-dashboard .hpc-content-type-grid,#hpr-dashboard .hpc-content-type-fields,#hpr-dashboard .hpc-content-type-facts,#hpr-dashboard .hexa-core-version,#hpr-dashboard .hexa-core-kv,#hpr-dashboard .hpc-gsc-inputs,#hpr-dashboard .hpc-gsc-preview-grid{grid-template-columns:1fr!important}
        #hpr-dashboard .hpc-gsc-row{grid-template-columns:34px minmax(0,1fr)!important}#hpr-dashboard .hpc-gsc-row-action{grid-column:2;justify-content:flex-start!important}
        #hpr-dashboard .hexa-log-row,#hpr-dashboard .hexa-core-log-row,#hpr-dashboard .hpc-gsc-log-row{display:block!important}#hpr-dashboard .hexa-log-row>*+*,#hpr-dashboard .hexa-core-log-row>*+*,#hpr-dashboard .hpc-gsc-log-row>*+*{margin-top:5px}
        @media(max-width:900px){.hpr-page-head{display:block}.hpr-page-head .hpc-pill{margin-top:10px}.hpr-data-row{display:block}.hpr-data-label{display:block;margin-bottom:5px}.hpr-record-head{display:block}.hpr-record-actions{margin-top:10px}.hpr-record-media{display:block}.hpr-record-thumb{margin-bottom:8px}}
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

function hpr_dynamic_notice( string $id, array $args = [] ): string {
    if ( class_exists( DynamicNotice::class ) ) {
        return DynamicNotice::render( array_merge( [ 'id' => $id, 'hidden' => true ], $args ) );
    }
    $hidden = ! array_key_exists( 'hidden', $args ) || ! empty( $args['hidden'] );
    return '<div id="' . esc_attr( $id ) . '" class="hpr-notice hpr-top-notice" role="status" aria-live="polite"' . ( $hidden ? ' hidden' : '' ) . '><strong class="hpc-dynamic-notice-title">' . esc_html( (string) ( $args['title'] ?? '' ) ) . '</strong><span class="hpc-dynamic-notice-message">' . esc_html( (string) ( $args['message'] ?? '' ) ) . '</span></div>';
}

function hpr_data_row( string $label, string $value_html, string $description = '' ): string {
    return '<div class="hpr-data-row"><div class="hpr-data-label">' . esc_html( $label ) . '</div><div class="hpr-data-value">' . $value_html
        . ( '' !== $description ? '<span class="hpr-data-description">' . esc_html( $description ) . '</span>' : '' )
        . '</div></div>';
}

function hpr_record_row( string $title, string $summary_html, string $actions_html = '', string $secondary_html = '' ): string {
    return '<article class="hpr-record"><div class="hpr-record-head"><div><h4 class="hpr-record-title">' . esc_html( $title ) . '</h4><div class="hpr-record-summary">' . $summary_html . '</div></div>'
        . ( '' !== $actions_html ? '<div class="hpr-record-actions">' . $actions_html . '</div>' : '' )
        . '</div>'
        . ( '' !== $secondary_html ? '<details class="hpr-secondary"><summary>More details</summary><div class="hpr-secondary-body">' . $secondary_html . '</div></details>' : '' )
        . '</article>';
}

function hpr_step_row( int $number, string $title, string $description, string $body_html ): string {
    return '<section class="hpr-step"><span class="hpr-step-number" aria-hidden="true">' . (int) $number . '</span><div class="hpr-step-main"><h4>' . esc_html( $title ) . '</h4><p>' . esc_html( $description ) . '</p>' . $body_html . '</div></section>';
}

function hpr_secondary_result( string $id, string $label = 'Technical response' ): string {
    return '<details class="hpr-secondary"><summary>' . esc_html( $label ) . '</summary><div class="hpr-secondary-body"><div id="' . esc_attr( $id ) . '" class="hpr-result"></div></div></details>';
}

function hpr_action_button( string $label, array $args = [] ): string {
    if ( class_exists( DynamicButton::class ) ) {
        return DynamicButton::render( array_merge( [ 'label' => $label, 'class' => 'hpc-button' ], $args ) );
    }
    $attrs = '';
    foreach ( (array) ( $args['attrs'] ?? [] ) as $name => $value ) {
        $attrs .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
    }
    return '<button type="button" class="' . esc_attr( (string) ( $args['class'] ?? 'hpc-button' ) ) . '"' . $attrs . '>' . esc_html( $label ) . '</button>';
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
