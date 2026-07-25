<?php
/**
 * Settings admin page.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\TranslateJob;
use BudgetTranslator\Translation\TranslationRepository;

/**
 * Class SettingsPage
 */
final class SettingsPage {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_bt_start_translate_job', array( $this, 'handle_start_job' ) );
	}

	/**
	 * Register top-level menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Budget Translator', 'budget-translator' ),
			__( 'Budget Translator', 'budget-translator' ),
			'manage_options',
			'budget-translator',
			array( $this, 'render' ),
			'dashicons-translation',
			58
		);

		add_submenu_page(
			'budget-translator',
			__( 'Settings', 'budget-translator' ),
			__( 'Settings', 'budget-translator' ),
			'manage_options',
			'budget-translator',
			array( $this, 'render' )
		);
	}

	/**
	 * Register setting.
	 */
	public function register_settings(): void {
		register_setting(
			'bt_settings_group',
			Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$defaults = Settings::defaults();
		$input    = is_array( $input ) ? $input : array();
		$langs    = Settings::available_languages();

		$source = isset( $input['source_lang'] ) ? sanitize_key( (string) $input['source_lang'] ) : $defaults['source_lang'];
		if ( ! isset( $langs[ $source ] ) ) {
			$source = $defaults['source_lang'];
		}

		$targets = array();
		if ( isset( $input['target_langs'] ) && is_array( $input['target_langs'] ) ) {
			foreach ( $input['target_langs'] as $code ) {
				$code = sanitize_key( (string) $code );
				if ( isset( $langs[ $code ] ) && $code !== $source ) {
					$targets[] = $code;
				}
			}
		}
		$targets = array_values( array_unique( $targets ) );
		if ( array() === $targets ) {
			$targets = array( 'en' !== $source ? 'en' : 'fr' );
		}

		$providers = Settings::available_providers();
		$provider  = isset( $input['provider'] ) ? sanitize_key( (string) $input['provider'] ) : $defaults['provider'];
		if ( ! isset( $providers[ $provider ] ) ) {
			$provider = $defaults['provider'];
		}

		$prev = Settings::all();

		return array(
			'source_lang'        => $source,
			'target_langs'       => $targets,
			'provider'           => $provider,
			'mymemory_email'     => isset( $input['mymemory_email'] ) ? sanitize_email( (string) $input['mymemory_email'] ) : '',
			'libretranslate_url' => isset( $input['libretranslate_url'] ) ? esc_url_raw( (string) $input['libretranslate_url'] ) : $defaults['libretranslate_url'],
			'libretranslate_key' => isset( $input['libretranslate_key'] ) ? sanitize_text_field( (string) $input['libretranslate_key'] ) : '',
			'deepl_api_key'      => isset( $input['deepl_api_key'] ) ? sanitize_text_field( (string) $input['deepl_api_key'] ) : (string) ( $prev['deepl_api_key'] ?? '' ),
			'deepl_api_url'      => isset( $input['deepl_api_url'] ) ? esc_url_raw( (string) $input['deepl_api_url'] ) : $defaults['deepl_api_url'],
			'google_api_key'     => isset( $input['google_api_key'] ) ? sanitize_text_field( (string) $input['google_api_key'] ) : (string) ( $prev['google_api_key'] ?? '' ),
			'enable_rewrites'    => empty( $input['enable_rewrites'] ) ? 0 : 1,
			'on_the_fly'         => empty( $input['on_the_fly'] ) ? 0 : 1,
			'language_switcher'  => empty( $input['language_switcher'] ) ? 0 : 1,
		);
	}

	/**
	 * Start a full-site translation job.
	 */
	public function handle_start_job(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'budget-translator' ) );
		}

		check_admin_referer( 'bt_start_translate_job' );

		TranslateJob::queue_site();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'budget-translator',
					'bt_job'  => 'queued',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = Settings::all();
		$languages = Settings::available_languages();
		$providers = Settings::available_providers();
		$repo      = new TranslationRepository();
		$stats     = $repo->stats();
		$job       = TranslateJob::get_status();

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'bt_flush_rewrite', 1 );
		}

		include BT_PLUGIN_DIR . 'views/admin-settings.php';
	}
}
