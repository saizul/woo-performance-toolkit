<?php
/**
 * Minimal PSR-4 autoloader (works with or without Composer).
 *
 * `SaizulAmin\WooPerformanceToolkit\Integration\*` maps to includes/ (WordPress
 * glue code); everything else under the root namespace maps to src/ (pure logic).
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'SaizulAmin\\WooPerformanceToolkit\\';

		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$base     = __DIR__ . '/src/';

		if ( strncmp( $relative, 'Integration\\', 12 ) === 0 ) {
			$relative = substr( $relative, 12 );
			$base     = __DIR__ . '/includes/';
		}

		$file = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
