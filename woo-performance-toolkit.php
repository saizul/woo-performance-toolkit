<?php
/**
 * Plugin Name:       Woo Performance Toolkit
 * Plugin URI:        https://github.com/saizul/woo-performance-toolkit
 * Description:       Trim WooCommerce overhead: cart fragments, front-end assets, heartbeat, scheduled database cleanup and a diagnostics screen with recommendations.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Saizul Amin
 * Author URI:        https://saizul.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-performance-toolkit
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   9.5
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'WOO_PERF_VERSION', '1.0.0' );
define( 'WOO_PERF_FILE', __FILE__ );
define( 'WOO_PERF_DIR', plugin_dir_path( __FILE__ ) );

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Woo Performance Toolkit requires PHP 8.1 or newer.', 'woo-performance-toolkit' );
			echo '</p></div>';
		}
	);
	return;
}

require_once WOO_PERF_DIR . 'autoload.php';

// Declare compatibility with High-Performance Order Storage and the Cart/Checkout blocks.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WOO_PERF_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WOO_PERF_FILE, true );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		wp_clear_scheduled_hook( \SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Scheduler::HOOK );
	}
);

add_action( 'plugins_loaded', array( \SaizulAmin\WooPerformanceToolkit\Integration\Plugin::class, 'boot' ) );
