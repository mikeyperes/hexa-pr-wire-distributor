<?php

namespace hpr_distributor;

use hpr_distributor\Content\PressReleaseLoopExclusion;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

/**
 * Compatibility callbacks retained for the existing Content Rules option schema.
 * Query behavior is owned by the namespaced content service.
 */
function hide_press_release_from_home_loop(): void {
    PressReleaseLoopExclusion::register();
}

function hide_press_release_from_author_loop(): void {
    PressReleaseLoopExclusion::register();
}

function hide_press_release_from_category_loop(): void {
    PressReleaseLoopExclusion::register();
}

function hide_press_release_from_tag_loop(): void {
    PressReleaseLoopExclusion::register();
}

function hide_press_release_from_related_single_loop(): void {
    PressReleaseLoopExclusion::register();
}
