<?php
/**
 * Lightweight language heuristics for segment routing.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class LanguageHint
 */
final class LanguageHint {

	/**
	 * Whether text looks primarily like the given language (de/en focused).
	 *
	 * Used to avoid de→en API calls when the "source" is already English
	 * (e.g. leftover from another translator written into post fields).
	 *
	 * @param string $text Text.
	 * @param string $lang Language code.
	 */
	public static function looks_like( string $text, string $lang ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}

		$lang = strtolower( $lang );

		return match ( $lang ) {
			'en'    => self::looks_like_english( $text ),
			'de'    => self::looks_like_german( $text ),
			default => false,
		};
	}

	/**
	 * Source already appears to be the target language (and not the configured source).
	 *
	 * @param string $text        Segment.
	 * @param string $source_lang Configured source.
	 * @param string $target_lang Target.
	 */
	public static function already_in_target( string $text, string $source_lang, string $target_lang ): bool {
		if ( $source_lang === $target_lang ) {
			return false;
		}
		if ( ! self::looks_like( $text, $target_lang ) ) {
			return false;
		}
		// If it also looks like the source language, do not skip (ambiguous / mixed).
		if ( self::looks_like( $text, $source_lang ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $text Text.
	 */
	private static function looks_like_english( string $text ): bool {
		if ( preg_match( '/[äöüÄÖÜß]/u', $text ) ) {
			return false;
		}

		// Obvious runtime / console English (length-agnostic).
		if ( preg_match(
			'/\b(no module named|modulenotfounderror|traceback \(most recent call last\)|permission denied|command not found|syntaxerror|filenotfounderror|nameerror|typeerror|attributeerror|importerror)\b/iu',
			$text
		) ) {
			return true;
		}

		if ( mb_strlen( $text ) < 12 ) {
			return false;
		}

		$hits = preg_match_all(
			'/\b(the|and|with|for|from|this|that|are|was|were|have|been|can|used|into|about|their|there|which|when|what|will|would|could|should|also|only|than|then|them|these|those|over|under|after|before|between|through|during|without|because|while|where|or|an|of|to|in|on|at|by|as|is|it|be|no|not|named|module|error|message|failed|missing|invalid|cannot|undefined|found|warning|exception|please|click|select|file|path|line|return|import|class|function)\b/iu',
			$text
		);
		$hits = $hits ?: 0;
		// Short technical lines rarely pack 4 function words.
		$need = mb_strlen( $text ) < 48 ? 2 : 4;

		return $hits >= $need;
	}

	/**
	 * @param string $text Text.
	 */
	private static function looks_like_german( string $text ): bool {
		if ( preg_match( '/[äöüÄÖÜß]/u', $text ) ) {
			return true;
		}
		if ( mb_strlen( $text ) < 24 ) {
			return false;
		}
		$hits = preg_match_all(
			'/\b(der|die|das|und|für|mit|nicht|auch|oder|ein|eine|einer|eines|ist|sind|von|zu|den|dem|des|auf|aus|bei|nach|über|unter|wenn|wie|noch|schon|werden|wurde|wurden|kann|können|sich|als|aber|nur|mehr|sehr|hier|dort|diese|dieser|dieses|einen|einem)\b/iu',
			$text
		);
		return ( $hits ?: 0 ) >= 4;
	}
}
