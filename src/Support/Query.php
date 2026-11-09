<?php
/**
 * Immutable SQL template + arguments, ready for $wpdb->prepare().
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Support;

/**
 * A prepared-statement template. Identifiers use %i, values use %s / %d.
 */
final class Query {

	/**
	 * @param string            $sql  SQL containing only %i, %s and %d placeholders.
	 * @param array<int,mixed>  $args Placeholder arguments, in order.
	 */
	public function __construct(
		public readonly string $sql,
		public readonly array $args
	) {
	}

	/**
	 * Number of placeholders in the template.
	 */
	public function placeholderCount(): int {
		return (int) preg_match_all( '/%[isd]/', $this->sql );
	}
}
