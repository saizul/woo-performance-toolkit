<?php
/**
 * Detects WooCommerce shortcodes and blocks in post content.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Frontend;

final class ContentDetector {

	private const SHORTCODE_PATTERN = '/\[(?:woocommerce_[a-z_]+|products?|product_[a-z_]+|add_to_cart(?:_url)?|shop_messages|recent_products|featured_products|sale_products|best_selling_products|top_rated_products|related_products)(?:[\s\]\/])/i';

	private const BLOCK_PATTERN = '/<!--\s+wp:woocommerce\//i';

	private const CART_SHORTCODE_PATTERN = '/\[woocommerce_(?:cart|checkout|my_account)(?:[\s\]\/])/i';

	private const CART_BLOCK_PATTERN = '/<!--\s+wp:woocommerce\/(?:mini-cart|cart|checkout|filled-cart|empty-cart|cart-link)\b/i';

	/**
	 * Whether the content uses any WooCommerce shortcode or block.
	 */
	public static function usesWooCommerce( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}

		return 1 === preg_match( self::SHORTCODE_PATTERN, $content )
			|| 1 === preg_match( self::BLOCK_PATTERN, $content );
	}

	/**
	 * Whether the content renders a cart (or checkout/account) component that
	 * relies on cart fragments.
	 */
	public static function usesCartComponent( string $content ): bool {
		if ( '' === $content ) {
			return false;
		}

		return 1 === preg_match( self::CART_SHORTCODE_PATTERN, $content )
			|| 1 === preg_match( self::CART_BLOCK_PATTERN, $content );
	}
}
