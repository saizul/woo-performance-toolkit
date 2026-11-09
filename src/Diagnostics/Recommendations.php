<?php
/**
 * Turns a diagnostics snapshot into prioritised recommendations.
 *
 * Snapshot keys (all optional; missing data produces no recommendation):
 *  - hpos_enabled     ?bool   null when WooCommerce is unavailable
 *  - object_cache     bool    persistent object cache present
 *  - autoload_bytes   int
 *  - largest_options  array   list of [name, size]
 *  - as_pending       ?int    null when Action Scheduler tables are missing
 *  - as_past_due      ?int
 *  - as_failed        ?int
 *  - variation_outliers array list of [product_id, variations]
 *  - php_version      string
 *  - cleanup_counts   array   task => reclaimable rows
 *  - cleanup_scheduled bool
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Support\Bytes;

final class Recommendations {

	/** Reclaimable rows above which enabling scheduled cleanup is suggested. */
	public const CLEANUP_SUGGEST_ROWS = 1000;

	/**
	 * @param array<string,mixed> $snapshot Diagnostics snapshot.
	 * @return Recommendation[] Most urgent first.
	 */
	public static function build( array $snapshot ): array {
		$list = array();

		self::hpos( $snapshot, $list );
		self::autoload( $snapshot, $list );
		self::objectCache( $snapshot, $list );
		self::actionScheduler( $snapshot, $list );
		self::variations( $snapshot, $list );
		self::php( $snapshot, $list );
		self::cleanup( $snapshot, $list );

		if ( array() === $list ) {
			$list[] = new Recommendation(
				Recommendation::INFO,
				'all_clear',
				'No issues found',
				'None of the checked metrics exceeded its threshold.'
			);
		}

		// Stable sort: most urgent first, otherwise insertion order.
		$indexed = array_map(
			static fn( Recommendation $r, int $i ): array => array( $r, $i ),
			$list,
			array_keys( $list )
		);

		usort(
			$indexed,
			static fn( array $a, array $b ): int => ( $b[0]->weight() <=> $a[0]->weight() ) ?: ( $a[1] <=> $b[1] )
		);

		return array_map( static fn( array $pair ): Recommendation => $pair[0], $indexed );
	}

	/**
	 * Highest severity present in a list.
	 *
	 * @param Recommendation[] $recommendations Recommendations.
	 */
	public static function worstSeverity( array $recommendations ): string {
		$worst = Thresholds::GOOD;
		$score = 0;

		foreach ( $recommendations as $rec ) {
			if ( $rec->weight() > $score && Recommendation::INFO !== $rec->severity ) {
				$score = $rec->weight();
				$worst = $rec->severity;
			}
		}

		return $worst;
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function hpos( array $snapshot, array &$list ): void {
		if ( array_key_exists( 'hpos_enabled', $snapshot ) && false === $snapshot['hpos_enabled'] ) {
			$list[] = new Recommendation(
				Thresholds::WARNING,
				'hpos_disabled',
				'High-Performance Order Storage is not enabled',
				'Orders are stored as posts. Enable HPOS under WooCommerce > Settings > Advanced > Features for faster order queries (test first and keep a backup).'
			);
		}
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function autoload( array $snapshot, array &$list ): void {
		if ( ! isset( $snapshot['autoload_bytes'] ) ) {
			return;
		}

		$bytes = (int) $snapshot['autoload_bytes'];
		$level = Thresholds::classify( Thresholds::AUTOLOAD_BYTES, $bytes );

		if ( Thresholds::GOOD === $level ) {
			return;
		}

		$top = OptionSizer::top( is_array( $snapshot['largest_options'] ?? null ) ? $snapshot['largest_options'] : array(), 3 );
		$who = array() === $top ? '' : ' Largest: ' . implode(
			', ',
			array_map( static fn( array $o ): string => $o['name'] . ' (' . Bytes::format( $o['size'] ) . ')', $top )
		) . '.';

		$list[] = new Recommendation(
			$level,
			'autoload_large',
			'Autoloaded options are large (' . Bytes::format( $bytes ) . ')',
			'Every request loads these options into memory. Remove data left by uninstalled plugins or set large, rarely used options to not autoload.' . $who
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function objectCache( array $snapshot, array &$list ): void {
		if ( ! array_key_exists( 'object_cache', $snapshot ) || true === $snapshot['object_cache'] ) {
			return;
		}

		$bytes    = (int) ( $snapshot['autoload_bytes'] ?? 0 );
		$severity = Thresholds::GOOD !== Thresholds::classify( Thresholds::AUTOLOAD_BYTES, $bytes ) ? Thresholds::WARNING : Recommendation::INFO;

		$list[] = new Recommendation(
			$severity,
			'object_cache_missing',
			'No persistent object cache',
			'Install Redis or Memcached with a matching drop-in to avoid repeated database reads, especially for options and sessions.'
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function actionScheduler( array $snapshot, array &$list ): void {
		$checks = array(
			array( 'as_pending', Thresholds::AS_PENDING, 'as_backlog', 'Action Scheduler has a large backlog', '%d pending actions. Check that WP-Cron or a real cron job triggers the queue runner, and look for plugins scheduling too many actions.' ),
			array( 'as_past_due', Thresholds::AS_PAST_DUE, 'as_past_due', 'Action Scheduler actions are overdue', '%d pending actions are past their scheduled time. The queue runner is probably not running often enough.' ),
			array( 'as_failed', Thresholds::AS_FAILED, 'as_failed', 'Many failed Action Scheduler actions', '%d failed actions are stored. Review them under Tools > Scheduled Actions and fix or purge the cause.' ),
		);

		foreach ( $checks as list( $key, $metric, $code, $title, $detail ) ) {
			if ( ! isset( $snapshot[ $key ] ) ) {
				continue;
			}

			$value = (int) $snapshot[ $key ];
			$level = Thresholds::classify( $metric, $value );

			if ( Thresholds::GOOD !== $level ) {
				$list[] = new Recommendation( $level, $code, $title, sprintf( $detail, $value ) );
			}
		}
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function variations( array $snapshot, array &$list ): void {
		$outliers = is_array( $snapshot['variation_outliers'] ?? null ) ? $snapshot['variation_outliers'] : array();

		if ( array() === $outliers ) {
			return;
		}

		$max   = 0;
		$names = array();

		foreach ( $outliers as $row ) {
			$count = (int) ( $row['variations'] ?? 0 );
			$max   = max( $max, $count );

			if ( count( $names ) < 3 ) {
				$names[] = '#' . (int) ( $row['product_id'] ?? 0 ) . ' (' . $count . ')';
			}
		}

		$list[] = new Recommendation(
			Thresholds::classify( Thresholds::VARIATIONS, $max ) === Thresholds::CRITICAL ? Thresholds::CRITICAL : Thresholds::WARNING,
			'variation_outliers',
			count( $outliers ) . ' product(s) with a very high number of variations',
			'Products with many variations make admin screens and variation lookups slow. Consider splitting them or using fewer attribute combinations. Worst: ' . implode( ', ', $names ) . '.'
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function php( array $snapshot, array &$list ): void {
		if ( empty( $snapshot['php_version'] ) || ! is_string( $snapshot['php_version'] ) ) {
			return;
		}

		$level = Thresholds::classifyPhp( $snapshot['php_version'] );

		if ( Thresholds::GOOD === $level ) {
			return;
		}

		$list[] = new Recommendation(
			$level,
			'php_outdated',
			'PHP ' . $snapshot['php_version'] . ' is outdated',
			'Upgrade to PHP ' . Thresholds::PHP_RECOMMENDED . ' or newer for better performance and security support.'
		);
	}

	/**
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @param Recommendation[]    $list     Output list.
	 */
	private static function cleanup( array $snapshot, array &$list ): void {
		$counts = is_array( $snapshot['cleanup_counts'] ?? null ) ? $snapshot['cleanup_counts'] : array();
		$total  = 0;

		foreach ( Tasks::all() as $task ) {
			$total += max( 0, (int) ( $counts[ $task ] ?? 0 ) );
		}

		if ( $total < self::CLEANUP_SUGGEST_ROWS || ! empty( $snapshot['cleanup_scheduled'] ) ) {
			return;
		}

		$list[] = new Recommendation(
			Thresholds::WARNING,
			'cleanup_available',
			number_format( $total ) . ' rows can be cleaned up',
			'Enable scheduled cleanup on the Cleanup tab, or run "wp woo-perf cleanup --dry-run" to preview.'
		);
	}
}
