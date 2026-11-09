<?php
/**
 * Reads the plugin settings option.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration;

use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class SettingsStore {

	public const OPTION = 'woo_performance_toolkit_settings';

	/**
	 * Resolved settings (stored values over defaults, sanitised).
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		return Options::resolve( get_option( self::OPTION, array() ) );
	}
}
