<?php
/**
 * Main plugin bootstrap.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator;

use BudgetTranslator\Admin\Assets;
use BudgetTranslator\Admin\ReviewPage;
use BudgetTranslator\Admin\SettingsPage;
use BudgetTranslator\Frontend\ContentFilters;
use BudgetTranslator\Frontend\LanguageDetector;
use BudgetTranslator\Frontend\LanguageSwitcher;
use BudgetTranslator\Frontend\Rewrites;
use BudgetTranslator\Rest\ReviewController;
use BudgetTranslator\Rest\TranslateJobController;
use BudgetTranslator\Translation\TranslateJob;

/**
 * Class Plugin
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Language detector.
	 *
	 * @var LanguageDetector|null
	 */
	private ?LanguageDetector $detector = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot hooks.
	 */
	public function boot(): void {
		// Strip language prefix as early as possible so WP routing stays normal.
		$this->detector = new LanguageDetector();
		$this->detector->detect_and_strip();

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
	}

	/**
	 * Initialize components.
	 */
	public function init(): void {
		load_plugin_textdomain( 'budget-translator', false, dirname( BT_PLUGIN_BASENAME ) . '/languages' );

		if ( get_option( 'bt_db_version' ) !== Activator::DB_VERSION ) {
			Activator::create_tables();
			Activator::ensure_defaults();
			update_option( 'bt_db_version', Activator::DB_VERSION );
		}

		$detector = $this->detector ?? new LanguageDetector();
		$detector->detect();

		( new Rewrites( $detector ) )->register();
		( new ContentFilters( $detector ) )->register();
		( new LanguageSwitcher( $detector ) )->register();

		if ( is_admin() ) {
			( new Assets() )->register();
			( new SettingsPage() )->register();
			( new ReviewPage() )->register();
		}

		( new ReviewController() )->register();
		( new TranslateJobController() )->register();

		add_action( 'bt_process_translation_queue', array( TranslateJob::class, 'process_queue' ) );
	}

	/**
	 * Custom cron interval for queue processing.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_cron_schedules( array $schedules ): array {
		$schedules['bt_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute (Budget Translator)', 'budget-translator' ),
		);

		return $schedules;
	}

	/**
	 * Shared detector instance.
	 */
	public function detector(): ?LanguageDetector {
		return $this->detector;
	}
}
