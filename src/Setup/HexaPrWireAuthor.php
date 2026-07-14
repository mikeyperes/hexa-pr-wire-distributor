<?php

namespace hpr_distributor\Setup;

if ( ! defined( "ABSPATH" ) ) {
    exit;
}

final class HexaPrWireAuthor {
    public const LOGIN = "hexaprwire";
    public const EMAIL = "info@hexaprwire.com";
    public const AVATAR_SOURCE_URL = "https://hexaprwire.com/wp-content/uploads/2023/03/Hexa-PR-Wire-Logo.jpeg";

    public static function profile(): array {
        return [
            "display_name" => "Hexa PR Wire",
            "first_name"   => "Hexa PR Wire",
            "last_name"    => "",
            "user_url"     => "https://hexaprwire.com/",
            "description"  => "Hexa PR Wire is a press release distribution service founded by Michael Peres in 2022. Its mission is to help businesses share their stories through quality press release distribution.",
            "urls"         => [
                "facebook"   => "https://www.facebook.com/hexaprwire/",
                "instagram"  => "https://www.instagram.com/hexaprwire",
                "linkedin"   => "https://www.linkedin.com/company/hexaprwire/",
                "x"          => "https://twitter.com/hexaprwire",
                "crunchbase" => "https://www.crunchbase.com/organization/hexa-pr-wire",
                "muckrack"   => "https://muckrack.com/media-outlet/hexaprwire",
                "website"    => "https://hexaprwire.com/",
                "the_org"    => "https://theorg.com/org/hexa-pr-wire",
                "calendly"   => "https://calendly.com/hexaprwire/",
            ],
        ];
    }

    public static function find(): ?\WP_User {
        $user = get_user_by( "login", self::LOGIN );
        if ( ! $user instanceof \WP_User ) {
            $user = get_user_by( "email", self::EMAIL );
        }

        return $user instanceof \WP_User ? $user : null;
    }

    public static function status(): array {
        $user = self::find();
        if ( ! $user instanceof \WP_User ) {
            return [
                "exists"          => false,
                "profile_correct" => false,
                "avatar_exists"   => false,
                "avatar_owned"    => false,
                "urls_complete"   => false,
                "user_id"         => 0,
                "avatar_id"       => 0,
            ];
        }

        $profile = self::profile();
        $urls_complete = true;
        foreach ( $profile["urls"] as $key => $url ) {
            if ( trim( (string) get_user_meta( $user->ID, "urls_" . $key, true ) ) !== $url ) {
                $urls_complete = false;
                break;
            }
        }

        $avatar_id = self::avatar_attachment_id( $user->ID );
        $avatar_owned = $avatar_id > 0 && (int) $user->ID === (int) get_post_field( "post_author", $avatar_id );

        return [
            "exists"          => true,
            "profile_correct" => self::LOGIN === $user->user_login
                && self::EMAIL === strtolower( (string) $user->user_email )
                && $profile["display_name"] === $user->display_name
                && $profile["user_url"] === trailingslashit( (string) $user->user_url )
                && in_array( "author", (array) $user->roles, true ),
            "avatar_exists"   => $avatar_id > 0,
            "avatar_owned"    => $avatar_owned,
            "urls_complete"   => $urls_complete,
            "user_id"         => (int) $user->ID,
            "avatar_id"       => $avatar_id,
            "edit_url"        => get_edit_user_link( $user->ID ),
            "view_url"        => get_author_posts_url( $user->ID ),
            "email"           => (string) $user->user_email,
            "display_name"    => (string) $user->display_name,
            "roles"           => array_values( (array) $user->roles ),
        ];
    }

    public static function provision( bool $force_avatar = false ): array|\WP_Error {
        $profile = self::profile();
        $user = self::find();

        if ( $user instanceof \WP_User && self::LOGIN !== $user->user_login ) {
            return new \WP_Error( "hpr_author_login_conflict", "The required email address belongs to a user with a different login." );
        }

        $email_owner = get_user_by( "email", self::EMAIL );
        if ( $email_owner instanceof \WP_User && ( ! $user instanceof \WP_User || $email_owner->ID !== $user->ID ) ) {
            return new \WP_Error( "hpr_author_email_conflict", "The required email address belongs to another WordPress user." );
        }

        $user_data = [
            "user_email"   => self::EMAIL,
            "display_name" => $profile["display_name"],
            "first_name"   => $profile["first_name"],
            "last_name"    => $profile["last_name"],
            "nickname"     => $profile["display_name"],
            "user_url"     => $profile["user_url"],
            "description"  => $profile["description"],
        ];

        if ( $user instanceof \WP_User ) {
            $user_data["ID"] = $user->ID;
            $user_id = wp_update_user( $user_data );
        } else {
            $user_data["user_login"] = self::LOGIN;
            $user_data["user_pass"] = wp_generate_password( 32, true, true );
            $user_data["role"] = "author";
            $user_id = wp_insert_user( $user_data );
        }

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $user_id = (int) $user_id;
        $provisioned_user = get_user_by( "id", $user_id );
        if ( $provisioned_user instanceof \WP_User && ! in_array( "author", (array) $provisioned_user->roles, true ) ) {
            $provisioned_user->set_role( "author" );
        }

        self::update_profile_meta( $user_id, $profile );

        $avatar_result = self::ensure_avatar( $user_id, $force_avatar );
        if ( is_wp_error( $avatar_result ) ) {
            return $avatar_result;
        }

        clean_user_cache( $user_id );

        return [
            "user_id"       => $user_id,
            "created"       => ! $user instanceof \WP_User,
            "avatar_id"     => (int) $avatar_result,
            "profile"       => self::status(),
            "source_avatar" => self::AVATAR_SOURCE_URL,
        ];
    }

    public static function ajax_provision(): void {
        \hpr_distributor\guard_ajax_request( "create_users" );

        $force_avatar = isset( $_POST["force_avatar"] ) && rest_sanitize_boolean( wp_unslash( $_POST["force_avatar"] ) );
        $result = self::provision( $force_avatar );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                [
                    "code"    => $result->get_error_code(),
                    "message" => $result->get_error_message(),
                ],
                400
            );
        }

        wp_send_json_success(
            [
                "message" => ! empty( $result["created"] ) ? "Hexa PR Wire author created." : "Hexa PR Wire author updated.",
                "result"  => $result,
            ]
        );
    }

    public static function checklist_provision( array $payload ): array {
        $force_avatar = ! empty( $payload["inputs"]["force_avatar"] );
        $result = self::provision( $force_avatar );

        if ( is_wp_error( $result ) ) {
            return [
                "success" => false,
                "message" => $result->get_error_message(),
                "logs"    => [
                    [
                        "level"   => "error",
                        "message" => "Hexa PR Wire author provisioning failed.",
                        "context" => [ "code" => $result->get_error_code() ],
                    ],
                ],
            ];
        }

        $status = $result["profile"];
        $complete = ! empty( $status["exists"] )
            && ! empty( $status["profile_correct"] )
            && ! empty( $status["avatar_exists"] )
            && ! empty( $status["avatar_owned"] )
            && ! empty( $status["urls_complete"] );

        return [
            "success" => $complete,
            "message" => $complete ? "Hexa PR Wire author, profile URLs, and avatar are ready." : "The author exists but one or more profile checks still need attention.",
            "logs"    => [
                [
                    "level"   => $complete ? "success" : "error",
                    "message" => $complete ? "Author provisioning passed every verification." : "Author provisioning did not pass every verification.",
                    "context" => $status,
                ],
            ],
            "data"    => [ "status" => $status ],
        ];
    }

    private static function update_profile_meta( int $user_id, array $profile ): void {
        $urls = $profile["urls"];

        foreach ( $urls as $key => $url ) {
            update_user_meta( $user_id, "urls_" . $key, $url );
        }

        $legacy = [
            "facebook"       => $urls["facebook"],
            "facebook_url"   => $urls["facebook"],
            "instagram"      => $urls["instagram"],
            "instagram_url"  => $urls["instagram"],
            "linkedin"       => $urls["linkedin"],
            "linkedin_url"   => $urls["linkedin"],
            "x"              => $urls["x"],
            "twitter"        => $urls["x"],
            "twitter_url"    => $urls["x"],
            "website"        => $urls["website"],
            "website_url"    => $urls["website"],
            "crunchbase_url" => $urls["crunchbase"],
            "muckrack_url"   => $urls["muckrack"],
            "author_title"   => "Hexa PR Wire Team",
            "staff_writer"   => 1,
        ];

        foreach ( $legacy as $key => $value ) {
            update_user_meta( $user_id, $key, $value );
        }

        update_user_meta( $user_id, "socials_facebook", $urls["facebook"] );
        update_user_meta( $user_id, "socials_instagram", $urls["instagram"] );
        update_user_meta( $user_id, "socials_linkedin", $urls["linkedin"] );
        update_user_meta( $user_id, "socials_x", $urls["x"] );

        if ( function_exists( "update_field" ) ) {
            update_field( "urls", $urls, "user_" . $user_id );
            update_field(
                "socials",
                [
                    "facebook"  => $urls["facebook"],
                    "instagram" => $urls["instagram"],
                    "linkedin"  => $urls["linkedin"],
                    "x"         => $urls["x"],
                ],
                "user_" . $user_id
            );
        }
    }

    private static function ensure_avatar( int $user_id, bool $force ): int|\WP_Error {
        $current_id = self::avatar_attachment_id( $user_id );
        $attachment_id = $current_id;
        if ( $force || $attachment_id < 1 ) {
            $attachment_id = self::source_avatar_attachment_id();
        }
        if ( $attachment_id < 1 ) {
            if ( ! function_exists( "media_sideload_image" ) ) {
                require_once ABSPATH . "wp-admin/includes/media.php";
                require_once ABSPATH . "wp-admin/includes/file.php";
                require_once ABSPATH . "wp-admin/includes/image.php";
            }

            $attachment_id = media_sideload_image( self::AVATAR_SOURCE_URL, 0, "Hexa PR Wire Profile Photo", "id" );
            if ( is_wp_error( $attachment_id ) ) {
                return $attachment_id;
            }

            $attachment_id = (int) $attachment_id;
            update_post_meta( $attachment_id, "_hpr_source_avatar_url", self::AVATAR_SOURCE_URL );
        }

        if ( ! self::avatar_attachment_is_usable( $attachment_id ) ) {
            return new \WP_Error(
                "hpr_avatar_attachment_invalid",
                "The Hexa PR Wire avatar attachment is missing or unreadable."
            );
        }

        $owner_result = wp_update_post(
            [
                "ID"          => $attachment_id,
                "post_author" => $user_id,
            ],
            true
        );
        if ( is_wp_error( $owner_result ) ) {
            return $owner_result;
        }
        if ( $attachment_id !== (int) $owner_result ) {
            return new \WP_Error(
                "hpr_avatar_owner_update_failed",
                "The Hexa PR Wire avatar could not be assigned to the canonical author."
            );
        }

        global $simple_local_avatars;
        if ( is_object( $simple_local_avatars ) && method_exists( $simple_local_avatars, "assign_new_user_avatar" ) ) {
            $simple_local_avatars->assign_new_user_avatar( $attachment_id, $user_id );
        } else {
            update_user_meta( $user_id, "wp_user_avatar", $attachment_id );
        }

        update_user_meta( $user_id, "simple_local_avatar_rating", "G" );
        return $attachment_id;
    }

    private static function avatar_attachment_id( int $user_id ): int {
        $attachment_id = 0;
        $simple = get_user_meta( $user_id, "simple_local_avatar", true );
        if ( is_array( $simple ) && ! empty( $simple["media_id"] ) ) {
            $attachment_id = (int) $simple["media_id"];
        }

        if ( $attachment_id < 1 ) {
            $attachment_id = (int) get_user_meta( $user_id, "wp_user_avatar", true );
        }

        return self::avatar_attachment_is_usable( $attachment_id ) ? $attachment_id : 0;
    }

    private static function avatar_attachment_is_usable( int $attachment_id ): bool {
        if ( $attachment_id < 1 || "attachment" !== get_post_type( $attachment_id ) ) {
            return false;
        }

        $file = get_attached_file( $attachment_id );
        return is_string( $file ) && "" !== trim( $file ) && is_file( $file );
    }

    private static function source_avatar_attachment_id(): int {
        $ids = get_posts(
            [
                "post_type"      => "attachment",
                "post_status"    => "inherit",
                "fields"         => "ids",
                "posts_per_page" => -1,
                "orderby"        => "ID",
                "order"          => "DESC",
                "meta_key"       => "_hpr_source_avatar_url",
                "meta_value"     => self::AVATAR_SOURCE_URL,
            ]
        );

        foreach ( $ids as $attachment_id ) {
            $attachment_id = (int) $attachment_id;
            if ( self::avatar_attachment_is_usable( $attachment_id ) ) {
                return $attachment_id;
            }
        }

        return 0;
    }
}
