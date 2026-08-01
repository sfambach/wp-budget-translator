<?php
/**
 * Queue translation when content changes.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;

/**
 * Class ContentChangeListener
 */
final class ContentChangeListener {

	/**
	 * Previous post snapshot before update.
	 *
	 * @var array<int, array{title:string,excerpt:string,content:string}>
	 */
	private array $before = array();

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'pre_post_update', array( $this, 'capture_before' ), 10, 2 );
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( 'wp_update_nav_menu_item', array( $this, 'on_menu_item' ), 20, 3 );
	}

	/**
	 * Remember post fields before they are overwritten.
	 *
	 * @param int                $post_id Post ID.
	 * @param array<string,mixed> $data    Data about to be saved.
	 */
	public function capture_before( int $post_id, array $data ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$this->before[ $post_id ] = array(
			'title'   => (string) $post->post_title,
			'excerpt' => (string) $post->post_excerpt,
			'content' => (string) $post->post_content,
		);
		unset( $data );
	}

	/**
	 * Queue changed post fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @param bool     $update  Whether update.
	 */
	public function on_save_post( int $post_id, $post, bool $update ): void {
		if ( ! Settings::get( 'auto_queue_on_save', 1 ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( Settings::is_excluded_post( $post_id ) ) {
			return;
		}

		if ( in_array( $post->post_type, Settings::excluded_post_types(), true ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		$prev  = $this->before[ $post_id ] ?? null;
		$texts = array();

		if ( ! $update || null === $prev || $prev['title'] !== (string) $post->post_title ) {
			if ( $post->post_title ) {
				$texts[] = $post->post_title;
			}
		}
		if ( ! $update || null === $prev || $prev['excerpt'] !== (string) $post->post_excerpt ) {
			if ( $post->post_excerpt ) {
				$texts[] = $post->post_excerpt;
			}
		}
		if ( ! $update || null === $prev || $prev['content'] !== (string) $post->post_content ) {
			if ( $post->post_content ) {
				$texts[] = $post->post_content;
			}
		}

		// Always refresh SEO meta when present (cheap; meta may change without content change).
		$seo_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		if ( is_string( $seo_title ) && '' !== trim( $seo_title ) ) {
			$texts[] = $seo_title;
		}
		$seo_desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( is_string( $seo_desc ) && '' !== trim( $seo_desc ) ) {
			$texts[] = $seo_desc;
		}

		unset( $this->before[ $post_id ] );
		TranslateJob::enqueue_texts( $texts );
	}

	/**
	 * Queue menu item title changes.
	 *
	 * @param int   $menu_id         Menu ID.
	 * @param int   $menu_item_db_id Item ID.
	 * @param array $args            Args.
	 */
	public function on_menu_item( int $menu_id, int $menu_item_db_id, $args ): void {
		unset( $menu_id, $args );

		if ( ! Settings::get( 'auto_queue_on_save', 1 ) ) {
			return;
		}

		$item = get_post( $menu_item_db_id );
		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return;
		}

		$title = $item->post_title;
		if ( '' === trim( (string) $title ) ) {
			$title = (string) get_post_meta( $menu_item_db_id, '_menu_item_title', true );
		}
		if ( '' === trim( (string) $title ) ) {
			return;
		}

		TranslateJob::enqueue_texts( array( (string) $title ) );
	}
}
