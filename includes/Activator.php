<?php
/**
 * Plugin activation / deactivation.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator;

/**
 * Class Activator
 */
final class Activator {

	public const DB_VERSION = '1.1.0';

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::ensure_defaults();

		update_option( 'bt_db_version', self::DB_VERSION );
		update_option( 'bt_flush_rewrite', 1 );

		wp_schedule_event( time() + 60, 'bt_every_minute', 'bt_process_translation_queue' );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bt_process_translation_queue' );
		flush_rewrite_rules();
	}

	/**
	 * Create the translations cache table.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table           = $wpdb->prefix . 'bt_translations';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hash char(64) NOT NULL,
			source_lang varchar(10) NOT NULL,
			target_lang varchar(10) NOT NULL,
			source_text longtext NOT NULL,
			previous_source_text longtext NULL,
			translated_text longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'auto',
			provider varchar(50) NOT NULL DEFAULT '',
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY hash (hash),
			KEY status_lang (status, target_lang),
			KEY target_lang (target_lang)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Ensure default settings exist.
	 */
	public static function ensure_defaults(): void {
		$defaults = Settings::defaults();
		$current  = get_option( Settings::OPTION_KEY, array() );

		if ( ! is_array( $current ) || array() === $current ) {
			update_option( Settings::OPTION_KEY, $defaults );
			return;
		}

		update_option( Settings::OPTION_KEY, wp_parse_args( $current, $defaults ) );
	}
}
