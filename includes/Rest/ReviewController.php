<?php
/**
 * REST API for review actions.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Rest;

use BudgetTranslator\Translation\ProviderFactory;
use BudgetTranslator\Translation\SourcePropagator;
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
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete' ),
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
			'/translations/(?P<id>\d+)/retranslate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'retranslate' ),
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

		register_rest_route(
			'budget-translator/v1',
			'/translations/purge-invalid',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'purge_invalid' ),
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
	 * Update a translation (and optional source correction).
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'edited', 'confirmed', 'auto' ), true ) ) {
			$status = 'edited';
		}

		$translated = $request->get_param( 'translated_text' );
		$source     = $request->get_param( 'source_text' );

		$repo   = new TranslationRepository();
		$result = $repo->update_entry(
			$id,
			is_string( $source ) ? $source : null,
			is_string( $translated ) ? $translated : null,
			$status
		);

		if ( empty( $result['success'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result['message'] ?? 'Update failed',
				),
				400
			);
		}

		$propagated = array(
			'posts' => 0,
			'menus' => 0,
		);

		if ( ! empty( $result['source_changed'] ) ) {
			$propagator = new SourcePropagator();
			$propagated = $propagator->replace(
				(string) $result['old_source'],
				(string) $result['new_source']
			);
			$repo->sync_source_text(
				(string) $result['old_source'],
				(string) $result['new_source'],
				$id
			);
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'id'             => $id,
				'status'         => $status,
				'source_changed' => (bool) $result['source_changed'],
				'propagated'     => $propagated,
				'next_id'        => $this->next_pending_id( $id, $request ),
			)
		);
	}

	/**
	 * Resolve next pending ID for focus review.
	 *
	 * @param int             $id      Current ID.
	 * @param WP_REST_Request $request Request.
	 */
	private function next_pending_id( int $id, WP_REST_Request $request ): ?int {
		$lang = sanitize_key( (string) $request->get_param( 'lang' ) );
		$repo = new TranslationRepository();
		$next = $repo->find_next_pending( $id, $lang );
		return $next ? (int) $next->id : null;
	}

	/**
	 * Delete a cached translation.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request['id'];
		$repo  = new TranslationRepository();
		$count = $repo->delete_ids( array( $id ) );

		return new WP_REST_Response(
			array(
				'success' => $count > 0,
				'count'   => $count,
			)
		);
	}

	/**
	 * Force retranslate a row via the active provider.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function retranslate( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request['id'];
		$repo = new TranslationRepository();
		$row  = $repo->find_by_id( $id );

		if ( ! $row ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Not found' ), 404 );
		}

		if ( 'confirmed' === $row->status ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Confirmed translations are protected. Unconfirm first.',
				),
				400
			);
		}

		try {
			$provider   = ProviderFactory::make();
			$translated = $provider->translate(
				(string) $row->source_text,
				(string) $row->source_lang,
				(string) $row->target_lang
			);
			$repo->upsert(
				(string) $row->source_lang,
				(string) $row->target_lang,
				(string) $row->source_text,
				$translated,
				$provider->get_slug(),
				'auto',
				true
			);
			$repo->increment_api_calls();
		} catch ( \Throwable $e ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				502
			);
		}

		$fresh = $repo->find_by_id( $id );
		if ( ! $fresh ) {
			$fresh = $repo->find_by_hash(
				$repo->hash( (string) $row->source_lang, (string) $row->target_lang, (string) $row->source_text )
			);
		}

		return new WP_REST_Response(
			array(
				'success'         => true,
				'id'              => $fresh ? (int) $fresh->id : $id,
				'translated_text' => $fresh ? (string) $fresh->translated_text : $translated,
				'status'          => $fresh ? (string) $fresh->status : 'auto',
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

	/**
	 * Remove auto-translations that contain provider error messages.
	 */
	public function purge_invalid(): WP_REST_Response {
		$repo  = new TranslationRepository();
		$count = $repo->delete_invalid_api_payloads();

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => $count,
			)
		);
	}
}
