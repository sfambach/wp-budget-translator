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
	 * Provider errors from the current request (cleared on consume).
	 *
	 * @var list<string>
	 */
	private static array $last_errors = array();

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

		// Affix cores for every segment that has edge punctuation.
		$affix_meta  = array();
		$core_hashes = array();
		foreach ( $segments as $segment ) {
			$parts = PunctuationAffix::split( $segment );
			if ( null === $parts ) {
				continue;
			}
			$core_hash = $this->repository->hash( $source_lang, $target_lang, $parts['core'] );
			$affix_meta[ $segment ] = array(
				'parts' => $parts,
				'hash'  => $core_hash,
			);
			$core_hashes[] = $core_hash;
		}
		$core_cached = $this->repository->find_by_hashes( $core_hashes );

		foreach ( $segments as $segment ) {
			$hash = $hashes[ $segment ];
			if ( LinkGuard::is_protected_segment( $segment ) || ShortcodeGuard::is_protected_segment( $segment ) || TermGuard::is_do_not_translate( $segment ) ) {
				$map[ $segment ] = $segment;
				continue;
			}

			$exact = $cached[ $hash ] ?? null;

			// Technical codes (R1,R2 / v2.1) or already-target language: confirm passthrough, no API.
			$is_technical = SegmentExtractor::looks_like_technical_token( $segment );
			if ( $is_technical || LanguageHint::already_in_target( $segment, $source_lang, $target_lang ) ) {
				$provider = $is_technical
					? 'passthrough'
					: ( ( null !== $exact && ! empty( $exact->provider ) )
						? (string) $exact->provider
						: 'passthrough' );
				$this->persist_confirmed_passthrough(
					$segment,
					$source_lang,
					$target_lang,
					$provider
				);
				$map[ $segment ] = $segment;
				$this->repository->increment_cache_hits();
				continue;
			}

			$exact_ok  = $exact && in_array( (string) $exact->status, array( 'confirmed', 'edited' ), true );
			$affix_hit = $this->affix_from_confirmed( $segment, $affix_meta, $core_cached, $target_lang );

			// Own confirmed/edited row wins (may differ from the bare core).
			if ( $exact_ok ) {
				$map[ $segment ] = (string) $exact->translated_text;
				$this->repository->increment_cache_hits();
				continue;
			}

			// Prefer confirmed/edited core (+ punctuation) over a stale auto exact match.
			if ( null !== $affix_hit ) {
				$map[ $segment ] = $affix_hit['text'];
				$this->repository->upsert(
					$source_lang,
					$target_lang,
					$segment,
					$affix_hit['text'],
					$affix_hit['provider'],
					$affix_hit['status'],
					true
				);
				$this->repository->increment_cache_hits();
				continue;
			}

			if ( $exact ) {
				$text = (string) $exact->translated_text;
				// Identical / already-target auto → confirm passthrough (not pending review).
				if ( 'auto' === (string) $exact->status
					&& $this->auto_confirm_target_passthrough(
						$segment,
						$source_lang,
						$target_lang,
						$text,
						(string) ( $exact->provider ?: 'passthrough' )
					) ) {
					$map[ $segment ] = $segment;
					$this->repository->increment_cache_hits();
					continue;
				}
				// Heal auto rows (punctuation / numbers / placeholder artifacts).
				if ( 'auto' === (string) $exact->status ) {
					$healed = $this->polish_translation( $segment, $text );
					if ( $healed !== $text ) {
						$this->repository->upsert(
							$source_lang,
							$target_lang,
							$segment,
							$healed,
							(string) ( $exact->provider ?: 'guard' ),
							'auto'
						);
						$text = $healed;
					}
				}
				$map[ $segment ] = $text;
				$this->repository->increment_cache_hits();
				continue;
			}

			$glossary = TermGuard::glossary_translation( $segment, $target_lang );
			if ( null !== $glossary ) {
				$map[ $segment ] = $glossary;
				$this->repository->upsert( $source_lang, $target_lang, $segment, $glossary, 'glossary', 'auto' );
				continue;
			}

			$misses[] = $segment;
		}

		if ( ! $fetch_missing || array() === $misses ) {
			return $map;
		}

		$provider = ProviderFactory::make();

		foreach ( $misses as $segment ) {
			if ( ! $this->can_fetch_from_provider() ) {
				$this->mark_partial();
				continue;
			}

			try {
				++self::$api_calls_used;
				$translated = $this->translate_via_provider( $segment, $source_lang, $target_lang, $provider );
				$this->repository->increment_api_calls();
				// Identical cross-language echo → store as confirmed passthrough (not pending auto).
				if ( $this->auto_confirm_target_passthrough(
					$segment,
					$source_lang,
					$target_lang,
					$translated,
					$provider->get_slug()
				) ) {
					$map[ $segment ] = $segment;
					continue;
				}
				$this->repository->upsert( $source_lang, $target_lang, $segment, $translated, $provider->get_slug(), 'auto' );
				$map[ $segment ] = $translated;
			} catch ( \Throwable $e ) {
				self::$last_errors[] = $e->getMessage();
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
	 * Return and clear provider errors from the current request.
	 *
	 * @return list<string>
	 */
	public static function consume_last_errors(): array {
		$errors            = self::$last_errors;
		self::$last_errors = array();
		return $errors;
	}

	/**
	 * Shared post-processing for provider output and cached auto rows.
	 *
	 * Used by frontend resolve, admin review, and by-post — one place for
	 * number/punctuation/placeholder fixes so both UIs stay consistent.
	 *
	 * @param string $source     Source segment.
	 * @param string $translated Raw or cached translation.
	 */
	public function polish_translation( string $source, string $translated ): string {
		if ( TokenPlaceholder::has_artifacts( $translated ) ) {
			$translated = TokenPlaceholder::scrub_artifacts( $translated );
		}
		$translated = NumberGuard::restore_missing( $source, $translated );
		$translated = NumberGuard::cleanup_translation( $translated );
		return PunctuationAffix::align_to_source( $source, $translated );
	}

	/**
	 * Call the provider with all guards (links, shortcodes, terms, numbers).
	 *
	 * @param string                 $source      Source text.
	 * @param string                 $source_lang Source language.
	 * @param string                 $target_lang Target language.
	 * @param Providers\ProviderInterface|null $provider Optional provider instance.
	 */
	public function translate_via_provider( string $source, string $source_lang, string $target_lang, ?Providers\ProviderInterface $provider = null ): string {
		$provider = $provider ?? ProviderFactory::make();

		[ $masked, $link_tokens ] = LinkGuard::mask( $source );
		[ $masked, $sc_tokens ]   = ShortcodeGuard::mask( $masked );
		[ $masked, $term_tokens ] = TermGuard::mask( $masked, $target_lang );
		[ $masked, $num_tokens ]  = NumberGuard::mask( $masked );

		$translated = $provider->translate( $masked, $source_lang, $target_lang );
		$translated = NumberGuard::unmask( $translated, $num_tokens );
		$translated = TermGuard::unmask( $translated, $term_tokens, $target_lang );
		$translated = ShortcodeGuard::unmask( $translated, $sc_tokens );
		$translated = LinkGuard::unmask( $translated, $link_tokens );

		return $this->polish_translation( $source, $translated );
	}

	/**
	 * Store/update a confirmed passthrough row (translation = source).
	 *
	 * @param string $segment     Source segment.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $provider    Provider slug.
	 */
	public function persist_confirmed_passthrough(
		string $segment,
		string $source_lang,
		string $target_lang,
		string $provider = 'passthrough'
	): void {
		$this->repository->upsert(
			$source_lang,
			$target_lang,
			$segment,
			$segment,
			'' !== $provider ? $provider : 'passthrough',
			'confirmed',
			true
		);
	}

	/**
	 * Persist a confirmed passthrough when source is a technical token, already looks like
	 * the target language, or the candidate translation equals the source (API/cache echo).
	 *
	 * Technical tokens heal garbled provider output (e.g. R1,R2 → R1:R2) back to source.
	 *
	 * @param string $segment     Source segment.
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $translated  Candidate translation (must be real cache/API output, not a dummy).
	 * @param string $provider    Provider slug for the row.
	 * @return bool True when confirmed and stored.
	 */
	public function auto_confirm_target_passthrough(
		string $segment,
		string $source_lang,
		string $target_lang,
		string $translated,
		string $provider = 'passthrough'
	): bool {
		if ( $source_lang === $target_lang ) {
			return false;
		}

		$identical = ( $translated === $segment );
		$looks     = LanguageHint::already_in_target( $segment, $source_lang, $target_lang );
		$technical = SegmentExtractor::looks_like_technical_token( $segment );
		if ( ! $identical && ! $looks && ! $technical ) {
			return false;
		}

		// Prefer passthrough provider when healing a garbled technical auto.
		if ( $technical ) {
			$provider = 'passthrough';
		}

		$this->persist_confirmed_passthrough( $segment, $source_lang, $target_lang, $provider );
		return true;
	}

	/**
	 * Upgrade existing auto passthrough / already-target rows to confirmed.
	 * Prefer cleanup_review_cache() on admin review open.
	 *
	 * @return int Rows confirmed.
	 */
	public function confirm_already_target_passthrough_autos(): int {
		return $this->repository->confirm_already_target_passthrough_autos();
	}

	/**
	 * One-shot whole-cache hygiene for admin review screens.
	 *
	 * Order: collation dedupe → invalid API / code-like purge → confirm passthrough autos.
	 * Safe to call multiple times in one request (runs once). Does not replace
	 * per-row heal_auto_rows() / dedupe_review_rows() on the loaded result set.
	 *
	 * @return array{deduped:int,purged:int,confirmed:int}
	 */
	public function cleanup_review_cache(): array {
		static $done = null;
		if ( null !== $done ) {
			return $done;
		}

		$deduped   = $this->repository->dedupe_collation_duplicates();
		$purged    = $this->repository->purge_junk_autos();
		$confirmed = $this->repository->confirm_already_target_passthrough_autos();

		$done = array(
			'deduped'   => $deduped,
			'purged'    => $purged,
			'confirmed' => $confirmed,
		);

		return $done;
	}

	/**
	 * Heal auto rows in-place (persist polished text). Shared by Bulk + By post.
	 * Also auto-confirms already-target / identical passthrough autos.
	 *
	 * @param list<object> $rows Cache rows.
	 * @return list<object> Same rows with updated translated_text / status where changed.
	 */
	public function heal_auto_rows( array $rows ): array {
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) || 'auto' !== (string) ( $row->status ?? '' ) ) {
				continue;
			}
			$source = (string) ( $row->source_text ?? '' );
			$text   = (string) ( $row->translated_text ?? '' );
			if ( '' === $source ) {
				continue;
			}

			$source_lang = (string) ( $row->source_lang ?? '' );
			$target_lang = (string) ( $row->target_lang ?? '' );
			$provider    = (string) ( $row->provider ?: 'passthrough' );

			if ( $this->auto_confirm_target_passthrough( $source, $source_lang, $target_lang, $text, $provider ) ) {
				$row->translated_text = $source;
				$row->status          = 'confirmed';
				continue;
			}

			$healed = $this->polish_translation( $source, $text );
			if ( $healed === $text ) {
				continue;
			}
			$this->repository->upsert(
				$source_lang,
				$target_lang,
				$source,
				$healed,
				(string) ( $row->provider ?: 'guard' ),
				'auto'
			);
			$row->translated_text = $healed;
		}

		return $rows;
	}

	/**
	 * Collapse near-duplicate review rows (e.g. "…" vs "...") to one winner.
	 *
	 * Shared by Bulk + By post. Prefer confirmed/edited, then a real
	 * translation (≠ source), then higher id. Inferior auto siblings are deleted.
	 *
	 * @param list<object> $rows Cache / pseudo rows.
	 * @return list<object>
	 */
	public function dedupe_review_rows( array $rows ): array {
		$groups = array();
		foreach ( $rows as $index => $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$source = (string) ( $row->source_text ?? '' );
			if ( '' === $source ) {
				$key = 'empty:' . $index;
			} else {
				$key = (string) ( $row->source_lang ?? '' ) . "\0"
					. (string) ( $row->target_lang ?? '' ) . "\0"
					. SegmentExtractor::canonical_source( $source );
			}
			$groups[ $key ][] = array(
				'index' => $index,
				'row'   => $row,
			);
		}

		$keep_indexes = array();
		$delete_ids   = array();

		foreach ( $groups as $members ) {
			usort(
				$members,
				function ( array $a, array $b ): int {
					$rank = $this->review_row_rank( $b['row'] ) <=> $this->review_row_rank( $a['row'] );
					if ( 0 !== $rank ) {
						return $rank;
					}
					return (int) ( $b['row']->id ?? 0 ) <=> (int) ( $a['row']->id ?? 0 );
				}
			);
			$winner                    = $members[0];
			$keep_indexes[ $winner['index'] ] = true;
			foreach ( array_slice( $members, 1 ) as $loser ) {
				$id     = (int) ( $loser['row']->id ?? 0 );
				$status = (string) ( $loser['row']->status ?? '' );
				// Only purge machine duplicates; never drop confirmed/edited silently.
				if ( $id > 0 && 'auto' === $status ) {
					$delete_ids[] = $id;
				} elseif ( 0 === $id || 'missing' === $status ) {
					// Pseudo missing row superseded by a cached sibling — drop from list.
					continue;
				} else {
					// Keep edited/confirmed losers visible if somehow grouped (should be rare).
					$keep_indexes[ $loser['index'] ] = true;
				}
			}
		}

		if ( array() !== $delete_ids ) {
			$this->repository->delete_ids( $delete_ids );
		}

		$out = array();
		foreach ( $rows as $index => $row ) {
			if ( isset( $keep_indexes[ $index ] ) ) {
				$out[] = $row;
			}
		}

		return $out;
	}

	/**
	 * Rank a review row for dedupe (higher wins).
	 *
	 * @param object $row Row.
	 */
	private function review_row_rank( object $row ): int {
		$status = (string) ( $row->status ?? '' );
		$score  = match ( $status ) {
			'confirmed' => 400,
			'edited'    => 300,
			'auto'      => 200,
			'missing'   => 0,
			default     => 100,
		};
		$source = (string) ( $row->source_text ?? '' );
		$trans  = (string) ( $row->translated_text ?? '' );
		if ( '' !== $trans && $trans !== $source ) {
			$score += 50;
		}
		// Prefer canonical "..." source over legacy "…" once both exist.
		if ( $source === SegmentExtractor::canonical_source( $source ) ) {
			$score += 10;
		}
		return $score;
	}

	/**
	 * Build translation from a confirmed/edited core plus edge punctuation.
	 *
	 * @param string                         $segment     Full segment.
	 * @param array<string, array<string, mixed>> $affix_meta Affix metadata.
	 * @param array<string, object>          $core_cached Core rows by hash.
	 * @param string                         $target_lang Target language.
	 * @return array{text:string,status:string,provider:string}|null
	 */
	private function affix_from_confirmed( string $segment, array $affix_meta, array $core_cached, string $target_lang ): ?array {
		if ( ! isset( $affix_meta[ $segment ] ) ) {
			return null;
		}

		$meta  = $affix_meta[ $segment ];
		$parts = $meta['parts'];
		$row   = $core_cached[ $meta['hash'] ] ?? null;

		if ( $row && in_array( (string) $row->status, array( 'confirmed', 'edited' ), true ) ) {
			return array(
				'text'     => PunctuationAffix::join( $parts['prefix'], (string) $row->translated_text, $parts['suffix'] ),
				'status'   => (string) $row->status,
				'provider' => (string) ( $row->provider ?: 'affix' ),
			);
		}

		$core_glossary = TermGuard::glossary_translation( $parts['core'], $target_lang );
		if ( null !== $core_glossary ) {
			return array(
				'text'     => PunctuationAffix::join( $parts['prefix'], $core_glossary, $parts['suffix'] ),
				'status'   => 'confirmed',
				'provider' => 'glossary',
			);
		}

		return null;
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
	 * Mark that this frontend request left some segments untranslated due to budget.
	 */
	private function mark_partial(): void {
		if ( is_admin() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		$GLOBALS['bt_partial_translation'] = true;
	}

	/**
	 * Whether the current request had incomplete on-the-fly translation.
	 */
	public static function had_partial_translation(): bool {
		return ! empty( $GLOBALS['bt_partial_translation'] );
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
