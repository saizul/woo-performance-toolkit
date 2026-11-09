<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Support;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Support\Lines;

final class LinesTest extends TestCase {

	public function test_splits_trims_and_drops_empty_lines(): void {
		$this->assertSame( array( 'a', 'b' ), Lines::parse( " a \n\n  b  \r\n" ) );
	}

	public function test_removes_duplicates_keeping_order(): void {
		$this->assertSame( array( 'x', 'y' ), Lines::parse( "x\ny\nx" ) );
	}

	public function test_ignores_comment_lines(): void {
		$this->assertSame( array( '/shop' ), Lines::parse( "# note\n/shop" ) );
	}

	public function test_strips_tags_and_control_characters(): void {
		$this->assertSame( array( 'alert(1)' ), Lines::parse( "<script>alert(1)</script>\x01" ) );
	}

	public function test_caps_number_of_lines(): void {
		$text = implode( "\n", range( 1, 50 ) );
		$this->assertCount( 5, Lines::parse( $text, 5 ) );
	}

	public function test_truncates_long_lines(): void {
		$parsed = Lines::parse( str_repeat( 'a', 1000 ) );
		$this->assertSame( Lines::MAX_LINE_LENGTH, strlen( $parsed[0] ) );
	}

	public function test_normalize_joins_with_newlines(): void {
		$this->assertSame( "1\n2", Lines::normalize( " 1 \n\n2\n1" ) );
	}

	public function test_empty_string(): void {
		$this->assertSame( array(), Lines::parse( '' ) );
	}
}
