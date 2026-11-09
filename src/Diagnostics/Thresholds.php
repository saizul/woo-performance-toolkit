<?php
/**
 * Warning / critical thresholds for diagnostics metrics.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

use InvalidArgumentException;

final class Thresholds {

	public const GOOD     = 'good';
	public const WARNING  = 'warning';
	public const CRITICAL = 'critical';

	public const AUTOLOAD_BYTES = 'autoload_bytes';
	public const AS_PENDING     = 'as_pending';
	public const AS_PAST_DUE    = 'as_past_due';
	public const AS_FAILED      = 'as_failed';
	public const VARIATIONS     = 'variations';

	/** Lowest PHP version without a warning. */
	public const PHP_RECOMMENDED = '8.3';

	/** Lowest PHP version that is not critical. */
	public const PHP_MINIMUM = '8.1';

	/**
	 * Metric => [warning at or above, critical at or above].
	 *
	 * @return array<string,array{0:int,1:int}>
	 */
	public static function limits(): array {
		return array(
			self::AUTOLOAD_BYTES => array( 800000, 2500000 ),
			self::AS_PENDING     => array( 1000, 10000 ),
			self::AS_PAST_DUE    => array( 100, 1000 ),
			self::AS_FAILED      => array( 50, 500 ),
			self::VARIATIONS     => array( 50, 200 ),
		);
	}

	/**
	 * Classify a numeric metric.
	 *
	 * @throws InvalidArgumentException For an unknown metric.
	 */
	public static function classify( string $metric, int $value ): string {
		$limits = self::limits();

		if ( ! isset( $limits[ $metric ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown metric "%s".', $metric ) );
		}

		list( $warning, $critical ) = $limits[ $metric ];

		if ( $value >= $critical ) {
			return self::CRITICAL;
		}

		if ( $value >= $warning ) {
			return self::WARNING;
		}

		return self::GOOD;
	}

	/**
	 * Classify a PHP version string.
	 */
	public static function classifyPhp( string $version ): string {
		if ( version_compare( $version, self::PHP_MINIMUM, '<' ) ) {
			return self::CRITICAL;
		}

		if ( version_compare( $version, self::PHP_RECOMMENDED, '<' ) ) {
			return self::WARNING;
		}

		return self::GOOD;
	}
}
