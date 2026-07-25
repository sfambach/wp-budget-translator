<?php
/**
 * Review admin page.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\TranslationRepository;

/**
 * Class ReviewPage
 */
final class ReviewPage {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Submenu for translation review.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'budget-translator',
			__( 'Translations', 'budget-translator' ),
			__( 'Translations', 'budget-translator' ),
			'manage_options',
			'budget-translator-review',
			array( $this, 'render' )
		);
	}

	/**
	 * Render review UI.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repo    = new TranslationRepository();
		$status  = isset( $_GET['bt_status'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang    = isset( $_GET['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per     = 20;

		$allowed_status = array( 'auto', 'edited', 'confirmed' );
		if ( $status && ! in_array( $status, $allowed_status, true ) ) {
			$status = '';
		}

		$result = $repo->query(
			array(
				'status' => $status,
				'lang'   => $lang,
				'search' => $search,
				'page'   => $page,
				'per'    => $per,
			)
		);

		$languages = Settings::available_languages();
		$targets   = Settings::target_langs();

		include BT_PLUGIN_DIR . 'views/admin-review.php';
	}
}
