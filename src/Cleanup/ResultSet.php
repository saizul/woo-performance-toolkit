<?php
/**
 * Per-task cleanup counts (rows found in a dry run, or rows deleted).
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Cleanup;

final class ResultSet {

	/** @var array<string,int> */
	private array $counts = array();

	/**
	 * Add to a task's count (negative values are ignored).
	 */
	public function add( string $task, int $count ): void {
		Tasks::assertValid( $task );

		$this->counts[ $task ] = ( $this->counts[ $task ] ?? 0 ) + max( 0, $count );
	}

	public function get( string $task ): int {
		return $this->counts[ $task ] ?? 0;
	}

	public function total(): int {
		return array_sum( $this->counts );
	}

	public function isEmpty(): bool {
		return 0 === $this->total();
	}

	/**
	 * @return array<string,int>
	 */
	public function toArray(): array {
		return $this->counts;
	}

	/**
	 * Rebuild from toArray() output, ignoring unknown keys.
	 *
	 * @param array<string,mixed> $data Stored data.
	 */
	public static function fromArray( array $data ): self {
		$set = new self();

		foreach ( Tasks::all() as $task ) {
			if ( isset( $data[ $task ] ) ) {
				$set->add( $task, (int) $data[ $task ] );
			}
		}

		return $set;
	}

	/**
	 * One-line summary, e.g. "sessions: 12, transients: 0".
	 */
	public function summary(): string {
		$parts = array();

		foreach ( $this->counts as $task => $count ) {
			$parts[] = $task . ': ' . $count;
		}

		return implode( ', ', $parts );
	}
}
