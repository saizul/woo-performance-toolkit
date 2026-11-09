<?php
/**
 * Removes cart fragments and WooCommerce assets from pages that do not need them.
 *
 * Filters:
 *  - woo_performance_toolkit_is_woocommerce_page ( bool $is_woo, int $post_id )
 *  - woo_performance_toolkit_keep_cart_fragments ( bool $keep, int $post_id )
 *  - woo_performance_toolkit_keep_handles        ( string[] $handles, string $type )
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Frontend;

use SaizulAmin\WooPerformanceToolkit\Frontend\AssetRules;
use SaizulAmin\WooPerformanceToolkit\Frontend\ContentDetector;
use SaizulAmin\WooPerformanceToolkit\Frontend\PageMatcher;
use SaizulAmin\WooPerformanceToolkit\Support\Lines;

final class Optimizer {

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function __construct( private array $settings ) {
	}

	public function register(): void {
		if ( ! empty( $this->settings['cart_fragments_disable'] ) || ! empty( $this->settings['assets_dequeue'] ) ) {
			// Late priority: after WooCommerce has enqueued its assets.
			add_action( 'wp_enqueue_scripts', array( $this, 'optimize' ), 99 );
		}
	}

	public function optimize(): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		$content = $this->queried_content();

		$allow_listed = PageMatcher::fromText( (string) $this->settings['cart_fragments_allowlist'] )
			->matches( $post_id, $this->request_path() );

		$has_widget = ( function_exists( 'is_active_widget' ) && is_active_widget( false, false, 'woocommerce_widget_cart', true ) )
			|| ContentDetector::usesCartComponent( $content );

		$is_cart_or_checkout = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() );

		$is_woo_page = function_exists( 'is_woocommerce' )
			&& ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );

		/**
		 * Filter whether the current request is treated as a WooCommerce page.
		 *
		 * @param bool $is_woo_page Detected value.
		 * @param int  $post_id     Queried object ID.
		 */
		$is_woo_page = (bool) apply_filters( 'woo_performance_toolkit_is_woocommerce_page', $is_woo_page, $post_id );

		if ( AssetRules::shouldDequeueCartFragments(
			! empty( $this->settings['cart_fragments_disable'] ),
			$is_cart_or_checkout,
			$allow_listed,
			$has_widget
		) ) {
			/**
			 * Filter to keep cart fragments on the current page.
			 *
			 * @param bool $keep    Whether to keep the script.
			 * @param int  $post_id Queried object ID.
			 */
			if ( ! apply_filters( 'woo_performance_toolkit_keep_cart_fragments', false, $post_id ) ) {
				wp_dequeue_script( AssetRules::CART_FRAGMENTS );
			}
		}

		if ( AssetRules::shouldDequeueAssets(
			! empty( $this->settings['assets_dequeue'] ),
			$is_woo_page,
			ContentDetector::usesWooCommerce( $content ),
			$allow_listed,
			$has_widget
		) ) {
			$this->dequeue_assets();
		}
	}

	private function dequeue_assets(): void {
		$configured = Lines::parse( (string) $this->settings['assets_keep_handles'] );

		foreach ( array( 'script', 'style' ) as $type ) {
			/**
			 * Filter handles that must never be dequeued.
			 *
			 * @param string[] $handles Handles to keep.
			 * @param string   $type    "script" or "style".
			 */
			$keep = (array) apply_filters( 'woo_performance_toolkit_keep_handles', $configured, $type );

			foreach ( AssetRules::handlesToRemove( array_map( 'strval', $keep ), $type ) as $handle ) {
				if ( 'script' === $type ) {
					wp_dequeue_script( $handle );
				} else {
					wp_dequeue_style( $handle );
				}
			}
		}
	}

	private function queried_content(): string {
		$post = get_queried_object();

		return ( $post instanceof \WP_Post ) ? (string) $post->post_content : '';
	}

	/**
	 * Request path relative to the site root, without query string.
	 */
	private function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$uri  = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$base = (string) wp_parse_url( home_url(), PHP_URL_PATH );

		return PageMatcher::stripBasePath( '' === $uri ? '/' : $uri, $base );
	}
}
