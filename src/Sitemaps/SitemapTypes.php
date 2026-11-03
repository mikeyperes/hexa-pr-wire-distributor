<?php

namespace hpr_distributor\Sitemaps;

use Hexa\PluginCore\CoreContracts\ModuleInterface;
use Hexa\PluginCore\Sitemaps\SitemapProviders;
use Hexa\PluginCore\Sitemaps\SitemapsPanel;

/**
 * The sitemaps this plugin owns: press releases. HWS Base Tools shows them
 * read-only and links to the Sitemaps tab.
 */
final class SitemapTypes implements ModuleInterface {
    public const SLUG = 'hexa-pr-wire-distributor';

    public static function definitions(): array {
        return [
            [ 'id' => 'post_type_press-release', 'recommended' => true, 'why' => 'Syndicated press releases are pages the outlet wants found.' ],
        ];
    }

    public function register(): void {
        if ( ! class_exists( SitemapProviders::class ) ) {
            return;
        }
        SitemapProviders::register( self::SLUG, 'Hexa PR Wire Distributor', admin_url( 'options-general.php?page=hpr-distributor&tab=sitemaps' ), self::definitions() );
        add_filter( 'hpr_distributor_dashboard_tabs', static function ( $tabs ) { $tabs = (array) $tabs; $tabs['sitemaps'] = 'Sitemaps'; return $tabs; } );
        add_filter( 'hpr_distributor_render_dashboard_tab', static function ( $handled, $tab ) {
            if ( 'sitemaps' !== $tab ) {
                return $handled;
            }
            SitemapsPanel::render( self::SLUG );
            return true;
        }, 10, 2 );
    }
}
