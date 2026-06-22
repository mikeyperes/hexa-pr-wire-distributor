<?php

namespace hpr_distributor;

use Hexa\PluginCore\WpAdminUiCleanup\CleanupRegistry;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

function hpr_get_ui_cleanup_registry(): CleanupRegistry {
    static $registry = null;

    if ( $registry instanceof CleanupRegistry ) {
        return $registry;
    }

    $registry = new CleanupRegistry(
        [
            "option_prefix" => "hpr_ui_cleanup_",
            "ajax_action"   => "hpr_ui_cleanup_toggle",
            "nonce_action"  => Config::AJAX_NONCE,
            "nonce_field"   => "nonce",
            "capability"    => Config::$settings_page_capability,
            "root_id"       => "hpr-ui-cleanup",
            "sections"      => [
                "post_editor" => [
                    "title"       => "Post Editor",
                    "description" => "Hide or collapse PR Wire related editor boxes on post edit screens.",
                    "icon"        => "Post",
                ],
            ],
            "options"       => [
                "hide_echo_auto_generated_post_info" => [
                    "label"       => "Echo Auto Generated Post Information",
                    "description" => "Hides the Echo auto-generated post information metabox on post editor screens.",
                    "section"     => "post_editor",
                    "default"     => true,
                    "admin_pages" => [ "post.php", "post-new.php" ],
                    "mode"        => "css_hide",
                    "selectors"   => [
                        "#echo_meta_box_function_add",
                        "#echo_meta_box_function_add-hide",
                        'label[for="echo_meta_box_function_add-hide"]',
                    ],
                ],
                "hide_fifu_featured_image_box" => [
                    "label"       => "Featured Image URL Box",
                    "description" => "Hides the Featured Image URL/Keywords metabox provided by FIFU.",
                    "section"     => "post_editor",
                    "default"     => false,
                    "admin_pages" => [ "post.php", "post-new.php" ],
                    "mode"        => "css_hide",
                    "selectors"   => [
                        "#imageUrlMetaBox",
                        "#imageUrlMetaBox-hide",
                        'label[for="imageUrlMetaBox-hide"]',
                    ],
                ],
                "collapse_fifu_featured_image_box" => [
                    "label"       => "Featured Image URL Box Collapsed By Default",
                    "description" => "Keeps the FIFU Featured Image URL/Keywords metabox available but forces it closed on editor load.",
                    "section"     => "post_editor",
                    "default"     => false,
                    "admin_pages" => [ "post.php", "post-new.php" ],
                    "mode"        => "postbox_collapse",
                    "selectors"   => [ "#imageUrlMetaBox" ],
                ],
            ],
        ]
    );

    return $registry;
}

function hpr_register_ui_cleanup(): void {
    hpr_get_ui_cleanup_registry()->register();
}

hpr_register_ui_cleanup();

function display_settings_ui_cleanup(): void {
    hpr_get_ui_cleanup_registry()->render();
}
