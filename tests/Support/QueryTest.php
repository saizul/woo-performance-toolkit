<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Support;

use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Support\Query;

final class QueryTest extends TestCase {

	public function test_counts_placeholders(): void {
		$q = new Query( 'SELECT * FROM %i WHERE a = %s AND b = %d', array( 't', 'x', 1 ) );
		$this->assertSame( 3, $q->placeholderCount() );
	}

	public function test_no_placeholders(): void {
		$this->assertSame( 0, ( new Query( 'SELECT 1', array() ) )->placeholderCount() );
	}
}
