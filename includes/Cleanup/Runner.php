<?php
/**
 * Executes (or counts) the cleanup tasks against the database.
 *
 * All SQL comes from the unit-tested SqlBuilder and is passed through
 * $wpdb->prepare().
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Cleanup;

use SaizulAmin\WooPerformanceToolkit\Cleanup\ResultSet;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Retention;
use SaizulAmin\WooPerformanceToolkit\Cleanup\SqlBuilder;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Cleanup\TransientNames;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class Runner {

	/** @var array<string,bool> */
	private array $table_cache = array();

	public function __construct( private \wpdb $wpdb, private ?int $now = null ) {
	}

	/**
	 * Fully-qualified table names.
	 *
	 * @return array<string,string>
	 */
	public function tables(): array {
		$prefix = $this->wpdb->prefix;

		return array(
			'sessions'    => $prefix . 'woocommerce_sessions',
			'options'     => $this->wpdb->options,
			'as_actions'  => $prefix . 'actionscheduler_actions',
			'as_logs'     => $prefix . 'actionscheduler_logs',
			'order_items' => $prefix . 'woocommerce_order_items',
			'order_meta'  => $prefix . 'woocommerce_order_itemmeta',
		);
	}

	public function table_exists( string $table ): bool {
		if ( ! isset( $this->table_cache[ $table ] ) ) {
			$pattern = $this->wpdb->esc_like( $table );
			$sql     = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern );
			$found   = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$this->table_cache[ $table ] = ( $found === $table );
		}

		return $this->table_cache[ $table ];
	}

	/**
	 * Dry run: how many rows each task would remove.
	 *
	 * @param string[]            $tasks    Task names.
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function count( array $tasks, array $settings ): ResultSet {
		$result = new ResultSet();

		foreach ( $tasks as $task ) {
			$result->add( $task, $this->count_task( $task, $settings ) );
		}

		return $result;
	}

	/**
	 * Delete rows in batches.
	 *
	 * @param string[]            $tasks       Task names.
	 * @param array<string,mixed> $settings    Resolved settings.
	 * @param int                 $max_batches Safety cap on batches per task.
	 */
	public function run( array $tasks, array $settings, int $max_batches = 20 ): ResultSet {
		$result = new ResultSet();
		$limit  = Retention::clampBatch( (int) $settings['cleanup_batch_size'] );

		foreach ( $tasks as $task ) {
			Tasks::assertValid( $task );

			for ( $i = 0; $i < $max_batches; $i++ ) {
				$deleted = $this->batch( $task, $settings, $limit );
				$result->add( $task, $deleted );

				if ( $deleted < 1 ) {
					break;
				}
			}
		}

		return $result;
	}

	private function now(): int {
		return $this->now ?? time();
	}

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	private function count_task( string $task, array $settings ): int {
		$t = $this->tables();

		switch ( $task ) {
			case Tasks::SESSIONS:
				return $this->table_exists( $t['sessions'] )
					? $this->var( SqlBuilder::countExpiredSessions( $t['sessions'], $this->now() ) )
					: 0;

			case Tasks::TRANSIENTS:
				return $this->var( SqlBuilder::countExpiredTransients( $t['options'], false, $this->now() ) )
					+ $this->var( SqlBuilder::countExpiredTransients( $t['options'], true, $this->now() ) );

			case Tasks::ACTION_SCHEDULER:
				return $this->table_exists( $t['as_actions'] )
					? $this->var( SqlBuilder::countFinishedActions( $t['as_actions'], $this->cutoff( $settings ) ) )
					: 0;

			case Tasks::ORPHAN_ITEM_META:
				return ( $this->table_exists( $t['order_meta'] ) && $this->table_exists( $t['order_items'] ) )
					? $this->var( SqlBuilder::countOrphanItemMeta( $t['order_meta'], $t['order_items'] ) )
					: 0;
		}

		return 0;
	}

	/**
	 * Delete one batch for a task; returns the number of "units" removed.
	 *
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	private function batch( string $task, array $settings, int $limit ): int {
		$t = $this->tables();

		switch ( $task ) {
			case Tasks::SESSIONS:
				if ( ! $this->table_exists( $t['sessions'] ) ) {
					return 0;
				}
				return $this->exec( SqlBuilder::deleteExpiredSessions( $t['sessions'], $this->now(), $limit ) );

			case Tasks::TRANSIENTS:
				return $this->transient_batch( false, $limit ) + $this->transient_batch( true, $limit );

			case Tasks::ACTION_SCHEDULER:
				if ( ! $this->table_exists( $t['as_actions'] ) ) {
					return 0;
				}
				$ids = $this->col( SqlBuilder::selectFinishedActionIds( $t['as_actions'], $this->cutoff( $settings ), $limit ) );
				foreach ( array_chunk( $ids, 500 ) as $chunk ) {
					if ( $this->table_exists( $t['as_logs'] ) ) {
						$this->exec( SqlBuilder::deleteActionLogs( $t['as_logs'], $chunk ) );
					}
					$this->exec( SqlBuilder::deleteActions( $t['as_actions'], $chunk ) );
				}
				return count( $ids );

			case Tasks::ORPHAN_ITEM_META:
				if ( ! $this->table_exists( $t['order_meta'] ) || ! $this->table_exists( $t['order_items'] ) ) {
					return 0;
				}
				$ids = $this->col( SqlBuilder::selectOrphanItemMetaIds( $t['order_meta'], $t['order_items'], $limit ) );
				foreach ( array_chunk( $ids, 500 ) as $chunk ) {
					$this->exec( SqlBuilder::deleteOrphanItemMeta( $t['order_meta'], $chunk ) );
				}
				return count( $ids );
		}

		return 0;
	}

	private function transient_batch( bool $site, int $limit ): int {
		$options = $this->tables()['options'];
		$names   = $this->col( SqlBuilder::selectExpiredTransientTimeouts( $options, $site, $this->now(), $limit ) );

		if ( array() === $names ) {
			return 0;
		}

		foreach ( array_chunk( TransientNames::namesToDelete( $names, $site ), 500 ) as $chunk ) {
			$this->exec( SqlBuilder::deleteOptionsByNames( $options, $chunk ) );
		}

		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return count( $names );
	}

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	private function cutoff( array $settings ): string {
		return Retention::cutoffGmt( $this->now(), (int) $settings['cleanup_retention_days'] );
	}

	private function prepare( Query $query ): string {
		// The template only contains %i/%s/%d placeholders (see SqlBuilder).
		return $this->wpdb->prepare( $query->sql, ...$query->args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function var( Query $query ): int {
		return (int) $this->wpdb->get_var( $this->prepare( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return int[]|string[]
	 */
	private function col( Query $query ): array {
		return (array) $this->wpdb->get_col( $this->prepare( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function exec( Query $query ): int {
		return (int) $this->wpdb->query( $this->prepare( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
