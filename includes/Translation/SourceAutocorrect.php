<?php
/**
 * Optional source-text auto-corrections before hashing/translation.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;

/**
 * Class SourceAutocorrect
 */
final class SourceAutocorrect {

	/**
	 * Known rule definitions: id => label (for admin UI).
	 *
	 * @return array<string, string>
	 */
	public static function rules(): array {
		return array(
			'space_before_punct'   => __( 'Remove spaces before punctuation (e.g. “hier :” → “hier:”, “Ende .” → “Ende.”)', 'budget-translator' ),
			'collapse_spaces'      => __( 'Collapse multiple spaces into one', 'budget-translator' ),
			'no_space_at_linebreak' => __( 'No space at mid-word line breaks (e.g. “el↵ectrolytic” → “electrolytic”; normal line breaks stay spaced)', 'budget-translator' ),
			'capitalize_sentences' => __( 'Capitalize sentence starts (first letter and after . ! ?)', 'budget-translator' ),
		);
	}

	/**
	 * Default enabled flags per rule.
	 *
	 * @return array<string, int>
	 */
	public static function default_rule_flags(): array {
		$flags = array();
		foreach ( array_keys( self::rules() ) as $id ) {
			$flags[ $id ] = 1;
		}
		return $flags;
	}

	/**
	 * Apply enabled corrections to a source segment.
	 *
	 * @param string $text Normalized-ish source text.
	 */
	public static function apply( string $text ): string {
		if ( ! Settings::get( 'source_autocorrect', 1 ) ) {
			return $text;
		}

		$flags = Settings::get( 'source_autocorrect_rules', array() );
		if ( ! is_array( $flags ) ) {
			$flags = array();
		}
		$flags = wp_parse_args( $flags, self::default_rule_flags() );

		// Line breaks first, then spaces, punctuation, capitalization.
		if ( ! empty( $flags['no_space_at_linebreak'] ) ) {
			$text = self::join_midword_linebreaks( $text );
		}
		$text = self::linebreaks_to_space( $text );
		if ( ! empty( $flags['collapse_spaces'] ) ) {
			$text = self::collapse_spaces( $text );
		}
		if ( ! empty( $flags['space_before_punct'] ) ) {
			$text = self::strip_space_before_punct( $text );
		}
		if ( ! empty( $flags['capitalize_sentences'] ) ) {
			$text = self::capitalize_sentences( $text );
		}

		return $text;
	}

	/**
	 * Join mid-word wraps without a space; leave real line breaks for spacing pass.
	 *
	 * Soft hyphen / hyphen+EOL and “letter + newline + lowercase” (word continuation).
	 *
	 * @param string $text Text.
	 */
	private static function join_midword_linebreaks( string $text ): string {
		// Soft hyphen then newline.
		$text = preg_replace( '/\x{00AD}[\r\n]+/u', '', $text ) ?? $text;
		// Explicit hyphenation at end of line: “Elektrolyt-\nkondensator”.
		$text = preg_replace( '/(\p{L})-[\r\n]+(\p{Ll})/u', '$1$2', $text ) ?? $text;
		// Accidental wrap inside a word: “el\nectrolytic” (continuation is lowercase).
		$text = preg_replace( '/(\p{L})[\r\n]+(\p{Ll})/u', '$1$2', $text ) ?? $text;
		return $text;
	}

	/**
	 * Convert remaining line breaks to a single space (keeps word boundaries).
	 *
	 * @param string $text Text.
	 */
	private static function linebreaks_to_space( string $text ): string {
		$fixed = preg_replace( '/[^\S\r\n]*[\r\n]+[^\S\r\n]*/u', ' ', $text );
		return is_string( $fixed ) ? $fixed : $text;
	}

	/**
	 * Collapse runs of spaces to a single space.
	 *
	 * @param string $text Text.
	 */
	private static function collapse_spaces( string $text ): string {
		$fixed = preg_replace( '/ {2,}/u', ' ', $text );
		return is_string( $fixed ) ? $fixed : $text;
	}

	/**
	 * Remove whitespace immediately before sentence punctuation.
	 *
	 * @param string $text Text.
	 */
	private static function strip_space_before_punct( string $text ): string {
		// “hier :” → “hier:”, “Ende .” → “Ende.”, also … and ...
		$fixed = preg_replace( '/\s+(\.{3}|[.…,;:!?])/u', '$1', $text );
		return is_string( $fixed ) ? $fixed : $text;
	}

	/**
	 * Uppercase the first letter of the text and of each new sentence.
	 *
	 * Single tokens without spaces/punctuation (link labels, codes like “winnt”)
	 * are left unchanged — they are not sentences.
	 *
	 * @param string $text Text.
	 */
	private static function capitalize_sentences( string $text ): string {
		if ( '' === $text ) {
			return $text;
		}

		// Not a sentence: one token, no sentence punctuation.
		if ( ! preg_match( '/\s|[.!?…]/u', $text ) ) {
			return $text;
		}

		$text = (string) preg_replace_callback(
			'/^(\P{L}*)(\p{Ll})/u',
			static function ( array $m ): string {
				return $m[1] . mb_strtoupper( $m[2], 'UTF-8' );
			},
			$text
		);

		$text = (string) preg_replace_callback(
			'/([.!?…])(\s+)(\p{Ll})/u',
			static function ( array $m ): string {
				return $m[1] . $m[2] . mb_strtoupper( $m[3], 'UTF-8' );
			},
			$text
		);

		return $text;
	}
}
