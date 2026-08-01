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
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_bt_start_translate_job', array( $this, 'handle_start_job' ) );
		add_action( 'admin_post_bt_export_translations', array( $this, 'handle_export' ) );
		add_action( 'admin_post_bt_import_translations', array( $this, 'handle_import' ) );
	}

	/**
	 * Settings as last submenu item.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'budget-translator',
			__( 'Settings', 'budget-translator' ),
			__( 'Settings', 'budget-translator' ),
			'manage_options',
			'budget-translator-settings',
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

		$excluded_types = array();
		if ( isset( $input['excluded_post_types'] ) && is_array( $input['excluded_post_types'] ) ) {
			foreach ( $input['excluded_post_types'] as $type ) {
				$type = sanitize_key( (string) $type );
				if ( '' !== $type ) {
					$excluded_types[] = $type;
				}
			}
		}

		$glossary = array();
		if ( isset( $input['glossary'] ) && is_array( $input['glossary'] ) ) {
			foreach ( $input['glossary'] as $code => $text ) {
				$code = sanitize_key( (string) $code );
				if ( ! isset( $langs[ $code ] ) ) {
					continue;
				}
				$glossary[ $code ] = sanitize_textarea_field( (string) $text );
			}
		}

		$rule_flags = array();
		foreach ( array_keys( \BudgetTranslator\Translation\SourceAutocorrect::rules() ) as $rule_id ) {
			$rule_flags[ $rule_id ] = empty( $input['source_autocorrect_rules'][ $rule_id ] ) ? 0 : 1;
		}

		return array(
			'source_lang'              => $source,
			'target_langs'             => $targets,
			'provider'                 => $provider,
			'mymemory_email'           => isset( $input['mymemory_email'] ) ? sanitize_email( (string) $input['mymemory_email'] ) : '',
			'libretranslate_url'       => isset( $input['libretranslate_url'] ) ? esc_url_raw( (string) $input['libretranslate_url'] ) : $defaults['libretranslate_url'],
			'libretranslate_key'       => isset( $input['libretranslate_key'] ) ? sanitize_text_field( (string) $input['libretranslate_key'] ) : '',
			'deepl_api_key'            => isset( $input['deepl_api_key'] ) ? sanitize_text_field( (string) $input['deepl_api_key'] ) : (string) ( $prev['deepl_api_key'] ?? '' ),
			'deepl_api_url'            => isset( $input['deepl_api_url'] ) ? esc_url_raw( (string) $input['deepl_api_url'] ) : $defaults['deepl_api_url'],
			'google_api_key'           => isset( $input['google_api_key'] ) ? sanitize_text_field( (string) $input['google_api_key'] ) : (string) ( $prev['google_api_key'] ?? '' ),
			'enable_rewrites'          => empty( $input['enable_rewrites'] ) ? 0 : 1,
			'on_the_fly'               => empty( $input['on_the_fly'] ) ? 0 : 1,
			'language_switcher'        => empty( $input['language_switcher'] ) ? 0 : 1,
			'do_not_translate'         => isset( $input['do_not_translate'] ) ? sanitize_textarea_field( (string) $input['do_not_translate'] ) : '',
			'glossary'                 => $glossary,
			'excluded_post_types'      => array_values( array_unique( $excluded_types ) ),
			'excluded_post_ids'        => isset( $input['excluded_post_ids'] ) ? sanitize_text_field( (string) $input['excluded_post_ids'] ) : '',
			'excluded_paths'           => isset( $input['excluded_paths'] ) ? sanitize_textarea_field( (string) $input['excluded_paths'] ) : '',
			'auto_queue_on_save'       => empty( $input['auto_queue_on_save'] ) ? 0 : 1,
			'show_partial_notice'      => empty( $input['show_partial_notice'] ) ? 0 : 1,
			'source_autocorrect'       => empty( $input['source_autocorrect'] ) ? 0 : 1,
			'source_autocorrect_rules' => $rule_flags,
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
					'page'   => 'budget-translator-settings',
					'bt_job' => 'queued',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Export translations as JSON download.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'budget-translator' ) );
		}
		check_admin_referer( 'bt_export_translations' );

		$repo  = new TranslationRepository();
		$items = $repo->export_all();
		$payload = array(
			'version'     => 1,
			'exported_at' => gmdate( 'c' ),
			'source_lang' => Settings::source_lang(),
			'items'       => $items,
		);

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( false === $json ) {
			wp_die( esc_html__( 'Export failed.', 'budget-translator' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=budget-translator-export-' . gmdate( 'Ymd-His' ) . '.json' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Import translations from JSON upload.
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'budget-translator' ) );
		}
		check_admin_referer( 'bt_import_translations' );

		$skip_confirmed = ! empty( $_POST['bt_import_skip_confirmed'] );
		$file           = $_FILES['bt_import_file'] ?? null;

		$redirect = array(
			'page'      => 'budget-translator-settings',
			'bt_import' => 'error',
		);

		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = file_get_contents( (string) $file['tmp_name'] );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			wp_safe_redirect( add_query_arg( $redirect, admin_url( 'admin.php' ) ) );
			exit;
		}

		$repo   = new TranslationRepository();
		$result = $repo->import_items( $data['items'], $skip_confirmed );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'budget-translator-settings',
					'bt_import'   => 'ok',
					'bt_imported' => (int) $result['imported'],
					'bt_skipped'  => (int) $result['skipped'],
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

		$settings   = Settings::all();
		$languages  = Settings::available_languages();
		$providers  = Settings::available_providers();
		$repo       = new TranslationRepository();
		$stats      = $repo->stats();
		$job        = TranslateJob::get_status();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_option( 'bt_flush_rewrite', 1 );
		}

		include BT_PLUGIN_DIR . 'views/admin-settings.php';
	}
}
