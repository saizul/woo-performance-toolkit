<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Heartbeat;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Heartbeat\HeartbeatPolicy;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class HeartbeatPolicyTest extends TestCase {

	public function test_default_mode_does_nothing(): void {
		$this->assertSame(
			array( 'action' => 'none', 'interval' => 0 ),
			HeartbeatPolicy::decide( Options::MODE_DEFAULT, 60, false )
		);
	}

	public function test_slow_mode_sets_interval(): void {
		$this->assertSame(
			array( 'action' => 'interval', 'interval' => 90 ),
			HeartbeatPolicy::decide( Options::MODE_SLOW, 90, false )
		);
	}

	public function test_interval_is_clamped(): void {
		$this->assertSame( 15, HeartbeatPolicy::decide( Options::MODE_SLOW, 1, false )['interval'] );
		$this->assertSame( 300, HeartbeatPolicy::decide( Options::MODE_SLOW, 9999, false )['interval'] );
	}

	public function test_disable_mode_outside_editor(): void {
		$this->assertSame( HeartbeatPolicy::ACTION_DISABLE, HeartbeatPolicy::decide( Options::MODE_DISABLE, 60, false )['action'] );
	}

	public function test_disable_mode_is_downgraded_in_post_editor(): void {
		$decision = HeartbeatPolicy::decide( Options::MODE_DISABLE, 20, true );

		$this->assertSame( HeartbeatPolicy::ACTION_INTERVAL, $decision['action'] );
		$this->assertSame( 60, $decision['interval'] );
	}

	public function test_disable_in_editor_keeps_larger_interval(): void {
		$this->assertSame( 120, HeartbeatPolicy::decide( Options::MODE_DISABLE, 120, true )['interval'] );
	}

	public function test_unknown_mode_does_nothing(): void {
		$this->assertSame( HeartbeatPolicy::ACTION_NONE, HeartbeatPolicy::decide( 'bogus', 60, false )['action'] );
	}
}
