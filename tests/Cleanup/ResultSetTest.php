<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\ResultSet;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;

final class ResultSetTest extends TestCase {

	public function test_starts_empty(): void {
		$set = new ResultSet();

		$this->assertTrue( $set->isEmpty() );
		$this->assertSame( 0, $set->total() );
		$this->assertSame( 0, $set->get( Tasks::SESSIONS ) );
	}

	public function test_add_accumulates(): void {
		$set = new ResultSet();
		$set->add( Tasks::SESSIONS, 5 );
		$set->add( Tasks::SESSIONS, 7 );
		$set->add( Tasks::TRANSIENTS, 3 );

		$this->assertSame( 12, $set->get( Tasks::SESSIONS ) );
		$this->assertSame( 15, $set->total() );
		$this->assertFalse( $set->isEmpty() );
	}

	public function test_negative_counts_are_ignored(): void {
		$set = new ResultSet();
		$set->add( Tasks::SESSIONS, -10 );

		$this->assertSame( 0, $set->total() );
	}

	public function test_unknown_task_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		( new ResultSet() )->add( 'nope', 1 );
	}

	public function test_round_trip_and_unknown_keys(): void {
		$set = ResultSet::fromArray( array( 'sessions' => '4', 'bogus' => 9 ) );

		$this->assertSame( array( 'sessions' => 4 ), $set->toArray() );
	}

	public function test_summary(): void {
		$set = new ResultSet();
		$set->add( Tasks::SESSIONS, 2 );
		$set->add( Tasks::TRANSIENTS, 0 );

		$this->assertSame( 'sessions: 2, transients: 0', $set->summary() );
	}
}
