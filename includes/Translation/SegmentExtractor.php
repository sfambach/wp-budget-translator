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
	private array $skip_tags = array( 'script', 'style', 'code', 'pre', 'textarea', 'svg' );

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
			return '' === $normalized ? array() : array( $normalized );
		}

		$segments = array();
		$dom      = $this->load_dom( $content );
		if ( null === $dom ) {
			$normalized = $this->normalize( wp_strip_all_tags( $content ) );
			return '' === $normalized ? array() : array( $normalized );
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
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		return trim( $text );
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
	 * Walk text nodes and collect segments.
	 *
	 * @param \DOMNode|null $node     Node.
	 * @param list<string>  $segments Collector.
	 */
	private function walk( ?\DOMNode $node, array &$segments ): void {
		if ( null === $node ) {
			return;
		}

		if ( XML_ELEMENT_NODE === $node->nodeType ) {
			$name = strtolower( $node->nodeName );
			if ( in_array( $name, $this->skip_tags, true ) ) {
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

		if ( XML_ELEMENT_NODE === $node->nodeType ) {
			$name = strtolower( $node->nodeName );
			if ( in_array( $name, $this->skip_tags, true ) ) {
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
		if ( preg_match( '#^https?://#i', $text ) ) {
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
