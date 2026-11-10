<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Diagnostics;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Export;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class ExportTest extends TestCase {

	/**
	 * @return array<string,mixed>
	 */
	private function snapshot(): array {
		return array(
			'hpos_enabled'       => false,
			'object_cache'       => false,
			'php_version'        => '8.3.1',
			'autoload_bytes'     => 2500000,
			'largest_options'    => array( array( 'name' => 'big_option', 'size' => 900000 ) ),
			'cleanup_counts'     => array( 'transients' => 12, 'sessions' => 3 ),
			'cleanup_scheduled'  => false,
			'as_pending'         => 40,
			'as_past_due'        => 2,
			'as_failed'          => 1,
			'variation_outliers' => array( array( 'product_id' => '15', 'variations' => '120' ) ),
		);
	}

	public function test_build_has_expected_sections(): void {
		$report = Export::build( $this->snapshot(), Options::defaults(), array( 'wp_version' => '6.7' ), '2026-11-10T10:00:00+00:00' );

		$this->assertSame( Export::SCHEMA_VERSION, $report['schema_version'] );
		$this->assertSame( '2026-11-10T10:00:00+00:00', $report['generated_at'] );
		$this->assertSame( array( 'wp_version' => '6.7' ), $report['environment'] );
		$this->assertSame( 2500000, $report['metrics']['autoload_bytes'] );
		$this->assertContains( 'hpos_disabled', array_column( $report['recommendations'], 'code' ) );
	}

	public function test_metrics_are_typed_and_normalised(): void {
		$metrics = Export::metrics( $this->snapshot() );

		$this->assertSame( array( array( 'name' => 'big_option', 'bytes' => 900000 ) ), $metrics['largest_options'] );
		$this->assertSame( array( array( 'product_id' => 15, 'variations' => 120 ) ), $metrics['variation_outliers'] );
		$this->assertSame( array( 'sessions' => 3, 'transients' => 12 ), $metrics['cleanup_counts'] );
		$this->assertSame( 40, $metrics['as_pending'] );
	}

	public function test_missing_data_stays_null_or_empty(): void {
		$metrics = Export::metrics( array() );

		$this->assertNull( $metrics['hpos_enabled'] );
		$this->assertNull( $metrics['autoload_bytes'] );
		$this->assertNull( $metrics['as_pending'] );
		$this->assertSame( array(), $metrics['largest_options'] );
		$this->assertSame( array(), $metrics['variation_outliers'] );
	}

	public function test_malformed_rows_are_dropped(): void {
		$metrics = Export::metrics(
			array(
				'largest_options'    => array( 'oops', array( 'name' => 'x' ), array( 'name' => 'ok', 'size' => 5 ) ),
				'variation_outliers' => 'nope',
			)
		);

		$this->assertSame( array( array( 'name' => 'ok', 'bytes' => 5 ) ), $metrics['largest_options'] );
		$this->assertSame( array(), $metrics['variation_outliers'] );
	}

	public function test_settings_free_text_is_reduced_to_entry_counts(): void {
		$settings = array_merge(
			Options::defaults(),
			array(
				'cart_fragments_allowlist' => "/private-page/\n# a comment\n/shop/*\n/private-page/",
				'assets_keep_handles'      => '',
			)
		);

		$clean = Export::settings( $settings );

		$this->assertSame( array( 'entries' => 2 ), $clean['cart_fragments_allowlist'] );
		$this->assertSame( array( 'entries' => 0 ), $clean['assets_keep_handles'] );
		$this->assertSame( 'slow', $clean['heartbeat_admin_mode'] );
		$this->assertSame( 60, $clean['heartbeat_admin_interval'] );
		$this->assertFalse( $clean['cleanup_enabled'] );
		$this->assertStringNotContainsString( 'private-page', json_encode( $clean ) );
	}

	public function test_settings_ignore_unknown_keys(): void {
		$clean = Export::settings( array( 'api_secret' => 'abc123', 'cleanup_enabled' => true ) );

		$this->assertSame( array( 'cleanup_enabled' => true ), $clean );
	}

	public function test_environment_only_keeps_known_scalar_keys(): void {
		$env = Export::environment(
			array(
				'php_version'    => "8.3.1\n",
				'multisite'      => false,
				'site_url'       => 'https://secret.example.com',
				'admin_email'    => 'owner@example.com',
				'memory_limit'   => str_repeat( 'x', 500 ),
				'wc_version'     => array( 'nested' ),
			)
		);

		$this->assertEqualsCanonicalizing( array( 'php_version', 'memory_limit', 'multisite' ), array_keys( $env ) );
		$this->assertSame( '8.3.1', $env['php_version'] );
		$this->assertSame( 100, strlen( $env['memory_limit'] ) );
		$this->assertArrayNotHasKey( 'site_url', $env );
		$this->assertArrayNotHasKey( 'admin_email', $env );
		$this->assertArrayNotHasKey( 'wc_version', $env );
	}

	public function test_json_round_trips(): void {
		$report = Export::build( $this->snapshot(), Options::defaults(), array(), '2026-11-10T10:00:00+00:00' );
		$json   = Export::toJson( $report );

		$this->assertStringEndsWith( "}\n", $json );
		$this->assertSame( $report, json_decode( $json, true ) );
	}

	/**
	 * @dataProvider fileNames
	 */
	public function test_file_name( string $host, string $date, string $expected ): void {
		$this->assertSame( $expected, Export::fileName( $host, $date ) );
	}

	/**
	 * @return array<string,array{string,string,string}>
	 */
	public static function fileNames(): array {
		return array(
			'plain host'        => array( 'shop.example.com', '20261110', 'woo-performance-diagnostics-shop-example-com-20261110.json' ),
			'uppercase + port'  => array( 'Shop.Example.COM:8080', '20261110', 'woo-performance-diagnostics-shop-example-com-8080-20261110.json' ),
			'unsafe characters' => array( '../../etc/passwd"', '2026-11-10', 'woo-performance-diagnostics-etc-passwd-20261110.json' ),
			'empty host'        => array( '', '20261110', 'woo-performance-diagnostics-20261110.json' ),
			'empty everything'  => array( '', '', 'woo-performance-diagnostics.json' ),
		);
	}
}
