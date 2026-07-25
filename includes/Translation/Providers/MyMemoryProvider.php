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
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'mymemory';
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string {
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

		if ( 200 !== $code || ! is_array( $body ) ) {
			throw new \RuntimeException( 'MyMemory returned an invalid response.' );
		}

		$translated = $body['responseData']['translatedText'] ?? '';
		if ( ! is_string( $translated ) || '' === $translated ) {
			throw new \RuntimeException( 'MyMemory returned empty translation.' );
		}

		// MyMemory sometimes echoes quota messages as translation.
		if ( str_contains( $translated, 'MYMEMORY WARNING' ) ) {
			throw new \RuntimeException( 'MyMemory quota exceeded.' );
		}

		return html_entity_decode( $translated, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
