<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Diagnostics;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Recommendation;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Recommendations;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Thresholds;

final class RecommendationsTest extends TestCase {

	/**
	 * A healthy snapshot.
	 *
	 * @param array<string,mixed> $override Overrides.
	 * @return array<string,mixed>
	 */
	private function healthy( array $override = array() ): array {
		return array_merge(
			array(
				'hpos_enabled'       => true,
				'object_cache'       => true,
				'autoload_bytes'     => 100000,
				'largest_options'    => array(),
				'as_pending'         => 5,
				'as_past_due'        => 0,
				'as_failed'          => 0,
				'variation_outliers' => array(),
				'php_version'        => '8.3.1',
				'cleanup_counts'     => array(),
				'cleanup_scheduled'  => false,
			),
			$override
		);
	}

	/**
	 * @param Recommendation[] $recs Recommendations.
	 * @return string[]
	 */
	private function codes( array $recs ): array {
		return array_map( static fn( Recommendation $r ): string => $r->code, $recs );
	}

	public function test_healthy_site_gets_all_clear(): void {
		$recs = Recommendations::build( $this->healthy() );

		$this->assertSame( array( 'all_clear' ), $this->codes( $recs ) );
		$this->assertSame( Recommendation::INFO, $recs[0]->severity );
	}

	public function test_empty_snapshot_does_not_fail(): void {
		$this->assertSame( array( 'all_clear' ), $this->codes( Recommendations::build( array() ) ) );
	}

	public function test_hpos_disabled_warns(): void {
		$recs = Recommendations::build( $this->healthy( array( 'hpos_enabled' => false ) ) );
		$this->assertSame( array( 'hpos_disabled' ), $this->codes( $recs ) );
	}

	public function test_unknown_hpos_is_not_reported(): void {
		$this->assertSame( array( 'all_clear' ), $this->codes( Recommendations::build( $this->healthy( array( 'hpos_enabled' => null ) ) ) ) );
	}

	public function test_large_autoload_names_biggest_options(): void {
		$recs = Recommendations::build(
			$this->healthy(
				array(
					'autoload_bytes'  => 3000000,
					'largest_options' => array(
						array( 'name' => 'big_option', 'size' => 2000000 ),
						array( 'name' => 'other', 'size' => 100 ),
					),
				)
			)
		);

		$this->assertSame( Thresholds::CRITICAL, $recs[0]->severity );
		$this->assertSame( 'autoload_large', $recs[0]->code );
		$this->assertStringContainsString( 'big_option', $recs[0]->detail );
		$this->assertStringContainsString( '2.9 MB', $recs[0]->title );
	}

	public function test_missing_object_cache_is_info_on_small_site(): void {
		$recs = Recommendations::build( $this->healthy( array( 'object_cache' => false ) ) );

		$this->assertSame( 'object_cache_missing', $recs[0]->code );
		$this->assertSame( Recommendation::INFO, $recs[0]->severity );
	}

	public function test_missing_object_cache_is_warning_with_large_autoload(): void {
		$recs = Recommendations::build( $this->healthy( array( 'object_cache' => false, 'autoload_bytes' => 900000 ) ) );

		$by_code = array_column( array_map( static fn( Recommendation $r ): array => $r->toArray(), $recs ), 'severity', 'code' );
		$this->assertSame( Thresholds::WARNING, $by_code['object_cache_missing'] );
	}

	public function test_action_scheduler_backlog_levels(): void {
		$warn = Recommendations::build( $this->healthy( array( 'as_pending' => 2000 ) ) );
		$crit = Recommendations::build( $this->healthy( array( 'as_pending' => 20000 ) ) );

		$this->assertSame( 'as_backlog', $warn[0]->code );
		$this->assertSame( Thresholds::WARNING, $warn[0]->severity );
		$this->assertSame( Thresholds::CRITICAL, $crit[0]->severity );
	}

	public function test_missing_action_scheduler_data_is_skipped(): void {
		$recs = Recommendations::build( $this->healthy( array( 'as_pending' => null, 'as_past_due' => null, 'as_failed' => null ) ) );
		$this->assertSame( array( 'all_clear' ), $this->codes( $recs ) );
	}

	public function test_past_due_and_failed_reported(): void {
		$recs = Recommendations::build( $this->healthy( array( 'as_past_due' => 150, 'as_failed' => 60 ) ) );
		$this->assertEqualsCanonicalizing( array( 'as_past_due', 'as_failed' ), $this->codes( $recs ) );
	}

	public function test_variation_outliers(): void {
		$recs = Recommendations::build(
			$this->healthy(
				array(
					'variation_outliers' => array(
						array( 'product_id' => 12, 'variations' => 340 ),
						array( 'product_id' => 15, 'variations' => 80 ),
					),
				)
			)
		);

		$this->assertSame( 'variation_outliers', $recs[0]->code );
		$this->assertSame( Thresholds::CRITICAL, $recs[0]->severity );
		$this->assertStringContainsString( '#12 (340)', $recs[0]->detail );
		$this->assertStringStartsWith( '2 product(s)', $recs[0]->title );
	}

	public function test_moderate_variation_outlier_is_warning(): void {
		$recs = Recommendations::build( $this->healthy( array( 'variation_outliers' => array( array( 'product_id' => 1, 'variations' => 60 ) ) ) ) );
		$this->assertSame( Thresholds::WARNING, $recs[0]->severity );
	}

	public function test_old_php_is_critical(): void {
		$recs = Recommendations::build( $this->healthy( array( 'php_version' => '7.4.33' ) ) );

		$this->assertSame( 'php_outdated', $recs[0]->code );
		$this->assertSame( Thresholds::CRITICAL, $recs[0]->severity );
	}

	public function test_php_81_is_warning(): void {
		$recs = Recommendations::build( $this->healthy( array( 'php_version' => '8.1.2' ) ) );
		$this->assertSame( Thresholds::WARNING, $recs[0]->severity );
	}

	public function test_cleanup_suggested_when_rows_pile_up(): void {
		$recs = Recommendations::build( $this->healthy( array( 'cleanup_counts' => array( 'sessions' => 800, 'transients' => 400 ) ) ) );

		$this->assertSame( 'cleanup_available', $recs[0]->code );
		$this->assertStringStartsWith( '1,200 rows', $recs[0]->title );
	}

	public function test_cleanup_not_suggested_when_already_scheduled(): void {
		$recs = Recommendations::build( $this->healthy( array( 'cleanup_counts' => array( 'sessions' => 5000 ), 'cleanup_scheduled' => true ) ) );
		$this->assertSame( array( 'all_clear' ), $this->codes( $recs ) );
	}

	public function test_cleanup_not_suggested_for_small_counts(): void {
		$recs = Recommendations::build( $this->healthy( array( 'cleanup_counts' => array( 'sessions' => 10 ) ) ) );
		$this->assertSame( array( 'all_clear' ), $this->codes( $recs ) );
	}

	public function test_sorted_most_urgent_first_and_stable(): void {
		$recs = Recommendations::build(
			$this->healthy(
				array(
					'hpos_enabled' => false,     // warning, added first.
					'php_version'  => '7.4.0',   // critical, added later.
					'as_failed'    => 100,       // warning.
				)
			)
		);

		$this->assertSame( array( 'php_outdated', 'hpos_disabled', 'as_failed' ), $this->codes( $recs ) );
	}

	public function test_worst_severity(): void {
		$this->assertSame( Thresholds::GOOD, Recommendations::worstSeverity( Recommendations::build( $this->healthy() ) ) );
		$this->assertSame( Thresholds::WARNING, Recommendations::worstSeverity( Recommendations::build( $this->healthy( array( 'hpos_enabled' => false ) ) ) ) );
		$this->assertSame( Thresholds::CRITICAL, Recommendations::worstSeverity( Recommendations::build( $this->healthy( array( 'php_version' => '7.0' ) ) ) ) );
	}

	public function test_recommendation_to_array_and_weight(): void {
		$rec = new Recommendation( Thresholds::WARNING, 'c', 't', 'd' );

		$this->assertSame( array( 'severity' => 'warning', 'code' => 'c', 'title' => 't', 'detail' => 'd' ), $rec->toArray() );
		$this->assertSame( 2, $rec->weight() );
		$this->assertSame( 1, ( new Recommendation( Recommendation::INFO, 'a', 'b', 'c' ) )->weight() );
	}
}
