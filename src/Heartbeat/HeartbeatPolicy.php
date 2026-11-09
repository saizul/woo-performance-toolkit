<?php
/**
 * Heartbeat throttling decisions.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Heartbeat;

use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class HeartbeatPolicy {

	public const ACTION_NONE     = 'none';
	public const ACTION_INTERVAL = 'interval';
	public const ACTION_DISABLE  = 'disable';

	public const MIN_INTERVAL = 15;
	public const MAX_INTERVAL = 300;

	/** Floor used when "disable" is downgraded on the post editor. */
	public const EDITOR_DISABLE_FALLBACK_INTERVAL = 60;

	/**
	 * Decide what to do with the heartbeat.
	 *
	 * The post editor needs heartbeat for autosave and post locking, so
	 * "disable" is downgraded to a slow interval there.
	 *
	 * @param string $mode           One of the Options::MODE_* constants.
	 * @param int    $interval       Desired interval in seconds.
	 * @param bool   $isPostEditor   Whether this is a post edit screen.
	 * @return array{action:string,interval:int}
	 */
	public static function decide( string $mode, int $interval, bool $isPostEditor ): array {
		$interval = max( self::MIN_INTERVAL, min( self::MAX_INTERVAL, $interval ) );

		switch ( $mode ) {
			case Options::MODE_SLOW:
				return array(
					'action'   => self::ACTION_INTERVAL,
					'interval' => $interval,
				);

			case Options::MODE_DISABLE:
				if ( $isPostEditor ) {
					return array(
						'action'   => self::ACTION_INTERVAL,
						'interval' => max( $interval, self::EDITOR_DISABLE_FALLBACK_INTERVAL ),
					);
				}

				return array(
					'action'   => self::ACTION_DISABLE,
					'interval' => 0,
				);

			default:
				return array(
					'action'   => self::ACTION_NONE,
					'interval' => 0,
				);
		}
	}
}
