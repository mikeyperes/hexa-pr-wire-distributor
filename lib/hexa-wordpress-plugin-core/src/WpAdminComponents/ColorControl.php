<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\BrandColors\BrandColorProvider;

final class ColorControl {
    public static function render( array $args ): string {
        $key = self::clean_key( (string) ( $args["key"] ?? "brand_primary_color" ) );
        $label = (string) ( $args["label"] ?? "Color" );
        $fallback = BrandColorProvider::normalize_hex( (string) ( $args["default"] ?? "#2d5277" ), "#2d5277" );
        $value = BrandColorProvider::normalize_hex( (string) ( $args["value"] ?? $fallback ), $fallback );
        $brand = BrandColorProvider::primary_color( $fallback );
        $description = (string) ( $args["description"] ?? "" );
        $control_class = trim( "hpc-color-control " . (string) ( $args["control_class"] ?? "" ) );
        $picker_class = trim( "hpc-color-picker " . (string) ( $args["picker_class"] ?? "" ) );
        $hex_input_class = trim( "hpc-color-hex-input " . (string) ( $args["hex_input_class"] ?? "" ) );
        $disabled = ! empty( $args["disabled"] ) ? " disabled" : "";
        $import_brand = ! empty( $args["import_brand"] );
        $import_class = trim( "hpc-button secondary hpc-brand-import " . (string) ( $args["import_button_class"] ?? "" ) );
        $import_label = (string) ( $args["import_label"] ?? "Import HWS primary" );
        $status_html = (string) ( $args["status_html"] ?? "" );
        $name = (string) ( $args["name"] ?? $key );
        $id = self::clean_id( (string) ( $args["id"] ?? "hpc-color-" . $key ) );
        $show_picker = array_key_exists( "show_picker", $args ) ? ! empty( $args["show_picker"] ) : true;
        $show_hex_input = array_key_exists( "show_hex_input", $args ) ? ! empty( $args["show_hex_input"] ) : true;
        $show_rgb = array_key_exists( "show_rgb", $args ) ? ! empty( $args["show_rgb"] ) : true;
        $show_hex_code = array_key_exists( "show_hex_code", $args ) ? ! empty( $args["show_hex_code"] ) : true;
        $show_swatch = array_key_exists( "show_swatch", $args ) ? ! empty( $args["show_swatch"] ) : true;
        $show_copy = array_key_exists( "show_copy", $args ) ? ! empty( $args["show_copy"] ) : true;

        ob_start();
        CoreUi::render_assets();
        echo self::assets_once(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
        <div class="<?php echo esc_attr( $control_class ); ?>" data-hpc-color-control data-key="<?php echo esc_attr( $key ); ?>" data-hpc-brand-primary="<?php echo esc_attr( $brand ); ?>">
            <div class="hpc-color-head">
                <h3><?php echo esc_html( $label ); ?></h3>
                <?php if ( "" !== $description ) : ?>
                    <p><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>
            </div>
            <div class="hpc-color-row">
                <?php if ( $show_picker ) : ?>
                    <label class="hpc-color-picker-shell" for="<?php echo esc_attr( $id ); ?>">
                        <span>Picker</span>
                        <input id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $picker_class ); ?>" type="color" value="<?php echo esc_attr( $value ); ?>" data-hpc-color-picker<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    </label>
                <?php endif; ?>
                <?php if ( $show_hex_input ) : ?>
                    <label class="hpc-color-hex-shell">
                        <span>Hex</span>
                        <input class="<?php echo esc_attr( $hex_input_class ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" data-key="<?php echo esc_attr( $key ); ?>" data-hpc-color-hex-input value="<?php echo esc_attr( $value ); ?>" inputmode="text" maxlength="7" autocomplete="off" spellcheck="false" pattern="#?[0-9a-fA-F]{6}"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    </label>
                <?php endif; ?>
                <?php if ( $show_rgb ) : ?>
                    <div class="hpc-color-value">
                        <span>RGB</span>
                        <code data-hpc-color-rgb data-smpi-color-rgb><?php echo esc_html( BrandColorProvider::rgb_string( $value ) ); ?></code>
                    </div>
                <?php endif; ?>
                <?php if ( $show_hex_code ) : ?>
                    <code class="hpc-color-hex-code" data-hpc-color-hex data-smpi-color-hex><?php echo esc_html( $value ); ?></code>
                <?php endif; ?>
                <?php if ( $show_swatch ) : ?>
                    <span class="hpc-color-swatch smpi-color-swatch" data-hpc-color-swatch style="background:<?php echo esc_attr( $value ); ?>"></span>
                <?php endif; ?>
                <?php if ( $show_copy ) : ?>
                    <?php echo CoreUi::copy_button( $value, "Copy hex" ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
                <?php if ( $import_brand ) : ?>
                    <button type="button" class="<?php echo esc_attr( $import_class ); ?>" data-hpc-brand-color-import data-smpi-import-brand-color data-key="<?php echo esc_attr( $key ); ?>" data-brand-color="<?php echo esc_attr( $brand ); ?>"><?php echo esc_html( $import_label ); ?></button>
                <?php endif; ?>
                <?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private static function assets_once(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-color-control{display:grid;gap:10px}.hpc-color-head h3{font-size:13px;letter-spacing:.05em;margin:0 0 5px;text-transform:uppercase}.hpc-color-head p{color:#64748b;margin:0}.hpc-color-row{align-items:end;display:flex;flex-wrap:wrap;gap:10px}.hpc-color-picker-shell,.hpc-color-hex-shell,.hpc-color-value{display:grid;gap:5px}.hpc-color-picker-shell span,.hpc-color-hex-shell span,.hpc-color-value span{color:#475569;font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase}.hpc-color-picker{height:42px;width:64px}.hpc-color-hex-input{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:8px 10px;width:130px}.hpc-color-value code,.hpc-color-hex-code{background:#eef0f3;border-radius:6px;color:#172033;display:inline-flex;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;min-height:40px;align-items:center;padding:8px 10px}.hpc-color-swatch{border:1px solid #cbd5e1;border-radius:10px;display:inline-block;height:40px;width:40px}.hpc-brand-import{white-space:nowrap}</style>
<script>(function(){if(window.hexaColorControlReady)return;window.hexaColorControlReady=true;function hex(v){v=String(v||"").trim().toLowerCase();if(v&&v.charAt(0)!=="#")v="#"+v;if(/^#[0-9a-f]{3}$/.test(v)){v="#"+v[1]+v[1]+v[2]+v[2]+v[3]+v[3]}return /^#[0-9a-f]{6}$/.test(v)?v:""}function rgb(h){h=hex(h);if(!h)return "";return "rgb("+parseInt(h.slice(1,3),16)+", "+parseInt(h.slice(3,5),16)+", "+parseInt(h.slice(5,7),16)+")"}function sync(control,value){var h=hex(value);if(!control||!h)return h;var picker=control.querySelector("[data-hpc-color-picker]");var input=control.querySelector("[data-hpc-color-hex-input]");var swatch=control.querySelector("[data-hpc-color-swatch]");var hexEl=control.querySelector("[data-hpc-color-hex]");var rgbEl=control.querySelector("[data-hpc-color-rgb]");var copy=control.querySelector("[data-hpc-copy]");if(picker)picker.value=h;if(input)input.value=h;if(swatch)swatch.style.background=h;if(hexEl)hexEl.textContent=h;if(rgbEl)rgbEl.textContent=rgb(h);if(copy)copy.setAttribute("data-hpc-copy",h);return h}document.addEventListener("input",function(event){var picker=event.target.closest("[data-hpc-color-picker]");var input=event.target.closest("[data-hpc-color-hex-input]");if(!picker&&!input)return;sync(event.target.closest("[data-hpc-color-control]"),event.target.value)});document.addEventListener("change",function(event){var picker=event.target.closest("[data-hpc-color-picker]");if(!picker)return;var control=picker.closest("[data-hpc-color-control]");var input=control?control.querySelector("[data-hpc-color-hex-input]"):null;var h=sync(control,picker.value);if(input&&h){input.dispatchEvent(new Event("change",{bubbles:true}))}})})();</script>
HTML;
    }

    private static function clean_key( string $value ): string {
        if ( function_exists( "sanitize_key" ) ) {
            return sanitize_key( $value );
        }

        return preg_replace( "/[^a-z0-9_\-]/", "", strtolower( $value ) ) ?: "color";
    }

    private static function clean_id( string $value ): string {
        if ( function_exists( "sanitize_html_class" ) ) {
            return sanitize_html_class( $value );
        }

        return preg_replace( "/[^a-zA-Z0-9_\-]/", "-", $value ) ?: "hpc-color";
    }
}
