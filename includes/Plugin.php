<?php
/**
 * Plugin bootstrap.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration;

use SaizulAmin\WooPerformanceToolkit\Integration\Admin\SettingsPage;
use SaizulAmin\WooPerformanceToolkit\Integration\Admin\WooAdmin;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Scheduler;
use SaizulAmin\WooPerformanceToolkit\Integration\Cli\Command;
use SaizulAmin\WooPerformanceToolkit\Integration\Frontend\Optimizer;
use SaizulAmin\WooPerformanceToolkit\Integration\Heartbeat\Controller;

final class Plugin {

	/**
	 * Wire every feature. Runs on plugins_loaded.
	 */
	public static function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_woocommerce_notice' ) );
			return;
		}

		$settings = SettingsStore::get();

		( new Optimizer( $settings ) )->register();
		( new WooAdmin( $settings ) )->register();
		( new Controller( $settings ) )->register();
		( new Scheduler( $settings ) )->register();

		if ( is_admin() ) {
			( new SettingsPage() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'woo-perf', Command::class );
		}
	}

	public static function missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Woo Performance Toolkit needs WooCommerce to be active.', 'woo-performance-toolkit' );
		echo '</p></div>';
	}
}
