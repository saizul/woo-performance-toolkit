<?php
/**
 * Describes a meta table whose rows point at a parent table, so rows whose
 * parent no longer exists ("orphans") can be found and removed.
 *
 * Table values are logical keys resolved by the caller (see the Runner's
 * tables() map); column values are real column names and are always bound
 * as %i identifiers by SqlBuilder.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

use InvalidArgumentException;

final class OrphanMeta {

	/**
	 * @param string $meta_table     Logical key of the meta table.
	 * @param string $parent_table   Logical key of the parent table.
	 * @param string $meta_id_column Primary key of the meta table.
	 * @param string $foreign_key    Column in the meta table that references the parent.
	 * @param string $parent_key     Primary key of the parent table.
	 */
	public function __construct(
		public readonly string $meta_table,
		public readonly string $parent_table,
		public readonly string $meta_id_column,
		public readonly string $foreign_key,
		public readonly string $parent_key
	) {
		foreach ( array( $meta_table, $parent_table, $meta_id_column, $foreign_key, $parent_key ) as $name ) {
			if ( 1 !== preg_match( '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name ) ) {
				throw new InvalidArgumentException( sprintf( 'Invalid table or column name "%s".', $name ) );
			}
		}
	}

	/**
	 * Specification for every orphaned-meta cleanup task, keyed by task name.
	 *
	 * @return array<string,self>
	 */
	public static function all(): array {
		return array(
			Tasks::ORPHAN_ITEM_META    => new self( 'order_meta', 'order_items', 'meta_id', 'order_item_id', 'order_item_id' ),
			Tasks::ORPHAN_ORDER_META   => new self( 'hpos_orders_meta', 'hpos_orders', 'id', 'order_id', 'id' ),
			Tasks::ORPHAN_POST_META    => new self( 'postmeta', 'posts', 'meta_id', 'post_id', 'ID' ),
			Tasks::ORPHAN_TERM_META    => new self( 'termmeta', 'terms', 'meta_id', 'term_id', 'term_id' ),
			Tasks::ORPHAN_COMMENT_META => new self( 'commentmeta', 'comments', 'meta_id', 'comment_id', 'comment_ID' ),
		);
	}

	/**
	 * Specification for a task, or null when the task is not an orphaned-meta task.
	 */
	public static function forTask( string $task ): ?self {
		return self::all()[ $task ] ?? null;
	}
}
