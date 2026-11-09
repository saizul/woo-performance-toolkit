<?php
/**
 * Byte formatting helpers.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Support;

final class Bytes {

	/**
	 * Format a byte count as a short human-readable string (1024 base).
	 */
	public static function format( int $bytes, int $precision = 1 ): string {
		if ( $bytes < 0 ) {
			$bytes = 0;
		}

		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}

		$units = array( 'KB', 'MB', 'GB', 'TB' );
		$value = $bytes / 1024;
		$index = 0;

		while ( $value >= 1024 && $index < count( $units ) - 1 ) {
			$value /= 1024;
			++$index;
		}

		$number = number_format( $value, max( 0, $precision ), '.', '' );
		if ( str_contains( $number, '.' ) ) {
			$number = rtrim( rtrim( $number, '0' ), '.' );
		}

		return $number . ' ' . $units[ $index ];
	}
}
