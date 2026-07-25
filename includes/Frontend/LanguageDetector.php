<?php
/**
 * Detect current request language from URL prefix.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Frontend;

use BudgetTranslator\Settings;

/**
 * Class LanguageDetector
 */
final class LanguageDetector {

	/**
	 * Current language code.
	 *
	 * @var string|null
	 */
	private ?string $current = null;

	/**
	 * Whether a language prefix was detected.
	 *
	 * @var bool
	 */
	private bool $has_prefix = false;

	/**
	 * Detect language and strip prefix from REQUEST_URI for WP routing.
	 */
	public function detect_and_strip(): void {
		if ( $this->is_backend_request() ) {
			$this->current = Settings::source_lang();
			return;
		}

		if ( ! Settings::get( 'enable_rewrites', 1 ) ) {
			$this->detect_from_cookie();
			return;
		}

		$targets = Settings::target_langs();
		if ( array() === $targets ) {
			$this->current = Settings::source_lang();
			return;
		}

		$uri       = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$parts     = wp_parse_url( $uri );
		$path      = $parts['path'] ?? '/';
		$query     = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );

		$relative = $path;
		if ( $home_path && str_starts_with( $path, $home_path ) ) {
			$relative = substr( $path, strlen( $home_path ) ) ?: '/';
		}
		$relative = '/' . ltrim( $relative, '/' );

		foreach ( $targets as $lang ) {
			$prefix = '/' . $lang;
			if ( $relative === $prefix || $relative === $prefix . '/' ) {
				$this->current    = $lang;
				$this->has_prefix = true;
				$new_relative     = '/';
				$_SERVER['REQUEST_URI'] = $home_path . $new_relative . $query;
				return;
			}
			if ( str_starts_with( $relative, $prefix . '/' ) ) {
				$this->current    = $lang;
				$this->has_prefix = true;
				$new_relative     = substr( $relative, strlen( $prefix ) );
				if ( '' === $new_relative ) {
					$new_relative = '/';
				}
				$_SERVER['REQUEST_URI'] = $home_path . $new_relative . $query;
				return;
			}
		}

		$this->detect_from_cookie();
	}

	/**
	 * Ensure current language is set.
	 */
	public function detect(): void {
		if ( null !== $this->current ) {
			return;
		}

		if ( $this->is_backend_request() ) {
			$this->current = Settings::source_lang();
			return;
		}

		$this->detect_from_cookie();
	}

	/**
	 * Cookie / default fallback.
	 */
	private function detect_from_cookie(): void {
		$source  = Settings::source_lang();
		$targets = Settings::target_langs();
		$cookie  = isset( $_COOKIE['bt_lang'] ) ? sanitize_key( (string) $_COOKIE['bt_lang'] ) : '';

		if ( $cookie && in_array( $cookie, $targets, true ) ) {
			$this->current = $cookie;
			return;
		}

		$this->current = $source;
	}

	/**
	 * Admin / cron / REST bootstrap requests should stay on source language.
	 */
	private function is_backend_request(): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		if ( str_contains( $uri, '/wp-json/' ) || str_contains( $uri, 'wp-cron.php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Current language.
	 */
	public function current(): string {
		if ( null === $this->current ) {
			$this->detect();
		}

		return $this->current ?? Settings::source_lang();
	}

	/**
	 * Whether viewing a translated language (not source).
	 */
	public function is_translated(): bool {
		return $this->current() !== Settings::source_lang();
	}

	/**
	 * Whether URL contains language prefix.
	 */
	public function has_prefix(): bool {
		return $this->has_prefix;
	}

	/**
	 * Build URL for a language.
	 *
	 * @param string      $lang Language code.
	 * @param string|null $url  Base URL (default current).
	 */
	public function url_for_lang( string $lang, ?string $url = null ): string {
		$source = Settings::source_lang();
		$url    = $url ?? $this->current_public_url();
		$home   = home_url( '/' );
		$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home_path = (string) wp_parse_url( $home, PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );

		if ( $home_path && str_starts_with( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) ) ?: '/';
		}

		$path = '/' . ltrim( $path, '/' );

		foreach ( Settings::target_langs() as $code ) {
			if ( $path === '/' . $code || $path === '/' . $code . '/' ) {
				$path = '/';
				break;
			}
			if ( str_starts_with( $path, '/' . $code . '/' ) ) {
				$path = substr( $path, strlen( $code ) + 1 );
				if ( '' === $path ) {
					$path = '/';
				}
				break;
			}
		}

		// If REQUEST_URI was already stripped, rebuild from queried object when possible.
		if ( $this->has_prefix && function_exists( 'get_queried_object_id' ) ) {
			// Keep path from stripped URI — already language-free.
		}

		if ( $lang === $source ) {
			$new_path = $path;
		} else {
			$new_path = '/' . $lang . ( '/' === $path ? '/' : $path );
		}

		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$built = home_url( user_trailingslashit( $new_path ) );
		if ( $query ) {
			$built .= ( str_contains( $built, '?' ) ? '&' : '?' ) . $query;
		}

		return $built;
	}

	/**
	 * Public URL including language prefix when present.
	 */
	public function current_public_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		if ( $this->has_prefix && $this->current && $this->current !== Settings::source_lang() ) {
			$parts     = wp_parse_url( $uri );
			$path      = $parts['path'] ?? '/';
			$query     = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
			$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			$relative  = $path;
			if ( $home_path && str_starts_with( $path, $home_path ) ) {
				$relative = substr( $path, strlen( $home_path ) ) ?: '/';
			}
			$relative = '/' . ltrim( $relative, '/' );
			$prefixed = '/' . $this->current . ( '/' === $relative ? '/' : $relative );
			return home_url( $prefixed ) . $query;
		}

		return home_url( $uri );
	}

	/**
	 * Current request URL (possibly stripped).
	 */
	public function current_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return home_url( $uri );
	}
}
