<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class TasksTest extends TestCase {

	public function test_every_task_has_a_label(): void {
		$this->assertSame( Tasks::all(), array_keys( Tasks::labels() ) );
	}

	public function test_every_task_setting_key_exists_in_options(): void {
		foreach ( Tasks::all() as $task ) {
			$this->assertArrayHasKey( Tasks::settingKey( $task ), Options::defaults() );
		}
	}

	public function test_enabled_from_defaults_is_all_tasks(): void {
		$this->assertSame( Tasks::all(), Tasks::enabledFromSettings( Options::defaults() ) );
	}

	public function test_enabled_from_settings_filters(): void {
		$settings                                  = Options::defaults();
		$settings['cleanup_task_transients']       = false;
		$settings['cleanup_task_orphan_item_meta'] = false;

		$this->assertSame( array( 'sessions', 'action_scheduler' ), Tasks::enabledFromSettings( $settings ) );
	}

	public function test_parse_list_orders_canonically_and_dedupes(): void {
		$this->assertSame( array( 'sessions', 'transients' ), Tasks::parseList( 'transients, sessions,sessions' ) );
	}

	public function test_parse_list_rejects_unknown_task(): void {
		$this->expectException( InvalidArgumentException::class );
		Tasks::parseList( 'sessions,bogus' );
	}

	public function test_parse_list_rejects_empty(): void {
		$this->expectException( InvalidArgumentException::class );
		Tasks::parseList( ' , ' );
	}

	public function test_setting_key_rejects_unknown_task(): void {
		$this->expectException( InvalidArgumentException::class );
		Tasks::settingKey( 'nope' );
	}
}
