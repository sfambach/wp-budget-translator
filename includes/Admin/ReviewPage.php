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

		add_submenu_page(
			'budget-translator',
			__( 'Review one by one', 'budget-translator' ),
			__( 'Review one by one', 'budget-translator' ),
			'manage_options',
			'budget-translator-focus',
			array( $this, 'render_focus' )
		);
	}

	/**
	 * Render list review UI.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repo = new TranslationRepository();
		// Default: pending review (hide already confirmed entries).
		$status = array_key_exists( 'bt_status', $_GET ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( (string) wp_unslash( $_GET['bt_status'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'pending';
		$lang   = isset( $_GET['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per    = 20;

		$allowed_status = array( 'pending', 'auto', 'edited', 'confirmed', 'all' );
		if ( $status && ! in_array( $status, $allowed_status, true ) ) {
			$status = 'pending';
		}
		if ( 'all' === $status ) {
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

	/**
	 * Render single-item focus review UI.
	 */
	public function render_focus(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$repo = new TranslationRepository();
		$lang = isset( $_GET['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_GET['bt_id'] ) ? absint( $_GET['bt_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$item = null;
		if ( $id > 0 ) {
			$item = $repo->find_by_id( $id );
			// If this ID is already confirmed, jump to next pending.
			if ( $item && 'confirmed' === $item->status ) {
				$item = $repo->find_next_pending( $id, $lang );
			} elseif ( $item && $lang && $item->target_lang !== $lang ) {
				$item = $repo->find_next_pending( 0, $lang );
			}
		}

		if ( ! $item ) {
			$item = $repo->find_next_pending( 0, $lang );
		}

		$pending_total = $repo->count_pending( $lang );
		$languages     = Settings::available_languages();
		$targets       = Settings::target_langs();
		$prev          = $item ? $repo->find_prev_pending( (int) $item->id, $lang ) : null;
		$next          = $item ? $repo->find_next_pending( (int) $item->id, $lang ) : null;

		include BT_PLUGIN_DIR . 'views/admin-review-focus.php';
	}
}
