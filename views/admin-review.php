<?php
/**
 * Review page view.
 *
 * @package BudgetTranslator
 *
 * @var \BudgetTranslator\Translation\TranslationRepository $repo
 * @var array{items:list<object>,total:int,pages:int} $result
 * @var array<string, string> $languages
 * @var list<string> $targets
 * @var string $status
 * @var string $lang
 * @var string $search
 * @var int $page
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base_url = admin_url( 'admin.php?page=budget-translator-review' );
?>
<div class="wrap bt-admin">
	<h1><?php echo esc_html__( 'Translations', 'budget-translator' ); ?></h1>

	<form method="get" class="bt-review-filters">
		<input type="hidden" name="page" value="budget-translator-review" />
		<label>
			<span class="screen-reader-text"><?php echo esc_html__( 'Status', 'budget-translator' ); ?></span>
			<select name="bt_status">
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php echo esc_html__( 'Pending review', 'budget-translator' ); ?></option>
				<option value="auto" <?php selected( $status, 'auto' ); ?>><?php echo esc_html__( 'Auto', 'budget-translator' ); ?></option>
				<option value="edited" <?php selected( $status, 'edited' ); ?>><?php echo esc_html__( 'Edited', 'budget-translator' ); ?></option>
				<option value="confirmed" <?php selected( $status, 'confirmed' ); ?>><?php echo esc_html__( 'Confirmed', 'budget-translator' ); ?></option>
				<option value="all" <?php selected( $status, '' ); ?>><?php echo esc_html__( 'All statuses', 'budget-translator' ); ?></option>
			</select>
		</label>
		<label>
			<span class="screen-reader-text"><?php echo esc_html__( 'Language', 'budget-translator' ); ?></span>
			<select name="bt_lang">
				<option value=""><?php echo esc_html__( 'All languages', 'budget-translator' ); ?></option>
				<?php foreach ( $targets as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $languages[ $code ] ?? $code ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search…', 'budget-translator' ); ?>" />
		<?php submit_button( __( 'Filter', 'budget-translator' ), 'secondary', '', false ); ?>
	</form>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=budget-translator-focus' ) ); ?>"><?php echo esc_html__( 'Review one by one', 'budget-translator' ); ?></a>
		<button type="button" class="button" id="bt-confirm-selected"><?php echo esc_html__( 'Confirm selected', 'budget-translator' ); ?></button>
		<button type="button" class="button" id="bt-purge-invalid"><?php echo esc_html__( 'Remove invalid API messages', 'budget-translator' ); ?></button>
		<span class="description"><?php printf( esc_html__( '%d segments', 'budget-translator' ), (int) $result['total'] ); ?></span>
	</p>

	<p class="description">
		<?php echo esc_html__( 'You can edit the German source and the translation together. Saving a changed source also updates matching text in posts, pages and menus.', 'budget-translator' ); ?>
	</p>

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
				<tr><td colspan="6"><?php echo esc_html__( 'No translations yet. Run “Translate website now” from Settings.', 'budget-translator' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $row ) : ?>
					<tr data-id="<?php echo esc_attr( (string) $row->id ); ?>">
						<th class="check-column"><input type="checkbox" class="bt-row-check" value="<?php echo esc_attr( (string) $row->id ); ?>" /></th>
						<td>
							<textarea class="bt-source-text large-text" rows="3"><?php echo esc_textarea( (string) $row->source_text ); ?></textarea>
						</td>
						<td>
							<textarea class="bt-translated large-text" rows="3"><?php echo esc_textarea( (string) $row->translated_text ); ?></textarea>
						</td>
						<td><code><?php echo esc_html( (string) $row->target_lang ); ?></code></td>
						<td class="bt-status"><?php echo esc_html( (string) $row->status ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=budget-translator-focus&bt_id=' . (int) $row->id ) ); ?>"><?php echo esc_html__( 'Open', 'budget-translator' ); ?></a>
							<button type="button" class="button button-small bt-save-row"><?php echo esc_html__( 'Save', 'budget-translator' ); ?></button>
							<button type="button" class="button button-small bt-confirm-row"><?php echo esc_html__( 'Confirm', 'budget-translator' ); ?></button>
							<button type="button" class="button button-small bt-retranslate-row"><?php echo esc_html__( 'Retranslate', 'budget-translator' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'current'   => $page,
							'total'     => $result['pages'],
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					) ?: ''
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
