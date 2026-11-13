<?php
/**
 * Cleanup task registry.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

use InvalidArgumentException;

final class Tasks {

	public const SESSIONS         = 'sessions';
	public const TRANSIENTS       = 'transients';
	public const ACTION_SCHEDULER = 'action_scheduler';
	public const ORPHAN_ITEM_META = 'orphan_item_meta';

	public const ORPHAN_ORDER_META   = 'orphan_order_meta';
	public const ORPHAN_POST_META    = 'orphan_post_meta';
	public const ORPHAN_TERM_META    = 'orphan_term_meta';
	public const ORPHAN_COMMENT_META = 'orphan_comment_meta';

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::SESSIONS,
			self::TRANSIENTS,
			self::ACTION_SCHEDULER,
			self::ORPHAN_ITEM_META,
			self::ORPHAN_ORDER_META,
			self::ORPHAN_POST_META,
			self::ORPHAN_TERM_META,
			self::ORPHAN_COMMENT_META,
		);
	}

	/**
	 * Human-readable (English) labels.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			self::SESSIONS            => 'Expired WooCommerce sessions',
			self::TRANSIENTS          => 'Expired transients',
			self::ACTION_SCHEDULER    => 'Old Action Scheduler completed/failed actions and logs',
			self::ORPHAN_ITEM_META    => 'Orphaned order item meta',
			self::ORPHAN_ORDER_META   => 'Orphaned HPOS order meta',
			self::ORPHAN_POST_META    => 'Orphaned post meta',
			self::ORPHAN_TERM_META    => 'Orphaned term meta',
			self::ORPHAN_COMMENT_META => 'Orphaned comment meta',
		);
	}

	/**
	 * Settings key that toggles a task.
	 */
	public static function settingKey( string $task ): string {
		self::assertValid( $task );

		return 'cleanup_task_' . $task;
	}

	/**
	 * Tasks enabled in the given settings array, in canonical order.
	 *
	 * @param array<string,mixed> $settings Resolved settings.
	 * @return string[]
	 */
	public static function enabledFromSettings( array $settings ): array {
		return array_values(
			array_filter(
				self::all(),
				static fn( string $task ): bool => ! empty( $settings[ self::settingKey( $task ) ] )
			)
		);
	}

	/**
	 * Parse a comma-separated task list (e.g. from WP-CLI).
	 *
	 * @return string[]
	 * @throws InvalidArgumentException On an unknown task name.
	 */
	public static function parseList( string $csv ): array {
		$requested = array_filter( array_map( 'trim', explode( ',', $csv ) ), static fn( string $v ): bool => '' !== $v );

		if ( array() === $requested ) {
			throw new InvalidArgumentException( 'No cleanup task given.' );
		}

		foreach ( $requested as $task ) {
			self::assertValid( $task );
		}

		return array_values( array_intersect( self::all(), $requested ) );
	}

	/**
	 * @throws InvalidArgumentException When the task is unknown.
	 */
	public static function assertValid( string $task ): void {
		if ( ! in_array( $task, self::all(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown cleanup task "%s".', $task ) );
		}
	}
}
