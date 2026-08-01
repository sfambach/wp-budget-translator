<?php
/**
 * Keep WordPress shortcodes out of machine translation.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class ShortcodeGuard
 */
final class ShortcodeGuard {

	/**
	 * Match a WP-style shortcode (self-closing or with closing tag).
	 */
	private const PATTERN = '/\[[a-zA-Z][\w-]*(?:\s+[^\]]*)?\](?:[\s\S]*?\[\/[a-zA-Z][\w-]*\])?/u';

	/**
	 * Whether the whole segment is a shortcode and must not be translated.
	 *
	 * @param string $text Segment.
	 */
	public static function is_protected_segment( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text || ! str_starts_with( $text, '[' ) ) {
			return false;
		}

		return 1 === preg_match(
			'/^\[[a-zA-Z][\w-]*(?:\s+[^\]]*)?\](?:[\s\S]*?\[\/[a-zA-Z][\w-]*\])?\s*$/u',
			$text
		);
	}

	/**
	 * Replace shortcodes with placeholders before the provider call.
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
				$key         = TokenPlaceholder::make( 'BTS', $index );
				$map[ $key ] = $matches[0];
				++$index;
				return $key;
			},
			$text
		);

		return array( is_string( $masked ) ? $masked : $text, $map );
	}

	/**
	 * Restore shortcode placeholders after translation.
	 *
	 * @param string                $text Translated text.
	 * @param array<string, string> $map  Placeholder map.
	 */
	public static function unmask( string $text, array $map ): string {
		return TokenPlaceholder::restore( $text, $map, 'BTS' );
	}
}
