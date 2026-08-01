<?php
/**
 * Bulk / one-by-one review admin pages (top-level menu + submenus).
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\TranslationRepository;
use BudgetTranslator\Translation\TranslationService;

/**
 * Class ReviewPage
 */
final class ReviewPage {

	/**
	 * Register hooks.
	 *
	 * Menu order: Bulk (9) → By post (20) → One by one (25) → Settings (99).
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 9 );
		add_action( 'admin_menu', array( $this, 'add_focus_menu' ), 25 );
	}

	/**
	 * Top-level menu opens Bulk; Settings registers later at the bottom.
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
			__( 'Bulk', 'budget-translator' ),
			__( 'Bulk', 'budget-translator' ),
			'manage_options',
			'budget-translator',
			array( $this, 'render' )
		);
	}

	/**
	 * One-by-one focus review after By post (priority 20).
	 */
	public function add_focus_menu(): void {
		add_submenu_page(
			'budget-translator',
			__( 'One by one', 'budget-translator' ),
			__( 'One by one', 'budget-translator' ),
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
		$sort   = isset( $_GET['bt_sort'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_sort'] ) ) : 'newest'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per    = 20;

		$allowed_status = array( 'pending', 'auto', 'edited', 'confirmed', 'all' );
		if ( $status && ! in_array( $status, $allowed_status, true ) ) {
			$status = 'pending';
		}
		if ( 'all' === $status ) {
			$status = '';
		}

		$allowed_sort = array_keys( TranslationRepository::review_order_by_map() );
		if ( ! in_array( $sort, $allowed_sort, true ) ) {
			$sort = 'newest';
		}

		// Whole-cache hygiene once per Bulk list load (not shared with focus).
		$service = new TranslationService();
		$service->cleanup_review_cache();

		$result = $repo->query(
			array(
				'status' => $status,
				'lang'   => $lang,
				'search' => $search,
				'sort'   => $sort,
				'page'   => $page,
				'per'    => $per,
			)
		);

		// Result-set polish only (not a second whole-cache purge).
		if ( ! empty( $result['items'] ) && is_array( $result['items'] ) ) {
			$result['items'] = $service->heal_auto_rows( $result['items'] );
			$result['items'] = $service->dedupe_review_rows( $result['items'] );
		}

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

		// Whole-cache hygiene once per focus-page load (separate request from list).
		$service = new TranslationService();
		$service->cleanup_review_cache();

		$item = null;
		if ( $id > 0 ) {
			$item = $repo->find_by_id( $id );
			// Keep explicitly requested IDs visible (needed for Previous).
			// Only fall back when the ID does not exist or language filter mismatches.
			if ( $item && $lang && $item->target_lang !== $lang && 'confirmed' !== $item->status ) {
				$item = $repo->find_next_pending( 0, $lang );
			}
		}

		if ( ! $item ) {
			$item = $repo->find_next_pending( 0, $lang );
		}

		// Result-set polish only (cleanup_review_cache already ran once above).
		if ( $item && 'auto' === (string) $item->status ) {
			$healed = $service->heal_auto_rows( array( $item ) );
			$item   = $healed[0] ?? $item;
		}

		$pending_total = $repo->count_pending( $lang );
		$languages     = Settings::available_languages();
		$targets       = Settings::target_langs();
		$prev          = $item ? $repo->find_prev_pending( (int) $item->id, $lang ) : null;
		$next          = $item ? $repo->find_next_pending( (int) $item->id, $lang ) : null;

		include BT_PLUGIN_DIR . 'views/admin-review-focus.php';
	}
}
