<?php

namespace hpr_distributor\Admin;

defined( 'ABSPATH' ) || exit;

final class PressReleaseSeoStatus {
    public static function register(): void {
        add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ] );
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'hpr_seo_status_report',
            'SEO Status Report - Hexa PR Wire',
            [ self::class, 'render' ],
            'press-release',
            'normal',
            'default'
        );
    }

    public static function render( \WP_Post $post ): void {
        $global_follow         = get_option( 'hpr_seo_follow_status', 'dofollow' );
        $global_sitemap        = get_option( 'hpr_seo_sitemap_status', 'include' );
        $cat_follow_overrides  = get_option( 'hpr_seo_cat_follow_overrides', [] );
        $cat_sitemap_overrides = get_option( 'hpr_seo_cat_sitemap_overrides', [] );
        $post_follow           = function_exists( 'get_field' ) ? get_field( 'hpr_seo_follow_override', $post->ID ) : 'inherit';
        $post_sitemap          = function_exists( 'get_field' ) ? get_field( 'hpr_seo_sitemap_override', $post->ID ) : 'inherit';
        $post_follow           = $post_follow ?: 'inherit';
        $post_sitemap          = $post_sitemap ?: 'inherit';
        $post_categories       = wp_get_post_categories( $post->ID, [ 'fields' => 'all' ] );
        $post_category_ids     = wp_list_pluck( $post_categories, 'term_id' );
        $cat_follow_match      = self::matching_override( $cat_follow_overrides, $post_category_ids );
        $cat_sitemap_match     = self::matching_override( $cat_sitemap_overrides, $post_category_ids );
        $effective_follow      = self::resolve_follow( $post_follow, $cat_follow_match, $global_follow );
        $effective_sitemap     = self::resolve_sitemap( $post_sitemap, $cat_sitemap_match, $global_sitemap );
        $follow_labels         = [
            'dofollow' => [ 'Do Follow', 'status-ok' ],
            'nofollow' => [ 'No Follow', 'status-bad' ],
            'default'  => [ 'Default (no modification)', 'status-warn' ],
        ];
        $sitemap_labels        = [
            'include' => [ 'Included in Sitemap', 'status-ok' ],
            'exclude' => [ 'Excluded from Sitemap', 'status-bad' ],
        ];
        $follow_label          = $follow_labels[ $effective_follow['value'] ] ?? [ $effective_follow['value'], '' ];
        $sitemap_label         = $sitemap_labels[ $effective_sitemap['value'] ] ?? [ $effective_sitemap['value'], '' ];
        ?>
        <style>
            .hpr-seo-report{display:grid;grid-template-columns:1fr 1fr;gap:20px}.hpr-seo-report-card{background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:16px}.hpr-seo-report-card h4{margin:0 0 10px;font-size:14px}.hpr-seo-report-card .effective{font-size:16px;font-weight:600;margin-bottom:12px}.hpr-seo-report-card .breakdown{font-size:12px;color:#666;line-height:1.7}.hpr-seo-report-card .level{display:flex;justify-content:space-between;padding:2px 0;border-bottom:1px solid #eee;gap:12px}.hpr-seo-report-card .level.active{font-weight:600;color:#1d2327}.hpr-seo-report-card .status-ok{color:#00a32a}.hpr-seo-report-card .status-bad{color:#d63638}.hpr-seo-report-card .status-warn{color:#996800}@media(max-width:782px){.hpr-seo-report{grid-template-columns:1fr}}
        </style>
        <div class="hpr-seo-report">
            <?php self::render_card( 'Anchor Follow Status', $follow_label, $effective_follow, $post_follow, $cat_follow_match, $global_follow ); ?>
            <?php self::render_card( 'Sitemap Inclusion', $sitemap_label, $effective_sitemap, $post_sitemap, $cat_sitemap_match, $global_sitemap ); ?>
        </div>
        <script>
        jQuery(function($){
            $('input[name="acf[field_hpr_seo_follow_override]"],input[name="acf[field_hpr_seo_sitemap_override]"]').on('change',function(){
                var report=$('.hpr-seo-report');
                if(!report.next('.hpr-live-hint').length){
                    report.after('<p class="hpr-live-hint">Save or update the press release to refresh this report.</p>');
                }
            });
        });
        </script>
        <?php
    }

    /** @param array<int,array<string,mixed>>|mixed $overrides @param array<int,int|string> $category_ids */
    private static function matching_override( mixed $overrides, array $category_ids ): ?array {
        if ( ! is_array( $overrides ) ) {
            return null;
        }
        foreach ( $overrides as $override ) {
            if ( is_array( $override ) && in_array( (int) ( $override['id'] ?? 0 ), array_map( 'intval', $category_ids ), true ) ) {
                return $override;
            }
        }
        return null;
    }

    /** @return array{value:string,source:string} */
    private static function resolve_follow( mixed $post_override, ?array $category, mixed $global ): array {
        if ( is_string( $post_override ) && 'inherit' !== $post_override && '' !== $post_override ) {
            return [ 'value' => $post_override, 'source' => 'post override' ];
        }
        if ( $category ) {
            return [ 'value' => (string) $category['status'], 'source' => 'category override' ];
        }
        return [ 'value' => (string) $global, 'source' => 'global setting' ];
    }

    /** @return array{value:string,source:string} */
    private static function resolve_sitemap( mixed $post_override, ?array $category, mixed $global ): array {
        if ( is_string( $post_override ) && 'inherit' !== $post_override && '' !== $post_override ) {
            return [ 'value' => $post_override, 'source' => 'post override' ];
        }
        if ( $category ) {
            return [ 'value' => (string) $category['status'], 'source' => 'category override' ];
        }
        return [ 'value' => (string) $global, 'source' => 'global setting' ];
    }

    /** @param array{0:string,1:string} $label @param array<string,mixed> $effective */
    private static function render_card( string $title, array $label, array $effective, mixed $post_value, ?array $category, mixed $global ): void {
        $source = (string) ( $effective['source'] ?? '' );
        ?>
        <div class="hpr-seo-report-card">
            <h4><?php echo esc_html( $title ); ?></h4>
            <div class="effective"><span class="<?php echo esc_attr( $label[1] ); ?>"><?php echo esc_html( $label[0] ); ?></span> <small>- determined by <?php echo esc_html( $source ); ?></small></div>
            <div class="breakdown">
                <div class="level <?php echo 'post override' === $source ? 'active' : ''; ?>"><span>Post Override</span><span><?php echo esc_html( 'inherit' === $post_value ? 'Inherit' : (string) $post_value ); ?></span></div>
                <div class="level <?php echo 'category override' === $source ? 'active' : ''; ?>"><span>Category Override</span><span><?php echo esc_html( $category ? (string) ( ( $category['name'] ?? '' ) . ': ' . ( $category['status'] ?? '' ) ) : 'None' ); ?></span></div>
                <div class="level <?php echo 'global setting' === $source ? 'active' : ''; ?>"><span>Global Setting</span><span><?php echo esc_html( (string) $global ); ?></span></div>
            </div>
            <p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=hpr-distributor&tab=overview' ) ); ?>">Manage SEO settings</a></p>
        </div>
        <?php
    }
}
