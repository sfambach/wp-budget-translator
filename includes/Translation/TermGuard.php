<?php
/**
 * Do-not-translate list and glossary handling.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;

/**
 * Class TermGuard
 */
final class TermGuard {

	/**
	 * Phrases that must never be sent to a provider (one per line in settings).
	 *
	 * @return list<string>
	 */
	public static function do_not_translate(): array {
		$raw = (string) Settings::get( 'do_not_translate', '' );
		return self::parse_lines( $raw );
	}

	/**
	 * Glossary map for a target language: source => forced translation.
	 *
	 * @param string $target_lang Target language.
	 * @return array<string, string>
	 */
	public static function glossary_map( string $target_lang ): array {
		$all = Settings::get( 'glossary', array() );
		if ( ! is_array( $all ) ) {
			return array();
		}

		$raw = isset( $all[ $target_lang ] ) ? (string) $all[ $target_lang ] : '';
		$map = array();

		foreach ( self::parse_lines( $raw ) as $line ) {
			if ( ! str_contains( $line, '=' ) ) {
				continue;
			}
			[ $source, $target ] = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( '' === $source || '' === $target ) {
				continue;
			}
			$map[ $source ] = $target;
		}

		// Longer sources first for partial masking.
		uksort(
			$map,
			static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a )
		);

		return $map;
	}

	/**
	 * Whether the whole segment is on the do-not-translate list.
	 *
	 * @param string $text Segment.
	 */
	public static function is_do_not_translate( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}

		foreach ( self::do_not_translate() as $phrase ) {
			if ( 0 === strcasecmp( $text, $phrase ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Forced glossary translation for an exact segment, or null.
	 *
	 * @param string $text        Segment.
	 * @param string $target_lang Target language.
	 */
	public static function glossary_translation( string $text, string $target_lang ): ?string {
		$map = self::glossary_map( $target_lang );
		if ( isset( $map[ $text ] ) ) {
			return $map[ $text ];
		}

		foreach ( $map as $source => $target ) {
			if ( 0 === strcasecmp( $text, $source ) ) {
				return $target;
			}
		}

		return null;
	}

	/**
	 * Mask do-not-translate and glossary phrases before provider call.
	 *
	 * @param string $text        Source text.
	 * @param string $target_lang Target language (for glossary phrases).
	 * @return array{0:string,1:array<string,string>}
	 */
	public static function mask( string $text, string $target_lang ): array {
		$map   = array();
		$index = 0;

		$phrases = self::do_not_translate();
		foreach ( self::glossary_map( $target_lang ) as $source => $_target ) {
			$phrases[] = $source;
		}

		$phrases = array_values( array_unique( $phrases ) );
		usort(
			$phrases,
			static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a )
		);

		foreach ( $phrases as $phrase ) {
			if ( '' === $phrase || false === stripos( $text, $phrase ) ) {
				continue;
			}

			$pattern = '/' . preg_quote( $phrase, '/' ) . '/iu';
			$text    = (string) preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( &$map, &$index ): string {
					$key         = TokenPlaceholder::make( 'BTX', $index );
					$map[ $key ] = $matches[0];
					++$index;
					return $key;
				},
				$text
			);
		}

		return array( $text, $map );
	}

	/**
	 * Restore TermGuard placeholders and apply glossary replacements on restored tokens.
	 *
	 * @param string               $text        Translated text.
	 * @param array<string,string> $map         Placeholder => original phrase.
	 * @param string               $target_lang Target language.
	 */
	public static function unmask( string $text, array $map, string $target_lang ): string {
		if ( array() === $map ) {
			return $text;
		}

		$glossary = self::glossary_map( $target_lang );
		$resolved = array();

		foreach ( $map as $key => $original ) {
			$replacement = $original;
			foreach ( $glossary as $source => $target ) {
				if ( 0 === strcasecmp( $original, $source ) ) {
					$replacement = $target;
					break;
				}
			}
			$resolved[ $key ] = $replacement;
		}

		return TokenPlaceholder::restore( $text, $resolved, 'BTX' );
	}

	/**
	 * Split textarea into non-empty trimmed lines (ignore # comments).
	 *
	 * @param string $raw Raw textarea.
	 * @return list<string>
	 */
	private static function parse_lines( string $raw ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			$out[] = $line;
		}

		usort(
			$out,
			static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a )
		);

		return $out;
	}
}
