<?php
/**
 * Filter content through the translation service.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Frontend;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\TranslationService;

/**
 * Class ContentFilters
 */
final class ContentFilters {

	/**
	 * Detector.
	 *
	 * @var LanguageDetector
	 */
	private LanguageDetector $detector;

	/**
	 * Translation service.
	 *
	 * @var TranslationService
	 */
	private TranslationService $service;

	/**
	 * Constructor.
	 *
	 * @param LanguageDetector $detector Detector.
	 */
	public function __construct( LanguageDetector $detector ) {
		$this->detector = $detector;
		$this->service  = new TranslationService();
	}

	/**
	 * Register content filters.
	 */
	public function register(): void {
		add_filter( 'the_title', array( $this, 'filter_title' ), 20, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'the_excerpt', array( $this, 'filter_excerpt' ), 20 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 20 );
		add_filter( 'nav_menu_item_title', array( $this, 'filter_menu_title' ), 20, 4 );
		add_filter( 'widget_title', array( $this, 'filter_widget_title' ), 20 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ), 20 );
		add_filter( 'wpseo_title', array( $this, 'filter_seo_string' ), 20 );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_seo_string' ), 20 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'filter_image_attributes' ), 20, 2 );
		add_action( 'wp_footer', array( $this, 'maybe_partial_notice' ), 99 );
	}

	/**
	 * Translate post title.
	 *
	 * @param mixed $title   Post title.
	 * @param mixed $post_id Post ID.
	 */
	public function filter_title( mixed $title, mixed $post_id = 0 ): string {
		if ( $post_id && Settings::is_excluded_post( (int) $post_id ) ) {
			return is_string( $title ) ? $title : ( null === $title ? '' : (string) $title );
		}
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate post content.
	 *
	 * @param mixed $content Content.
	 */
	public function filter_content( mixed $content ): string {
		if ( in_the_loop() && Settings::is_excluded_post( (int) get_the_ID() ) ) {
			return is_string( $content ) ? $content : ( null === $content ? '' : (string) $content );
		}
		return $this->maybe_translate( $content );
	}

	/**
	 * Translate excerpt.
	 *
	 * @param mixed $excerpt Excerpt (WP may pass null).
	 */
	public function filter_excerpt( mixed $excerpt ): string {
		if ( in_the_loop() && Settings::is_excluded_post( (int) get_the_ID() ) ) {
			return is_string( $excerpt ) ? $excerpt : ( null === $excerpt ? '' : (string) $excerpt );
		}
		return $this->maybe_translate( $excerpt );
	}

	/**
	 * Translate menu item title.
	 *
	 * @param mixed $title Menu title.
	 * @param mixed $item  Menu item.
	 * @param mixed $args  Args.
	 * @param mixed $depth Depth.
	 */
	public function filter_menu_title( mixed $title, mixed $item = null, mixed $args = null, mixed $depth = 0 ): string {
		unset( $item, $args, $depth );
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate widget titles.
	 *
	 * @param mixed $title Title.
	 */
	public function filter_widget_title( mixed $title ): string {
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate document title parts.
	 *
	 * @param array<string, mixed> $parts Parts.
	 * @return array<string, mixed>
	 */
	public function filter_document_title( array $parts ): array {
		foreach ( $parts as $key => $value ) {
			if ( is_string( $value ) && '' !== $value && 'site' !== $key ) {
				$parts[ $key ] = $this->maybe_translate( $value );
			}
		}
		return $parts;
	}

	/**
	 * Translate Yoast SEO strings.
	 *
	 * @param mixed $text Text.
	 */
	public function filter_seo_string( mixed $text ): string {
		return $this->maybe_translate( $text );
	}

	/**
	 * Translate image alt attributes.
	 *
	 * @param array<string, mixed> $attr       Attributes.
	 * @param \WP_Post             $attachment Attachment.
	 * @return array<string, mixed>
	 */
	public function filter_image_attributes( array $attr, $attachment ): array {
		if ( empty( $attr['alt'] ) || ! is_string( $attr['alt'] ) ) {
			return $attr;
		}
		if ( $attachment instanceof \WP_Post && Settings::is_excluded_post( (int) $attachment->ID ) ) {
			return $attr;
		}
		$attr['alt'] = $this->maybe_translate( $attr['alt'] );
		return $attr;
	}

	/**
	 * Admin / optional public notice when on-the-fly budget skipped segments.
	 */
	public function maybe_partial_notice(): void {
		if ( ! TranslationService::had_partial_translation() ) {
			return;
		}

		$show_public = (bool) Settings::get( 'show_partial_notice', 0 );
		$is_admin    = current_user_can( 'manage_options' );
		if ( ! $is_admin && ! $show_public ) {
			return;
		}

		$message = __( 'Budget Translator: some passages are still in the source language (on-the-fly limit). Run “Translate website now” or reload later.', 'budget-translator' );
		printf(
			'<div class="bt-partial-notice" role="status" style="position:fixed;bottom:12px;right:12px;z-index:99999;max-width:320px;padding:10px 12px;background:#fff8e5;border:1px solid #dba617;border-radius:4px;font:13px/1.4 sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.12);">%s</div>',
			esc_html( $message )
		);
	}

	/**
	 * Translate when viewing a target language on the public frontend.
	 *
	 * @param mixed $text Text.
	 */
	private function maybe_translate( mixed $text ): string {
		$text = is_string( $text ) ? $text : ( null === $text ? '' : (string) $text );

		// Admin, editor REST preloads, and cron must never translate.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_cron() ) {
			return $text;
		}

		if ( Settings::is_excluded_request() ) {
			return $text;
		}

		if ( ! $this->detector->is_translated() || '' === trim( $text ) ) {
			return $text;
		}

		$fetch = (bool) Settings::get( 'on_the_fly', 1 );
		return $this->service->translate_content( $text, $this->detector->current(), $fetch );
	}
}
