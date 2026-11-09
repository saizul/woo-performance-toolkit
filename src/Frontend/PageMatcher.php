<?php
/**
 * Allow-list matching for post IDs and URL paths.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Frontend;

use SaizulAmin\WooPerformanceToolkit\Support\Lines;

/**
 * Entries are either numeric post IDs, or URL paths / full URLs. A trailing
 * asterisk makes a path entry a prefix match ("/blog/*").
 */
final class PageMatcher {

	/** @var int[] */
	private array $ids = array();

	/** @var string[] */
	private array $paths = array();

	/**
	 * @param string[] $entries Raw allow-list entries.
	 */
	public function __construct( array $entries ) {
		foreach ( $entries as $entry ) {
			$entry = trim( (string) $entry );

			if ( '' === $entry ) {
				continue;
			}

			if ( ctype_digit( $entry ) ) {
				$id = (int) $entry;
				if ( $id > 0 && ! in_array( $id, $this->ids, true ) ) {
					$this->ids[] = $id;
				}
				continue;
			}

			$path = self::normalizePath( $entry );
			if ( ! in_array( $path, $this->paths, true ) ) {
				$this->paths[] = $path;
			}
		}
	}

	/**
	 * Build from a textarea value.
	 */
	public static function fromText( string $text ): self {
		return new self( Lines::parse( $text ) );
	}

	/**
	 * @return int[]
	 */
	public function ids(): array {
		return $this->ids;
	}

	/**
	 * @return string[]
	 */
	public function paths(): array {
		return $this->paths;
	}

	/**
	 * Whether the current request is on the allow-list.
	 *
	 * @param int    $postId      Queried object ID (0 if none).
	 * @param string $requestPath Request path, relative to the site root.
	 */
	public function matches( int $postId, string $requestPath ): bool {
		if ( $postId > 0 && in_array( $postId, $this->ids, true ) ) {
			return true;
		}

		if ( array() === $this->paths ) {
			return false;
		}

		$path = self::normalizePath( $requestPath );

		foreach ( $this->paths as $candidate ) {
			if ( str_ends_with( $candidate, '*' ) ) {
				if ( str_starts_with( $path, substr( $candidate, 0, -1 ) ) ) {
					return true;
				}
				continue;
			}

			if ( $candidate === $path ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalise a URL or path: strip scheme/host/query/fragment, lowercase,
	 * collapse duplicate slashes, force a leading slash, drop trailing slash.
	 */
	public static function normalizePath( string $value ): string {
		$value = trim( $value );

		if ( str_contains( $value, '://' ) ) {
			$parsed = parse_url( $value, PHP_URL_PATH );
			$value  = is_string( $parsed ) ? $parsed : '/';
		}

		$cut = strcspn( $value, '?#' );
		if ( $cut < strlen( $value ) ) {
			$value = substr( $value, 0, $cut );
		}

		$value = strtolower( $value );
		$value = '/' . ltrim( $value, '/' );
		$value = (string) preg_replace( '#/{2,}#', '/', $value );

		if ( strlen( $value ) > 1 ) {
			$value = rtrim( $value, '/' );
			if ( '' === $value ) {
				$value = '/';
			}
		}

		return $value;
	}

	/**
	 * Remove a sub-directory install prefix from a request path.
	 *
	 * @param string $path Request path (e.g. "/wp/shop/").
	 * @param string $base Site base path (e.g. "/wp").
	 */
	public static function stripBasePath( string $path, string $base ): string {
		$base = rtrim( $base, '/' );

		if ( '' === $base || '/' === $base ) {
			return $path;
		}

		if ( $path === $base ) {
			return '/';
		}

		if ( str_starts_with( $path, $base . '/' ) ) {
			return substr( $path, strlen( $base ) );
		}

		return $path;
	}
}
