<?php
/**
 * DeepL translation provider.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation\Providers;

use BudgetTranslator\Settings;

/**
 * Class DeepLProvider
 */
final class DeepLProvider implements ProviderInterface {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'deepl';
	}

	/**
	 * {@inheritdoc}
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string {
		$api_key = (string) Settings::get( 'deepl_api_key', '' );
		if ( '' === $api_key ) {
			throw new \RuntimeException( 'DeepL API key is missing.' );
		}

		$base = rtrim( (string) Settings::get( 'deepl_api_url', 'https://api-free.deepl.com' ), '/' );

		$response = wp_remote_post(
			$base . '/v2/translate',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'DeepL-Auth-Key ' . $api_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'text'        => $text,
					'source_lang' => strtoupper( $source_lang ),
					'target_lang' => $this->map_target( $target_lang ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['translations'][0]['text'] ) ) {
			throw new \RuntimeException( 'DeepL request failed.' );
		}

		return (string) $body['translations'][0]['text'];
	}

	/**
	 * Map ISO codes to DeepL target codes.
	 *
	 * @param string $lang Language code.
	 */
	private function map_target( string $lang ): string {
		$map = array(
			'en' => 'EN-US',
			'pt' => 'PT-PT',
			'zh' => 'ZH',
		);

		return $map[ $lang ] ?? strtoupper( $lang );
	}
}
