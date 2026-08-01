<?php
/**
 * Collect and extract translatable segments from a post/page.
 *
 * Shared by By-post review and (optionally) queue jobs — one extraction path.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class PostSegments
 */
final class PostSegments {

	/**
	 * Raw field texts (title, excerpt, content, SEO).
	 *
	 * @param \WP_Post $post Post.
	 * @return list<string>
	 */
	public static function collect_texts( \WP_Post $post ): array {
		$texts = array();
		if ( $post->post_title ) {
			$texts[] = $post->post_title;
		}
		if ( $post->post_excerpt ) {
			$texts[] = $post->post_excerpt;
		}
		if ( $post->post_content ) {
			$texts[] = $post->post_content;
		}
		$seo_title = get_post_meta( $post->ID, '_yoast_wpseo_title', true );
		if ( is_string( $seo_title ) && '' !== trim( $seo_title ) ) {
			$texts[] = $seo_title;
		}
		$seo_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( is_string( $seo_desc ) && '' !== trim( $seo_desc ) ) {
			$texts[] = $seo_desc;
		}
		return $texts;
	}

	/**
	 * Unique segments from each field separately (avoids title+HTML phantom blobs).
	 *
	 * @param \WP_Post $post Post.
	 * @return list<string>
	 */
	public static function extract( \WP_Post $post ): array {
		$extractor = new SegmentExtractor();
		$unique    = array();

		foreach ( self::collect_texts( $post ) as $text ) {
			foreach ( $extractor->extract( (string) $text ) as $segment ) {
				$unique[ $segment ] = true;
			}
		}

		return array_keys( $unique );
	}
}
