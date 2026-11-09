<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Diagnostics;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Thresholds;

final class ThresholdsTest extends TestCase {

	/**
	 * @return array<string,array{string,int,string}>
	 */
	public static function numeric(): array {
		return array(
			'autoload good'      => array( 'autoload_bytes', 799999, 'good' ),
			'autoload warning'   => array( 'autoload_bytes', 800000, 'warning' ),
			'autoload critical'  => array( 'autoload_bytes', 2500000, 'critical' ),
			'pending good'       => array( 'as_pending', 0, 'good' ),
			'pending warning'    => array( 'as_pending', 1000, 'warning' ),
			'pending critical'   => array( 'as_pending', 10000, 'critical' ),
			'past due warning'   => array( 'as_past_due', 100, 'warning' ),
			'failed critical'    => array( 'as_failed', 500, 'critical' ),
			'variations good'    => array( 'variations', 49, 'good' ),
			'variations warning' => array( 'variations', 50, 'warning' ),
			'variations crit'    => array( 'variations', 200, 'critical' ),
		);
	}

	#[DataProvider( 'numeric' )]
	public function test_classify( string $metric, int $value, string $expected ): void {
		$this->assertSame( $expected, Thresholds::classify( $metric, $value ) );
	}

	public function test_unknown_metric(): void {
		$this->expectException( InvalidArgumentException::class );
		Thresholds::classify( 'bogus', 1 );
	}

	public function test_warning_is_below_critical_for_every_metric(): void {
		foreach ( Thresholds::limits() as $metric => $limits ) {
			$this->assertLessThan( $limits[1], $limits[0], $metric );
		}
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function phpVersions(): array {
		return array(
			'7.4'   => array( '7.4.33', 'critical' ),
			'8.0'   => array( '8.0.30', 'critical' ),
			'8.1'   => array( '8.1.0', 'warning' ),
			'8.2'   => array( '8.2.20', 'warning' ),
			'8.3'   => array( '8.3.0', 'good' ),
			'8.5'   => array( '8.5.6', 'good' ),
			'8.4rc' => array( '8.4.0RC1', 'good' ),
		);
	}

	#[DataProvider( 'phpVersions' )]
	public function test_classify_php( string $version, string $expected ): void {
		$this->assertSame( $expected, Thresholds::classifyPhp( $version ) );
	}
}
