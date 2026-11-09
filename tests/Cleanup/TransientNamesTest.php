<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\TransientNames;

final class TransientNamesTest extends TestCase {

	public function test_prefixes(): void {
		$this->assertSame( '_transient_timeout_', TransientNames::timeoutPrefix( false ) );
		$this->assertSame( '_site_transient_', TransientNames::valuePrefix( true ) );
	}

	public function test_value_name_for_timeout(): void {
		$this->assertSame( '_transient_foo_bar', TransientNames::valueNameFor( '_transient_timeout_foo_bar', false ) );
		$this->assertSame( '_site_transient_x', TransientNames::valueNameFor( '_site_transient_timeout_x', true ) );
	}

	public function test_non_timeout_names_are_rejected(): void {
		$this->assertNull( TransientNames::valueNameFor( '_transient_foo', false ) );
		$this->assertNull( TransientNames::valueNameFor( '_transient_timeout_', false ) );
		$this->assertNull( TransientNames::valueNameFor( '_site_transient_timeout_x', false ) );
	}

	public function test_names_to_delete_includes_both_rows(): void {
		$this->assertSame(
			array( '_transient_timeout_a', '_transient_a', '_transient_timeout_b', '_transient_b' ),
			TransientNames::namesToDelete( array( '_transient_timeout_a', '_transient_timeout_b' ), false )
		);
	}

	public function test_names_to_delete_skips_foreign_names_and_dedupes(): void {
		$this->assertSame(
			array( '_transient_timeout_a', '_transient_a' ),
			TransientNames::namesToDelete( array( '_transient_timeout_a', 'siteurl', '_transient_timeout_a' ), false )
		);
	}

	public function test_names_to_delete_empty(): void {
		$this->assertSame( array(), TransientNames::namesToDelete( array(), true ) );
	}
}
