<?php
/**
 * Builds prepared-statement templates for the cleanup tasks.
 *
 * Every method returns a Query whose SQL contains only %i (identifier),
 * %s and %d placeholders. The caller passes it through $wpdb->prepare()
 * (WordPress 6.2+ supports %i). Nothing here concatenates a value into SQL.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

use InvalidArgumentException;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class SqlBuilder {

	/** Action Scheduler statuses considered finished. */
	public const FINISHED_STATUSES = array( 'complete', 'failed' );

	/** Placeholder for "never attempted" action rows. */
	public const ZERO_DATE = '0001-01-01 00:00:00';

	/**
	 * Escape LIKE wildcards (mirrors wpdb::esc_like without needing wpdb).
	 */
	public static function escLike( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/* ---------------------------------------------------------------- Sessions */

	public static function countExpiredSessions( string $table, int $now ): Query {
		return new Query( 'SELECT COUNT(*) FROM %i WHERE session_expiry < %d', array( $table, $now ) );
	}

	public static function deleteExpiredSessions( string $table, int $now, int $limit ): Query {
		return new Query(
			'DELETE FROM %i WHERE session_expiry < %d LIMIT %d',
			array( $table, $now, self::positive( $limit ) )
		);
	}

	/* -------------------------------------------------------------- Transients */

	public static function countExpiredTransients( string $optionsTable, bool $site, int $now ): Query {
		return new Query(
			'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d',
			array( $optionsTable, self::escLike( TransientNames::timeoutPrefix( $site ) ) . '%', $now )
		);
	}

	public static function selectExpiredTransientTimeouts( string $optionsTable, bool $site, int $now, int $limit ): Query {
		return new Query(
			'SELECT option_name FROM %i WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d ORDER BY option_id ASC LIMIT %d',
			array( $optionsTable, self::escLike( TransientNames::timeoutPrefix( $site ) ) . '%', $now, self::positive( $limit ) )
		);
	}

	/**
	 * @param string[] $names Option names.
	 */
	public static function deleteOptionsByNames( string $optionsTable, array $names ): Query {
		$names = array_values( array_unique( array_map( 'strval', $names ) ) );

		if ( array() === $names ) {
			throw new InvalidArgumentException( 'At least one option name is required.' );
		}

		return new Query(
			'DELETE FROM %i WHERE option_name IN (' . implode( ',', array_fill( 0, count( $names ), '%s' ) ) . ')',
			array_merge( array( $optionsTable ), $names )
		);
	}

	/* -------------------------------------------------------- Action Scheduler */

	public static function countFinishedActions( string $actionsTable, string $cutoffGmt ): Query {
		return new Query(
			'SELECT COUNT(*) FROM %i WHERE status IN (%s,%s) AND last_attempt_gmt < %s AND last_attempt_gmt > %s',
			array_merge( array( $actionsTable ), self::FINISHED_STATUSES, array( $cutoffGmt, self::ZERO_DATE ) )
		);
	}

	public static function selectFinishedActionIds( string $actionsTable, string $cutoffGmt, int $limit ): Query {
		return new Query(
			'SELECT action_id FROM %i WHERE status IN (%s,%s) AND last_attempt_gmt < %s AND last_attempt_gmt > %s ORDER BY action_id ASC LIMIT %d',
			array_merge( array( $actionsTable ), self::FINISHED_STATUSES, array( $cutoffGmt, self::ZERO_DATE, self::positive( $limit ) ) )
		);
	}

	/**
	 * @param int[] $actionIds Action IDs.
	 */
	public static function deleteActionLogs( string $logsTable, array $actionIds ): Query {
		return self::deleteByIds( $logsTable, 'action_id', $actionIds );
	}

	/**
	 * @param int[] $actionIds Action IDs.
	 */
	public static function deleteActions( string $actionsTable, array $actionIds ): Query {
		return self::deleteByIds( $actionsTable, 'action_id', $actionIds );
	}

	/* ------------------------------------------------------ Orphaned item meta */

	public static function countOrphanItemMeta( string $metaTable, string $itemsTable ): Query {
		return self::countOrphanMeta( self::itemMetaSpec(), $metaTable, $itemsTable );
	}

	public static function selectOrphanItemMetaIds( string $metaTable, string $itemsTable, int $limit ): Query {
		return self::selectOrphanMetaIds( self::itemMetaSpec(), $metaTable, $itemsTable, $limit );
	}

	/**
	 * @param int[] $metaIds Meta IDs.
	 */
	public static function deleteOrphanItemMeta( string $metaTable, array $metaIds ): Query {
		return self::deleteByIds( $metaTable, 'meta_id', $metaIds );
	}

	/* ----------------------------------------------------- Orphaned meta (any) */

	/**
	 * Count meta rows whose parent row does not exist.
	 */
	public static function countOrphanMeta( OrphanMeta $spec, string $metaTable, string $parentTable ): Query {
		return new Query(
			'SELECT COUNT(*) FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL',
			array( $metaTable, $parentTable, $spec->parent_key, $spec->foreign_key, $spec->parent_key )
		);
	}

	/**
	 * Lowest-first batch of orphaned meta IDs.
	 */
	public static function selectOrphanMetaIds( OrphanMeta $spec, string $metaTable, string $parentTable, int $limit ): Query {
		return new Query(
			'SELECT m.%i FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL ORDER BY m.%i ASC LIMIT %d',
			array(
				$spec->meta_id_column,
				$metaTable,
				$parentTable,
				$spec->parent_key,
				$spec->foreign_key,
				$spec->parent_key,
				$spec->meta_id_column,
				self::positive( $limit ),
			)
		);
	}

	/**
	 * Delete the given meta IDs, re-checking that each is still orphaned.
	 *
	 * The join is repeated in the DELETE so a row whose parent was (re)created
	 * between the SELECT and the DELETE is left alone.
	 *
	 * @param int[] $metaIds Meta IDs; non-positive values are discarded.
	 */
	public static function deleteOrphanMeta( OrphanMeta $spec, string $metaTable, string $parentTable, array $metaIds ): Query {
		$ids = self::positiveIds( $metaIds );

		return new Query(
			'DELETE m FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL AND m.%i IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
			array_merge(
				array( $metaTable, $parentTable, $spec->parent_key, $spec->foreign_key, $spec->parent_key, $spec->meta_id_column ),
				$ids
			)
		);
	}

	/* ----------------------------------------------------------------- Helpers */

	/**
	 * DELETE ... WHERE <column> IN (%d, ...).
	 *
	 * @param int[] $ids IDs; non-positive values are discarded.
	 */
	public static function deleteByIds( string $table, string $column, array $ids ): Query {
		$ids = self::positiveIds( $ids );

		return new Query(
			'DELETE FROM %i WHERE %i IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
			array_merge( array( $table, $column ), $ids )
		);
	}

	/**
	 * Unique positive integer IDs, in input order.
	 *
	 * @param array<int,mixed> $ids Raw IDs.
	 * @return int[]
	 * @throws InvalidArgumentException When no positive ID remains.
	 */
	private static function positiveIds( array $ids ): array {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					static fn( int $id ): bool => $id > 0
				)
			)
		);

		if ( array() === $ids ) {
			throw new InvalidArgumentException( 'At least one positive ID is required.' );
		}

		return $ids;
	}

	private static function itemMetaSpec(): OrphanMeta {
		return OrphanMeta::all()[ Tasks::ORPHAN_ITEM_META ];
	}

	private static function positive( int $limit ): int {
		return max( 1, $limit );
	}
}
