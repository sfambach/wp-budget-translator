<?php
/**
 * By-post review view (list + detail).
 *
 * @package BudgetTranslator
 *
 * @var bool $detail
 * @var \WP_Post|null $post
 * @var array{items:list<object>,total:int,pages:int}|null $result
 * @var list<\WP_Post> $posts
 * @var array<int, array{segments:int,cached:int,confirmed:int,pending:int,cached_pct:int,confirmed_pct:int}> $stats_map
 * @var array<string, string> $languages
 * @var list<string> $targets
 * @var string $status
 * @var string $lang
 * @var string $search
 * @var string $coverage_filter
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var list<string> $missing
 * @var array<string, mixed> $job
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detail          = ! empty( $detail );
$base            = admin_url( 'admin.php?page=budget-translator-by-post' );
$targets         = $targets ?? \BudgetTranslator\Settings::target_langs();
$status          = $status ?? 'needs_work';
$lang            = $lang ?? '';
$missing         = $missing ?? array();
$job             = $job ?? array();
$stats_map       = $stats_map ?? array();
$coverage_filter = $coverage_filter ?? 'all';
$languages       = $languages ?? \BudgetTranslator\Settings::available_languages();
?>
<div class="wrap bt-admin">
	<h1><?php echo esc_html__( 'By post', 'budget-translator' ); ?></h1>

	<p class="description">
		<?php echo esc_html__( 'Open a post: missing texts are translated automatically. Confirm the suggestions you want to keep.', 'budget-translator' ); ?>
	</p>

	<?php
	$flash_translated = array_key_exists( 'bt_translated', $_GET ) ? (int) $_GET['bt_translated'] : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$flash_remaining  = array_key_exists( 'bt_remaining', $_GET ) ? (int) $_GET['bt_remaining'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$flash_error      = isset( $_GET['bt_error'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['bt_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<?php if ( null !== $flash_translated ) : ?>
		<?php if ( '' !== $flash_error || ( $flash_remaining > 0 && $flash_translated <= 0 ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php
				printf(
					/* translators: 1: failed/remaining count, 2: error detail */
					esc_html__( 'Could not translate %1$d text(s). %2$s', 'budget-translator' ),
					max( 1, $flash_remaining ),
					'' !== $flash_error ? esc_html( $flash_error ) : esc_html__( 'Try again, or switch provider in settings.', 'budget-translator' )
				);
				?>
			</p></div>
		<?php elseif ( $flash_remaining > 0 ) : ?>
			<div class="notice notice-warning is-dismissible"><p>
				<?php
				printf(
					/* translators: 1: newly translated count, 2: still remaining */
					esc_html__( 'Translated %1$d text(s); %2$d still missing. Retry Translate for the rest.', 'budget-translator' ),
					$flash_translated,
					$flash_remaining
				);
				?>
			</p></div>
		<?php else : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				printf(
					/* translators: %d: newly translated count */
					esc_html__( 'Translated %d text(s) for this post.', 'budget-translator' ),
					$flash_translated
				);
				?>
			</p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! $detail ) : ?>
		<form method="get" class="bt-review-filters">
			<input type="hidden" name="page" value="budget-translator-by-post" />
			<label>
				<span class="screen-reader-text"><?php echo esc_html__( 'Language', 'budget-translator' ); ?></span>
				<select name="bt_lang">
					<?php foreach ( $targets as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $languages[ $code ] ?? $code ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php echo esc_html__( 'Coverage', 'budget-translator' ); ?></span>
				<select name="bt_coverage">
					<option value="all" <?php selected( $coverage_filter, 'all' ); ?>><?php echo esc_html__( 'All posts', 'budget-translator' ); ?></option>
					<option value="none" <?php selected( $coverage_filter, 'none' ); ?>><?php echo esc_html__( 'Not translated', 'budget-translator' ); ?></option>
					<option value="partial" <?php selected( $coverage_filter, 'partial' ); ?>><?php echo esc_html__( 'Partially translated', 'budget-translator' ); ?></option>
					<option value="complete" <?php selected( $coverage_filter, 'complete' ); ?>><?php echo esc_html__( 'Fully translated (any status)', 'budget-translator' ); ?></option>
					<option value="needs_review" <?php selected( $coverage_filter, 'needs_review' ); ?>><?php echo esc_html__( 'Needs work', 'budget-translator' ); ?></option>
					<option value="fully_confirmed" <?php selected( $coverage_filter, 'fully_confirmed' ); ?>><?php echo esc_html__( 'Fully confirmed', 'budget-translator' ); ?></option>
				</select>
			</label>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search posts…', 'budget-translator' ); ?>" />
			<?php submit_button( __( 'Filter', 'budget-translator' ), 'secondary', '', false ); ?>
		</form>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Title', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Translated', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Confirmed', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Modified', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'budget-translator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $posts ) ) : ?>
					<tr><td colspan="6"><?php echo esc_html__( 'No posts found for this filter.', 'budget-translator' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $posts as $p ) : ?>
						<?php
						$st = $stats_map[ (int) $p->ID ] ?? array(
							'cached_pct'    => 0,
							'confirmed_pct' => 0,
							'cached'        => 0,
							'confirmed'     => 0,
							'segments'      => 0,
							'pending'       => 0,
						);
						$detail_url = add_query_arg(
							array(
								'post_id'   => (int) $p->ID,
								'bt_lang'   => $lang,
								'bt_status' => 'needs_work',
							),
							$base
						);
						?>
						<tr>
							<td><strong><?php echo esc_html( get_the_title( $p ) ); ?></strong></td>
							<td><?php echo esc_html( $p->post_type ); ?></td>
							<td>
								<strong><?php echo esc_html( (string) (int) $st['cached_pct'] ); ?>%</strong>
								<span class="description">(<?php echo esc_html( (string) (int) $st['cached'] . '/' . (int) $st['segments'] ); ?>)</span>
								<div class="bt-progress bt-progress--inline" role="presentation">
									<span class="bt-progress__bar" style="width:<?php echo esc_attr( (string) (int) $st['cached_pct'] ); ?>%"></span>
								</div>
							</td>
							<td>
								<strong><?php echo esc_html( (string) (int) $st['confirmed_pct'] ); ?>%</strong>
								<span class="description">(<?php echo esc_html( (string) (int) $st['confirmed'] . '/' . (int) $st['segments'] ); ?>)</span>
								<?php if ( (int) $st['pending'] > 0 ) : ?>
									<br /><span class="description"><?php echo esc_html( sprintf( /* translators: %d pending count */ __( '%d need work', 'budget-translator' ), (int) $st['pending'] ) ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_the_modified_date( '', $p ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">
									<?php echo esc_html__( 'Review translations', 'budget-translator' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg(
									array(
										'paged'       => '%#%',
										'bt_coverage' => $coverage_filter,
										'bt_lang'     => $lang,
										's'           => $search,
									),
									$base
								),
								'format'  => '',
								'current' => $page,
								'total'   => $pages,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<?php
		$cov = $stats_map[ (int) $post->ID ] ?? array(
			'cached_pct'    => 0,
			'confirmed_pct' => 0,
			'cached'        => 0,
			'confirmed'     => 0,
			'segments'      => 0,
			'pending'       => 0,
		);
		$needs_work = (int) ( $cov['pending'] ?? 0 ) + count( $missing );
		?>
		<p>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'bt_lang' => $lang, 'bt_coverage' => $coverage_filter ), $base ) ); ?>"><?php echo esc_html__( 'Back to posts', 'budget-translator' ); ?></a>
			<a class="button" href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html__( 'Edit post', 'budget-translator' ); ?></a>
		</p>
		<h2><?php echo esc_html( get_the_title( $post ) ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: confirmed pct, 2: texts needing work */
				esc_html__( 'Confirmed %1$d%% · %2$d texts still need work', 'budget-translator' ),
				(int) $cov['confirmed_pct'],
				$needs_work
			);
			?>
			<br />
			<span class="description">
				<?php echo esc_html__( 'This list shows texts from this post that still need confirmation. New texts are machine-translated when you open the post.', 'budget-translator' ); ?>
			</span>
		</p>

		<form method="get" class="bt-review-filters">
			<input type="hidden" name="page" value="budget-translator-by-post" />
			<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
			<label>
				<span class="screen-reader-text"><?php echo esc_html__( 'Status', 'budget-translator' ); ?></span>
				<select name="bt_status">
					<option value="needs_work" <?php selected( $status, 'needs_work' ); ?>><?php echo esc_html__( 'Needs work', 'budget-translator' ); ?></option>
					<option value="pending" <?php selected( $status, 'pending' ); ?>><?php echo esc_html__( 'Translated, not confirmed', 'budget-translator' ); ?></option>
					<option value="confirmed" <?php selected( $status, 'confirmed' ); ?>><?php echo esc_html__( 'Confirmed', 'budget-translator' ); ?></option>
					<option value="all" <?php selected( $status, 'all' ); ?>><?php echo esc_html__( 'All texts', 'budget-translator' ); ?></option>
				</select>
			</label>
			<label>
				<span class="screen-reader-text"><?php echo esc_html__( 'Language', 'budget-translator' ); ?></span>
				<select name="bt_lang">
					<?php foreach ( $targets as $code ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $languages[ $code ] ?? $code ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php submit_button( __( 'Filter', 'budget-translator' ), 'secondary', '', false ); ?>
		</form>

		<div class="bt-by-post-actions">
			<?php if ( ! empty( $missing ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bt-inline-form">
					<input type="hidden" name="action" value="bt_queue_post" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
					<input type="hidden" name="bt_lang" value="<?php echo esc_attr( $lang ); ?>" />
					<?php wp_nonce_field( 'bt_queue_post' ); ?>
					<?php submit_button( __( 'Retry failed translations', 'budget-translator' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<button type="button" class="button" id="bt-confirm-selected"><?php echo esc_html__( 'Confirm selected', 'budget-translator' ); ?></button>
			<span class="description">
				<?php
				printf(
					/* translators: 1: rows shown, 2: still missing machine translation */
					esc_html__( '%1$d shown · %2$d not translated yet', 'budget-translator' ),
					(int) ( $result['total'] ?? 0 ),
					count( $missing )
				);
				?>
			</span>
		</div>

		<?php
		$auto_translated = $auto_translated ?? 0;
		$auto_remaining  = $auto_remaining ?? 0;
		$auto_error      = $auto_error ?? '';
		?>
		<?php if ( $auto_translated > 0 && '' === $auto_error && 0 === $auto_remaining ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( 'Automatically translated %d new text(s). Please confirm them below.', 'budget-translator' ),
					(int) $auto_translated
				);
				?>
			</p></div>
		<?php elseif ( $auto_translated > 0 && $auto_remaining > 0 ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				printf(
					/* translators: 1: ok count, 2: remaining */
					esc_html__( 'Automatically translated %1$d text(s); %2$d still failed — use Retry.', 'budget-translator' ),
					(int) $auto_translated,
					(int) $auto_remaining
				);
				?>
			</p></div>
		<?php elseif ( ! empty( $missing ) && '' !== $auto_error ) : ?>
			<div class="notice notice-error inline"><p>
				<?php echo esc_html( $auto_error ); ?>
			</p></div>
		<?php elseif ( ! empty( $missing ) && 'needs_work' === $status ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php echo esc_html__( 'Some texts could not be translated yet. Use “Retry failed translations”.', 'budget-translator' ); ?>
			</p></div>
		<?php endif; ?>

		<table class="widefat striped bt-review-table">
			<thead>
				<tr>
					<td class="check-column"><input type="checkbox" id="bt-check-all" /></td>
					<th><?php echo esc_html__( 'Source', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Translation', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Lang', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'budget-translator' ); ?></th>
					<th><?php echo esc_html__( 'Actions', 'budget-translator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $result['items'] ) ) : ?>
					<tr><td colspan="6">
						<?php
						if ( 'needs_work' === $status && 0 === $needs_work ) {
							echo esc_html__( 'All texts in this post are confirmed. Switch to “Confirmed” or “All texts” to browse them.', 'budget-translator' );
						} else {
							echo esc_html__( 'No texts for this filter.', 'budget-translator' );
						}
						?>
					</td></tr>
				<?php else : ?>
					<?php foreach ( $result['items'] as $row ) : ?>
						<?php
						$is_missing = 'missing' === (string) $row->status || (int) $row->id <= 0;
						?>
						<tr data-id="<?php echo esc_attr( (string) (int) $row->id ); ?>" <?php echo $is_missing ? 'class="bt-row-missing"' : ''; ?>>
							<th class="check-column">
								<?php if ( ! $is_missing ) : ?>
									<input type="checkbox" class="bt-row-check" value="<?php echo esc_attr( (string) $row->id ); ?>" />
								<?php endif; ?>
							</th>
							<td>
								<textarea class="bt-source-text large-text" rows="3" <?php disabled( $is_missing ); ?>><?php echo esc_textarea( (string) $row->source_text ); ?></textarea>
							</td>
							<td>
								<textarea class="bt-translated large-text" rows="3" <?php disabled( $is_missing ); ?> placeholder="<?php echo esc_attr__( 'Not translated yet', 'budget-translator' ); ?>"><?php echo esc_textarea( (string) $row->translated_text ); ?></textarea>
							</td>
							<td><code><?php echo esc_html( (string) $row->target_lang ); ?></code></td>
							<td class="bt-status"><?php echo esc_html( $is_missing ? __( 'missing', 'budget-translator' ) : (string) $row->status ); ?></td>
							<td>
								<?php if ( $is_missing ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bt-inline-form">
										<input type="hidden" name="action" value="bt_translate_segment" />
										<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>" />
										<input type="hidden" name="bt_lang" value="<?php echo esc_attr( $lang ); ?>" />
										<input type="hidden" name="source_hash" value="<?php echo esc_attr( (string) $row->hash ); ?>" />
										<?php wp_nonce_field( 'bt_translate_segment' ); ?>
										<?php submit_button( __( 'Translate', 'budget-translator' ), 'small', 'submit', false ); ?>
									</form>
								<?php else : ?>
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=budget-translator-focus&bt_id=' . (int) $row->id ) ); ?>"><?php echo esc_html__( 'Open', 'budget-translator' ); ?></a>
									<button type="button" class="button button-small bt-save-row"><?php echo esc_html__( 'Save', 'budget-translator' ); ?></button>
									<button type="button" class="button button-small bt-confirm-row"><?php echo esc_html__( 'Confirm', 'budget-translator' ); ?></button>
									<button type="button" class="button button-small bt-retranslate-row"><?php echo esc_html__( 'Retranslate', 'budget-translator' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
