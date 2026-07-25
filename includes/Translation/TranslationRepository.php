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

		$hash = $this->hash( $source_lang, $target_lang, $source_text );
		$existing = $this->find_by_hash( $hash );

		if ( $existing ) {
			if ( ! $force && in_array( $existing->status, array( 'confirmed', 'edited' ), true ) && 'auto' === $status ) {
				return (int) $existing->id;
			}

			$wpdb->update(
				$this->table(),
				array(
					'translated_text' => $translated,
					'status'          => $status,
					'provider'        => $provider,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%s', '%s' ),
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
	 * Update translation text and status by ID.
	 *
	 * @param int    $id      Row ID.
	 * @param string $text    New translation.
	 * @param string $status  Status.
	 */
	public function update_translation( int $id, string $text, string $status = 'edited' ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'translated_text' => $text,
				'status'          => $status,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
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
		$sql          = 'UPDATE ' . $this->table() . " SET status = 'confirmed' WHERE id IN ({$placeholders})";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( $sql, ...$ids ) );
	}

	/**
	 * Query rows for review UI.
	 *
	 * @param array{status?:string,lang?:string,search?:string,page?:int,per?:int} $args Args.
	 * @return array{items: list<object>, total: int, pages: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$status = $args['status'] ?? '';
		$lang   = $args['lang'] ?? '';
		$search = $args['search'] ?? '';
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per    = max( 1, min( 100, (int) ( $args['per'] ?? 20 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where  = array( '1=1' );
		$params = array();

		if ( $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
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
		$table     = $this->table();

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
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
}
