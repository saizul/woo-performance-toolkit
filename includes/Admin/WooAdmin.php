<?php
/**
 * Reduces WooCommerce admin overhead using documented filters only.
 *
 * Filters:
 *  - woo_performance_toolkit_disabled_admin_features ( string[] $features )
 *    WooCommerce Admin features removed when "marketing" is disabled.
 *    Default: array( 'marketing' ).
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Admin;

final class WooAdmin {

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function __construct( private array $settings ) {
	}

	public function register(): void {
		if ( ! empty( $this->settings['marketing_disable'] ) ) {
			add_filter( 'woocommerce_admin_features', array( $this, 'remove_features' ) );
			add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
		}

		if ( ! empty( $this->settings['wc_admin_disable'] ) ) {
			add_filter( 'woocommerce_admin_disabled', '__return_true' );
		}
	}

	/**
	 * @param mixed $features Enabled WooCommerce Admin features.
	 * @return array<int,string>
	 */
	public function remove_features( $features ): array {
		/**
		 * Filter the WooCommerce Admin features to remove.
		 *
		 * @param string[] $remove Feature slugs. Default: marketing.
		 */
		$remove = (array) apply_filters( 'woo_performance_toolkit_disabled_admin_features', array( 'marketing' ) );

		return array_values( array_diff( (array) $features, array_map( 'strval', $remove ) ) );
	}
}
