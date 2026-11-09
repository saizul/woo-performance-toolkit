<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Support\Bytes;

final class BytesTest extends TestCase {

	/**
	 * @return array<string,array{int,string}>
	 */
	public static function cases(): array {
		return array(
			'zero'        => array( 0, '0 B' ),
			'negative'    => array( -5, '0 B' ),
			'bytes'       => array( 1023, '1023 B' ),
			'one kb'      => array( 1024, '1 KB' ),
			'one and half' => array( 1536, '1.5 KB' ),
			'mb'          => array( 5 * 1048576, '5 MB' ),
			'gb'          => array( 3 * 1073741824, '3 GB' ),
			'rounding'    => array( 800000, '781.3 KB' ),
		);
	}

	#[DataProvider( 'cases' )]
	public function test_format( int $input, string $expected ): void {
		$this->assertSame( $expected, Bytes::format( $input ) );
	}

	public function test_zero_precision(): void {
		$this->assertSame( '2 KB', Bytes::format( 1900, 0 ) );
	}
}
