<?php

namespace Hexa\PluginCore\EntitySources;

final class PrimaryEntityManager {
    private array $config;

    public function __construct( array $config = [] ) {
        $this->config = array_merge(
            [
                'entity_option' => 'hws_primary_entity', 'site_type_option' => 'hws_site_type',
                'site_types' => [], 'sources' => [], 'migration_flag' => 'hws_primary_entity_migrated_v1',
                'legacy_resolvers' => [],
            ],
            $config
        );
    }

    /** @return array<string,string> */
    public function site_types(): array {
        return (array) $this->config['site_types'];
    }

    /** @return array<string,array<string,mixed>> */
    public function sources(): array {
        $sources = [];
        foreach ( (array) $this->config['sources'] as $id => $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }
            $id = sanitize_key( is_string( $id ) ? $id : (string) ( $source['id'] ?? '' ) );
            $kind = in_array( (string) ( $source['kind'] ?? '' ), [ 'user', 'post' ], true ) ? (string) $source['kind'] : '';
            if ( '' === $id || '' === $kind ) {
                continue;
            }
            $sources[ $id ] = [
                'id' => $id, 'label' => sanitize_text_field( (string) ( $source['label'] ?? $id ) ),
                'kind' => $kind, 'post_type' => sanitize_key( (string) ( $source['post_type'] ?? '' ) ),
                'description' => sanitize_text_field( (string) ( $source['description'] ?? '' ) ),
            ];
        }
        return $sources;
    }

    public function site_type(): string {
        $default = array_key_first( $this->site_types() ) ?: 'other';
        $value = sanitize_key( (string) get_option( (string) $this->config['site_type_option'], $default ) );
        return array_key_exists( $value, $this->site_types() ) ? $value : $default;
    }

    /** @return array<string,mixed> */
    public function settings(): array {
        return CanonicalEntityResolver::settings( (string) $this->config['entity_option'] );
    }

    /** @return array<string,mixed>|null */
    public function resolve(): ?array {
        return CanonicalEntityResolver::resolve( $this->settings(), (string) $this->config['entity_option'] );
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    public function save( array $values ): array {
        $site_type = sanitize_key( (string) ( $values['site_type'] ?? '' ) );
        if ( ! array_key_exists( $site_type, $this->site_types() ) ) {
            $site_type = $this->site_type();
        }

        $sources = $this->sources();
        $source_id = sanitize_key( (string) ( $values['source'] ?? '' ) );
        $source = $sources[ $source_id ] ?? null;
        $type = sanitize_key( (string) ( $values['entity_type'] ?? 'auto' ) );
        $type = in_array( $type, CanonicalEntityResolver::TYPES, true ) ? $type : 'auto';

        $settings = [
            'enabled' => ! empty( $values['enabled'] ) && null !== $source && ! empty( $values['object_id'] ) ? 1 : 0,
            'source' => $source_id, 'kind' => $source['kind'] ?? '', 'post_type' => $source['post_type'] ?? '',
            'object_id' => max( 0, (int) ( $values['object_id'] ?? 0 ) ), 'entity_type' => $type,
            'migrated_from' => sanitize_text_field( (string) ( $values['migrated_from'] ?? $this->settings()['migrated_from'] ) ),
        ];

        update_option( (string) $this->config['site_type_option'], $site_type, false );
        update_option( (string) $this->config['entity_option'], $settings, false );
        return [ 'site_type' => $site_type, 'settings' => $settings, 'entity' => CanonicalEntityResolver::resolve( $settings ) ];
    }

    public function migrate_legacy(): void {
        $flag = (string) $this->config['migration_flag'];
        if ( get_option( $flag, false ) || ! empty( $this->settings()['object_id'] ) ) {
            return;
        }
        foreach ( (array) $this->config['legacy_resolvers'] as $resolver ) {
            if ( ! is_callable( $resolver ) ) {
                continue;
            }
            $candidate = call_user_func( $resolver );
            if ( ! is_array( $candidate ) || empty( $candidate['object_id'] ) || empty( $candidate['source'] ) ) {
                continue;
            }
            $candidate['enabled'] = 1;
            $this->save( array_merge( [ 'site_type' => $this->site_type(), 'entity_type' => 'auto' ], $candidate ) );
            break;
        }
        update_option( $flag, 1, false );
    }

    public function config( string $key, mixed $default = null ): mixed {
        return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
    }
}
