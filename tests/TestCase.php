<?php

namespace hpr_distributor\Tests;

final class TestCase {
    private static int $assertions = 0;

    public static function same( $expected, $actual, string $message ): void {
        self::$assertions++;

        if ( $expected !== $actual ) {
            throw new \RuntimeException(
                $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true )
            );
        }
    }

    public static function true( bool $condition, string $message ): void {
        self::$assertions++;

        if ( ! $condition ) {
            throw new \RuntimeException( $message );
        }
    }

    public static function false( bool $condition, string $message ): void {
        self::true( ! $condition, $message );
    }

    public static function contains( $needle, array $haystack, string $message ): void {
        self::$assertions++;

        if ( ! in_array( $needle, $haystack, true ) ) {
            throw new \RuntimeException( $message );
        }
    }

    public static function count(): int {
        return self::$assertions;
    }
}
