<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Frontend;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Frontend\AssetRules;

final class AssetRulesTest extends TestCase {

	public function test_cart_fragments_handle_is_in_script_list(): void {
		$this->assertContains( AssetRules::CART_FRAGMENTS, AssetRules::scriptHandles() );
	}

	public function test_handle_lists_are_unique(): void {
		$this->assertSame( AssetRules::scriptHandles(), array_values( array_unique( AssetRules::scriptHandles() ) ) );
		$this->assertSame( AssetRules::styleHandles(), array_values( array_unique( AssetRules::styleHandles() ) ) );
	}

	public function test_keep_list_is_respected(): void {
		$remove = AssetRules::handlesToRemove( array( 'woocommerce', 'js-cookie' ), 'script' );

		$this->assertNotContains( 'woocommerce', $remove );
		$this->assertNotContains( 'js-cookie', $remove );
		$this->assertContains( 'wc-cart-fragments', $remove );
	}

	public function test_style_type(): void {
		$this->assertContains( 'woocommerce-general', AssetRules::handlesToRemove( array(), 'style' ) );
		$this->assertNotContains( 'woocommerce', AssetRules::handlesToRemove( array(), 'style' ) );
	}

	public function test_dequeue_assets_requires_toggle(): void {
		$this->assertFalse( AssetRules::shouldDequeueAssets( false, false, false, false, false ) );
	}

	public function test_dequeue_assets_on_plain_page(): void {
		$this->assertTrue( AssetRules::shouldDequeueAssets( true, false, false, false, false ) );
	}

	public function test_assets_kept_on_any_woo_signal(): void {
		$this->assertFalse( AssetRules::shouldDequeueAssets( true, true, false, false, false ) );
		$this->assertFalse( AssetRules::shouldDequeueAssets( true, false, true, false, false ) );
		$this->assertFalse( AssetRules::shouldDequeueAssets( true, false, false, true, false ) );
		$this->assertFalse( AssetRules::shouldDequeueAssets( true, false, false, false, true ) );
	}

	public function test_cart_fragments_toggle_off(): void {
		$this->assertFalse( AssetRules::shouldDequeueCartFragments( false, false, false, false ) );
	}

	public function test_cart_fragments_removed_by_default_when_enabled(): void {
		$this->assertTrue( AssetRules::shouldDequeueCartFragments( true, false, false, false ) );
	}

	public function test_cart_fragments_kept_on_cart_allowlist_or_widget(): void {
		$this->assertFalse( AssetRules::shouldDequeueCartFragments( true, true, false, false ) );
		$this->assertFalse( AssetRules::shouldDequeueCartFragments( true, false, true, false ) );
		$this->assertFalse( AssetRules::shouldDequeueCartFragments( true, false, false, true ) );
	}
}
