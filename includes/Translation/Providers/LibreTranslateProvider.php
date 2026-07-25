<?php
/**
 * LibreTranslate provider.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation\Providers;

use BudgetTranslator\Settings;

/**
 * Class LibreTranslateProvider
 */
final class LibreTranslateProvider implements ProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'libretranslate';
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string {
		$base = rtrim( (string) Settings::get( 'libretranslate_url', 'https://libretranslate.com' ), '/' );
		$key  = (string) Settings::get( 'libretranslate_key', '' );

		$payload = array(
			'q'      => $text,
			'source' => $source_lang,
			'target' => $target_lang,
			'format' => 'text',
		);
		if ( $key ) {
			$payload['api_key'] = $key;
		}

		$response = wp_remote_post(
			$base . '/translate',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) && isset( $body['error'] ) ? (string) $body['error'] : 'LibreTranslate request failed.';
			throw new \RuntimeException( $message );
		}

		$translated = $body['translatedText'] ?? '';
		if ( ! is_string( $translated ) || '' === $translated ) {
			throw new \RuntimeException( 'LibreTranslate returned empty translation.' );
		}

		return $translated;
	}
}
