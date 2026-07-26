<?php
/**
 * Propagate corrected source segments into site content.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class SourcePropagator
 */
final class SourcePropagator {

	/**
	 * Replace an exact source segment in posts, pages and menu labels.
	 *
	 * @param string $old_source Original segment.
	 * @param string $new_source Corrected segment.
	 * @return array{posts:int,menus:int}
	 */
	public function replace( string $old_source, string $new_source ): array {
		$counts = array(
			'posts' => 0,
			'menus' => 0,
		);

		if ( '' === $old_source || $old_source === $new_source ) {
			return $counts;
		}

		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$changed = false;
			$title   = $post->post_title;
			$excerpt = $post->post_excerpt;
			$content = $post->post_content;

			if ( str_contains( $title, $old_source ) ) {
				$title   = str_replace( $old_source, $new_source, $title );
				$changed = true;
			}
			if ( str_contains( $excerpt, $old_source ) ) {
				$excerpt = str_replace( $old_source, $new_source, $excerpt );
				$changed = true;
			}
			if ( str_contains( $content, $old_source ) ) {
				$content = str_replace( $old_source, $new_source, $content );
				$changed = true;
			}

			if ( $changed ) {
				wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_title'   => $title,
						'post_excerpt' => $excerpt,
						'post_content' => $content,
					)
				);
				++$counts['posts'];
			}
		}

		$menus = wp_get_nav_menus();
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			if ( ! $items ) {
				continue;
			}
			foreach ( $items as $item ) {
				if ( empty( $item->title ) || ! str_contains( (string) $item->title, $old_source ) ) {
					continue;
				}
				wp_update_nav_menu_item(
					(int) $menu->term_id,
					(int) $item->ID,
					array(
						'menu-item-title'  => str_replace( $old_source, $new_source, (string) $item->title ),
						'menu-item-object' => $item->object,
						'menu-item-object-id' => $item->object_id,
						'menu-item-parent-id' => $item->menu_item_parent,
						'menu-item-type'   => $item->type,
						'menu-item-status' => 'publish',
						'menu-item-url'    => $item->url,
					)
				);
				++$counts['menus'];
			}
		}

		return $counts;
	}
}
