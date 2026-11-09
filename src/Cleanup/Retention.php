<?php
/**
 * Retention and batch-size maths.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

final class Retention {

	public const MIN_DAYS = 1;
	public const MAX_DAYS = 3650;

	public const MIN_BATCH = 100;
	public const MAX_BATCH = 10000;

	public static function clampDays( int $days ): int {
		return max( self::MIN_DAYS, min( self::MAX_DAYS, $days ) );
	}

	public static function clampBatch( int $batch ): int {
		return max( self::MIN_BATCH, min( self::MAX_BATCH, $batch ) );
	}

	/**
	 * Unix timestamp before which records are considered old.
	 */
	public static function cutoffTimestamp( int $now, int $days ): int {
		return $now - ( self::clampDays( $days ) * 86400 );
	}

	/**
	 * Same cutoff as a GMT MySQL datetime string.
	 */
	public static function cutoffGmt( int $now, int $days ): string {
		return gmdate( 'Y-m-d H:i:s', self::cutoffTimestamp( $now, $days ) );
	}
}
