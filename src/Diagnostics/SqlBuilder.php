<?php
/**
 * Prepared-statement templates for the diagnostics screen.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class SqlBuilder {

	/**
	 * @return string[]
	 */
	private static function autoloadValues(): array {
		return OptionSizer::autoloadValues();
	}

	private static function autoloadPlaceholders(): string {
		return implode( ',', array_fill( 0, count( self::autoloadValues() ), '%s' ) );
	}

	/**
	 * Total bytes of autoloaded option values.
	 */
	public static function autoloadTotalBytes( string $optionsTable ): Query {
		return new Query(
			'SELECT COALESCE(SUM(LENGTH(option_value)), 0) FROM %i WHERE autoload IN (' . self::autoloadPlaceholders() . ')',
			array_merge( array( $optionsTable ), self::autoloadValues() )
		);
	}

	/**
	 * Largest autoloaded options.
	 */
	public static function largestAutoloadedOptions( string $optionsTable, int $limit ): Query {
		return new Query(
			'SELECT option_name AS name, LENGTH(option_value) AS size FROM %i WHERE autoload IN (' . self::autoloadPlaceholders() . ') ORDER BY size DESC, option_name ASC LIMIT %d',
			array_merge( array( $optionsTable ), self::autoloadValues(), array( max( 1, $limit ) ) )
		);
	}

	/**
	 * Action Scheduler action counts grouped by status.
	 */
	public static function actionBacklogByStatus( string $actionsTable ): Query {
		return new Query(
			'SELECT status, COUNT(*) AS total FROM %i WHERE status IN (%s,%s,%s,%s) GROUP BY status',
			array( $actionsTable, 'pending', 'in-progress', 'failed', 'past-due' )
		);
	}

	/**
	 * Pending actions scheduled before $nowGmt (i.e. overdue).
	 */
	public static function actionsPastDue( string $actionsTable, string $nowGmt ): Query {
		return new Query(
			'SELECT COUNT(*) FROM %i WHERE status = %s AND scheduled_date_gmt < %s',
			array( $actionsTable, 'pending', $nowGmt )
		);
	}

	/**
	 * Products whose variation count exceeds the threshold.
	 */
	public static function variationOutliers( string $postsTable, int $threshold, int $limit ): Query {
		return new Query(
			'SELECT post_parent AS product_id, COUNT(*) AS variations FROM %i WHERE post_type = %s AND post_status IN (%s,%s) AND post_parent > 0 GROUP BY post_parent HAVING COUNT(*) > %d ORDER BY variations DESC, post_parent ASC LIMIT %d',
			array( $postsTable, 'product_variation', 'publish', 'private', max( 0, $threshold ), max( 1, $limit ) )
		);
	}
}
