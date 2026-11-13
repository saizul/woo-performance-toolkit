<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\OrphanMeta;
use SaizulAmin\WooPerformanceToolkit\Cleanup\SqlBuilder;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class SqlBuilderTest extends TestCase {

	/**
	 * Every builder, called with typical arguments.
	 *
	 * @return array<string,array{Query}>
	 */
	public static function allQueries(): array {
		$queries = array(
			'count sessions'      => array( SqlBuilder::countExpiredSessions( 'wp_woocommerce_sessions', 100 ) ),
			'delete sessions'     => array( SqlBuilder::deleteExpiredSessions( 'wp_woocommerce_sessions', 100, 500 ) ),
			'count transients'    => array( SqlBuilder::countExpiredTransients( 'wp_options', false, 100 ) ),
			'count site trans.'   => array( SqlBuilder::countExpiredTransients( 'wp_options', true, 100 ) ),
			'select timeouts'     => array( SqlBuilder::selectExpiredTransientTimeouts( 'wp_options', false, 100, 50 ) ),
			'delete options'      => array( SqlBuilder::deleteOptionsByNames( 'wp_options', array( 'a', 'b', 'c' ) ) ),
			'count as'            => array( SqlBuilder::countFinishedActions( 'wp_actionscheduler_actions', '2024-01-01 00:00:00' ) ),
			'select as'           => array( SqlBuilder::selectFinishedActionIds( 'wp_actionscheduler_actions', '2024-01-01 00:00:00', 10 ) ),
			'delete as logs'      => array( SqlBuilder::deleteActionLogs( 'wp_actionscheduler_logs', array( 1, 2 ) ) ),
			'delete as actions'   => array( SqlBuilder::deleteActions( 'wp_actionscheduler_actions', array( 1, 2 ) ) ),
			'count orphans'       => array( SqlBuilder::countOrphanItemMeta( 'm', 'i' ) ),
			'select orphans'      => array( SqlBuilder::selectOrphanItemMetaIds( 'm', 'i', 10 ) ),
			'delete orphans'      => array( SqlBuilder::deleteOrphanItemMeta( 'm', array( 5 ) ) ),
		);

		foreach ( OrphanMeta::all() as $task => $spec ) {
			$queries[ 'count ' . $task ]  = array( SqlBuilder::countOrphanMeta( $spec, 'meta', 'parent' ) );
			$queries[ 'select ' . $task ] = array( SqlBuilder::selectOrphanMetaIds( $spec, 'meta', 'parent', 10 ) );
			$queries[ 'delete ' . $task ] = array( SqlBuilder::deleteOrphanMeta( $spec, 'meta', 'parent', array( 3, 4 ) ) );
		}

		return $queries;
	}

	#[DataProvider( 'allQueries' )]
	public function test_placeholder_count_matches_args( Query $query ): void {
		$this->assertSame( $query->placeholderCount(), count( $query->args ) );
	}

	#[DataProvider( 'allQueries' )]
	public function test_no_literal_values_or_unsafe_percent( Query $query ): void {
		// Only %i, %s and %d may appear.
		$stripped = (string) preg_replace( '/%[isd]/', '', $query->sql );
		$this->assertStringNotContainsString( '%', $stripped );
		$this->assertDoesNotMatchRegularExpression( "/['\"]/", $query->sql );
	}

	#[DataProvider( 'allQueries' )]
	public function test_table_names_are_identifier_placeholders( Query $query ): void {
		$this->assertMatchesRegularExpression( '/(FROM|JOIN) %i/', $query->sql );
	}

	public function test_esc_like(): void {
		$this->assertSame( '\_transient\_timeout\_', SqlBuilder::escLike( '_transient_timeout_' ) );
		$this->assertSame( '100\%', SqlBuilder::escLike( '100%' ) );
	}

	public function test_count_sessions_args(): void {
		$q = SqlBuilder::countExpiredSessions( 'wp_woocommerce_sessions', 1234 );

		$this->assertSame( 'SELECT COUNT(*) FROM %i WHERE session_expiry < %d', $q->sql );
		$this->assertSame( array( 'wp_woocommerce_sessions', 1234 ), $q->args );
	}

	public function test_delete_sessions_limit_is_at_least_one(): void {
		$this->assertSame( 1, SqlBuilder::deleteExpiredSessions( 't', 1, 0 )->args[2] );
	}

	public function test_transient_like_pattern_is_escaped_and_bound(): void {
		$q = SqlBuilder::selectExpiredTransientTimeouts( 'wp_options', false, 100, 10 );

		$this->assertSame( '\_transient\_timeout\_%', $q->args[1] );
		$this->assertSame( 100, $q->args[2] );
		$this->assertSame( 10, $q->args[3] );
	}

	public function test_site_transient_pattern(): void {
		$this->assertSame(
			'\_site\_transient\_timeout\_%',
			SqlBuilder::countExpiredTransients( 'wp_options', true, 1 )->args[1]
		);
	}

	public function test_delete_options_by_names_placeholders(): void {
		$q = SqlBuilder::deleteOptionsByNames( 'wp_options', array( 'a', 'b', 'a' ) );

		$this->assertSame( 'DELETE FROM %i WHERE option_name IN (%s,%s)', $q->sql );
		$this->assertSame( array( 'wp_options', 'a', 'b' ), $q->args );
	}

	public function test_delete_options_by_names_requires_names(): void {
		$this->expectException( InvalidArgumentException::class );
		SqlBuilder::deleteOptionsByNames( 'wp_options', array() );
	}

	public function test_sql_injection_in_names_stays_in_args(): void {
		$evil = "x') OR 1=1 --";
		$q    = SqlBuilder::deleteOptionsByNames( 'wp_options', array( $evil ) );

		$this->assertStringNotContainsString( 'OR 1=1', $q->sql );
		$this->assertContains( $evil, $q->args );
	}

	public function test_finished_actions_use_complete_and_failed(): void {
		$q = SqlBuilder::countFinishedActions( 'wp_actionscheduler_actions', '2024-01-01 00:00:00' );

		$this->assertSame(
			array( 'wp_actionscheduler_actions', 'complete', 'failed', '2024-01-01 00:00:00', SqlBuilder::ZERO_DATE ),
			$q->args
		);
	}

	public function test_select_finished_actions_limit_is_last_arg(): void {
		$q = SqlBuilder::selectFinishedActionIds( 't', 'c', 25 );
		$this->assertSame( 25, array_slice( $q->args, -1 )[0] );
	}

	public function test_delete_by_ids_sanitises_ids(): void {
		$q = SqlBuilder::deleteByIds( 't', 'meta_id', array( '5', 5, 0, -2, 'abc', 9 ) );

		$this->assertSame( 'DELETE FROM %i WHERE %i IN (%d,%d)', $q->sql );
		$this->assertSame( array( 't', 'meta_id', 5, 9 ), $q->args );
	}

	public function test_delete_by_ids_requires_valid_ids(): void {
		$this->expectException( InvalidArgumentException::class );
		SqlBuilder::deleteByIds( 't', 'id', array( 0, -1 ) );
	}

	public function test_orphan_query_joins_items_table(): void {
		$q = SqlBuilder::countOrphanItemMeta( 'wp_woocommerce_order_itemmeta', 'wp_woocommerce_order_items' );

		$this->assertSame( 'SELECT COUNT(*) FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL', $q->sql );
		$this->assertSame(
			array( 'wp_woocommerce_order_itemmeta', 'wp_woocommerce_order_items', 'order_item_id', 'order_item_id', 'order_item_id' ),
			$q->args
		);
	}

	public function test_count_orphan_post_meta_binds_columns(): void {
		$q = SqlBuilder::countOrphanMeta( OrphanMeta::forTask( Tasks::ORPHAN_POST_META ), 'wp_postmeta', 'wp_posts' );

		$this->assertSame( array( 'wp_postmeta', 'wp_posts', 'ID', 'post_id', 'ID' ), $q->args );
	}

	public function test_select_orphan_meta_ids_orders_by_meta_id_and_limits(): void {
		$spec = OrphanMeta::forTask( Tasks::ORPHAN_COMMENT_META );
		$q    = SqlBuilder::selectOrphanMetaIds( $spec, 'wp_commentmeta', 'wp_comments', 250 );

		$this->assertSame(
			'SELECT m.%i FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL ORDER BY m.%i ASC LIMIT %d',
			$q->sql
		);
		$this->assertSame(
			array( 'meta_id', 'wp_commentmeta', 'wp_comments', 'comment_ID', 'comment_id', 'comment_ID', 'meta_id', 250 ),
			$q->args
		);
	}

	public function test_select_orphan_meta_ids_limit_is_at_least_one(): void {
		$q = SqlBuilder::selectOrphanMetaIds( OrphanMeta::forTask( Tasks::ORPHAN_TERM_META ), 'm', 'p', -5 );
		$this->assertSame( 1, array_slice( $q->args, -1 )[0] );
	}

	public function test_hpos_order_meta_uses_id_columns(): void {
		$q = SqlBuilder::selectOrphanMetaIds( OrphanMeta::forTask( Tasks::ORPHAN_ORDER_META ), 'wp_wc_orders_meta', 'wp_wc_orders', 10 );

		$this->assertSame( array( 'id', 'wp_wc_orders_meta', 'wp_wc_orders', 'id', 'order_id', 'id', 'id', 10 ), $q->args );
	}

	public function test_delete_orphan_meta_rechecks_parent_and_sanitises_ids(): void {
		$spec = OrphanMeta::forTask( Tasks::ORPHAN_POST_META );
		$q    = SqlBuilder::deleteOrphanMeta( $spec, 'wp_postmeta', 'wp_posts', array( '7', 7, 0, -1, 'x', 12 ) );

		$this->assertSame(
			'DELETE m FROM %i AS m LEFT JOIN %i AS p ON p.%i = m.%i WHERE p.%i IS NULL AND m.%i IN (%d,%d)',
			$q->sql
		);
		$this->assertSame( array( 'wp_postmeta', 'wp_posts', 'ID', 'post_id', 'ID', 'meta_id', 7, 12 ), $q->args );
	}

	public function test_delete_orphan_meta_requires_ids(): void {
		$this->expectException( InvalidArgumentException::class );
		SqlBuilder::deleteOrphanMeta( OrphanMeta::forTask( Tasks::ORPHAN_POST_META ), 'm', 'p', array( 0 ) );
	}
}
