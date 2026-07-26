<?php
/**
 * Protect emails, URLs and website tokens from translation.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class LinkGuard
 */
final class LinkGuard {

	/**
	 * Pattern for emails, URLs and bare website hosts.
	 */
	private const TOKEN_PATTERN = '/(?:mailto:)?[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}'
		. '|(?:https?:\/\/|ftp:\/\/|www\.)[^\s<>"\']+'
		. '|(?<![\w.@])(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+(?:com|net|org|de|eu|io|info|biz|app|dev|shop|blog|cloud|edu|gov|uk|us|fr|it|nl|at|ch)(?:\/[^\s<>"\']*)?/i';

	/**
	 * Whether the whole segment is an email, URL or website and must not be translated.
	 *
	 * @param string $text Segment.
	 */
	public static function is_protected_segment( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}

		if ( preg_match( '/^(?:mailto:)?[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $text ) ) {
			return true;
		}

		if ( preg_match( '#^(?:https?://|ftp://|www\.)\S+#i', $text ) ) {
			return true;
		}

		// Bare domain / website path without scheme.
		if ( preg_match( '/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+(?:com|net|org|de|eu|io|info|biz|app|dev|shop|blog|cloud|edu|gov|uk|us|fr|it|nl|at|ch)(?:\/\S*)?$/i', $text ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Replace emails/URLs with placeholders before sending to a provider.
	 *
	 * @param string $text Source text.
	 * @return array{0:string,1:array<string,string>} Masked text and placeholder map.
	 */
	public static function mask( string $text ): array {
		$map   = array();
		$index = 0;

		$masked = preg_replace_callback(
			self::TOKEN_PATTERN,
			static function ( array $matches ) use ( &$map, &$index ): string {
				$token = $matches[0];
				// Trim trailing punctuation commonly glued to URLs.
				$trail = '';
				if ( preg_match( '/^(.*?)([.,;:!?)\]]+)$/u', $token, $parts ) ) {
					$token = $parts[1];
					$trail = $parts[2];
				}
				$key         = '⟦BT' . $index . '⟧';
				$map[ $key ] = $token;
				++$index;
				return $key . $trail;
			},
			$text
		);

		return array( is_string( $masked ) ? $masked : $text, $map );
	}

	/**
	 * Restore placeholders after translation.
	 *
	 * @param string               $text Translated text.
	 * @param array<string,string> $map  Placeholder map.
	 */
	public static function unmask( string $text, array $map ): string {
		if ( array() === $map ) {
			return $text;
		}

		// Restore exact keys first.
		$text = strtr( $text, $map );

		// Providers sometimes alter brackets/spacing around placeholders.
		foreach ( $map as $key => $original ) {
			$num = null;
			if ( preg_match( '/BT(\d+)/', $key, $m ) ) {
				$num = $m[1];
			}
			if ( null === $num ) {
				continue;
			}
			$patterns = array(
				'/⟦\s*BT\s*' . preg_quote( $num, '/' ) . '\s*⟧/iu',
				'/\[\s*BT\s*' . preg_quote( $num, '/' ) . '\s*\]/iu',
				'/\(\s*BT\s*' . preg_quote( $num, '/' ) . '\s*\)/iu',
				'/\bBT\s*' . preg_quote( $num, '/' ) . '\b/iu',
			);
			foreach ( $patterns as $pattern ) {
				$text = preg_replace( $pattern, $original, $text ) ?? $text;
			}
		}

		return $text;
	}
}
