<?php
/**
 * Admin assets.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

/**
 * Class Assets
 */
final class Assets {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue admin CSS/JS on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, 'budget-translator' ) ) {
			return;
		}

		wp_enqueue_style(
			'bt-admin',
			BT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BT_VERSION
		);

		wp_enqueue_script(
			'bt-admin',
			BT_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BT_VERSION,
			true
		);

		wp_localize_script(
			'bt-admin',
			'btAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'budget-translator/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'saved'     => __( 'Saved.', 'budget-translator' ),
					'confirmed' => __( 'Confirmed.', 'budget-translator' ),
					'error'     => __( 'Something went wrong.', 'budget-translator' ),
					'running'   => __( 'Translation job running…', 'budget-translator' ),
					'done'      => __( 'Translation job finished.', 'budget-translator' ),
				),
			)
		);
	}
}
