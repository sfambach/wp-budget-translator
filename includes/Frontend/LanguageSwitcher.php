<?php
/**
 * Frontend language switcher.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Frontend;

use BudgetTranslator\Settings;

/**
 * Class LanguageSwitcher
 */
final class LanguageSwitcher {

	/**
	 * Detector.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_footer_switcher' ), 20 );
		add_shortcode( 'bt_language_switcher', array( $this, 'shortcode' ) );
		add_filter( 'wp_nav_menu_items', array( $this, 'append_to_menu' ), 20, 2 );
	}

	/**
	 * Frontend assets.
	 */
	public function enqueue(): void {
		if ( ! Settings::get( 'language_switcher', 1 ) ) {
			return;
		}

		wp_enqueue_style(
			'bt-frontend',
			BT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BT_VERSION
		);
	}

	/**
	 * Shortcode output.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function shortcode( $atts = array() ): string {
		return $this->render();
	}

	/**
	 * Footer switcher when enabled.
	 */
	public function render_footer_switcher(): void {
		if ( ! Settings::get( 'language_switcher', 1 ) || is_admin() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside render.
		echo $this->render( 'bt-lang-switcher--footer' );
	}

	/**
	 * Optionally append switcher links to primary menu.
	 *
	 * @param string   $items Menu HTML.
	 * @param \stdClass $args Menu args.
	 */
	public function append_to_menu( string $items, $args ): string {
		if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
			return $items;
		}

		if ( ! Settings::get( 'language_switcher', 1 ) ) {
			return $items;
		}

		$languages = Settings::available_languages();
		$current   = $this->detector->current();

		foreach ( Settings::all_langs() as $code ) {
			$url   = esc_url( $this->detector->url_for_lang( $code ) );
			$label = esc_html( $languages[ $code ] ?? strtoupper( $code ) );
			$class = $code === $current ? ' current-lang' : '';
			$items .= sprintf(
				'<li class="menu-item bt-menu-lang%s"><a href="%s" hreflang="%s" lang="%s">%s</a></li>',
				esc_attr( $class ),
				$url,
				esc_attr( $code ),
				esc_attr( $code ),
				$label
			);
		}

		return $items;
	}

	/**
	 * Render switcher markup.
	 *
	 * @param string $extra_class Extra CSS class.
	 */
	public function render( string $extra_class = '' ): string {
		$languages = Settings::available_languages();
		$current   = $this->detector->current();
		$class     = trim( 'bt-lang-switcher ' . $extra_class );

		$html = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Language', 'budget-translator' ) . '"><ul>';

		foreach ( Settings::all_langs() as $code ) {
			$url      = esc_url( $this->detector->url_for_lang( $code ) );
			$label    = esc_html( $languages[ $code ] ?? strtoupper( $code ) );
			$active   = $code === $current ? ' is-active' : '';
			$html    .= sprintf(
				'<li class="%s"><a href="%s" hreflang="%s" lang="%s"%s>%s</a></li>',
				esc_attr( 'bt-lang-switcher__item' . $active ),
				$url,
				esc_attr( $code ),
				esc_attr( $code ),
				$code === $current ? ' aria-current="page"' : '',
				$label
			);

			// Persist choice.
			if ( $code === $current && $code !== Settings::source_lang() ) {
				// Cookie set via headers would be better; skip here.
			}
		}

		$html .= '</ul></nav>';

		// Set cookie for fallback detection when user switches.
		if ( ! headers_sent() && $current !== Settings::source_lang() ) {
			setcookie( 'bt_lang', $current, time() + MONTH_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		return $html;
	}
}
