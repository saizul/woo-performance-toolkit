<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Frontend\PageMatcher;

final class PageMatcherTest extends TestCase {

	public function test_splits_ids_and_paths(): void {
		$m = PageMatcher::fromText( "42\n/Shop/\nhttps://example.com/sale?x=1\n7" );

		$this->assertSame( array( 42, 7 ), $m->ids() );
		$this->assertSame( array( '/shop', '/sale' ), $m->paths() );
	}

	public function test_matches_by_id(): void {
		$m = PageMatcher::fromText( '42' );

		$this->assertTrue( $m->matches( 42, '/anything' ) );
		$this->assertFalse( $m->matches( 43, '/anything' ) );
	}

	public function test_zero_post_id_never_matches_id(): void {
		$this->assertFalse( PageMatcher::fromText( '0' )->matches( 0, '/' ) );
	}

	public function test_matches_exact_path_ignoring_case_and_trailing_slash(): void {
		$m = PageMatcher::fromText( '/Landing-Page' );

		$this->assertTrue( $m->matches( 0, '/landing-page/' ) );
		$this->assertTrue( $m->matches( 0, '/LANDING-PAGE?utm=1' ) );
		$this->assertFalse( $m->matches( 0, '/landing-page/sub' ) );
	}

	public function test_wildcard_prefix(): void {
		$m = PageMatcher::fromText( '/blog/*' );

		$this->assertTrue( $m->matches( 0, '/blog/hello' ) );
		$this->assertTrue( $m->matches( 0, '/blog/a/b' ) );
		$this->assertFalse( $m->matches( 0, '/blogger' ) );
	}

	public function test_full_url_entry_is_reduced_to_path(): void {
		$this->assertTrue( PageMatcher::fromText( 'https://shop.test/offers/' )->matches( 0, '/offers' ) );
	}

	public function test_empty_matcher_matches_nothing(): void {
		$this->assertFalse( PageMatcher::fromText( '' )->matches( 5, '/' ) );
	}

	public function test_root_path(): void {
		$m = PageMatcher::fromText( '/' );
		$this->assertTrue( $m->matches( 0, '/' ) );
		$this->assertFalse( $m->matches( 0, '/about' ) );
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function normalizeCases(): array {
		return array(
			'plain'        => array( 'shop', '/shop' ),
			'double slash' => array( '//a//b/', '/a/b' ),
			'query'        => array( '/a?b=1#c', '/a' ),
			'uppercase'    => array( '/ABC', '/abc' ),
			'url'          => array( 'http://x.test', '/' ),
			'root'         => array( '/', '/' ),
			'wildcard'     => array( '/Shop/*', '/shop/*' ),
		);
	}

	#[DataProvider( 'normalizeCases' )]
	public function test_normalize_path( string $input, string $expected ): void {
		$this->assertSame( $expected, PageMatcher::normalizePath( $input ) );
	}

	public function test_strip_base_path(): void {
		$this->assertSame( '/shop/', PageMatcher::stripBasePath( '/wp/shop/', '/wp' ) );
		$this->assertSame( '/', PageMatcher::stripBasePath( '/wp', '/wp/' ) );
		$this->assertSame( '/wpx/shop', PageMatcher::stripBasePath( '/wpx/shop', '/wp' ) );
		$this->assertSame( '/shop', PageMatcher::stripBasePath( '/shop', '' ) );
	}
}
