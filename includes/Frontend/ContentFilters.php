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
	}

	/**
	 * Translate post title.
	 *
	 * @param string $title Post title.
	 * @param int    $post_id Post ID.
	 */
	public function filter_title( string $title, $post_id = 0 ): string {
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate post content.
	 *
	 * @param string $content Content.
	 */
	public function filter_content( string $content ): string {
		return $this->maybe_translate( $content );
	}

	/**
	 * Translate excerpt.
	 *
	 * @param string $excerpt Excerpt.
	 */
	public function filter_excerpt( string $excerpt ): string {
		return $this->maybe_translate( $excerpt );
	}

	/**
	 * Translate menu item title.
	 *
	 * @param string   $title Menu title.
	 * @param \WP_Post $item  Menu item.
	 * @param stdClass $args  Args.
	 * @param int      $depth Depth.
	 */
	public function filter_menu_title( string $title, $item, $args, int $depth ): string {
		unset( $item, $args, $depth );
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate widget titles.
	 *
	 * @param string $title Title.
	 */
	public function filter_widget_title( string $title ): string {
		return $this->maybe_translate( $title );
	}

	/**
	 * Translate document title parts.
	 *
	 * @param array<string, string> $parts Parts.
	 * @return array<string, string>
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
	 * Translate when viewing a target language.
	 *
	 * @param string $text Text.
	 */
	private function maybe_translate( string $text ): string {
		if ( is_admin() || ! $this->detector->is_translated() || '' === trim( $text ) ) {
			return $text;
		}

		$fetch = (bool) Settings::get( 'on_the_fly', 1 );
		return $this->service->translate_content( $text, $this->detector->current(), $fetch );
	}
}
