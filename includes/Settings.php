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
			'do_not_translate'     => '',
			'glossary'             => array(),
			'excluded_post_types'  => array(),
			'excluded_post_ids'    => '',
			'excluded_paths'       => '',
			'auto_queue_on_save'        => 1,
			'show_partial_notice'       => 0,
			'source_autocorrect'        => 1,
			'source_autocorrect_rules'  => array(
				'space_before_punct'   => 1,
				'collapse_spaces'      => 1,
				'no_space_at_linebreak' => 1,
				'capitalize_sentences' => 1,
			),
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

	/**
	 * Excluded post type slugs.
	 *
	 * @return list<string>
	 */
	public static function excluded_post_types(): array {
		$types = self::get( 'excluded_post_types', array() );
		if ( ! is_array( $types ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'strval', $types ) ) );
	}

	/**
	 * Excluded post IDs.
	 *
	 * @return list<int>
	 */
	public static function excluded_post_ids(): array {
		$raw = (string) self::get( 'excluded_post_ids', '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[\s,]+/', $raw ) ?: array();
		$ids   = array();
		foreach ( $parts as $part ) {
			$id = (int) $part;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Excluded URL path prefixes (relative, leading slash).
	 *
	 * @return list<string>
	 */
	public static function excluded_paths(): array {
		$raw   = (string) self::get( 'excluded_paths', '' );
		$lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			$path = '/' . ltrim( $line, '/' );
			$out[] = untrailingslashit( $path ) ?: '/';
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Whether a post should be skipped.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_excluded_post( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		if ( in_array( $post_id, self::excluded_post_ids(), true ) ) {
			return true;
		}

		$type = get_post_type( $post_id );
		if ( $type && in_array( $type, self::excluded_post_types(), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the current frontend request path is excluded.
	 */
	public static function is_excluded_request(): bool {
		$paths = self::excluded_paths();
		if ( array() === $paths ) {
			return false;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?? '/' );
		$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) ) ?: '/';
		}
		$path = '/' . ltrim( $path, '/' );

		// Strip language prefix for matching.
		foreach ( self::target_langs() as $lang ) {
			$prefix = '/' . $lang;
			if ( $path === $prefix || $path === $prefix . '/' ) {
				$path = '/';
				break;
			}
			if ( str_starts_with( $path, $prefix . '/' ) ) {
				$path = substr( $path, strlen( $prefix ) ) ?: '/';
				break;
			}
		}

		foreach ( $paths as $excluded ) {
			if ( '/' === $excluded ) {
				continue;
			}
			if ( $path === $excluded || str_starts_with( $path, $excluded . '/' ) ) {
				return true;
			}
		}

		return false;
	}
}
