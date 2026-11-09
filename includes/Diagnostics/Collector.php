<?php
/**
 * Gathers the diagnostics snapshot from the live site.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Diagnostics;

use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\OptionSizer;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\SqlBuilder;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Thresholds;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Runner;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class Collector {

	public function __construct( private \wpdb $wpdb ) {
	}

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 * @return array<string,mixed>
	 */
	public function snapshot( array $settings ): array {
		$runner = new Runner( $this->wpdb );
		$tables = $runner->tables();

		$snapshot = array(
			'hpos_enabled'      => $this->hpos_enabled(),
			'object_cache'      => (bool) wp_using_ext_object_cache(),
			'php_version'       => PHP_VERSION,
			'autoload_bytes'    => $this->var( SqlBuilder::autoloadTotalBytes( $tables['options'] ) ),
			'largest_options'   => OptionSizer::normalizeRows(
				$this->rows( SqlBuilder::largestAutoloadedOptions( $tables['options'], 10 ) )
			),
			'cleanup_counts'    => $runner->count( Tasks::all(), $settings )->toArray(),
			'cleanup_scheduled' => ! empty( $settings['cleanup_enabled'] ),
			'as_pending'        => null,
			'as_past_due'       => null,
			'as_failed'         => null,
		);

		if ( $runner->table_exists( $tables['as_actions'] ) ) {
			$by_status = array();
			foreach ( $this->rows( SqlBuilder::actionBacklogByStatus( $tables['as_actions'] ) ) as $row ) {
				$by_status[ (string) $row['status'] ] = (int) $row['total'];
			}

			$snapshot['as_pending']  = ( $by_status['pending'] ?? 0 ) + ( $by_status['past-due'] ?? 0 );
			$snapshot['as_failed']   = $by_status['failed'] ?? 0;
			$snapshot['as_past_due'] = $this->var( SqlBuilder::actionsPastDue( $tables['as_actions'], gmdate( 'Y-m-d H:i:s' ) ) );
		}

		/**
		 * Filter the variation-count threshold used to flag outlier products.
		 *
		 * @param int $threshold Default 50 (the "warning" limit).
		 */
		$threshold = (int) apply_filters(
			'woo_performance_toolkit_variation_threshold',
			Thresholds::limits()[ Thresholds::VARIATIONS ][0]
		);

		$snapshot['variation_outliers'] = $this->rows( SqlBuilder::variationOutliers( $this->wpdb->posts, $threshold, 10 ) );

		return $snapshot;
	}

	private function hpos_enabled(): ?bool {
		if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			return (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return null;
	}

	private function prepare( Query $query ): string {
		return $this->wpdb->prepare( $query->sql, ...$query->args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function var( Query $query ): int {
		return (int) $this->wpdb->get_var( $this->prepare( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function rows( Query $query ): array {
		return (array) $this->wpdb->get_results( $this->prepare( $query ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
