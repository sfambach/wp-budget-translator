<?php
/**
 * Extract / reassemble text segments from HTML.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class SegmentExtractor
 */
final class SegmentExtractor {

	/**
	 * Tags whose text content should not be translated.
	 *
	 * @var list<string>
	 */
	private array $skip_tags = array( 'script', 'style', 'code', 'pre', 'textarea', 'svg', 'kbd', 'samp', 'tt', 'var' );

	/**
	 * Class tokens that mark an element (and descendants) as code.
	 *
	 * @var list<string>
	 */
	private array $skip_class_tokens = array(
		'wp-block-code',
		'wp-block-preformatted',
		'hljs',
		'crayon-',
		'syntaxhighlighter',
		'language-',
	);

	/**
	 * Extract unique translatable text segments from HTML or plain text.
	 *
	 * @param string $content Content.
	 * @return list<string>
	 */
	public function extract( string $content ): array {
		$content = trim( $content );
		if ( '' === $content ) {
			return array();
		}

		if ( ! preg_match( '/<[^>]+>/', $content ) ) {
			$normalized = $this->normalize( $content );
			if ( '' === $normalized || ! $this->is_translatable( $normalized ) ) {
				return array();
			}
			return array( $normalized );
		}

		$segments = array();
		$dom      = $this->load_dom( $content );
		if ( null === $dom ) {
			$normalized = $this->normalize( wp_strip_all_tags( $content ) );
			if ( '' === $normalized || ! $this->is_translatable( $normalized ) ) {
				return array();
			}
			return array( $normalized );
		}

		$xpath = new \DOMXPath( $dom );
		$root  = $xpath->query( '//*[@id="bt-root"]' )->item( 0 );
		$this->walk( $root ?? $dom->documentElement, $segments );

		$unique = array();
		foreach ( $segments as $segment ) {
			$unique[ $segment ] = true;
		}

		return array_keys( $unique );
	}

	/**
	 * Apply translations to HTML content.
	 *
	 * @param string                $content      Original content.
	 * @param array<string, string> $translations Source => translated map.
	 */
	public function apply( string $content, array $translations ): string {
		if ( '' === $content || array() === $translations ) {
			return $content;
		}

		if ( ! preg_match( '/<[^>]+>/', $content ) ) {
			$key = $this->normalize( $content );
			return $translations[ $key ] ?? $content;
		}

		$dom = $this->load_dom( $content );
		if ( null === $dom ) {
			return $content;
		}

		$xpath = new \DOMXPath( $dom );
		$root  = $xpath->query( '//*[@id="bt-root"]' )->item( 0 );
		if ( ! $root ) {
			return $content;
		}

		$this->replace_walk( $root, $translations );

		$html = '';
		foreach ( $root->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Normalize whitespace for hashing / matching.
	 *
	 * @param string $text Text.
	 */
	public function normalize( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Tabs/form-feed → space. Do not use \v here — in PHP it also matches newlines.
		$text = preg_replace( '/[\t\f]+/u', ' ', $text ) ?? $text;
		if ( ! \BudgetTranslator\Settings::get( 'source_autocorrect', 1 ) ) {
			$text = preg_replace( '/[\r\n]+/u', ' ', $text ) ?? $text;
		}
		$text = trim( $text );
		// Always unify ellipsis so "…" and "..." share one cache hash (avoids twin review rows).
		$text = self::unify_ellipsis( $text );
		return SourceAutocorrect::apply( $text );
	}

	/**
	 * Canonical form for near-duplicate detection (ellipsis + spaces).
	 *
	 * Used by review list dedupe; keep in sync with normalize()'s always-on rules.
	 *
	 * @param string $text Source text.
	 */
	public static function canonical_source( string $text ): string {
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = trim( $text );
		return self::unify_ellipsis( $text );
	}

	/**
	 * Map Unicode ellipsis to three ASCII dots.
	 *
	 * @param string $text Text.
	 */
	private static function unify_ellipsis( string $text ): string {
		return str_replace(
			array( "\u{2026}", "\u{00A0}", "\u{00AD}" ),
			array( '...', ' ', '' ),
			$text
		);
	}

	/**
	 * Whether text looks like source code or a console/prompt line.
	 *
	 * Public so the repository can purge cached code-like segments.
	 *
	 * @param string $text Segment.
	 */
	public static function looks_like_code( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}

		// Shell / console prompts.
		if ( preg_match( '/^(?:\$|#|>|PS(?:\s+C:)?|C:\\\\)\s+\S/u', $text ) ) {
			return true;
		}

		// Common CLI tool invocations at line start.
		if ( preg_match( '/^(?:npm|npx|yarn|pnpm|git|composer|wp|docker|curl|ssh|pip|python|node|cargo|make|sudo)\s+\S/iu', $text ) ) {
			return true;
		}

		// Windows / PowerShell paths used as commands.
		if ( preg_match( '/^[A-Za-z]:\\\\[^\s]+/u', $text ) ) {
			return true;
		}

		// Dense code punctuation vs letters.
		$len = mb_strlen( $text );
		if ( $len >= 8 ) {
			$code_chars = preg_match_all( '/[{}();=\[\]<>$|\\\\\/`]/u', $text ) ?: 0;
			$letters    = preg_match_all( '/\p{L}/u', $text ) ?: 0;
			if ( $code_chars >= 4 && $letters > 0 && ( $code_chars / $len ) >= 0.12 ) {
				return true;
			}
		}

		// Multi-line-ish snippets glued into one segment.
		if ( substr_count( $text, ';' ) >= 2 && preg_match( '/[{}=()]/', $text ) ) {
			return true;
		}

		if ( self::looks_like_opaque_id( $text ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Opaque technical IDs (long hyphenated alphanumerics, mostly hex/digits).
	 *
	 * E.g. hpcom-v11-6387f94d73599945123156 — not natural language.
	 *
	 * @param string $text Text.
	 */
	public static function looks_like_opaque_id( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text || preg_match( '/\s/u', $text ) || mb_strlen( $text ) < 16 ) {
			return false;
		}
		if ( ! preg_match( '/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)+$/u', $text ) ) {
			return false;
		}
		$hexish = preg_match_all( '/[0-9a-fA-F]/u', $text ) ?: 0;
		return ( $hexish / mb_strlen( $text ) ) >= 0.55;
	}

	/**
	 * Short non-translatable technical tokens / part codes (not natural language).
	 *
	 * Examples: R1,R2 · R1:R2 · U1/U2 · v2.1 · ESP32 · ABC-12/34 · USB 3.0
	 * Conservative: requires a digit, rejects word-like alphabetic chunks.
	 * Prefer auto-confirm passthrough in TranslationService (keep coverage) over skipping extraction.
	 *
	 * @param string $text Segment.
	 */
	public static function looks_like_technical_token( string $text ): bool {
		$text = trim( $text );
		if ( '' === $text ) {
			return false;
		}
		if ( self::looks_like_opaque_id( $text ) ) {
			return true;
		}

		$len = mb_strlen( $text );
		if ( $len < 2 || $len > 40 ) {
			return false;
		}

		// Letters, digits, and technical separators only (spaces optional).
		if ( ! preg_match( '/^[A-Za-z0-9](?:[A-Za-z0-9,\-\/_.:\s]*[A-Za-z0-9])?$/u', $text ) ) {
			return false;
		}

		// Need both a letter and a digit (pure numbers / pure words handled elsewhere).
		if ( ! preg_match( '/[A-Za-z]/u', $text ) || ! preg_match( '/\d/u', $text ) ) {
			return false;
		}

		$parts = preg_split( '/[,\-\/_.:\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) || array() === $parts ) {
			return false;
		}

		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^[A-Za-z0-9]+$/u', $part ) ) {
				return false;
			}
			// Word-like alphabetic chunk: reject lowercase/mixed 3+ letter words (und, the, der…).
			// Allow short ALL-CAPS labels (USB, LED) beside version/number parts.
			if ( preg_match( '/^[A-Za-z]{4,}$/u', $part ) ) {
				return false;
			}
			if ( preg_match( '/^[A-Za-z]{3}$/u', $part ) && $part !== strtoupper( $part ) ) {
				return false;
			}
		}

		$has_sep = (bool) preg_match( '/[,\-\/_.:\s]/u', $text );
		if ( $has_sep ) {
			return count( $parts ) >= 2;
		}

		// Single token without separators: short letter↔digit codes only (R1, ESP32, 3V).
		if ( $len > 12 ) {
			return false;
		}
		$part = $parts[0];
		return (bool) preg_match( '/^(?:[A-Za-z]{1,4}\d[A-Za-z0-9]*|\d+[.,]?\d*[A-Za-z]{1,4})$/u', $part );
	}

	/**
	 * Load HTML into DOMDocument wrapped in body.
	 *
	 * @param string $content HTML.
	 */
	private function load_dom( string $content ): ?\DOMDocument {
		$dom     = new \DOMDocument();
		$wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="bt-root">' . $content . '</div></body></html>';

		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( $wrapped, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $dom : null;
	}

	/**
	 * Whether an element should be skipped entirely (code block).
	 *
	 * @param \DOMElement $element Element.
	 */
	private function should_skip_element( \DOMElement $element ): bool {
		$name = strtolower( $element->nodeName );
		if ( in_array( $name, $this->skip_tags, true ) ) {
			return true;
		}

		$class = $element->getAttribute( 'class' );
		if ( '' === $class ) {
			return false;
		}

		$lower = strtolower( $class );
		foreach ( $this->skip_class_tokens as $token ) {
			if ( str_contains( $lower, $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Walk text nodes and collect segments.
	 *
	 * @param \DOMNode|null $node     Node.
	 * @param list<string>  $segments Collector.
	 */
	private function walk( ?\DOMNode $node, array &$segments ): void {
		if ( null === $node ) {
			return;
		}

		if ( XML_ELEMENT_NODE === $node->nodeType && $node instanceof \DOMElement ) {
			if ( $this->should_skip_element( $node ) ) {
				return;
			}
		}

		if ( XML_TEXT_NODE === $node->nodeType ) {
			$normalized = $this->normalize( $node->nodeValue ?? '' );
			if ( $this->is_translatable( $normalized ) ) {
				$segments[] = $normalized;
			}
			return;
		}

		if ( $node->hasChildNodes() ) {
			foreach ( iterator_to_array( $node->childNodes ) as $child ) {
				$this->walk( $child, $segments );
			}
		}
	}

	/**
	 * Replace text nodes using translation map.
	 *
	 * @param \DOMNode|null         $node          Node.
	 * @param array<string, string> $translations  Map.
	 */
	private function replace_walk( ?\DOMNode $node, array $translations ): void {
		if ( null === $node ) {
			return;
		}

		if ( XML_ELEMENT_NODE === $node->nodeType && $node instanceof \DOMElement ) {
			if ( $this->should_skip_element( $node ) ) {
				return;
			}
		}

		if ( XML_TEXT_NODE === $node->nodeType ) {
			$raw        = $node->nodeValue ?? '';
			$normalized = $this->normalize( $raw );
			if ( isset( $translations[ $normalized ] ) ) {
				$leading  = preg_match( '/^\s+/u', $raw, $m1 ) ? $m1[0] : '';
				$trailing = preg_match( '/\s+$/u', $raw, $m2 ) ? $m2[0] : '';
				$node->nodeValue = $leading . $translations[ $normalized ] . $trailing;
			}
			return;
		}

		if ( $node->hasChildNodes() ) {
			foreach ( iterator_to_array( $node->childNodes ) as $child ) {
				$this->replace_walk( $child, $translations );
			}
		}
	}

	/**
	 * Whether a segment should be translated.
	 *
	 * @param string $text Text.
	 */
	private function is_translatable( string $text ): bool {
		if ( '' === $text || mb_strlen( $text ) < 2 ) {
			return false;
		}

		// Skip pure numbers / punctuation / URLs.
		if ( preg_match( '/^[\d\s\.\,\:\;\-\+\(\)\/\%€$]+$/u', $text ) ) {
			return false;
		}
		if ( LinkGuard::is_protected_segment( $text ) ) {
			return false;
		}
		if ( ShortcodeGuard::is_protected_segment( $text ) ) {
			return false;
		}
		if ( TermGuard::is_do_not_translate( $text ) ) {
			return false;
		}
		if ( self::looks_like_code( $text ) ) {
			return false;
		}

		// Skip technical tokens like 3V, 5V, 3.3V, 100mA — identical across languages.
		if ( preg_match( '/^\d+([.,]\d+)?\s*[A-Za-zΩ°%]{1,4}$/u', $text ) ) {
			return false;
		}

		// Skip short all-caps codes (USB, ESP, LED, GPIO…).
		if ( mb_strlen( $text ) <= 5 && preg_match( '/^[A-Z0-9][A-Z0-9\-\+]*$/u', $text ) ) {
			return false;
		}

		return (bool) preg_match( '/\p{L}/u', $text );
	}
}
