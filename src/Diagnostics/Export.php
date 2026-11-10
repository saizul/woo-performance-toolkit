<?php
/**
 * Builds the support export (JSON) from a diagnostics snapshot (no WordPress dependency).
 *
 * The export is meant to be attached to a support request, so it is deliberately
 * limited: environment versions, plugin settings (free-text lists are reduced to a
 * line count), the diagnostics metrics and the recommendations. It never contains
 * site URLs, users, emails, order data or option values.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

use SaizulAmin\WooPerformanceToolkit\Settings\Options;
use SaizulAmin\WooPerformanceToolkit\Support\Lines;

final class Export {

	public const SCHEMA_VERSION = 1;

	/** Environment keys that are copied to the export; anything else is dropped. */
	private const ENVIRONMENT_KEYS = array(
		'plugin_version',
		'wp_version',
		'wc_version',
		'php_version',
		'db_version',
		'memory_limit',
		'multisite',
		'object_cache',
		'active_plugin_count',
	);

	/**
	 * @param array<string,mixed> $snapshot    Collector snapshot.
	 * @param array<string,mixed> $settings    Resolved plugin settings.
	 * @param array<string,mixed> $environment Versions and runtime facts supplied by the caller.
	 * @param string              $generated_at ISO 8601 timestamp.
	 * @return array<string,mixed>
	 */
	public static function build( array $snapshot, array $settings, array $environment, string $generated_at ): array {
		$recommendations = array_map(
			static fn( Recommendation $r ): array => $r->toArray(),
			Recommendations::build( $snapshot )
		);

		return array(
			'schema_version'  => self::SCHEMA_VERSION,
			'generated_at'    => $generated_at,
			'environment'     => self::environment( $environment ),
			'settings'        => self::settings( $settings ),
			'metrics'         => self::metrics( $snapshot ),
			'recommendations' => $recommendations,
		);
	}

	/**
	 * @param array<string,mixed> $environment Raw environment.
	 * @return array<string,mixed>
	 */
	public static function environment( array $environment ): array {
		$clean = array();

		foreach ( self::ENVIRONMENT_KEYS as $key ) {
			if ( ! array_key_exists( $key, $environment ) ) {
				continue;
			}

			$value = $environment[ $key ];
			if ( is_bool( $value ) || is_int( $value ) || null === $value ) {
				$clean[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$clean[ $key ] = substr( preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) ?? '', 0, 100 );
			}
		}

		return $clean;
	}

	/**
	 * Booleans, numbers and short enum-like values are kept; free-text lists
	 * (allow-lists, handles) are reduced to how many entries they hold.
	 *
	 * @param array<string,mixed> $settings Resolved settings.
	 * @return array<string,mixed>
	 */
	public static function settings( array $settings ): array {
		$clean = array();

		foreach ( array_keys( Options::defaults() ) as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$value = $settings[ $key ];
			if ( is_bool( $value ) || is_int( $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_string( $value ) && 1 === preg_match( '/^[a-z_]{1,20}$/', $value ) ) {
				$clean[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$clean[ $key ] = array( 'entries' => count( Lines::parse( $value ) ) );
			}
		}

		return $clean;
	}

	/**
	 * @param array<string,mixed> $snapshot Collector snapshot.
	 * @return array<string,mixed>
	 */
	public static function metrics( array $snapshot ): array {
		$options = array();
		foreach ( is_array( $snapshot['largest_options'] ?? null ) ? $snapshot['largest_options'] : array() as $row ) {
			if ( is_array( $row ) && isset( $row['name'], $row['size'] ) ) {
				$options[] = array(
					'name'  => (string) $row['name'],
					'bytes' => (int) $row['size'],
				);
			}
		}

		$outliers = array();
		foreach ( is_array( $snapshot['variation_outliers'] ?? null ) ? $snapshot['variation_outliers'] : array() as $row ) {
			if ( is_array( $row ) && isset( $row['product_id'], $row['variations'] ) ) {
				$outliers[] = array(
					'product_id' => (int) $row['product_id'],
					'variations' => (int) $row['variations'],
				);
			}
		}

		$counts = array();
		foreach ( is_array( $snapshot['cleanup_counts'] ?? null ) ? $snapshot['cleanup_counts'] : array() as $task => $rows ) {
			$counts[ (string) $task ] = (int) $rows;
		}
		ksort( $counts );

		return array(
			'hpos_enabled'       => $snapshot['hpos_enabled'] ?? null,
			'object_cache'       => isset( $snapshot['object_cache'] ) ? (bool) $snapshot['object_cache'] : null,
			'autoload_bytes'     => isset( $snapshot['autoload_bytes'] ) ? (int) $snapshot['autoload_bytes'] : null,
			'largest_options'    => $options,
			'cleanup_counts'     => $counts,
			'cleanup_scheduled'  => isset( $snapshot['cleanup_scheduled'] ) ? (bool) $snapshot['cleanup_scheduled'] : null,
			'as_pending'         => isset( $snapshot['as_pending'] ) ? (int) $snapshot['as_pending'] : null,
			'as_past_due'        => isset( $snapshot['as_past_due'] ) ? (int) $snapshot['as_past_due'] : null,
			'as_failed'          => isset( $snapshot['as_failed'] ) ? (int) $snapshot['as_failed'] : null,
			'variation_outliers' => $outliers,
		);
	}

	/**
	 * Pretty-printed JSON.
	 *
	 * @param array<string,mixed> $report Output of build().
	 */
	public static function toJson( array $report ): string {
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

		return json_encode( $report, $flags ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure class without WordPress.
	}

	/**
	 * Download file name such as woo-performance-diagnostics-example-com-20261110.json.
	 *
	 * @param string $host Site host name (may be empty).
	 * @param string $date Date as Ymd.
	 */
	public static function fileName( string $host, string $date ): string {
		$slug = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $host ) ), '-' );
		$slug = substr( $slug, 0, 40 );
		$date = (string) preg_replace( '/\D/', '', $date );

		return 'woo-performance-diagnostics' . ( '' === $slug ? '' : '-' . $slug ) . ( '' === $date ? '' : '-' . $date ) . '.json';
	}
}
