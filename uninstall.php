<?php
/**
 * Uninstall cleanup.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'bt_settings' );
delete_option( 'bt_db_version' );
delete_option( 'bt_flush_rewrite' );
delete_option( 'bt_api_calls' );
delete_option( 'bt_cache_hits' );
delete_option( 'bt_job_status' );
delete_option( 'bt_job_queue' );

$table = $wpdb->prefix . 'bt_translations';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

wp_clear_scheduled_hook( 'bt_process_translation_queue' );
