<?php

namespace Hexa\PluginCore\SchemaTools;

final class SchemaGraph {
    public static function clean( array $schema ): array {
        foreach ( $schema as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = self::clean( $value );
            }

            if ( null === $value || false === $value || '' === $value || [] === $value ) {
                unset( $schema[ $key ] );
                continue;
            }

            $schema[ $key ] = $value;
        }

        return $schema;
    }

    public static function types( array $schema ): array {
        $nodes = isset( $schema['@graph'] ) && is_array( $schema['@graph'] ) ? $schema['@graph'] : [ $schema ];
        $types = [];

        foreach ( $nodes as $node ) {
            if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
                continue;
            }

            foreach ( (array) $node['@type'] as $type ) {
                if ( is_scalar( $type ) && '' !== (string) $type ) {
                    $types[] = (string) $type;
                }
            }
        }

        return array_values( array_unique( $types ) );
    }

    public static function ref( string $id ): array {
        return '' === trim( $id ) ? [] : [ '@id' => $id ];
    }

    public static function refs( array $ids ): array {
        $refs = [];
        foreach ( $ids as $id ) {
            if ( ! is_scalar( $id ) || '' === trim( (string) $id ) ) {
                continue;
            }
            $refs[] = [ '@id' => (string) $id ];
        }

        return $refs;
    }

    public static function duration_from_seconds( int $seconds ): string {
        if ( $seconds <= 0 ) {
            return '';
        }

        $hours = intdiv( $seconds, HOUR_IN_SECONDS );
        $seconds -= $hours * HOUR_IN_SECONDS;
        $minutes = intdiv( $seconds, MINUTE_IN_SECONDS );
        $seconds -= $minutes * MINUTE_IN_SECONDS;

        $duration = 'PT';
        if ( $hours > 0 ) {
            $duration .= $hours . 'H';
        }
        if ( $minutes > 0 ) {
            $duration .= $minutes . 'M';
        }
        if ( $seconds > 0 || 'PT' === $duration ) {
            $duration .= $seconds . 'S';
        }

        return $duration;
    }

    public static function validator_url( string $url ): string {
        return '' === trim( $url ) ? '' : 'https://validator.schema.org/#url=' . rawurlencode( $url );
    }
}
