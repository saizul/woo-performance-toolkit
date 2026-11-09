<?php
/**
 * WP-Cron integration for the scheduled cleanup.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Cleanup;

use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Integration\SettingsStore;

final class Scheduler {

	public const HOOK        = 'woo_performance_toolkit_cleanup';
	public const LAST_RUN    = 'woo_performance_toolkit_last_run';
	public const WEEKLY_SLUG = 'woo_perf_weekly';

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function __construct( private array $settings ) {
	}

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'sync' ) );
		add_action( 'update_option_' . SettingsStore::OPTION, array( $this, 'sync_after_update' ) );
	}

	/**
	 * @param mixed $schedules Registered cron schedules.
	 * @return array<string,mixed>
	 */
	public function add_schedules( $schedules ): array {
		$schedules                      = is_array( $schedules ) ? $schedules : array();
		$schedules[ self::WEEKLY_SLUG ] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once weekly', 'woo-performance-toolkit' ),
		);

		return $schedules;
	}

	public function sync_after_update(): void {
		$this->settings = SettingsStore::get();
		$this->sync();
	}

	/**
	 * Make the scheduled event match the settings.
	 */
	public function sync(): void {
		$event = wp_get_scheduled_event( self::HOOK );

		if ( empty( $this->settings['cleanup_enabled'] ) ) {
			if ( $event ) {
				wp_clear_scheduled_hook( self::HOOK );
			}
			return;
		}

		$wanted = 'weekly' === $this->settings['cleanup_frequency'] ? self::WEEKLY_SLUG : 'daily';

		if ( $event && $event->schedule === $wanted ) {
			return;
		}

		if ( $event ) {
			wp_clear_scheduled_hook( self::HOOK );
		}

		wp_schedule_event( time() + HOUR_IN_SECONDS, $wanted, self::HOOK );
	}

	/**
	 * Cron callback.
	 */
	public function run(): void {
		global $wpdb;

		$settings = SettingsStore::get();

		if ( empty( $settings['cleanup_enabled'] ) ) {
			return;
		}

		$tasks  = Tasks::enabledFromSettings( $settings );
		$result = ( new Runner( $wpdb ) )->run( $tasks, $settings );

		update_option(
			self::LAST_RUN,
			array(
				'time'    => time(),
				'results' => $result->toArray(),
			),
			false
		);
	}
}
