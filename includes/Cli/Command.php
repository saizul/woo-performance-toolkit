<?php
/**
 * WP-CLI commands: `wp woo-perf cleanup` and `wp woo-perf status`.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Cli;

use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Recommendations;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Runner;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Scheduler;
use SaizulAmin\WooPerformanceToolkit\Integration\Diagnostics\Collector;
use SaizulAmin\WooPerformanceToolkit\Integration\SettingsStore;
use SaizulAmin\WooPerformanceToolkit\Support\Bytes;

/**
 * Inspect and clean up WooCommerce performance hot spots.
 */
final class Command {

	/**
	 * Remove expired sessions, expired transients, old Action Scheduler records
	 * and orphaned order item meta.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Only count the rows that would be removed.
	 *
	 * [--task=<tasks>]
	 * : Comma-separated tasks: sessions, transients, action_scheduler, orphan_item_meta.
	 * Defaults to the tasks enabled in the plugin settings.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-perf cleanup --dry-run
	 *     wp woo-perf cleanup --task=sessions,transients --yes
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Associative arguments.
	 */
	public function cleanup( array $args, array $assoc_args ): void {
		global $wpdb;

		$settings = SettingsStore::get();

		try {
			$tasks = isset( $assoc_args['task'] )
				? Tasks::parseList( (string) $assoc_args['task'] )
				: Tasks::enabledFromSettings( $settings );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}

		if ( array() === $tasks ) {
			\WP_CLI::error( 'No cleanup tasks are enabled. Enable some in the settings or pass --task=<tasks>.' );
			return;
		}

		$runner = new Runner( $wpdb );
		$labels = Tasks::labels();
		$dry    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$counts = $runner->count( $tasks, $settings );

		$items = array();
		foreach ( $tasks as $task ) {
			$items[] = array(
				'task'        => $task,
				'description' => $labels[ $task ],
				'rows'        => $counts->get( $task ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $items, array( 'task', 'description', 'rows' ) );

		if ( $dry ) {
			\WP_CLI::success( sprintf( 'Dry run: %d rows would be removed. Nothing was changed.', $counts->total() ) );
			return;
		}

		if ( $counts->isEmpty() ) {
			\WP_CLI::success( 'Nothing to clean up.' );
			return;
		}

		\WP_CLI::confirm( sprintf( 'Permanently delete %d rows?', $counts->total() ), $assoc_args );

		// CLI has no request time limit, so allow many more batches than cron.
		$result = $runner->run( $tasks, $settings, 1000 );

		\WP_CLI::success( sprintf( 'Removed %d rows (%s).', $result->total(), $result->summary() ) );
	}

	/**
	 * Show enabled features, pending cleanup counts and diagnostics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, yaml.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp woo-perf status
	 *     wp woo-perf status --format=json
	 *
	 * @param string[]             $args       Positional arguments.
	 * @param array<string,string> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		global $wpdb;

		$settings = SettingsStore::get();
		$snapshot = ( new Collector( $wpdb ) )->snapshot( $settings );
		$next     = wp_next_scheduled( Scheduler::HOOK );
		$onoff    = static fn( $v ): string => $v ? 'on' : 'off';
		$na       = static fn( $v ): string => null === $v ? 'n/a' : (string) $v;

		$rows = array(
			array( 'item' => 'cart_fragments_disable', 'value' => $onoff( $settings['cart_fragments_disable'] ) ),
			array( 'item' => 'assets_dequeue', 'value' => $onoff( $settings['assets_dequeue'] ) ),
			array( 'item' => 'marketing_disable', 'value' => $onoff( $settings['marketing_disable'] ) ),
			array( 'item' => 'heartbeat_admin', 'value' => (string) $settings['heartbeat_admin_mode'] ),
			array( 'item' => 'heartbeat_frontend', 'value' => (string) $settings['heartbeat_frontend_mode'] ),
			array( 'item' => 'cleanup_scheduled', 'value' => $onoff( $settings['cleanup_enabled'] ) ),
			array( 'item' => 'cleanup_next_run', 'value' => $next ? gmdate( 'Y-m-d H:i:s', $next ) . ' UTC' : 'none' ),
		);

		foreach ( Tasks::all() as $task ) {
			$rows[] = array(
				'item'  => 'cleanup_pending_' . $task,
				'value' => (string) ( $snapshot['cleanup_counts'][ $task ] ?? 0 ),
			);
		}

		$rows[] = array( 'item' => 'hpos_enabled', 'value' => null === $snapshot['hpos_enabled'] ? 'unknown' : $onoff( $snapshot['hpos_enabled'] ) );
		$rows[] = array( 'item' => 'object_cache', 'value' => $onoff( $snapshot['object_cache'] ) );
		$rows[] = array( 'item' => 'autoload_size', 'value' => Bytes::format( (int) $snapshot['autoload_bytes'] ) );
		$rows[] = array( 'item' => 'as_pending', 'value' => $na( $snapshot['as_pending'] ) );
		$rows[] = array( 'item' => 'as_past_due', 'value' => $na( $snapshot['as_past_due'] ) );
		$rows[] = array( 'item' => 'as_failed', 'value' => $na( $snapshot['as_failed'] ) );
		$rows[] = array( 'item' => 'variation_outliers', 'value' => (string) count( $snapshot['variation_outliers'] ) );
		$rows[] = array( 'item' => 'php_version', 'value' => (string) $snapshot['php_version'] );

		\WP_CLI\Utils\format_items( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ), $rows, array( 'item', 'value' ) );

		foreach ( Recommendations::build( $snapshot ) as $rec ) {
			\WP_CLI::log( sprintf( '[%s] %s: %s', strtoupper( $rec->severity ), $rec->title, $rec->detail ) );
		}
	}
}
