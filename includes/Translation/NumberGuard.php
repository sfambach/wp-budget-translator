<?php
/**
 * Protect numeric tokens (issue numbers, versions, units) from MT loss/mangling.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class NumberGuard
 */
final class NumberGuard {

	/**
	 * Common electronics / measurement unit suffixes (longest first).
	 */
	private const UNITS = 'uF|uf|µF|mF|nF|pF|mA|uA|µA|mV|kV|kHz|MHz|GHz|mW|kW|Hz|Ω|ohm|°C|°F|[VAW%]';

	/**
	 * Versions (1.2.3), decimals (2,2 / 3.3), integers, optional unit (uF, mA, V…).
	 */
	private const PATTERN = '/(?:(?<![\p{L}\p{N}_])|(?<=[vV]))(?:'
		. '\d+(?:\.\d+){2,}'
		. '|'
		. '\d+(?:[.,]\d+)?(?:\s*(?:' . self::UNITS . '))?'
		. ')(?![\p{L}\p{N}_])/u';

	/**
	 * Extract number tokens in appearance order.
	 *
	 * @param string $text Text.
	 * @return list<string>
	 */
	public static function extract( string $text ): array {
		if ( ! preg_match_all( self::PATTERN, $text, $matches ) ) {
			return array();
		}
		return array_values( $matches[0] );
	}

	/**
	 * Replace numbers (and spaced plus joiners) with placeholders before the provider call.
	 *
	 * @param string $text Source text.
	 * @return array{0:string,1:array<string,string>}
	 */
	public static function mask( string $text ): array {
		$map   = array();
		$index = 0;

		$masked = preg_replace_callback(
			self::PATTERN,
			static function ( array $matches ) use ( &$map, &$index ): string {
				$key         = TokenPlaceholder::make( 'BTN', $index );
				$map[ $key ] = $matches[0];
				++$index;
				return $key;
			},
			$text
		);
		$masked = is_string( $masked ) ? $masked : $text;

		// Keep “ + ” list joiners (providers often drop them).
		$masked = preg_replace_callback(
			'/\s\+\s/u',
			static function ( array $matches ) use ( &$map, &$index ): string {
				$key         = TokenPlaceholder::make( 'BTN', $index );
				$map[ $key ] = $matches[0];
				++$index;
				return $key;
			},
			$masked
		);

		return array( is_string( $masked ) ? $masked : $text, $map );
	}

	/**
	 * Restore number placeholders after translation.
	 *
	 * @param string                $text Translated text.
	 * @param array<string, string> $map  Placeholder map.
	 */
	public static function unmask( string $text, array $map ): string {
		return TokenPlaceholder::restore( $text, $map, 'BTN' );
	}

	/**
	 * Append any source numbers the translation dropped (e.g. "PC World 115" → "PC World").
	 *
	 * @param string $source     Source segment.
	 * @param string $translated Translation.
	 */
	public static function restore_missing( string $source, string $translated ): string {
		$numbers = self::extract( $source );
		if ( array() === $numbers ) {
			return self::strip_duplicate_trailing_numbers( $translated );
		}

		$missing = array();
		foreach ( $numbers as $number ) {
			if ( ! self::contains_token( $translated, $number ) ) {
				$missing[] = $number;
			}
		}

		if ( array() !== $missing ) {
			$translated = rtrim( $translated ) . ' ' . implode( ' ', array_unique( $missing ) );
		}

		// Providers sometimes keep the placeholder result and also invent the same number
		// (“v2.1 2.1”). Drop trailing duplicates.
		return self::strip_duplicate_trailing_numbers( $translated );
	}

	/**
	 * Remove a trailing number token that already appears earlier in the text.
	 *
	 * @param string $text Text.
	 */
	private static function strip_duplicate_trailing_numbers( string $text ): string {
		$tokens = self::extract( $text );
		if ( count( $tokens ) < 2 ) {
			return $text;
		}

		$changed = true;
		while ( $changed ) {
			$changed = false;
			$tokens  = self::extract( $text );
			if ( count( $tokens ) < 2 ) {
				break;
			}
			$last = $tokens[ count( $tokens ) - 1 ];
			$count = 0;
			foreach ( $tokens as $token ) {
				if ( $token === $last ) {
					++$count;
				}
			}
			if ( $count < 2 ) {
				break;
			}
			$pattern = '/\s+' . preg_quote( $last, '/' ) . '\s*$/u';
			$next    = preg_replace( $pattern, '', $text );
			if ( is_string( $next ) && $next !== $text ) {
				$text    = rtrim( $next );
				$changed = true;
			} else {
				break;
			}
		}

		return $text;
	}

	/**
	 * Light cleanup of provider output (spaces / punctuation / glued units).
	 *
	 * @param string $text Translation.
	 */
	public static function cleanup_translation( string $text ): string {
		$fixed = preg_replace( '/ {2,}/u', ' ', $text );
		$text  = is_string( $fixed ) ? $fixed : $text;
		$fixed = preg_replace( '/\s+(\.{3}|[.…,;:!?])/u', '$1', $text );
		$text  = is_string( $fixed ) ? $fixed : $text;
		// “2,2uFelectrolytic” → “2,2uF electrolytic”
		$fixed = preg_replace(
			'/(\d+(?:[.,]\d+)?\s*(?:' . self::UNITS . '))(\p{Ll}{3,})/u',
			'$1 $2',
			$text
		);
		$text = is_string( $fixed ) ? $fixed : $text;
		return trim( $text );
	}

	/**
	 * Whether a number token appears with the same boundaries as extract().
	 *
	 * Left edge allows a version-like `v`/`V` prefix so `11` inside `v11` counts as present.
	 *
	 * @param string $text  Haystack.
	 * @param string $token Number token.
	 */
	private static function contains_token( string $text, string $token ): bool {
		$pattern = '/(?:(?<![\p{L}\p{N}_])|(?<=[vV]))'
			. preg_quote( $token, '/' )
			. '(?![\p{L}\p{N}_])/u';
		return 1 === preg_match( $pattern, $text );
	}
}
