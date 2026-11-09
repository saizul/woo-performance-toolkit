<?php
/**
 * A single diagnostics recommendation.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Diagnostics;

final class Recommendation {

	public const INFO = 'info';

	/**
	 * @param string $severity One of Thresholds::CRITICAL / WARNING or Recommendation::INFO.
	 * @param string $code     Stable machine-readable identifier.
	 * @param string $title    Short title.
	 * @param string $detail   Explanation and suggested action.
	 */
	public function __construct(
		public readonly string $severity,
		public readonly string $code,
		public readonly string $title,
		public readonly string $detail
	) {
	}

	/**
	 * Sort weight: higher is more urgent.
	 */
	public function weight(): int {
		return match ( $this->severity ) {
			Thresholds::CRITICAL => 3,
			Thresholds::WARNING  => 2,
			default              => 1,
		};
	}

	/**
	 * @return array{severity:string,code:string,title:string,detail:string}
	 */
	public function toArray(): array {
		return array(
			'severity' => $this->severity,
			'code'     => $this->code,
			'title'    => $this->title,
			'detail'   => $this->detail,
		);
	}
}
