<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Frontend\ContentDetector;

final class ContentDetectorTest extends TestCase {

	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function wooCases(): array {
		return array(
			'empty'               => array( '', false ),
			'plain text'          => array( 'Just a post about products and carts.', false ),
			'products shortcode'  => array( 'See [products limit="4"] here', true ),
			'product shortcode'   => array( '[product id="5"]', true ),
			'product_page'        => array( '[product_page id="5"]', true ),
			'woocommerce_cart'    => array( '[woocommerce_cart]', true ),
			'add_to_cart'         => array( '[add_to_cart id="9"]', true ),
			'woo block'           => array( '<!-- wp:woocommerce/product-collection {"a":1} /-->', true ),
			'core block'          => array( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', false ),
			'similar shortcode'   => array( '[productive]', false ),
		);
	}

	#[DataProvider( 'wooCases' )]
	public function test_uses_woocommerce( string $content, bool $expected ): void {
		$this->assertSame( $expected, ContentDetector::usesWooCommerce( $content ) );
	}

	/**
	 * @return array<string,array{string,bool}>
	 */
	public static function cartCases(): array {
		return array(
			'empty'            => array( '', false ),
			'cart shortcode'   => array( '[woocommerce_cart]', true ),
			'checkout'         => array( '[woocommerce_checkout]', true ),
			'mini cart block'  => array( '<!-- wp:woocommerce/mini-cart /-->', true ),
			'checkout block'   => array( '<!-- wp:woocommerce/checkout -->', true ),
			'product list'     => array( '[products]', false ),
		);
	}

	#[DataProvider( 'cartCases' )]
	public function test_uses_cart_component( string $content, bool $expected ): void {
		$this->assertSame( $expected, ContentDetector::usesCartComponent( $content ) );
	}
}
