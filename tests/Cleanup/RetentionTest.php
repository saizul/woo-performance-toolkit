<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Retention;

final class RetentionTest extends TestCase {

	public function test_clamp_days(): void {
		$this->assertSame( 1, Retention::clampDays( -3 ) );
		$this->assertSame( 30, Retention::clampDays( 30 ) );
		$this->assertSame( 3650, Retention::clampDays( 99999 ) );
	}

	public function test_clamp_batch(): void {
		$this->assertSame( 100, Retention::clampBatch( 1 ) );
		$this->assertSame( 500, Retention::clampBatch( 500 ) );
		$this->assertSame( 10000, Retention::clampBatch( 50000 ) );
	}

	public function test_cutoff_timestamp(): void {
		$this->assertSame( 1700000000 - 30 * 86400, Retention::cutoffTimestamp( 1700000000, 30 ) );
	}

	public function test_cutoff_uses_clamped_days(): void {
		$this->assertSame( 1700000000 - 86400, Retention::cutoffTimestamp( 1700000000, 0 ) );
	}

	public function test_cutoff_gmt_format(): void {
		$this->assertSame( '2023-11-14 22:13:20', Retention::cutoffGmt( 1700000000 + 86400, 1 ) );
	}
}
