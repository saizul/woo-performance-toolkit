<?php
/**
 * Multi-line textarea setting helpers.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Support;

final class Lines {

	public const MAX_LINES = 200;

	public const MAX_LINE_LENGTH = 300;

	/**
	 * Split a textarea value into clean, unique, non-empty lines.
	 *
	 * Lines starting with "#" are treated as comments and dropped. Tags and
	 * control characters are stripped.
	 *
	 * @return string[]
	 */
	public static function parse( string $text, int $max = self::MAX_LINES ): array {
		$result = array();

		foreach ( preg_split( '/\R/u', $text ) ?: array() as $line ) {
			$line = strip_tags( $line );
			$line = (string) preg_replace( '/[\x00-\x1F\x7F]/u', '', $line );
			$line = trim( $line );

			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			$line = substr( $line, 0, self::MAX_LINE_LENGTH );

			if ( in_array( $line, $result, true ) ) {
				continue;
			}

			$result[] = $line;

			if ( count( $result ) >= $max ) {
				break;
			}
		}

		return $result;
	}

	/**
	 * Normalise a textarea value (parse, then join with newlines).
	 */
	public static function normalize( string $text, int $max = self::MAX_LINES ): string {
		return implode( "\n", self::parse( $text, $max ) );
	}
}
