<?php
/**
 * MyMemory free translation provider.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation\Providers;

use BudgetTranslator\Settings;

/**
 * Class MyMemoryProvider
 */
final class MyMemoryProvider implements ProviderInterface {

	/**
	 * Soft character limit for free GET requests (MyMemory ~500).
	 */
	private const MAX_CHARS = 450;

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'mymemory';
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}

		if ( mb_strlen( $text ) <= self::MAX_CHARS ) {
			return $this->request( $text, $source_lang, $target_lang );
		}

		// Long segments: translate sentence-sized chunks so langpair is not dropped from the GET URL.
		$parts  = $this->split_text( $text );
		$out    = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( mb_strlen( $part ) > self::MAX_CHARS ) {
				$part = mb_substr( $part, 0, self::MAX_CHARS );
			}
			$out[] = $this->request( $part, $source_lang, $target_lang );
		}

		return trim( implode( ' ', $out ) );
	}

	/**
	 * Perform a single MyMemory request.
	 *
	 * @param string $text        Source text.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 */
	private function request( string $text, string $source_lang, string $target_lang ): string {
		$email = (string) Settings::get( 'mymemory_email', '' );
		$query = array(
			'q'        => $text,
			'langpair' => $source_lang . '|' . $target_lang,
		);
		if ( $email ) {
			$query['de'] = $email;
		}

		$url      = add_query_arg( $query, 'https://api.mymemory.translated.net/get' );
		$response = wp_remote_get(
			$url,
			array(
				// Long motherboard/product blurbs often need >8s; admin sync translates in one request.
				'timeout' => 20,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code ) {
			throw new \RuntimeException( 'MyMemory rate limit exceeded. Wait a moment or add your email in settings.' );
		}

		if ( ( $code < 200 || $code >= 300 ) || ! is_array( $body ) ) {
			throw new \RuntimeException( 'MyMemory returned an invalid response.' );
		}

		$status = (int) ( $body['responseStatus'] ?? 0 );
		$translated = $body['responseData']['translatedText'] ?? '';

		if ( ! is_string( $translated ) || '' === trim( $translated ) ) {
			throw new \RuntimeException( 'MyMemory returned empty translation.' );
		}

		if ( 200 !== $status ) {
			throw new \RuntimeException( 'MyMemory error: ' . $translated );
		}

		if ( $this->looks_like_api_error( $translated ) ) {
			throw new \RuntimeException( 'MyMemory returned an API message instead of a translation.' );
		}

		return html_entity_decode( $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Detect known MyMemory error payloads stored as "translations".
	 *
	 * @param string $text Candidate translation.
	 */
	private function looks_like_api_error( string $text ): bool {
		$upper = strtoupper( $text );
		$needles = array(
			'MYMEMORY WARNING',
			'PLEASE SELECT TWO-LETTER ISO',
			'RFC3066',
			'INVALID LANGUAGE PAIR',
			'QUERY LENGTH LIMIT',
			'YOU USED ALL AVAILABLE FREE TRANSLATIONS',
			'IS NOT SUPPORTED',
		);

		foreach ( $needles as $needle ) {
			if ( str_contains( $upper, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split long text into sentence-ish chunks under the API limit.
	 *
	 * @param string $text Text.
	 * @return list<string>
	 */
	private function split_text( string $text ): array {
		$parts = preg_split( '/(?<=[\.\!\?\…])\s+/u', $text ) ?: array( $text );
		$chunks = array();
		$buffer = '';

		foreach ( $parts as $part ) {
			$candidate = '' === $buffer ? $part : $buffer . ' ' . $part;
			if ( mb_strlen( $candidate ) <= self::MAX_CHARS ) {
				$buffer = $candidate;
				continue;
			}
			if ( '' !== $buffer ) {
				$chunks[] = $buffer;
			}
			$buffer = $part;
		}

		if ( '' !== $buffer ) {
			$chunks[] = $buffer;
		}

		return $chunks ?: array( $text );
	}
}
