<?php
/**
 * Google Cloud Translation provider (v2 REST).
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation\Providers;

use BudgetTranslator\Settings;

/**
 * Class GoogleProvider
 */
final class GoogleProvider implements ProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'google';
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string {
		$api_key = (string) Settings::get( 'google_api_key', '' );
		if ( '' === $api_key ) {
			throw new \RuntimeException( 'Google API key is missing.' );
		}

		$url = add_query_arg(
			array( 'key' => $api_key ),
			'https://translation.googleapis.com/language/translate/v2'
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'q'      => $text,
						'source' => $source_lang,
						'target' => $target_lang,
						'format' => 'text',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		$translated = $body['data']['translations'][0]['translatedText'] ?? null;
		if ( 200 !== $code || ! is_string( $translated ) || '' === $translated ) {
			throw new \RuntimeException( 'Google Translation request failed.' );
		}

		return html_entity_decode( $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
