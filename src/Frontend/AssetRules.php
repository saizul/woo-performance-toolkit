<?php
/**
 * Which WooCommerce front-end assets to remove, and when.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Frontend;

final class AssetRules {

	public const CART_FRAGMENTS = 'wc-cart-fragments';

	/**
	 * Script handles registered by WooCommerce's front end.
	 *
	 * @return string[]
	 */
	public static function scriptHandles(): array {
		return array(
			'woocommerce',
			'wc-add-to-cart',
			'wc-add-to-cart-variation',
			'wc-cart',
			'wc-cart-fragments',
			'wc-checkout',
			'wc-country-select',
			'wc-address-i18n',
			'wc-credit-card-form',
			'wc-single-product',
			'wc-order-attribution',
			'jquery-blockui',
			'jquery-payment',
			'jquery-placeholder',
			'js-cookie',
			'flexslider',
			'photoswipe',
			'photoswipe-ui-default',
			'prettyPhoto',
			'zoom',
			'selectWoo',
		);
	}

	/**
	 * Style handles registered by WooCommerce's front end.
	 *
	 * @return string[]
	 */
	public static function styleHandles(): array {
		return array(
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce_frontend_styles',
			'woocommerce_fancybox_styles',
			'woocommerce_chosen_styles',
			'woocommerce_prettyPhoto_css',
			'wc-blocks-style',
			'wc-blocks-vendors-style',
			'wc-all-blocks-style',
			'photoswipe',
			'photoswipe-default-skin',
			'select2',
		);
	}

	/**
	 * Handles to dequeue after removing the keep-list.
	 *
	 * @param string[] $keep Handles that must stay.
	 * @param string   $type "script" or "style".
	 * @return string[]
	 */
	public static function handlesToRemove( array $keep, string $type ): array {
		$all = 'style' === $type ? self::styleHandles() : self::scriptHandles();

		return array_values( array_diff( $all, $keep ) );
	}

	/**
	 * Whether the WooCommerce assets should be removed for this request.
	 *
	 * @param bool $enabled         Feature toggle.
	 * @param bool $isWooPage       WooCommerce endpoint/archive/single/cart/checkout/account.
	 * @param bool $contentUsesWoo  Content contains WooCommerce shortcodes/blocks.
	 * @param bool $isAllowListed   Page is on the allow-list.
	 * @param bool $hasCartWidget   An active cart widget is on the page.
	 */
	public static function shouldDequeueAssets( bool $enabled, bool $isWooPage, bool $contentUsesWoo, bool $isAllowListed, bool $hasCartWidget ): bool {
		if ( ! $enabled ) {
			return false;
		}

		return ! ( $isWooPage || $contentUsesWoo || $isAllowListed || $hasCartWidget );
	}

	/**
	 * Whether wc-cart-fragments should be dequeued for this request.
	 *
	 * @param bool $enabled        Feature toggle.
	 * @param bool $isCartOrCheckout Cart or checkout page.
	 * @param bool $isAllowListed  Page is on the allow-list.
	 * @param bool $hasCartWidget  An active cart widget / cart block is on the page.
	 */
	public static function shouldDequeueCartFragments( bool $enabled, bool $isCartOrCheckout, bool $isAllowListed, bool $hasCartWidget ): bool {
		if ( ! $enabled ) {
			return false;
		}

		return ! ( $isCartOrCheckout || $isAllowListed || $hasCartWidget );
	}
}
