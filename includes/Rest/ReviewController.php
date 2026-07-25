<?php
/**
 * REST API for review actions.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Rest;

use BudgetTranslator\Translation\TranslationRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class ReviewController
 */
final class ReviewController {

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
			'/translations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			'budget-translator/v1',
			'/translations/confirm',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'confirm' ),
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
	 * Update a translation.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request['id'];
		$text   = (string) $request->get_param( 'translated_text' );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'edited', 'confirmed', 'auto' ), true ) ) {
			$status = 'edited';
		}

		$repo = new TranslationRepository();
		$ok   = $repo->update_translation( $id, $text, $status );

		if ( ! $ok ) {
			return new WP_REST_Response( array( 'success' => false ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'id'      => $id,
				'status'  => $status,
			)
		);
	}

	/**
	 * Bulk confirm.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function confirm( WP_REST_Request $request ): WP_REST_Response {
		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}

		$repo  = new TranslationRepository();
		$count = $repo->confirm_ids( array_map( 'intval', $ids ) );

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => $count,
			)
		);
	}
}
