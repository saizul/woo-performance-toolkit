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

	public function test_enabled_from_defaults_keeps_original_tasks_only(): void {
		$this->assertSame(
			array( 'sessions', 'transients', 'action_scheduler', 'orphan_item_meta' ),
			Tasks::enabledFromSettings( Options::defaults() )
		);
	}

	public function test_core_table_orphan_tasks_are_opt_in(): void {
		$defaults = Options::defaults();

		foreach ( array( Tasks::ORPHAN_ORDER_META, Tasks::ORPHAN_POST_META, Tasks::ORPHAN_TERM_META, Tasks::ORPHAN_COMMENT_META ) as $task ) {
			$this->assertFalse( $defaults[ Tasks::settingKey( $task ) ], $task );
		}
	}

	public function test_every_task_setting_is_a_boolean_option(): void {
		foreach ( Tasks::all() as $task ) {
			$this->assertContains( Tasks::settingKey( $task ), Options::booleanKeys() );
		}
	}

	public function test_enabled_from_settings_filters(): void {
		$settings                                  = Options::defaults();
		$settings['cleanup_task_transients']       = false;
		$settings['cleanup_task_orphan_item_meta'] = false;
		$settings['cleanup_task_orphan_post_meta'] = true;

		$this->assertSame( array( 'sessions', 'action_scheduler', 'orphan_post_meta' ), Tasks::enabledFromSettings( $settings ) );
	}

	public function test_parse_list_accepts_orphan_meta_tasks(): void {
		$this->assertSame(
			array( 'orphan_post_meta', 'orphan_comment_meta' ),
			Tasks::parseList( 'orphan_comment_meta,orphan_post_meta' )
		);
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
