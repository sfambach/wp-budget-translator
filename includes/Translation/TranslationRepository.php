<?php
/**
 * Translations table repository.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

/**
 * Class TranslationRepository
 */
final class TranslationRepository {

	/**
	 * Table name with prefix.
	 */
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bt_translations';
	}

	/**
	 * Build cache hash.
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $source_text Source segment.
	 */
	public function hash( string $source_lang, string $target_lang, string $source_text ): string {
		return hash( 'sha256', $source_lang . "\0" . $target_lang . "\0" . $source_text );
	}

	/**
	 * Find by hash.
	 *
	 * @param string $hash Segment hash.
	 * @return object|null
	 */
	public function find_by_hash( string $hash ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE hash = %s LIMIT 1',
				$hash
			)
		);

		return $row ?: null;
	}

	/**
	 * Find many hashes at once.
	 *
	 * @param list<string> $hashes Hashes.
	 * @return array<string, object> Hash => row.
	 */
	public function find_by_hashes( array $hashes ): array {
		global $wpdb;

		$hashes = array_values( array_unique( array_filter( $hashes ) ) );
		if ( array() === $hashes ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
		$sql          = 'SELECT * FROM ' . $this->table() . " WHERE hash IN ({$placeholders})";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$hashes ) );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->hash ] = $row;
		}

		return $map;
	}

	/**
	 * Insert or update an auto translation (never overwrite confirmed/edited unless forced).
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $source_text Source text.
	 * @param string $translated  Translated text.
	 * @param string $provider    Provider slug.
	 * @param string $status      Status.
	 * @param bool   $force       Force overwrite confirmed/edited.
	 * @return int Row ID.
	 */
	public function upsert(
		string $source_lang,
		string $target_lang,
		string $source_text,
		string $translated,
		string $provider,
		string $status = 'auto',
		bool $force = false
	): int {
		global $wpdb;

		$hash     = $this->hash( $source_lang, $target_lang, $source_text );
		$existing = $this->find_by_hash( $hash );

		// Collation twin: same langs + source_text under MySQL unicode_ci, different PHP hash
		// (e.g. "..." vs "…", or case-only differences). Merge into one row.
		if ( ! $existing ) {
			$existing = $this->find_collation_match( $source_lang, $target_lang, $source_text );
			if ( $existing ) {
				$this->delete_collation_siblings( $source_lang, $target_lang, $source_text, (int) $existing->id );
			}
		}

		if ( $existing ) {
			if ( ! $force && in_array( $existing->status, array( 'confirmed', 'edited' ), true ) && 'auto' === $status ) {
				// Still canonicalize source/hash when the twin used a different byte form.
				if ( (string) $existing->hash !== $hash || (string) $existing->source_text !== $source_text ) {
					$this->canonicalize_row_source( (int) $existing->id, $source_text, $hash );
				}
				return (int) $existing->id;
			}

			$data = array(
				'source_text'     => $source_text,
				'translated_text' => $translated,
				'status'          => $status,
				'provider'        => $provider,
				'hash'            => $hash,
			);
			$wpdb->update(
				$this->table(),
				$data,
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing->id;
		}

		$wpdb->insert(
			$this->table(),
			array(
				'hash'            => $hash,
				'source_lang'     => $source_lang,
				'target_lang'     => $target_lang,
				'source_text'     => $source_text,
				'translated_text' => $translated,
				'status'          => $status,
				'provider'        => $provider,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a row MySQL considers equal for (langs + source_text), preferring confirmed/edited.
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $source_text Source segment.
	 */
	public function find_collation_match( string $source_lang, string $target_lang, string $source_text ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE source_lang = %s AND target_lang = %s AND source_text = %s
				ORDER BY CASE status WHEN %s THEN 0 WHEN %s THEN 1 ELSE 2 END, id DESC
				LIMIT 1',
				$source_lang,
				$target_lang,
				$source_text,
				'confirmed',
				'edited'
			)
		);

		return $row ?: null;
	}

	/**
	 * Delete other rows that match langs+source under DB collation, keeping one id.
	 *
	 * @param string $source_lang Source language.
	 * @param string $target_lang Target language.
	 * @param string $source_text Source segment.
	 * @param int    $keep_id     Row to keep.
	 */
	public function delete_collation_siblings( string $source_lang, string $target_lang, string $source_text, int $keep_id ): int {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->table() . ' WHERE source_lang = %s AND target_lang = %s AND source_text = %s AND id <> %d',
				$source_lang,
				$target_lang,
				$source_text,
				$keep_id
			)
		);

		return $this->delete_ids( array_map( 'intval', $ids ?: array() ) );
	}

	/**
	 * Rewrite source_text/hash when a collation twin is folded to the canonical form.
	 *
	 * @param int    $id          Row ID.
	 * @param string $source_text Canonical source.
	 * @param string $hash        Canonical hash.
	 */
	private function canonicalize_row_source( int $id, string $source_text, string $hash ): void {
		global $wpdb;

		$conflict = $this->find_by_hash( $hash );
		if ( $conflict && (int) $conflict->id !== $id ) {
			// Canonical hash already owned elsewhere — drop this twin.
			$this->delete_ids( array( $id ) );
			return;
		}

		$wpdb->update(
			$this->table(),
			array(
				'source_text' => $source_text,
				'hash'        => $hash,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Merge rows that MySQL collation treats as the same source (different PHP hashes).
	 *
	 * @return int Number of duplicate rows deleted.
	 */
	public function dedupe_collation_duplicates(): int {
		global $wpdb;

		$table  = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$groups = $wpdb->get_results(
			"SELECT source_lang, target_lang, source_text, COUNT(*) AS c, GROUP_CONCAT(id ORDER BY id) AS ids
			FROM {$table}
			GROUP BY source_lang, target_lang, source_text
			HAVING c > 1"
		);

		if ( ! $groups ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $groups as $group ) {
			$ids = array_values(
				array_filter(
					array_map( 'intval', explode( ',', (string) $group->ids ) )
				)
			);
			if ( count( $ids ) < 2 ) {
				continue;
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $table . " WHERE id IN ({$placeholders})", ...$ids ) );
			if ( ! $rows || count( $rows ) < 2 ) {
				continue;
			}

			$winner = $this->pick_dedupe_winner( $rows );
			$losers = array();
			foreach ( $rows as $row ) {
				if ( (int) $row->id !== (int) $winner->id ) {
					$losers[] = (int) $row->id;
				}
			}

			$deleted += $this->delete_ids( $losers );

			// Fold winner source to the current normalizer so future hashes match.
			$canonical = ( new SegmentExtractor() )->normalize( (string) $winner->source_text );
			if ( '' !== $canonical && $canonical !== (string) $winner->source_text ) {
				$hash = $this->hash( (string) $winner->source_lang, (string) $winner->target_lang, $canonical );
				$this->canonicalize_row_source( (int) $winner->id, $canonical, $hash );
			}
		}

		return $deleted;
	}

	/**
	 * Auto-confirm passthrough autos that are not real cross-language translations.
	 *
	 * Typical case: English left in a DE post (or MyMemory echoed English). The text is
	 * correct for /en/ as-is — mark confirmed so review does not ask for Confirm.
	 * Also heals technical codes garbled by providers (R1,R2 → R1:R2) back to source.
	 *
	 * Rules (no ML):
	 * - auto + source_lang ≠ target_lang + source_text === translated_text → confirmed
	 * - auto + source already looks like the target language → confirmed (translated = source)
	 * - auto + source looks like a technical token → confirmed (translated = source, provider passthrough)
	 *
	 * @return int Rows upgraded to confirmed.
	 */
	public function confirm_already_target_passthrough_autos(): int {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			"SELECT id, source_lang, target_lang, source_text, translated_text, provider
			FROM {$table}
			WHERE status = 'auto'"
		);

		$updated = 0;
		foreach ( $rows ?: array() as $row ) {
			$source_lang = (string) $row->source_lang;
			$target_lang = (string) $row->target_lang;
			if ( $source_lang === $target_lang ) {
				continue;
			}

			$source     = (string) $row->source_text;
			$trans      = (string) $row->translated_text;
			$looks      = LanguageHint::already_in_target( $source, $source_lang, $target_lang );
			$technical  = SegmentExtractor::looks_like_technical_token( $source );

			if ( $source !== $trans && ! $looks && ! $technical ) {
				continue;
			}

			$provider = (string) ( $row->provider ?: 'passthrough' );
			if ( $technical ) {
				$provider = 'passthrough';
			}

			$this->upsert(
				$source_lang,
				$target_lang,
				$source,
				$source,
				$provider,
				'confirmed',
				true
			);
			++$updated;
		}

		return $updated;
	}

	/**
	 * Prefer confirmed/edited and real translations over passthrough auto rows.
	 *
	 * @param list<object> $rows Duplicate group.
	 */
	private function pick_dedupe_winner( array $rows ): object {
		$rank = static function ( object $row ): array {
			$status = (string) $row->status;
			$status_rank = match ( $status ) {
				'confirmed' => 0,
				'edited'    => 1,
				default     => 2,
			};
			$passthrough = ( (string) $row->source_text === (string) $row->translated_text ) ? 1 : 0;
			return array( $status_rank, $passthrough, -(int) $row->id );
		};

		usort(
			$rows,
			static function ( object $a, object $b ) use ( $rank ): int {
				return $rank( $a ) <=> $rank( $b );
			}
		);

		return $rows[0];
	}

	/**
	 * Update translation text and status by ID.
	 *
	 * @param int    $id      Row ID.
	 * @param string $text    New translation.
	 * @param string $status  Status.
	 */
	public function update_translation( int $id, string $text, string $status = 'edited' ): bool {
		$result = $this->update_entry( $id, null, $text, $status );
		return (bool) ( $result['success'] ?? false );
	}

	/**
	 * Update source and/or translation for a row (recomputes hash when source changes).
	 *
	 * @param int         $id               Row ID.
	 * @param string|null $source_text      New source or null to keep.
	 * @param string|null $translated_text  New translation or null to keep.
	 * @param string      $status           Status.
	 * @return array{success:bool,source_changed:bool,old_source:string,new_source:string,message?:string}
	 */
	public function update_entry( int $id, ?string $source_text, ?string $translated_text, string $status = 'edited' ): array {
		global $wpdb;

		$row = $this->find_by_id( $id );
		if ( ! $row ) {
			return array(
				'success'        => false,
				'source_changed' => false,
				'old_source'     => '',
				'new_source'     => '',
				'message'        => 'Not found',
			);
		}

		$old_source = (string) $row->source_text;
		$new_source = null === $source_text ? $old_source : $source_text;
		$new_trans  = null === $translated_text ? (string) $row->translated_text : $translated_text;
		$source_changed = $new_source !== $old_source;
		$new_hash       = $this->hash( (string) $row->source_lang, (string) $row->target_lang, $new_source );

		if ( $source_changed ) {
			$conflict = $this->find_by_hash( $new_hash );
			if ( $conflict && (int) $conflict->id !== $id ) {
				return array(
					'success'        => false,
					'source_changed' => true,
					'old_source'     => $old_source,
					'new_source'     => $new_source,
					'message'        => 'Another translation already exists for this corrected source text.',
				);
			}
		}

		$data = array(
			'source_text'     => $new_source,
			'translated_text' => $new_trans,
			'status'          => $status,
			'hash'            => $new_hash,
		);
		$formats = array( '%s', '%s', '%s', '%s' );

		if ( $source_changed ) {
			$data['previous_source_text'] = $old_source;
			$formats[]                     = '%s';
		}

		$result = $wpdb->update(
			$this->table(),
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return array(
			'success'        => false !== $result,
			'source_changed' => $source_changed,
			'old_source'     => $old_source,
			'new_source'     => $new_source,
		);
	}

	/**
	 * Update source_text/hash on sibling rows (other target languages) with the same old source.
	 *
	 * @param string $old_source Old source.
	 * @param string $new_source New source.
	 * @param int    $except_id  Row already updated.
	 */
	public function sync_source_text( string $old_source, string $new_source, int $except_id = 0 ): int {
		global $wpdb;

		if ( $old_source === $new_source ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, source_lang, target_lang FROM ' . $this->table() . ' WHERE source_text = %s AND id <> %d',
				$old_source,
				$except_id
			)
		);

		$updated = 0;
		foreach ( $rows as $row ) {
			$hash = $this->hash( (string) $row->source_lang, (string) $row->target_lang, $new_source );
			$conflict = $this->find_by_hash( $hash );
			if ( $conflict && (int) $conflict->id !== (int) $row->id ) {
				continue;
			}

			$ok = $wpdb->update(
				$this->table(),
				array(
					'source_text'          => $new_source,
					'hash'                 => $hash,
					'previous_source_text' => $old_source,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false !== $ok ) {
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Confirm one or many IDs.
	 *
	 * @param list<int> $ids IDs.
	 */
	public function confirm_ids( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = 'UPDATE ' . $this->table() . " SET status = 'confirmed', previous_source_text = NULL WHERE id IN ({$placeholders})";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$ids ) );
	}

	/**
	 * Find row by ID.
	 *
	 * @param int $id Row ID.
	 */
	public function find_by_id( int $id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE id = %d LIMIT 1',
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Delete rows by ID.
	 *
	 * @param list<int> $ids IDs.
	 */
	public function delete_ids( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = 'DELETE FROM ' . $this->table() . " WHERE id IN ({$placeholders})";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$ids ) );
	}

	/**
	 * Remove junk cache rows (invalid API payloads + code/console/shortcode segments).
	 * Same criteria as the former Translations purge buttons; run on review open.
	 *
	 * @return int Total rows deleted.
	 */
	public function purge_junk_autos(): int {
		return $this->delete_invalid_api_payloads() + $this->delete_code_like_payloads();
	}

	/**
	 * Delete cached rows that contain known provider error messages.
	 */
	public function delete_invalid_api_payloads(): int {
		global $wpdb;

		$table = $this->table();
		$like_parts = array(
			'%MYMEMORY WARNING%',
			'%PLEASE SELECT TWO-LETTER ISO%',
			'%RFC3066%',
			'%INVALID LANGUAGE PAIR%',
			'%QUERY LENGTH LIMIT%',
			'%YOU USED ALL AVAILABLE FREE TRANSLATIONS%',
		);

		$where = array();
		$params = array();
		foreach ( $like_parts as $like ) {
			$where[]  = 'translated_text LIKE %s';
			$params[] = $like;
		}

		$sql = 'DELETE FROM ' . $table . ' WHERE status = %s AND (' . implode( ' OR ', $where ) . ')';
		array_unshift( $params, 'auto' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$deleted = (int) $wpdb->query( $wpdb->prepare( $sql, ...$params ) );

		return $deleted + $this->delete_trivial_segments();
	}

	/**
	 * Remove short technical auto-segments that do not need review (e.g. 3V).
	 */
	public function delete_trivial_segments(): int {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, source_text, translated_text, status FROM {$table} WHERE status = 'auto'" );
		$ids  = array();

		foreach ( $rows as $row ) {
			$source = (string) $row->source_text;
			if ( LinkGuard::is_protected_segment( $source ) ) {
				$ids[] = (int) $row->id;
				continue;
			}
			if ( preg_match( '/^\d+([.,]\d+)?\s*[A-Za-zΩ°%]{1,4}$/u', $source ) ) {
				$ids[] = (int) $row->id;
				continue;
			}
			if ( mb_strlen( $source ) <= 5 && preg_match( '/^[A-Z0-9][A-Z0-9\-\+]*$/u', $source ) ) {
				$ids[] = (int) $row->id;
			}
		}

		return $this->delete_ids( $ids );
	}

	/**
	 * Delete cached rows whose source looks like code, console prompts, or shortcodes.
	 */
	public function delete_code_like_payloads(): int {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, source_text FROM {$table}" );
		$ids  = array();

		foreach ( $rows as $row ) {
			$source = (string) $row->source_text;
			if (
				SegmentExtractor::looks_like_code( $source )
				|| ShortcodeGuard::is_protected_segment( $source )
				|| SegmentExtractor::looks_like_opaque_id( $source )
			) {
				$ids[] = (int) $row->id;
			}
		}

		return $this->delete_ids( $ids );
	}

	/**
	 * Find rows matching source texts for a target language.
	 *
	 * @param list<string> $sources     Source segments.
	 * @param string       $source_lang Source language.
	 * @param string       $target_lang Target language.
	 * @param string       $status      Optional status filter (pending|auto|edited|confirmed|'').
	 * @return list<object>
	 */
	public function find_for_sources( array $sources, string $source_lang, string $target_lang, string $status = '' ): array {
		$hashes = array();
		foreach ( $sources as $source ) {
			$source = (string) $source;
			if ( '' === trim( $source ) ) {
				continue;
			}
			$hashes[] = $this->hash( $source_lang, $target_lang, $source );
		}

		$map = $this->find_by_hashes( $hashes );
		$out = array_values( $map );

		if ( 'pending' === $status ) {
			$out = array_values(
				array_filter(
					$out,
					static fn( object $row ): bool => in_array( (string) $row->status, array( 'auto', 'edited' ), true )
				)
			);
		} elseif ( in_array( $status, array( 'auto', 'edited', 'confirmed' ), true ) ) {
			$out = array_values(
				array_filter(
					$out,
					static fn( object $row ): bool => (string) $row->status === $status
				)
			);
		}

		usort(
			$out,
			static fn( object $a, object $b ): int => (int) $a->id <=> (int) $b->id
		);

		return $out;
	}

	/**
	 * Allowed review-list sort keys → SQL ORDER BY clause (no user input).
	 *
	 * @return array<string, string>
	 */
	public static function review_order_by_map(): array {
		return array(
			'newest'   => 'updated_at DESC, id DESC',
			'oldest'   => 'updated_at ASC, id ASC',
			'shortest' => 'CHAR_LENGTH(source_text) ASC, id ASC',
			'longest'  => 'CHAR_LENGTH(source_text) DESC, id DESC',
		);
	}

	/**
	 * Resolve a review sort key to a safe ORDER BY clause.
	 *
	 * @param string $sort Sort key (newest|oldest|shortest|longest).
	 */
	public static function review_order_by_sql( string $sort ): string {
		$map = self::review_order_by_map();
		return $map[ $sort ] ?? $map['newest'];
	}

	/**
	 * Query rows for review UI.
	 *
	 * @param array{status?:string,lang?:string,search?:string,sort?:string,page?:int,per?:int} $args Args.
	 * @return array{items: list<object>, total: int, pages: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$status = $args['status'] ?? '';
		$lang   = $args['lang'] ?? '';
		$search = $args['search'] ?? '';
		$sort   = sanitize_key( (string) ( $args['sort'] ?? 'newest' ) );
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per    = max( 1, min( 100, (int) ( $args['per'] ?? 20 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where  = array( '1=1' );
		$params = array();

		if ( $status ) {
			if ( 'pending' === $status ) {
				$where[] = "status IN ('auto','edited')";
			} else {
				$where[]  = 'status = %s';
				$params[] = $status;
			}
		}
		if ( $lang ) {
			$where[]  = 'target_lang = %s';
			$params[] = $lang;
		}
		if ( $search ) {
			$where[]  = '(source_text LIKE %s OR translated_text LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$order_sql = self::review_order_by_sql( $sort );
		$table     = $this->table();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// ORDER BY is from a fixed whitelist (review_order_by_sql), not user input.
		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$items = $wpdb->get_results( $wpdb->prepare( $list_sql, ...$list_params ) );

		return array(
			'items' => $items ?: array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
		);
	}

	/**
	 * Find adjacent pending item (with wrap, never returns the same ID).
	 *
	 * @param int    $current_id Current row ID.
	 * @param string $direction  'prev' or 'next'.
	 * @param string $lang       Optional target language filter.
	 */
	public function find_adjacent_pending( int $current_id, string $direction = 'next', string $lang = '' ): ?object {
		global $wpdb;

		$table  = $this->table();
		$where  = array( "status IN ('auto','edited')" );
		$params = array();

		if ( $lang ) {
			$where[]  = 'target_lang = %s';
			$params[] = $lang;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT id FROM {$table} WHERE {$where_sql} ORDER BY id ASC";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $wpdb->get_col( $sql );
		}

		$ids = array_map( 'intval', $ids ?: array() );
		if ( array() === $ids ) {
			return null;
		}

		// Only one pending item overall.
		if ( 1 === count( $ids ) ) {
			return (int) $ids[0] === $current_id ? null : $this->find_by_id( (int) $ids[0] );
		}

		$index = array_search( $current_id, $ids, true );
		$count = count( $ids );

		if ( false === $index ) {
			// Current not in pending list (e.g. just confirmed): next = first, prev = last.
			$index = 'next' === $direction ? -1 : 0;
		}

		if ( 'prev' === $direction ) {
			$index = ( $index - 1 + $count ) % $count;
		} else {
			$index = ( $index + 1 ) % $count;
		}

		$target_id = (int) $ids[ $index ];
		if ( $target_id === $current_id ) {
			return null;
		}

		return $this->find_by_id( $target_id );
	}

	/**
	 * Find a pending item for single review, optionally after a given ID.
	 *
	 * @param int    $after_id Prefer next ID after this.
	 * @param string $lang     Optional target language filter.
	 */
	public function find_next_pending( int $after_id = 0, string $lang = '' ): ?object {
		if ( $after_id > 0 ) {
			$adjacent = $this->find_adjacent_pending( $after_id, 'next', $lang );
			if ( $adjacent ) {
				return $adjacent;
			}
		}

		global $wpdb;

		$table  = $this->table();
		$where  = array( "status IN ('auto','edited')" );
		$params = array();

		if ( $lang ) {
			$where[]  = 'target_lang = %s';
			$params[] = $lang;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id ASC LIMIT 1";

		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $sql );
		}

		return $row ?: null;
	}

	/**
	 * Find previous pending item before ID (with wrap).
	 *
	 * @param int    $before_id Current ID.
	 * @param string $lang      Optional language.
	 */
	public function find_prev_pending( int $before_id, string $lang = '' ): ?object {
		return $this->find_adjacent_pending( $before_id, 'prev', $lang );
	}

	/**
	 * Count pending items.
	 *
	 * @param string $lang Optional language.
	 */
	public function count_pending( string $lang = '' ): int {
		global $wpdb;

		$table = $this->table();
		if ( $lang ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status IN ('auto','edited') AND target_lang = %s",
					$lang
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status IN ('auto','edited')" );
	}

	/**
	 * Aggregate stats.
	 *
	 * @return array{total:int,auto:int,edited:int,confirmed:int,api_calls:int,cache_hits:int}
	 */
	public function stats(): array {
		global $wpdb;

		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status" );

		$stats = array(
			'total'      => 0,
			'auto'       => 0,
			'edited'     => 0,
			'confirmed'  => 0,
			'api_calls'  => (int) get_option( 'bt_api_calls', 0 ),
			'cache_hits' => (int) get_option( 'bt_cache_hits', 0 ),
		);

		foreach ( $rows as $row ) {
			$count = (int) $row->c;
			$stats['total'] += $count;
			if ( isset( $stats[ $row->status ] ) ) {
				$stats[ $row->status ] = $count;
			}
		}

		return $stats;
	}

	/**
	 * Increment API call counter.
	 */
	public function increment_api_calls( int $by = 1 ): void {
		update_option( 'bt_api_calls', (int) get_option( 'bt_api_calls', 0 ) + $by, false );
	}

	/**
	 * Increment cache hit counter.
	 */
	public function increment_cache_hits( int $by = 1 ): void {
		update_option( 'bt_cache_hits', (int) get_option( 'bt_cache_hits', 0 ) + $by, false );
	}

	/**
	 * Export all rows for backup.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function export_all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( 'SELECT hash, source_lang, target_lang, source_text, translated_text, status, provider FROM ' . $this->table() . ' ORDER BY id ASC', ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'hash'            => (string) ( $row['hash'] ?? '' ),
					'source_lang'     => (string) ( $row['source_lang'] ?? '' ),
					'target_lang'     => (string) ( $row['target_lang'] ?? '' ),
					'source_text'     => (string) ( $row['source_text'] ?? '' ),
					'translated_text' => (string) ( $row['translated_text'] ?? '' ),
					'status'          => (string) ( $row['status'] ?? 'auto' ),
					'provider'        => (string) ( $row['provider'] ?? '' ),
				);
			},
			$rows
		);
	}

	/**
	 * Import rows from export payload.
	 *
	 * @param list<mixed> $items          Items.
	 * @param bool        $skip_confirmed Skip overwriting confirmed rows.
	 * @return array{imported:int,skipped:int}
	 */
	public function import_items( array $items, bool $skip_confirmed = true ): array {
		$imported = 0;
		$skipped  = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				++$skipped;
				continue;
			}

			$source_lang = sanitize_key( (string) ( $item['source_lang'] ?? '' ) );
			$target_lang = sanitize_key( (string) ( $item['target_lang'] ?? '' ) );
			$source_text = (string) ( $item['source_text'] ?? '' );
			$translated  = (string) ( $item['translated_text'] ?? '' );
			$status      = sanitize_key( (string) ( $item['status'] ?? 'auto' ) );
			$provider    = sanitize_key( (string) ( $item['provider'] ?? 'import' ) );

			if ( '' === $source_lang || '' === $target_lang || '' === $source_text || '' === $translated ) {
				++$skipped;
				continue;
			}

			if ( ! in_array( $status, array( 'auto', 'edited', 'confirmed' ), true ) ) {
				$status = 'auto';
			}

			$hash     = $this->hash( $source_lang, $target_lang, $source_text );
			$existing = $this->find_by_hash( $hash );

			if ( $existing && $skip_confirmed && 'confirmed' === (string) $existing->status ) {
				++$skipped;
				continue;
			}

			$this->upsert( $source_lang, $target_lang, $source_text, $translated, $provider ?: 'import', $status, true );
			++$imported;
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}
}
