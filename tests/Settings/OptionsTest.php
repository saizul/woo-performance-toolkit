<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Settings;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class OptionsTest extends TestCase {

	public function test_defaults_are_safe(): void {
		$d = Options::defaults();

		$this->assertFalse( $d['cart_fragments_disable'] );
		$this->assertFalse( $d['assets_dequeue'] );
		$this->assertFalse( $d['marketing_disable'] );
		$this->assertFalse( $d['wc_admin_disable'] );
		$this->assertFalse( $d['cleanup_enabled'] );
		$this->assertSame( Options::MODE_SLOW, $d['heartbeat_admin_mode'] );
	}

	public function test_sanitize_empty_input_turns_all_booleans_off(): void {
		$out = Options::sanitize( array() );

		foreach ( Options::booleanKeys() as $key ) {
			$this->assertFalse( $out[ $key ], $key );
		}
	}

	public function test_sanitize_has_same_keys_as_defaults(): void {
		$this->assertSame( array_keys( Options::defaults() ), array_keys( Options::sanitize( array( 'junk' => 1 ) ) ) );
	}

	public function test_unknown_keys_are_dropped(): void {
		$this->assertArrayNotHasKey( 'junk', Options::sanitize( array( 'junk' => 1 ) ) );
	}

	public function test_checkbox_values(): void {
		$out = Options::sanitize( array( 'assets_dequeue' => '1', 'marketing_disable' => 'on', 'wc_admin_disable' => '0' ) );

		$this->assertTrue( $out['assets_dequeue'] );
		$this->assertTrue( $out['marketing_disable'] );
		$this->assertFalse( $out['wc_admin_disable'] );
	}

	public function test_invalid_mode_falls_back(): void {
		$out = Options::sanitize( array( 'heartbeat_admin_mode' => 'turbo' ) );
		$this->assertSame( Options::MODE_SLOW, $out['heartbeat_admin_mode'] );
	}

	public function test_valid_mode_accepted(): void {
		$out = Options::sanitize( array( 'heartbeat_frontend_mode' => 'disable' ) );
		$this->assertSame( Options::MODE_DISABLE, $out['heartbeat_frontend_mode'] );
	}

	public function test_interval_is_clamped(): void {
		$this->assertSame( 15, Options::sanitize( array( 'heartbeat_admin_interval' => '1' ) )['heartbeat_admin_interval'] );
		$this->assertSame( 300, Options::sanitize( array( 'heartbeat_admin_interval' => 99999 ) )['heartbeat_admin_interval'] );
	}

	public function test_non_numeric_interval_uses_default(): void {
		$this->assertSame( 60, Options::sanitize( array( 'heartbeat_admin_interval' => 'abc' ) )['heartbeat_admin_interval'] );
	}

	public function test_retention_days_clamped(): void {
		$this->assertSame( 1, Options::sanitize( array( 'cleanup_retention_days' => 0 ) )['cleanup_retention_days'] );
		$this->assertSame( 3650, Options::sanitize( array( 'cleanup_retention_days' => 10000 ) )['cleanup_retention_days'] );
	}

	public function test_batch_size_clamped(): void {
		$this->assertSame( 100, Options::sanitize( array( 'cleanup_batch_size' => 5 ) )['cleanup_batch_size'] );
		$this->assertSame( 10000, Options::sanitize( array( 'cleanup_batch_size' => 999999 ) )['cleanup_batch_size'] );
	}

	public function test_frequency_whitelist(): void {
		$this->assertSame( 'weekly', Options::sanitize( array( 'cleanup_frequency' => 'weekly' ) )['cleanup_frequency'] );
		$this->assertSame( 'daily', Options::sanitize( array( 'cleanup_frequency' => 'hourly' ) )['cleanup_frequency'] );
	}

	public function test_textareas_are_normalised(): void {
		$out = Options::sanitize( array( 'cart_fragments_allowlist' => " 12 \n\n/Shop/\n12" ) );
		$this->assertSame( "12\n/Shop/", $out['cart_fragments_allowlist'] );
	}

	public function test_non_string_textarea_is_ignored(): void {
		$out = Options::sanitize( array( 'assets_keep_handles' => array( 'x' ) ) );
		$this->assertSame( '', $out['assets_keep_handles'] );
	}

	public function test_resolve_uses_defaults_for_missing_values(): void {
		$out = Options::resolve( array( 'assets_dequeue' => true ) );

		$this->assertTrue( $out['assets_dequeue'] );
		$this->assertTrue( $out['cleanup_task_sessions'] );
		$this->assertSame( 30, $out['cleanup_retention_days'] );
	}

	public function test_resolve_handles_non_array(): void {
		$this->assertSame( Options::resolve( false ), Options::resolve( array() ) );
		$this->assertSame( Options::defaults(), Options::resolve( null ) );
	}

	public function test_resolve_is_idempotent(): void {
		$once = Options::resolve( array( 'cleanup_retention_days' => '45', 'assets_dequeue' => true ) );
		$this->assertSame( $once, Options::resolve( $once ) );
	}

	public function test_to_bool(): void {
		$this->assertTrue( Options::toBool( 1 ) );
		$this->assertTrue( Options::toBool( ' TRUE ' ) );
		$this->assertFalse( Options::toBool( 2 ) );
		$this->assertFalse( Options::toBool( null ) );
		$this->assertFalse( Options::toBool( array( 1 ) ) );
	}
}
