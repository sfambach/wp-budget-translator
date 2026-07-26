<?php
/**
 * Single-item focus review view.
 *
 * @package BudgetTranslator
 *
 * @var \BudgetTranslator\Translation\TranslationRepository $repo
 * @var object|null $item
 * @var object|null $prev
 * @var object|null $next
 * @var int $pending_total
 * @var array<string, string> $languages
 * @var list<string> $targets
 * @var string $lang
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_url  = admin_url( 'admin.php?page=budget-translator-review' );
$focus_url = admin_url( 'admin.php?page=budget-translator-focus' );
?>
<div class="wrap bt-admin bt-focus" data-lang="<?php echo esc_attr( $lang ); ?>">
	<h1><?php echo esc_html__( 'Review one by one', 'budget-translator' ); ?></h1>

	<p>
		<a class="button" href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html__( 'Back to list', 'budget-translator' ); ?></a>
		<span class="description bt-focus__count">
			<?php
			printf(
				/* translators: %d: pending count */
				esc_html__( '%d pending', 'budget-translator' ),
				(int) $pending_total
			);
			?>
		</span>
	</p>

	<form method="get" class="bt-review-filters">
		<input type="hidden" name="page" value="budget-translator-focus" />
		<label>
			<span class="screen-reader-text"><?php echo esc_html__( 'Language', 'budget-translator' ); ?></span>
			<select name="bt_lang" onchange="this.form.submit()">
				<option value=""><?php echo esc_html__( 'All languages', 'budget-translator' ); ?></option>
				<?php foreach ( $targets as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lang, $code ); ?>><?php echo esc_html( $languages[ $code ] ?? $code ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</form>

	<?php if ( ! $item ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html__( 'Nothing left to review. All pending translations are confirmed.', 'budget-translator' ); ?></p></div>
	<?php else : ?>
		<div
			class="bt-focus-card"
			id="bt-focus-card"
			data-id="<?php echo esc_attr( (string) $item->id ); ?>"
			data-focus-url="<?php echo esc_url( $focus_url ); ?>"
		>
			<div class="bt-focus-card__meta">
				<span>#<?php echo esc_html( (string) $item->id ); ?></span>
				<code><?php echo esc_html( (string) $item->source_lang ); ?> → <?php echo esc_html( (string) $item->target_lang ); ?></code>
				<span class="bt-status"><?php echo esc_html( (string) $item->status ); ?></span>
				<span class="description"><?php echo esc_html( (string) $item->provider ); ?></span>
			</div>

			<label class="bt-focus-card__label" for="bt-focus-source"><?php echo esc_html__( 'Source', 'budget-translator' ); ?></label>
			<textarea id="bt-focus-source" class="bt-source-text large-text" rows="8"><?php echo esc_textarea( (string) $item->source_text ); ?></textarea>

			<label class="bt-focus-card__label" for="bt-focus-translated"><?php echo esc_html__( 'Translation', 'budget-translator' ); ?></label>
			<textarea id="bt-focus-translated" class="bt-translated large-text" rows="8"><?php echo esc_textarea( (string) $item->translated_text ); ?></textarea>

			<div class="bt-focus-card__actions">
				<?php
				$nav_args = array();
				if ( $lang ) {
					$nav_args['bt_lang'] = $lang;
				}
				?>
				<?php if ( $prev ) : ?>
					<a class="button bt-focus-prev" href="<?php echo esc_url( add_query_arg( array_merge( $nav_args, array( 'bt_id' => (int) $prev->id ) ), $focus_url ) ); ?>"><?php echo esc_html__( 'Previous', 'budget-translator' ); ?></a>
				<?php else : ?>
					<button type="button" class="button" disabled><?php echo esc_html__( 'Previous', 'budget-translator' ); ?></button>
				<?php endif; ?>

				<button type="button" class="button bt-focus-save"><?php echo esc_html__( 'Save', 'budget-translator' ); ?></button>
				<button type="button" class="button bt-focus-retranslate"><?php echo esc_html__( 'Retranslate', 'budget-translator' ); ?></button>
				<button type="button" class="button button-primary bt-focus-confirm"><?php echo esc_html__( 'Confirm & next', 'budget-translator' ); ?> <kbd>Ctrl</kbd>+<kbd>Enter</kbd></button>

				<?php if ( $next ) : ?>
					<a class="button bt-focus-skip" href="<?php echo esc_url( add_query_arg( array_merge( $nav_args, array( 'bt_id' => (int) $next->id ) ), $focus_url ) ); ?>"><?php echo esc_html__( 'Skip', 'budget-translator' ); ?></a>
				<?php else : ?>
					<button type="button" class="button" disabled><?php echo esc_html__( 'Skip', 'budget-translator' ); ?></button>
				<?php endif; ?>
			</div>

			<?php if ( 'confirmed' === $item->status ) : ?>
				<p class="description"><?php echo esc_html__( 'This entry is already confirmed. Use Previous/Skip to move, or edit and save again.', 'budget-translator' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
