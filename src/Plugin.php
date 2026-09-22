<?php

namespace hpr_distributor;

use hpr_distributor\Admin\DashboardActions;
use hpr_distributor\Admin\GoingLiveTab;
use hpr_distributor\Admin\PressReleaseSeoStatus;
use hpr_distributor\Api\OnboardingContract;
use hpr_distributor\Content\PressReleaseLoopExclusion;
use hpr_distributor\Core\CoreIntegration;
use hpr_distributor\Import\NativeFeedImporter;
use hpr_distributor\Import\NativeFeedSettings;
use hpr_distributor\Lifecycle\DeletionSync;
use hpr_distributor\Media\ExternalImageSizing;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class Plugin {
    private static bool $booted = false;

    public static function boot(): void {
        if ( self::$booted ) {
            return;
        }

        PressReleaseLoopExclusion::register();
        CoreIntegration::boot();
        ExternalImageSizing::register();
        NativeFeedSettings::register();
        NativeFeedImporter::register();
        DeletionSync::register();
        OnboardingContract::register();

        if ( is_admin() ) {
            DashboardActions::register();
            GoingLiveTab::register();
            PressReleaseSeoStatus::register();
        }

        self::$booted = true;
    }
}
