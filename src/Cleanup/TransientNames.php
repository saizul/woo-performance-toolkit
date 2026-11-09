<?php
/**
 * Maps transient timeout option names to the rows that must be removed.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

final class TransientNames {

	public static function valuePrefix( bool $site ): string {
		return $site ? '_site_transient_' : '_transient_';
	}

	public static function timeoutPrefix( bool $site ): string {
		return $site ? '_site_transient_timeout_' : '_transient_timeout_';
	}

	/**
	 * Value option name that pairs with a timeout option name, or null if the
	 * given name is not a timeout row.
	 */
	public static function valueNameFor( string $timeoutName, bool $site ): ?string {
		$prefix = self::timeoutPrefix( $site );

		if ( ! str_starts_with( $timeoutName, $prefix ) || strlen( $timeoutName ) === strlen( $prefix ) ) {
			return null;
		}

		return self::valuePrefix( $site ) . substr( $timeoutName, strlen( $prefix ) );
	}

	/**
	 * Every option name to delete (timeout row plus value row), unique.
	 *
	 * @param string[] $timeoutNames Timeout option names.
	 * @return string[]
	 */
	public static function namesToDelete( array $timeoutNames, bool $site ): array {
		$names = array();

		foreach ( $timeoutNames as $timeout ) {
			$value = self::valueNameFor( (string) $timeout, $site );

			if ( null === $value ) {
				continue;
			}

			$names[] = (string) $timeout;
			$names[] = $value;
		}

		return array_values( array_unique( $names ) );
	}
}
