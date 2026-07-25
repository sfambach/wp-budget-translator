<?php
/**
 * Language subdirectory rewrite helpers & SEO tags.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Frontend;

use BudgetTranslator\Settings;

/**
 * Class Rewrites
 */
final class Rewrites {

	/**
	 * Language detector.
	 *
	 * @var LanguageDetector
	 */
	private LanguageDetector $detector;

	/**
	 * Constructor.
	 *
	 * @param LanguageDetector $detector Detector.
	 */
	public function __construct( LanguageDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_action( 'wp_head', array( $this, 'output_hreflang' ), 2 );
		add_filter( 'redirect_canonical', array( $this, 'preserve_lang_canonical' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'prefix_link' ), 20 );
		add_filter( 'post_link', array( $this, 'prefix_link' ), 20 );
		add_filter( 'post_type_link', array( $this, 'prefix_link' ), 20 );
	}

	/**
	 * Add rewrite rules so /en/... is not a 404 before URI strip (backup).
	 */
	public function add_rewrite_rules(): void {
		if ( ! Settings::get( 'enable_rewrites', 1 ) ) {
			return;
		}

		foreach ( Settings::target_langs() as $lang ) {
			add_rewrite_rule(
				'^' . preg_quote( $lang, '#' ) . '/?$',
				'index.php',
				'top'
			);
			add_rewrite_rule(
				'^' . preg_quote( $lang, '#' ) . '/(.+)/?$',
				'index.php?bt_passthrough=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Register query vars.
	 *
	 * @param list<string> $vars Vars.
	 * @return list<string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'bt_passthrough';
		return $vars;
	}

	/**
	 * Flush rewrite rules when flagged.
	 */
	public function maybe_flush(): void {
		if ( ! get_option( 'bt_flush_rewrite' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( 'bt_flush_rewrite' );
	}

	/**
	 * Prefix permalinks while viewing a translated language.
	 *
	 * @param string $url Permalink.
	 */
	public function prefix_link( string $url ): string {
		if ( is_admin() || ! $this->detector->is_translated() ) {
			return $url;
		}

		return $this->detector->url_for_lang( $this->detector->current(), $url );
	}

	/**
	 * Output hreflang tags.
	 */
	public function output_hreflang(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! is_singular() && ! is_front_page() && ! is_home() ) {
			return;
		}

		$source = Settings::source_lang();
		$langs  = Settings::all_langs();

		foreach ( $langs as $lang ) {
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $lang ),
				esc_url( $this->detector->url_for_lang( $lang ) )
			);
		}

		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $this->detector->url_for_lang( $source ) )
		);
	}

	/**
	 * Keep language prefix on canonical redirects.
	 *
	 * @param string|false $redirect  Redirect URL.
	 * @param string       $requested Requested URL.
	 * @return string|false
	 */
	public function preserve_lang_canonical( $redirect, string $requested ) {
		unset( $requested );

		if ( ! $redirect || ! $this->detector->has_prefix() || ! $this->detector->is_translated() ) {
			return $redirect;
		}

		return $this->detector->url_for_lang( $this->detector->current(), $redirect );
	}
}
