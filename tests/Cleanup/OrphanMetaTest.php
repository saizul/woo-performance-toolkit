<?php
declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Tests\Cleanup;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SaizulAmin\WooPerformanceToolkit\Cleanup\OrphanMeta;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;

final class OrphanMetaTest extends TestCase {

	public function test_every_spec_belongs_to_a_known_task(): void {
		foreach ( array_keys( OrphanMeta::all() ) as $task ) {
			$this->assertContains( $task, Tasks::all() );
		}
	}

	public function test_orphan_tasks_are_covered(): void {
		$this->assertSame(
			array(
				Tasks::ORPHAN_ITEM_META,
				Tasks::ORPHAN_ORDER_META,
				Tasks::ORPHAN_POST_META,
				Tasks::ORPHAN_TERM_META,
				Tasks::ORPHAN_COMMENT_META,
			),
			array_keys( OrphanMeta::all() )
		);
	}

	public function test_non_orphan_tasks_have_no_spec(): void {
		$this->assertNull( OrphanMeta::forTask( Tasks::SESSIONS ) );
		$this->assertNull( OrphanMeta::forTask( Tasks::TRANSIENTS ) );
		$this->assertNull( OrphanMeta::forTask( Tasks::ACTION_SCHEDULER ) );
		$this->assertNull( OrphanMeta::forTask( 'unknown' ) );
	}

	public function test_post_meta_spec_matches_core_schema(): void {
		$spec = OrphanMeta::forTask( Tasks::ORPHAN_POST_META );

		$this->assertNotNull( $spec );
		$this->assertSame( 'postmeta', $spec->meta_table );
		$this->assertSame( 'posts', $spec->parent_table );
		$this->assertSame( 'meta_id', $spec->meta_id_column );
		$this->assertSame( 'post_id', $spec->foreign_key );
		$this->assertSame( 'ID', $spec->parent_key );
	}

	public function test_comment_meta_spec_uses_comment_id_casing(): void {
		$spec = OrphanMeta::forTask( Tasks::ORPHAN_COMMENT_META );

		$this->assertNotNull( $spec );
		$this->assertSame( 'comment_id', $spec->foreign_key );
		$this->assertSame( 'comment_ID', $spec->parent_key );
	}

	public function test_meta_and_parent_tables_differ(): void {
		foreach ( OrphanMeta::all() as $task => $spec ) {
			$this->assertNotSame( $spec->meta_table, $spec->parent_table, $task );
		}
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidNames(): array {
		return array(
			'empty'        => array( '' ),
			'backtick'     => array( 'meta`id' ),
			'space'        => array( 'meta id' ),
			'dot'          => array( 'p.ID' ),
			'leading digit' => array( '1meta' ),
			'injection'    => array( 'ID; DROP TABLE wp_posts' ),
			'too long'     => array( str_repeat( 'a', 65 ) ),
		);
	}

	#[DataProvider( 'invalidNames' )]
	public function test_rejects_invalid_identifiers( string $name ): void {
		$this->expectException( InvalidArgumentException::class );
		new OrphanMeta( 'postmeta', 'posts', 'meta_id', $name, 'ID' );
	}
}
