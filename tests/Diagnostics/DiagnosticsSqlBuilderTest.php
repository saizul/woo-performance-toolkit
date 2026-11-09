<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Diagnostics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\OptionSizer;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\SqlBuilder;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class DiagnosticsSqlBuilderTest extends TestCase {

	/**
	 * @return array<string,array{Query}>
	 */
	public static function queries(): array {
		return array(
			'autoload total'   => array( SqlBuilder::autoloadTotalBytes( 'wp_options' ) ),
			'largest'          => array( SqlBuilder::largestAutoloadedOptions( 'wp_options', 10 ) ),
			'backlog'          => array( SqlBuilder::actionBacklogByStatus( 'wp_actionscheduler_actions' ) ),
			'past due'         => array( SqlBuilder::actionsPastDue( 'wp_actionscheduler_actions', '2025-01-01 00:00:00' ) ),
			'variations'       => array( SqlBuilder::variationOutliers( 'wp_posts', 50, 10 ) ),
		);
	}

	#[DataProvider( 'queries' )]
	public function test_placeholders_match_args( Query $query ): void {
		$this->assertSame( $query->placeholderCount(), count( $query->args ) );
	}

	#[DataProvider( 'queries' )]
	public function test_no_inline_literals( Query $query ): void {
		$this->assertDoesNotMatchRegularExpression( "/['\"]/", $query->sql );
	}

	public function test_autoload_query_binds_every_autoload_value(): void {
		$q = SqlBuilder::autoloadTotalBytes( 'wp_options' );

		$this->assertSame( array_merge( array( 'wp_options' ), OptionSizer::autoloadValues() ), $q->args );
		$this->assertSame( 4, substr_count( $q->sql, '%s' ) );
	}

	public function test_largest_options_limit_is_at_least_one(): void {
		$q = SqlBuilder::largestAutoloadedOptions( 'wp_options', 0 );
		$this->assertSame( 1, array_slice( $q->args, -1 )[0] );
	}

	public function test_variation_outlier_args(): void {
		$q = SqlBuilder::variationOutliers( 'wp_posts', 50, 10 );

		$this->assertSame( array( 'wp_posts', 'product_variation', 'publish', 'private', 50, 10 ), $q->args );
		$this->assertStringContainsString( 'HAVING COUNT(*) > %d', $q->sql );
	}

	public function test_past_due_only_counts_pending(): void {
		$q = SqlBuilder::actionsPastDue( 't', '2025-01-01 00:00:00' );
		$this->assertSame( array( 't', 'pending', '2025-01-01 00:00:00' ), $q->args );
	}
}
