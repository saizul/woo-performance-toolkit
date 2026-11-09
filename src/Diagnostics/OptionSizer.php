<?php
/**
 * Autoloaded-option sizing helpers.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

final class OptionSizer {

	/**
	 * `autoload` column values that mean "loaded on every request".
	 *
	 * WordPress 6.6 introduced on / auto-on / auto next to the legacy "yes".
	 *
	 * @return string[]
	 */
	public static function autoloadValues(): array {
		return array( 'yes', 'on', 'auto-on', 'auto' );
	}

	/**
	 * Keep only valid rows, cast sizes to int, sort largest first.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows with "name" and "size".
	 * @return array<int,array{name:string,size:int}>
	 */
	public static function normalizeRows( array $rows ): array {
		$clean = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['name'], $row['size'] ) || ! is_numeric( $row['size'] ) ) {
				continue;
			}

			$name = (string) $row['name'];
			$size = (int) $row['size'];

			if ( '' === $name || $size < 0 ) {
				continue;
			}

			$clean[] = array(
				'name' => $name,
				'size' => $size,
			);
		}

		usort(
			$clean,
			static fn( array $a, array $b ): int => ( $b['size'] <=> $a['size'] ) ?: strcmp( $a['name'], $b['name'] )
		);

		return $clean;
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Rows with "name" and "size".
	 */
	public static function total( array $rows ): int {
		return array_sum( array_column( self::normalizeRows( $rows ), 'size' ) );
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Rows with "name" and "size".
	 * @return array<int,array{name:string,size:int}>
	 */
	public static function top( array $rows, int $limit ): array {
		return array_slice( self::normalizeRows( $rows ), 0, max( 0, $limit ) );
	}

	/**
	 * Percentage (0-100, one decimal) of the total.
	 */
	public static function share( int $size, int $total ): float {
		if ( $total <= 0 || $size <= 0 ) {
			return 0.0;
		}

		return round( min( 100, ( $size / $total ) * 100 ), 1 );
	}
}
