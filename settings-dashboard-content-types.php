<?php

namespace hpr_distributor;

use Hexa\PluginCore\ContentTypes\ContentTypeRenderer;
use hpr_distributor\ContentTypes\PressReleaseStructures;

defined( 'ABSPATH' ) || exit;

function display_settings_content_types(): void {
    if ( ! class_exists( ContentTypeRenderer::class ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'The Hexa WP Core content-type component is unavailable.', 'hexa-pr-wire-distributor' ) . '</p></div>';
        return;
    }

    echo ( new ContentTypeRenderer() )->render(
        PressReleaseStructures::registry(),
        [
            'title'          => 'Custom Post Types',
            'description'    => 'Manage the Press Release content type and its ACF structures. The internal post-type key remains fixed so existing releases cannot be orphaned.',
            'persist_prefix' => 'hpr-content-types',
        ]
    );
}
