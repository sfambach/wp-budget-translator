<?php
/**
 * Per-post translation review admin page.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Admin;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\PostSegments;
use BudgetTranslator\Translation\TranslateJob;
use BudgetTranslator\Translation\TranslationRepository;
use BudgetTranslator\Translation\TranslationService;

/**
 * Class PostReviewPage
 */
final class PostReviewPage {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_post_bt_queue_post', array( $this, 'handle_queue_post' ) );
		add_action( 'admin_post_bt_translate_segment', array( $this, 'handle_translate_segment' ) );
	}

	/**
	 * Submenu: By post.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'budget-translator',
			__( 'By post', 'budget-translator' ),
			__( 'By post', 'budget-translator' ),
			'manage_options',
			'budget-translator-by-post',
			array( $this, 'render' )
		);
	}

	/**
	 * Meta box on post/page editor.
	 */
	public function add_meta_box(): void {
		foreach ( array( 'post', 'page' ) as $type ) {
			add_meta_box(
				'bt-post-review',
				__( 'Budget Translator', 'budget-translator' ),
				array( $this, 'render_meta_box' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Meta box content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$url = admin_url( 'admin.php?page=budget-translator-by-post&post_id=' . (int) $post->ID );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Review translations for this post', 'budget-translator' ) . '</a></p>';
	}

	/**
	 * Translate missing segments for one post immediately (admin sync).
	 */
	public function handle_queue_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'budget-translator' ) );
		}
		check_admin_referer( 'bt_queue_post' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$lang    = isset( $_POST['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_POST['bt_lang'] ) ) : '';
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=budget-translator-by-post' ) );
			exit;
		}

		$targets = Settings::target_langs();
		if ( '' === $lang || ! in_array( $lang, $targets, true ) ) {
			$lang = $targets[0] ?? '';
		}

		$translated = 0;
		$remaining  = 0;
		$error_msg  = '';

		if ( '' !== $lang ) {
			$result     = $this->fill_missing_translations( $post, $lang );
			$translated = $result['translated'];
			$remaining  = $result['remaining'];
			$error_msg  = $result['error'];
		}

		$args = array(
			'page'          => 'budget-translator-by-post',
			'post_id'       => $post_id,
			'bt_lang'       => $lang,
			'bt_status'     => 'needs_work',
			'bt_translated' => (string) $translated,
			'bt_remaining'  => (string) $remaining,
		);
		if ( '' !== $error_msg ) {
			$args['bt_error'] = $error_msg;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Translate a single missing segment for a post (admin sync).
	 */
	public function handle_translate_segment(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'budget-translator' ) );
		}
		check_admin_referer( 'bt_translate_segment' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$lang    = isset( $_POST['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_POST['bt_lang'] ) ) : '';
		$hash    = isset( $_POST['source_hash'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['source_hash'] ) ) : '';
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || '' === $hash ) {
			wp_safe_redirect( admin_url( 'admin.php?page=budget-translator-by-post' ) );
			exit;
		}

		$targets = Settings::target_langs();
		if ( '' === $lang || ! in_array( $lang, $targets, true ) ) {
			$lang = $targets[0] ?? '';
		}

		$translated = 0;
		$remaining  = 1;
		$error_msg  = '';

		if ( '' !== $lang ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$source_lang = Settings::source_lang();
			$sources     = $this->extract_post_sources( $post );
			$repo        = new TranslationRepository();
			$service     = new TranslationService();

			$source = '';
			foreach ( $sources as $candidate ) {
				if ( $repo->hash( $source_lang, $lang, $candidate ) === $hash ) {
					$source = $candidate;
					break;
				}
			}

			if ( '' === $source ) {
				$error_msg = __( 'Text not found in this post.', 'budget-translator' );
			} else {
				TranslationService::consume_last_errors();
				$service->resolve_segments( array( $source ), $source_lang, $lang, true );
				$errors = TranslationService::consume_last_errors();
				$row    = $repo->find_by_hash( $hash );
				if ( $row ) {
					$translated = 1;
					$remaining  = 0;
				} else {
					$error_msg = $errors[0] ?? __( 'Translation failed for this text.', 'budget-translator' );
				}
			}
		}

		$args = array(
			'page'          => 'budget-translator-by-post',
			'post_id'       => $post_id,
			'bt_lang'       => $lang,
			'bt_status'     => 'needs_work',
			'bt_translated' => (string) $translated,
			'bt_remaining'  => (string) $remaining,
		);
		if ( '' !== $error_msg ) {
			$args['bt_error'] = $error_msg;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Sources that have no cached translation row yet.
	 *
	 * @param list<string>          $sources     Extracted sources.
	 * @param string                $source_lang Source language.
	 * @param string                $target_lang Target language.
	 * @param TranslationRepository $repo        Repository.
	 * @return list<string>
	 */
	private function missing_sources( array $sources, string $source_lang, string $target_lang, TranslationRepository $repo ): array {
		$rows = $repo->find_for_sources( $sources, $source_lang, $target_lang, '' );
		$have = array();
		foreach ( $rows as $row ) {
			$have[ (string) $row->hash ] = true;
		}

		$missing = array();
		foreach ( $sources as $source ) {
			$hash = $repo->hash( $source_lang, $target_lang, $source );
			if ( ! isset( $have[ $hash ] ) ) {
				$missing[] = $source;
			}
		}

		return $missing;
	}

	/**
	 * Render list or detail.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id > 0 ) {
			$this->render_detail( $post_id );
			return;
		}

		$this->render_list();
	}

	/**
	 * Post picker list with coverage % and coverage filters.
	 */
	private function render_list(): void {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset( $_GET['bt_coverage'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_coverage'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$lang   = isset( $_GET['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per    = 20;

		$targets = Settings::target_langs();
		if ( '' === $lang && array() !== $targets ) {
			$lang = $targets[0];
		}

		$allowed_filters = array( 'all', 'none', 'partial', 'complete', 'needs_review', 'fully_confirmed' );
		if ( ! in_array( $filter, $allowed_filters, true ) ) {
			$filter = 'all';
		}

		$query = new \WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 200,
				'paged'                  => 1,
				's'                      => $search,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$stats_map = array();
		$rows      = array();
		foreach ( $query->posts as $p ) {
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			$stats                     = $this->post_coverage( $p, $lang );
			$stats_map[ (int) $p->ID ] = $stats;
			if ( ! $this->matches_coverage_filter( $stats, $filter ) ) {
				continue;
			}
			$rows[] = $p;
		}

		$total           = count( $rows );
		$pages           = max( 1, (int) ceil( $total / $per ) );
		$page            = min( $paged, $pages );
		$posts           = array_slice( $rows, ( $page - 1 ) * $per, $per );
		$languages       = Settings::available_languages();
		$detail          = false;
		$coverage_filter = $filter;

		include BT_PLUGIN_DIR . 'views/admin-review-by-post.php';
	}

	/**
	 * Detail review for one post.
	 *
	 * @param int $post_id Post ID.
	 */
	private function render_detail( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Post not found.', 'budget-translator' ) . '</p></div></div>';
			return;
		}

		$status = array_key_exists( 'bt_status', $_GET ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( (string) wp_unslash( $_GET['bt_status'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: 'needs_work';
		$lang = isset( $_GET['bt_lang'] ) ? sanitize_key( (string) wp_unslash( $_GET['bt_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed = array( 'needs_work', 'pending', 'auto', 'edited', 'confirmed', 'all' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'needs_work';
		}

		$targets = Settings::target_langs();
		if ( '' === $lang && array() !== $targets ) {
			$lang = $targets[0];
		}

		$source_lang     = Settings::source_lang();
		$sources         = $this->extract_post_sources( $post );
		$repo            = new TranslationRepository();
		$service         = new TranslationService();
		$auto_translated = 0;
		$auto_remaining  = 0;
		$auto_error      = '';

		// One whole-cache hygiene pass (dedupe + junk purge + passthrough confirm).
		$service->cleanup_review_cache();

		// Opening the post should translate missing texts — no extra click required.
		$missing_now = $this->missing_sources( $sources, $source_lang, $lang, $repo );
		if ( array() !== $missing_now && '' !== $lang ) {
			$filled          = $this->fill_missing_translations( $post, $lang );
			$auto_translated = $filled['translated'];
			$auto_remaining  = $filled['remaining'];
			$auto_error      = $filled['error'];
		}

		$all_cached = $repo->find_for_sources( $sources, $source_lang, $lang, '' );
		$coverage   = $this->coverage_from_rows( $sources, $all_cached, $source_lang, $lang, $repo );

		$by_hash = array();
		foreach ( $all_cached as $row ) {
			$by_hash[ (string) $row->hash ] = $row;
		}

		$missing = array();
		$items   = array();
		foreach ( $sources as $source ) {
			$hash = $repo->hash( $source_lang, $lang, $source );
			$row  = $by_hash[ $hash ] ?? null;
			if ( null === $row ) {
				$missing[] = $source;
				$pseudo    = (object) array(
					'id'              => 0,
					'source_text'     => $source,
					'translated_text' => '',
					'target_lang'     => $lang,
					'status'          => 'missing',
					'hash'            => $hash,
				);
				if ( in_array( $status, array( 'needs_work', 'all' ), true ) ) {
					$items[] = $pseudo;
				}
				continue;
			}

			$row_status = (string) $row->status;
			$include    = match ( $status ) {
				'needs_work' => in_array( $row_status, array( 'auto', 'edited' ), true ),
				'pending'    => in_array( $row_status, array( 'auto', 'edited' ), true ),
				'auto', 'edited', 'confirmed' => $row_status === $status,
				'all'        => true,
				default      => false,
			};
			if ( $include ) {
				$items[] = $row;
			}
		}

		// Shared polish with global Bulk list.
		if ( array() !== $items ) {
			$items = $service->heal_auto_rows( $items );
			$items = $service->dedupe_review_rows( $items );
		}

		$languages       = Settings::available_languages();
		$job             = TranslateJob::get_status();
		$result          = array(
			'items' => $items,
			'total' => count( $items ),
			'pages' => 1,
		);
		$page            = 1;
		$search          = '';
		$detail          = true;
		$posts           = array();
		$pages           = 1;
		$total           = count( $items );
		$stats_map       = array( $post_id => $coverage );
		$coverage_filter = 'all';

		include BT_PLUGIN_DIR . 'views/admin-review-by-post.php';
	}

	/**
	 * Machine-translate all missing segments for a post/language (admin, sync).
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $lang Target language.
	 * @return array{translated:int,remaining:int,error:string}
	 */
	private function fill_missing_translations( \WP_Post $post, string $lang ): array {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$source_lang = Settings::source_lang();
		$sources     = $this->extract_post_sources( $post );
		$repo        = new TranslationRepository();
		$service     = new TranslationService();
		$missing     = $this->missing_sources( $sources, $source_lang, $lang, $repo );

		if ( array() === $missing ) {
			return array(
				'translated' => 0,
				'remaining'  => 0,
				'error'      => '',
			);
		}

		TranslationService::consume_last_errors();
		$service->resolve_segments( $missing, $source_lang, $lang, true );

		$leftover   = $this->missing_sources( $sources, $source_lang, $lang, $repo );
		$errors     = TranslationService::consume_last_errors();
		$remaining  = count( $leftover );
		$translated = max( 0, count( $missing ) - $remaining );

		if ( array() !== $leftover ) {
			$service->resolve_segments( $leftover, $source_lang, $lang, true );
			$errors     = array_merge( $errors, TranslationService::consume_last_errors() );
			$leftover   = $this->missing_sources( $sources, $source_lang, $lang, $repo );
			$remaining  = count( $leftover );
			$translated = max( 0, count( $missing ) - $remaining );
		}

		$error_msg = '';
		if ( array() !== $leftover && array() !== $errors ) {
			$error_msg = $errors[0];
		} elseif ( array() !== $leftover ) {
			$error_msg = __( 'Translation provider did not return a result for every text.', 'budget-translator' );
		}

		return array(
			'translated' => $translated,
			'remaining'  => $remaining,
			'error'      => $error_msg,
		);
	}

	/**
	 * Coverage stats for a post in one target language.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $lang Target language.
	 * @return array{segments:int,cached:int,confirmed:int,pending:int,cached_pct:int,confirmed_pct:int}
	 */
	private function post_coverage( \WP_Post $post, string $lang ): array {
		$sources     = $this->extract_post_sources( $post );
		$repo        = new TranslationRepository();
		$source_lang = Settings::source_lang();
		$rows        = $repo->find_for_sources( $sources, $source_lang, $lang, '' );
		return $this->coverage_from_rows( $sources, $rows, $source_lang, $lang, $repo );
	}

	/**
	 * Build coverage array from sources + cached rows.
	 *
	 * @param list<string>          $sources     Sources.
	 * @param list<object>          $rows        Cached rows for the target language.
	 * @param string                $source_lang Source language.
	 * @param string                $target_lang Target language.
	 * @param TranslationRepository $repo        Repository.
	 * @return array{segments:int,cached:int,confirmed:int,pending:int,cached_pct:int,confirmed_pct:int}
	 */
	private function coverage_from_rows( array $sources, array $rows, string $source_lang, string $target_lang, TranslationRepository $repo ): array {
		$segments  = count( $sources );
		$cached    = 0;
		$confirmed = 0;
		$pending   = 0;

		$by_hash = array();
		foreach ( $rows as $row ) {
			$by_hash[ (string) $row->hash ] = $row;
		}

		foreach ( $sources as $source ) {
			$hash = $repo->hash( $source_lang, $target_lang, $source );
			if ( ! isset( $by_hash[ $hash ] ) ) {
				++$pending; // Not translated yet — still needs work.
				continue;
			}
			++$cached;
			$status = (string) $by_hash[ $hash ]->status;
			if ( 'confirmed' === $status ) {
				++$confirmed;
			} elseif ( in_array( $status, array( 'auto', 'edited' ), true ) ) {
				++$pending;
			}
		}

		return array(
			'segments'      => $segments,
			'cached'        => $cached,
			'confirmed'     => $confirmed,
			'pending'       => $pending,
			'cached_pct'    => $segments > 0 ? (int) round( ( $cached / $segments ) * 100 ) : 100,
			'confirmed_pct' => $segments > 0 ? (int) round( ( $confirmed / $segments ) * 100 ) : 100,
		);
	}

	/**
	 * Whether stats match the list coverage filter.
	 *
	 * @param array{segments:int,cached:int,confirmed:int,pending:int,cached_pct:int,confirmed_pct:int} $stats Stats.
	 * @param string                                                                                      $filter Filter key.
	 */
	private function matches_coverage_filter( array $stats, string $filter ): bool {
		$segments  = (int) $stats['segments'];
		$cached    = (int) $stats['cached'];
		$pending   = (int) $stats['pending'];
		$confirmed = (int) $stats['confirmed'];

		return match ( $filter ) {
			'none'            => $cached === 0 && $segments > 0,
			'partial'         => $cached > 0 && $cached < $segments,
			'complete'        => $segments > 0 && $cached >= $segments,
			'needs_review'    => $pending > 0,
			'fully_confirmed' => $segments > 0 && $confirmed >= $segments,
			default           => true,
		};
	}

	/**
	 * Extract unique segments from a post (delegates to PostSegments).
	 *
	 * @param \WP_Post $post Post.
	 * @return list<string>
	 */
	private function extract_post_sources( \WP_Post $post ): array {
		return PostSegments::extract( $post );
	}
}
