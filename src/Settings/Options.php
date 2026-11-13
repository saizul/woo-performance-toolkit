<?php
/**
 * Settings schema: defaults and sanitisation (no WordPress dependency).
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Settings;

use SaizulAmin\WooPerformanceToolkit\Support\Lines;

final class Options {

	public const MODE_DEFAULT = 'default';
	public const MODE_SLOW    = 'slow';
	public const MODE_DISABLE = 'disable';

	public const FREQ_DAILY  = 'daily';
	public const FREQ_WEEKLY = 'weekly';

	/**
	 * Safe defaults: only the heartbeat throttle is active out of the box.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'cart_fragments_disable'           => false,
			'cart_fragments_allowlist'         => '',
			'assets_dequeue'                   => false,
			'assets_keep_handles'              => '',
			'marketing_disable'                => false,
			'wc_admin_disable'                 => false,
			'heartbeat_admin_mode'             => self::MODE_SLOW,
			'heartbeat_admin_interval'         => 60,
			'heartbeat_frontend_mode'          => self::MODE_SLOW,
			'heartbeat_frontend_interval'      => 120,
			'cleanup_enabled'                  => false,
			'cleanup_frequency'                => self::FREQ_DAILY,
			'cleanup_task_sessions'            => true,
			'cleanup_task_transients'          => true,
			'cleanup_task_action_scheduler'    => true,
			'cleanup_task_orphan_item_meta'    => true,
			// Opt-in: these touch core WordPress tables, so existing schedules
			// keep their behaviour until the site owner enables them.
			'cleanup_task_orphan_order_meta'   => false,
			'cleanup_task_orphan_post_meta'    => false,
			'cleanup_task_orphan_term_meta'    => false,
			'cleanup_task_orphan_comment_meta' => false,
			'cleanup_retention_days'           => 30,
			'cleanup_batch_size'               => 1000,
		);
	}

	/**
	 * Keys holding booleans.
	 *
	 * @return string[]
	 */
	public static function booleanKeys(): array {
		return array(
			'cart_fragments_disable',
			'assets_dequeue',
			'marketing_disable',
			'wc_admin_disable',
			'cleanup_enabled',
			'cleanup_task_sessions',
			'cleanup_task_transients',
			'cleanup_task_action_scheduler',
			'cleanup_task_orphan_item_meta',
			'cleanup_task_orphan_order_meta',
			'cleanup_task_orphan_post_meta',
			'cleanup_task_orphan_term_meta',
			'cleanup_task_orphan_comment_meta',
		);
	}

	/**
	 * Sanitise a submitted settings array.
	 *
	 * Missing boolean keys are treated as false (an unchecked checkbox is not
	 * submitted). Missing non-boolean keys fall back to the default.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$out      = array();

		foreach ( self::booleanKeys() as $key ) {
			$out[ $key ] = array_key_exists( $key, $input ) && self::toBool( $input[ $key ] );
		}

		$out['cart_fragments_allowlist'] = Lines::normalize( self::toString( $input['cart_fragments_allowlist'] ?? '' ) );
		$out['assets_keep_handles']      = Lines::normalize( self::toString( $input['assets_keep_handles'] ?? '' ) );

		$modes = array( self::MODE_DEFAULT, self::MODE_SLOW, self::MODE_DISABLE );

		$out['heartbeat_admin_mode']    = self::toEnum( $input['heartbeat_admin_mode'] ?? null, $modes, (string) $defaults['heartbeat_admin_mode'] );
		$out['heartbeat_frontend_mode'] = self::toEnum( $input['heartbeat_frontend_mode'] ?? null, $modes, (string) $defaults['heartbeat_frontend_mode'] );

		$out['heartbeat_admin_interval']    = self::toInt( $input['heartbeat_admin_interval'] ?? null, 15, 300, (int) $defaults['heartbeat_admin_interval'] );
		$out['heartbeat_frontend_interval'] = self::toInt( $input['heartbeat_frontend_interval'] ?? null, 15, 300, (int) $defaults['heartbeat_frontend_interval'] );

		$out['cleanup_frequency']      = self::toEnum( $input['cleanup_frequency'] ?? null, array( self::FREQ_DAILY, self::FREQ_WEEKLY ), (string) $defaults['cleanup_frequency'] );
		$out['cleanup_retention_days'] = self::toInt( $input['cleanup_retention_days'] ?? null, 1, 3650, (int) $defaults['cleanup_retention_days'] );
		$out['cleanup_batch_size']     = self::toInt( $input['cleanup_batch_size'] ?? null, 100, 10000, (int) $defaults['cleanup_batch_size'] );

		return array_merge( $defaults, $out );
	}

	/**
	 * Merge stored values over the defaults, then sanitise.
	 *
	 * @param mixed $stored Value returned by get_option().
	 * @return array<string,mixed>
	 */
	public static function resolve( mixed $stored ): array {
		$merged = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		return self::sanitize( $merged );
	}

	/**
	 * Loose boolean conversion for form/stored values.
	 */
	public static function toBool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return false;
	}

	private static function toString( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}

	/**
	 * @param string[] $allowed Allowed values.
	 */
	private static function toEnum( mixed $value, array $allowed, string $fallback ): string {
		return ( is_string( $value ) && in_array( $value, $allowed, true ) ) ? $value : $fallback;
	}

	private static function toInt( mixed $value, int $min, int $max, int $fallback ): int {
		if ( is_int( $value ) ) {
			$number = $value;
		} elseif ( is_string( $value ) && preg_match( '/^\s*-?\d+\s*$/', $value ) ) {
			$number = (int) $value;
		} else {
			return $fallback;
		}

		return max( $min, min( $max, $number ) );
	}
}
