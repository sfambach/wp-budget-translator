<?php
/**
 * Background translation job / queue.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;

/**
 * Class TranslateJob
 */
final class TranslateJob {

	public const STATUS_OPTION = 'bt_job_status';
	public const QUEUE_OPTION  = 'bt_job_queue';

	/**
	 * Queue all site content for translation into all target languages.
	 */
	public static function queue_site(): void {
		$items = self::collect_content_items();
		$langs = Settings::target_langs();
		$queue = array();

		foreach ( $langs as $lang ) {
			foreach ( $items as $item ) {
				$queue[] = array(
					'type' => $item['type'],
					'id'   => $item['id'],
					'lang' => $lang,
					'text' => $item['text'],
				);
			}
		}

		update_option(
			self::STATUS_OPTION,
			array(
				'state'     => 'running',
				'total'     => count( $queue ),
				'done'      => 0,
				'errors'    => 0,
				'started'   => time(),
				'finished'  => 0,
				'message'   => '',
			),
			false
		);

		update_option( self::QUEUE_OPTION, $queue, false );

		if ( ! wp_next_scheduled( 'bt_process_translation_queue' ) ) {
			wp_schedule_event( time(), 'bt_every_minute', 'bt_process_translation_queue' );
		}

		// Process a first chunk immediately.
		self::process_queue();
	}

	/**
	 * Process a chunk of the queue.
	 */
	public static function process_queue(): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( ! is_array( $queue ) || array() === $queue ) {
			$status = self::get_status();
			if ( 'running' === ( $status['state'] ?? '' ) ) {
				$status['state']    = 'done';
				$status['finished'] = time();
				update_option( self::STATUS_OPTION, $status, false );
			}
			return;
		}

		$service = new TranslationService();
		$chunk   = array_splice( $queue, 0, 15 );
		$status  = self::get_status();
		$errors  = (int) ( $status['errors'] ?? 0 );
		$done    = (int) ( $status['done'] ?? 0 );

		foreach ( $chunk as $item ) {
			$text = (string) ( $item['text'] ?? '' );
			$lang = (string) ( $item['lang'] ?? '' );
			if ( '' === $text || '' === $lang ) {
				++$done;
				continue;
			}

			try {
				$service->translate_content( $text, $lang, true );
			} catch ( \Throwable $e ) {
				++$errors;
			}
			++$done;
		}

		update_option( self::QUEUE_OPTION, $queue, false );

		$status['done']   = $done;
		$status['errors'] = $errors;
		$status['state']  = array() === $queue ? 'done' : 'running';
		if ( 'done' === $status['state'] ) {
			$status['finished'] = time();
		}
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Current job status.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_status(): array {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Collect translatable site strings.
	 *
	 * @return list<array{type:string,id:int,text:string}>
	 */
	private static function collect_content_items(): array {
		$items = array();

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			if ( $post->post_title ) {
				$items[] = array(
					'type' => 'post_title',
					'id'   => (int) $post->ID,
					'text' => $post->post_title,
				);
			}
			if ( $post->post_excerpt ) {
				$items[] = array(
					'type' => 'post_excerpt',
					'id'   => (int) $post->ID,
					'text' => $post->post_excerpt,
				);
			}
			if ( $post->post_content ) {
				$items[] = array(
					'type' => 'post_content',
					'id'   => (int) $post->ID,
					'text' => $post->post_content,
				);
			}
		}

		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu ) {
			$menu_items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $menu_items ) {
				continue;
			}
			foreach ( $menu_items as $menu_item ) {
				if ( ! empty( $menu_item->title ) ) {
					$items[] = array(
						'type' => 'menu_item',
						'id'   => (int) $menu_item->ID,
						'text' => $menu_item->title,
					);
				}
			}
		}

		return $items;
	}
}
