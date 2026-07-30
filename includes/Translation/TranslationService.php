<?php
/**
 * Cache-first translation service.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;

/**
 * Class TranslationService
 */
final class TranslationService {

	/**
	 * Max provider API calls during a public frontend page render.
	 * Prevents PHP max_execution_time fatals when many segments are uncached.
	 */
	private const FRONTEND_API_BUDGET = 5;

	/**
	 * Provider calls used in the current PHP request.
	 *
	 * @var int
	 */
	private static int $api_calls_used = 0;

	/**
	 * Repository.
	 *
	 * @var TranslationRepository
	 */
	private TranslationRepository $repository;

	/**
	 * Segment extractor.
	 *
	 * @var SegmentExtractor
	 */
	private SegmentExtractor $extractor;

	/**
	 * Constructor.
	 *
	 * @param TranslationRepository|null $repository Repository.
	 * @param SegmentExtractor|null      $extractor  Extractor.
	 */
	public function __construct( ?TranslationRepository $repository = null, ?SegmentExtractor $extractor = null ) {
		$this->repository = $repository ?? new TranslationRepository();
		$this->extractor  = $extractor ?? new SegmentExtractor();
	}

	/**
	 * Translate full content (HTML or plain) to target language.
	 *
	 * @param string $content     Content.
	 * @param string $target_lang Target language.
	 * @param bool   $fetch_missing Call provider for cache misses.
	 */
	public function translate_content( string $content, string $target_lang, bool $fetch_missing = true ): string {
		$source_lang = Settings::source_lang();
		if ( $target_lang === $source_lang || '' === trim( $content ) ) {
			return $content;
		}

		$segments = $this->extractor->extract( $content );
		if ( array() === $segments ) {
			return $content;
		}

		$map = $this->resolve_segments( $segments, $source_lang, $target_lang, $fetch_missing );
		return $this->extractor->apply( $content, $map );
	}

	/**
	 * Translate a plain string.
	 *
	 * @param string $text        Text.
	 * @param string $target_lang Target language.
	 * @param bool   $fetch_missing Call provider on miss.
	 */
	public function translate_string( string $text, string $target_lang, bool $fetch_missing = true ): string {
		$source_lang = Settings::source_lang();
		if ( $target_lang === $source_lang ) {
			return $text;
		}

		$normalized = $this->extractor->normalize( $text );
		if ( '' === $normalized ) {
			return $text;
		}

		$map = $this->resolve_segments( array( $normalized ), $source_lang, $target_lang, $fetch_missing );
		return $map[ $normalized ] ?? $text;
	}

	/**
	 * Resolve translations for segments (cache + optional provider).
	 *
	 * @param list<string> $segments     Segments.
	 * @param string       $source_lang  Source.
	 * @param string       $target_lang  Target.
	 * @param bool         $fetch_missing Fetch misses.
	 * @return array<string, string>
	 */
	public function resolve_segments( array $segments, string $source_lang, string $target_lang, bool $fetch_missing = true ): array {
		$hashes = array();
		foreach ( $segments as $segment ) {
			$hashes[ $segment ] = $this->repository->hash( $source_lang, $target_lang, $segment );
		}

		$cached = $this->repository->find_by_hashes( array_values( $hashes ) );
		$map    = array();
		$misses = array();

		foreach ( $segments as $segment ) {
			$hash = $hashes[ $segment ];
			if ( LinkGuard::is_protected_segment( $segment ) ) {
				$map[ $segment ] = $segment;
				continue;
			}
			if ( isset( $cached[ $hash ] ) ) {
				$map[ $segment ] = (string) $cached[ $hash ]->translated_text;
				$this->repository->increment_cache_hits();
			} else {
				$misses[] = $segment;
			}
		}

		if ( ! $fetch_missing || array() === $misses ) {
			return $map;
		}

		$provider = ProviderFactory::make();

		foreach ( $misses as $segment ) {
			if ( ! $this->can_fetch_from_provider() ) {
				// Leave untranslated for this request; batch job / next view fills cache.
				continue;
			}

			try {
				++self::$api_calls_used;
				[ $masked, $tokens ] = LinkGuard::mask( $segment );
				$translated            = $provider->translate( $masked, $source_lang, $target_lang );
				$translated            = LinkGuard::unmask( $translated, $tokens );
				$this->repository->upsert( $source_lang, $target_lang, $segment, $translated, $provider->get_slug(), 'auto' );
				$this->repository->increment_api_calls();
				$map[ $segment ] = $translated;
			} catch ( \Throwable $e ) {
				// Keep original on failure; log for admins.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Budget Translator: ' . $e->getMessage() );
				}
			}
		}

		return $map;
	}

	/**
	 * Whether another provider call is allowed in this request.
	 *
	 * Admin, REST (review/batch) and cron have no budget limit.
	 * Public frontend is capped so slow free APIs cannot kill the page.
	 */
	private function can_fetch_from_provider(): bool {
		if ( is_admin() || wp_doing_cron() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return self::$api_calls_used < self::FRONTEND_API_BUDGET;
	}

	/**
	 * Extractor accessor.
	 */
	public function extractor(): SegmentExtractor {
		return $this->extractor;
	}

	/**
	 * Repository accessor.
	 */
	public function repository(): TranslationRepository {
		return $this->repository;
	}
}
