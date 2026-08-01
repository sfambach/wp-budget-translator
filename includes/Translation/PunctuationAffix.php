<?php
/**
 * Peel / reattach edge punctuation for cache lookup.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class PunctuationAffix
 */
final class PunctuationAffix {

	/**
	 * Leading/trailing punctuation and symbols (not letters/digits).
	 */
	private const EDGE = '[\p{P}\p{S}\s]+';

	/**
	 * Peel edge punctuation; always returns parts (edges may be empty).
	 *
	 * @param string $text Text.
	 * @return array{prefix:string,core:string,suffix:string}
	 */
	public static function parts( string $text ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			return array(
				'prefix' => '',
				'core'   => '',
				'suffix' => '',
			);
		}

		$prefix = '';
		$suffix = '';
		$core   = $text;

		if ( preg_match( '/^(' . self::EDGE . ')/u', $core, $m ) ) {
			$prefix = $m[1];
			$core   = mb_substr( $core, mb_strlen( $prefix ) );
		}

		if ( preg_match( '/(' . self::EDGE . ')$/u', $core, $m ) ) {
			$suffix = $m[1];
			$core   = mb_substr( $core, 0, mb_strlen( $core ) - mb_strlen( $suffix ) );
		}

		return array(
			'prefix' => $prefix,
			'core'   => trim( $core ),
			'suffix' => $suffix,
		);
	}

	/**
	 * Split leading/trailing punctuation from a segment.
	 *
	 * @param string $text Segment.
	 * @return array{prefix:string,core:string,suffix:string}|null Null if nothing to peel or core too short.
	 */
	public static function split( string $text ): ?array {
		$parts = self::parts( $text );

		if ( '' === $parts['core'] || ( '' === $parts['prefix'] && '' === $parts['suffix'] ) ) {
			return null;
		}

		if ( mb_strlen( $parts['core'] ) < 2 || ! preg_match( '/\p{L}/u', $parts['core'] ) ) {
			return null;
		}

		return $parts;
	}

	/**
	 * Reattach prefix/suffix around a translated core.
	 *
	 * @param string $prefix Prefix.
	 * @param string $core   Translated core.
	 * @param string $suffix Suffix.
	 */
	public static function join( string $prefix, string $core, string $suffix ): string {
		return $prefix . $core . $suffix;
	}

	/**
	 * Keep the translation core, but force leading/trailing punctuation to match the source.
	 *
	 * Stops providers from inventing trailing dots (e.g. Herunterladen → "Download .").
	 *
	 * @param string $source     Source segment.
	 * @param string $translated Provider translation.
	 */
	public static function align_to_source( string $source, string $translated ): string {
		$src = self::parts( $source );
		$tgt = self::parts( $translated );

		if ( '' === $tgt['core'] ) {
			return trim( $translated );
		}

		// Same edge punctuation already — keep as-is (preserves inner spacing quirks).
		if ( $src['prefix'] === $tgt['prefix'] && $src['suffix'] === $tgt['suffix'] ) {
			return $translated;
		}

		return self::join( $src['prefix'], $tgt['core'], $src['suffix'] );
	}
}
