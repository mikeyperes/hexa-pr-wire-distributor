<?php

declare(strict_types=1);

class WP_User {
    public string $display_name = 'Primary Person';
    public array $roles = [ 'author' ];
    public string $first_name = 'Primary';
    public string $last_name = 'Person';
    public string $user_login = 'primary';
    public string $user_url = 'https://example.test/person';
    public string $user_email = 'primary@example.test';
    public string $description = 'Biography';
}
class WP_Post {
    public int $ID = 22;
    public string $post_title = 'Verified Person';
    public string $post_type = 'profile';
    public string $post_status = 'publish';
    public string $post_name = 'verified-person';
    public string $post_excerpt = 'Profile';
    public string $post_content = 'Profile content';
    public int $post_author = 11;
}

$entity_options = [];

function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?: ''; }
function sanitize_text_field( mixed $value ): string { return trim( strip_tags( (string) $value ) ); }
function get_option( string $name, mixed $default = false ): mixed { global $entity_options; return $entity_options[ $name ] ?? $default; }
function update_option( string $name, mixed $value, mixed $autoload = null ): bool { global $entity_options; $entity_options[ $name ] = $value; return true; }
function get_userdata( int $id ): WP_User|false { return 11 === $id ? new WP_User() : false; }
function get_edit_user_link( int $id ): string { return 'https://example.test/wp-admin/user-edit.php?user_id=' . $id; }
function get_author_posts_url( int $id ): string { return 'https://example.test/author/' . $id; }
function get_avatar_url( int $id, array $args = [] ): string { return 'https://example.test/avatar/' . $id . '.jpg'; }
function get_post( int $id ): WP_Post|null { return 22 === $id ? new WP_Post() : null; }
function get_the_title( int $id ): string { return 'Verified Person'; }
function get_edit_post_link( int $id, string $context = 'display' ): string { return 'https://example.test/wp-admin/post.php?post=' . $id; }
function get_permalink( int $id ): string { return 'https://example.test/profile/' . $id; }
function get_the_post_thumbnail_url( int $id, string $size = 'post-thumbnail' ): string { return ''; }
function get_post_type_object( string $post_type ): object { return (object) [ 'labels' => (object) [ 'singular_name' => 'Verified Profile' ] ]; }
function get_field( string $name, string|int $context ): mixed {
    if ( 'entity_type' === $name && 'user_11' === $context ) return 'organization';
    if ( 'legal_name' === $name && 22 === $context ) return 'Verified Person LLC';
    return null;
}
function get_user_meta( int $user_id, string $key, bool $single = false ): mixed { return 'job_title' === $key ? 'Publisher' : ''; }
function get_post_meta( int $post_id, string $key, bool $single = false ): mixed { return ''; }

$root = dirname( __DIR__ );
require $root . '/src/EntitySources/CanonicalEntityResolver.php';
require $root . '/src/EntitySources/PrimaryEntityManager.php';

use Hexa\PluginCore\EntitySources\CanonicalEntityResolver;
use Hexa\PluginCore\EntitySources\PrimaryEntityManager;

function entity_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
        exit( 1 );
    }
}

entity_assert( null === CanonicalEntityResolver::resolve(), 'A site without a primary entity must remain a valid configuration.' );

$manager = new PrimaryEntityManager(
    [
        'entity_option' => 'hws_primary_entity',
        'site_type_option' => 'hws_site_type',
        'site_types' => [ 'news_outlet' => 'News Outlet', 'other' => 'Other' ],
        'sources' => [
            'wordpress_user' => [ 'kind' => 'user', 'label' => 'WordPress Author' ],
            'verified_profile' => [ 'kind' => 'post', 'post_type' => 'profile', 'label' => 'Verified Profile' ],
        ],
    ]
);

$saved = $manager->save( [ 'site_type' => 'news_outlet', 'enabled' => true, 'source' => 'wordpress_user', 'object_id' => 11, 'entity_type' => 'auto' ] );
entity_assert( 'organization' === $saved['entity']['entity_type'], 'User ACF semantic type must be respected when automatic detection is selected.' );
entity_assert( 'user_11' === $saved['entity']['context'], 'WordPress users must expose the correct ACF context.' );

$saved = $manager->save( [ 'site_type' => 'news_outlet', 'enabled' => true, 'source' => 'verified_profile', 'object_id' => 22, 'entity_type' => 'person' ] );
entity_assert( 'profile' === $saved['entity']['post_type'] && 'person' === $saved['entity']['entity_type'], 'Verified Profile posts must resolve through the same canonical contract.' );
entity_assert( 11 === $saved['entity']['attached_user_id'], 'Post-backed entities must expose their bound WordPress author through the canonical contract.' );
entity_assert( 'Verified Person LLC' === CanonicalEntityResolver::field( $saved['entity'], 'legal_name' ), 'Consumers must read ACF fields from a canonical post source.' );
entity_assert( 'Verified Person' === CanonicalEntityResolver::first_field( $saved['entity'], [ 'missing', 'name' ] ), 'Consumers must resolve ordered canonical field fallbacks.' );

$saved = $manager->save( [ 'site_type' => 'other', 'enabled' => false, 'source' => '', 'object_id' => 0, 'entity_type' => 'auto' ] );
entity_assert( null === $saved['entity'] && 'other' === $saved['site_type'], 'Website type must remain independent when no entity is attached.' );

echo "PASS: Canonical entities support optional user/post sources and independent website classification.\n";
