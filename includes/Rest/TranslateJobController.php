<?php
/**
 * REST API for translation job status / start.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Rest;

use BudgetTranslator\Translation\TranslateJob;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class TranslateJobController
 */
final class TranslateJobController {

	/**
	 * Register routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function routes(): void {
		register_rest_route(
			'budget-translator/v1',
			'/job',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			'budget-translator/v1',
			'/job/process',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'process' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Capability check.
	 */
	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Job status.
	 */
	public function status(): WP_REST_Response {
		return new WP_REST_Response( TranslateJob::get_status() );
	}

	/**
	 * Start site translation job.
	 */
	public function start(): WP_REST_Response {
		TranslateJob::queue_site();
		return new WP_REST_Response( TranslateJob::get_status() );
	}

	/**
	 * Process one queue chunk (for admin polling).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function process( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		TranslateJob::process_queue();
		return new WP_REST_Response( TranslateJob::get_status() );
	}
}
