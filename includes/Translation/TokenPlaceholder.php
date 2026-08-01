<?php
/**
 * ASCII-safe MT placeholders (free APIs often mangle Unicode brackets).
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class TokenPlaceholder
 */
final class TokenPlaceholder {

	/**
	 * Build a placeholder token.
	 *
	 * @param string $prefix Prefix (BTL, BTN, BTS, BTX).
	 * @param int    $index  Index.
	 */
	public static function make( string $prefix, int $index ): string {
		return '__' . $prefix . $index . '__';
	}

	/**
	 * Restore placeholders, including common provider mangling.
	 *
	 * @param string                $text   Translated text.
	 * @param array<string, string> $map    Placeholder => original.
	 * @param string                $prefix Prefix used in make().
	 */
	public static function restore( string $text, array $map, string $prefix ): string {
		if ( array() === $map ) {
			return $text;
		}

		$text = strtr( $text, $map );

		foreach ( $map as $key => $original ) {
			$num = null;
			if ( preg_match( '/' . preg_quote( $prefix, '/' ) . '(\d+)/', $key, $m ) ) {
				$num = $m[1];
			}
			if ( null === $num ) {
				continue;
			}

			$patterns = array(
				'/__\s*' . preg_quote( $prefix, '/' ) . '\s*' . preg_quote( $num, '/' ) . '\s*__/iu',
				'/⟦\s*' . preg_quote( $prefix, '/' ) . '\s*' . preg_quote( $num, '/' ) . '\s*⟧/iu',
				'/\[\s*' . preg_quote( $prefix, '/' ) . '\s*' . preg_quote( $num, '/' ) . '\s*\]/iu',
				'/\(\s*' . preg_quote( $prefix, '/' ) . '\s*' . preg_quote( $num, '/' ) . '\s*\)/iu',
				'/\b' . preg_quote( $prefix, '/' ) . '\s*' . preg_quote( $num, '/' ) . '\b/iu',
			);
			foreach ( $patterns as $pattern ) {
				$text = preg_replace( $pattern, $original, $text ) ?? $text;
			}

			// Provider dropped the token id but kept brackets around the original value.
			$quoted = preg_quote( $original, '/' );
			$text   = preg_replace( '/⟦\s*' . $quoted . '\s*⟧/u', $original, $text ) ?? $text;
			$text   = preg_replace( '/\[\s*' . $quoted . '\s*\]/u', $original, $text ) ?? $text;
		}

		return self::scrub_artifacts( $text );
	}

	/**
	 * Remove leftover Unicode / ASCII bracket artifacts from mangled placeholders.
	 *
	 * @param string $text Text.
	 */
	public static function scrub_artifacts( string $text ): string {
		if ( ! str_contains( $text, '⟦' ) && ! str_contains( $text, '⟧' ) ) {
			return $text;
		}

		$text = str_replace( array( '⟦', '⟧' ), '', $text );
		$text = preg_replace( '/\s{2,}/u', ' ', $text ) ?? $text;
		return trim( $text );
	}

	/**
	 * Whether text still has leftover placeholder bracket artifacts.
	 *
	 * @param string $text Text.
	 */
	public static function has_artifacts( string $text ): bool {
		return str_contains( $text, '⟦' ) || str_contains( $text, '⟧' );
	}
}
