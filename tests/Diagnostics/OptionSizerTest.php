<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Diagnostics;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\OptionSizer;

final class OptionSizerTest extends TestCase {

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function rows(): array {
		return array(
			array( 'name' => 'small', 'size' => '10' ),
			array( 'name' => 'huge', 'size' => 5000 ),
			array( 'name' => 'mid', 'size' => 200 ),
			array( 'name' => 'bad', 'size' => 'x' ),
			array( 'name' => '', 'size' => 5 ),
			array( 'size' => 5 ),
			array( 'name' => 'neg', 'size' => -4 ),
		);
	}

	public function test_autoload_values_cover_legacy_and_modern(): void {
		$values = OptionSizer::autoloadValues();

		$this->assertContains( 'yes', $values );
		$this->assertContains( 'auto-on', $values );
		$this->assertNotContains( 'no', $values );
	}

	public function test_normalize_filters_and_sorts(): void {
		$this->assertSame(
			array(
				array( 'name' => 'huge', 'size' => 5000 ),
				array( 'name' => 'mid', 'size' => 200 ),
				array( 'name' => 'small', 'size' => 10 ),
			),
			OptionSizer::normalizeRows( $this->rows() )
		);
	}

	public function test_ties_sorted_by_name(): void {
		$rows = OptionSizer::normalizeRows( array( array( 'name' => 'b', 'size' => 1 ), array( 'name' => 'a', 'size' => 1 ) ) );
		$this->assertSame( 'a', $rows[0]['name'] );
	}

	public function test_total(): void {
		$this->assertSame( 5210, OptionSizer::total( $this->rows() ) );
	}

	public function test_top(): void {
		$top = OptionSizer::top( $this->rows(), 2 );

		$this->assertCount( 2, $top );
		$this->assertSame( 'huge', $top[0]['name'] );
		$this->assertSame( array(), OptionSizer::top( $this->rows(), 0 ) );
	}

	public function test_share(): void {
		$this->assertSame( 25.0, OptionSizer::share( 25, 100 ) );
		$this->assertSame( 33.3, OptionSizer::share( 1, 3 ) );
		$this->assertSame( 0.0, OptionSizer::share( 5, 0 ) );
		$this->assertSame( 100.0, OptionSizer::share( 500, 100 ) );
	}
}
