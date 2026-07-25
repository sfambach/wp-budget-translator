<?php
/**
 * Plugin settings helper.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator;

/**
 * Class Settings
 */
final class Settings {

	public const OPTION_KEY = 'bt_settings';

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'source_lang'          => 'de',
			'target_langs'         => array( 'en' ),
			'provider'             => 'mymemory',
			'mymemory_email'       => '',
			'libretranslate_url'   => 'https://libretranslate.com',
			'libretranslate_key'   => '',
			'deepl_api_key'        => '',
			'deepl_api_url'        => 'https://api-free.deepl.com',
			'google_api_key'       => '',
			'enable_rewrites'      => 1,
			'on_the_fly'           => 1,
			'language_switcher'    => 1,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all = self::all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Update settings (partial merge).
	 *
	 * @param array<string, mixed> $data New values.
	 */
	public static function update( array $data ): void {
		$merged = wp_parse_args( $data, self::all() );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * Supported language codes and labels.
	 *
	 * @return array<string, string>
	 */
	public static function available_languages(): array {
		return array(
			'de' => __( 'German', 'budget-translator' ),
			'en' => __( 'English', 'budget-translator' ),
			'fr' => __( 'French', 'budget-translator' ),
			'es' => __( 'Spanish', 'budget-translator' ),
			'it' => __( 'Italian', 'budget-translator' ),
			'nl' => __( 'Dutch', 'budget-translator' ),
			'pl' => __( 'Polish', 'budget-translator' ),
			'pt' => __( 'Portuguese', 'budget-translator' ),
			'ru' => __( 'Russian', 'budget-translator' ),
			'uk' => __( 'Ukrainian', 'budget-translator' ),
			'cs' => __( 'Czech', 'budget-translator' ),
			'sv' => __( 'Swedish', 'budget-translator' ),
			'da' => __( 'Danish', 'budget-translator' ),
			'fi' => __( 'Finnish', 'budget-translator' ),
			'no' => __( 'Norwegian', 'budget-translator' ),
			'ja' => __( 'Japanese', 'budget-translator' ),
			'zh' => __( 'Chinese', 'budget-translator' ),
			'ar' => __( 'Arabic', 'budget-translator' ),
			'tr' => __( 'Turkish', 'budget-translator' ),
			'hu' => __( 'Hungarian', 'budget-translator' ),
		);
	}

	/**
	 * Available translation providers.
	 *
	 * @return array<string, string>
	 */
	public static function available_providers(): array {
		return array(
			'mymemory'       => __( 'MyMemory (free)', 'budget-translator' ),
			'libretranslate' => __( 'LibreTranslate (free / self-hosted)', 'budget-translator' ),
			'deepl'          => __( 'DeepL (API key)', 'budget-translator' ),
			'google'         => __( 'Google Cloud Translation (API key)', 'budget-translator' ),
		);
	}

	/**
	 * Source language code.
	 */
	public static function source_lang(): string {
		return (string) self::get( 'source_lang', 'de' );
	}

	/**
	 * Target language codes.
	 *
	 * @return list<string>
	 */
	public static function target_langs(): array {
		$langs = self::get( 'target_langs', array( 'en' ) );
		if ( ! is_array( $langs ) ) {
			return array( 'en' );
		}

		$source = self::source_lang();
		$langs  = array_values(
			array_filter(
				array_map( 'strval', $langs ),
				static fn( string $code ): bool => $code !== '' && $code !== $source
			)
		);

		return $langs;
	}

	/**
	 * All active language codes including source.
	 *
	 * @return list<string>
	 */
	public static function all_langs(): array {
		return array_values( array_unique( array_merge( array( self::source_lang() ), self::target_langs() ) ) );
	}
}
