<?php
/**
 * Settings page view.
 *
 * @package BudgetTranslator
 *
 * @var array<string, mixed> $settings
 * @var array<string, string> $languages
 * @var array<string, string> $providers
 * @var array<string, int> $stats
 * @var array<string, mixed> $job
 * @var array<string, \WP_Post_Type> $post_types
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$target_langs   = isset( $settings['target_langs'] ) && is_array( $settings['target_langs'] ) ? $settings['target_langs'] : array();
$excluded_types = isset( $settings['excluded_post_types'] ) && is_array( $settings['excluded_post_types'] ) ? $settings['excluded_post_types'] : array();
$glossary       = isset( $settings['glossary'] ) && is_array( $settings['glossary'] ) ? $settings['glossary'] : array();
$api_calls      = (int) ( $stats['api_calls'] ?? 0 );
$cache_hits     = (int) ( $stats['cache_hits'] ?? 0 );
$lookups        = $api_calls + $cache_hits;
$hit_rate       = $lookups > 0 ? (int) round( ( $cache_hits / $lookups ) * 100 ) : 0;
$opt            = \BudgetTranslator\Settings::OPTION_KEY;
?>
<div class="wrap bt-admin">
	<h1><?php echo esc_html__( 'Budget Translator', 'budget-translator' ); ?></h1>

	<?php if ( isset( $_GET['bt_job'] ) && 'queued' === $_GET['bt_job'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Translation job queued. Progress updates below.', 'budget-translator' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['bt_import'] ) && 'ok' === $_GET['bt_import'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			printf(
				/* translators: 1: imported count, 2: skipped count */
				esc_html__( 'Import finished: %1$d imported, %2$d skipped.', 'budget-translator' ),
				(int) ( $_GET['bt_imported'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				(int) ( $_GET['bt_skipped'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);
			?>
		</p></div>
	<?php elseif ( isset( $_GET['bt_import'] ) && 'error' === $_GET['bt_import'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Import failed. Please upload a valid Budget Translator JSON export.', 'budget-translator' ); ?></p></div>
	<?php endif; ?>

	<div class="bt-admin__grid">
		<div class="bt-admin__main">
			<form method="post" action="options.php">
				<?php settings_fields( 'bt_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bt_source_lang"><?php echo esc_html__( 'Source language', 'budget-translator' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[source_lang]" id="bt_source_lang">
								<?php foreach ( $languages as $code => $label ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['source_lang'], $code ); ?>><?php echo esc_html( $label . ' (' . $code . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Target languages', 'budget-translator' ); ?></th>
						<td>
							<fieldset class="bt-lang-checks">
								<?php foreach ( $languages as $code => $label ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[target_langs][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $target_langs, true ) ); ?> />
										<?php echo esc_html( $label . ' (' . $code . ')' ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php echo esc_html__( 'Translated pages use URL prefixes like /en/…', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_provider"><?php echo esc_html__( 'Translation provider', 'budget-translator' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $opt ); ?>[provider]" id="bt_provider">
								<?php foreach ( $providers as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['provider'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_mymemory_email"><?php echo esc_html__( 'MyMemory email (optional)', 'budget-translator' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="bt_mymemory_email" name="<?php echo esc_attr( $opt ); ?>[mymemory_email]" value="<?php echo esc_attr( (string) $settings['mymemory_email'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Raises the free daily quota.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_libre_url"><?php echo esc_html__( 'LibreTranslate URL', 'budget-translator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="bt_libre_url" name="<?php echo esc_attr( $opt ); ?>[libretranslate_url]" value="<?php echo esc_attr( (string) $settings['libretranslate_url'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_libre_key"><?php echo esc_html__( 'LibreTranslate API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bt_libre_key" name="<?php echo esc_attr( $opt ); ?>[libretranslate_key]" value="<?php echo esc_attr( (string) $settings['libretranslate_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_deepl_key"><?php echo esc_html__( 'DeepL API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="bt_deepl_key" name="<?php echo esc_attr( $opt ); ?>[deepl_api_key]" value="<?php echo esc_attr( (string) $settings['deepl_api_key'] ); ?>" autocomplete="new-password" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_deepl_url"><?php echo esc_html__( 'DeepL API URL', 'budget-translator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="bt_deepl_url" name="<?php echo esc_attr( $opt ); ?>[deepl_api_url]" value="<?php echo esc_attr( (string) $settings['deepl_api_url'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Use https://api.deepl.com for Pro accounts.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_google_key"><?php echo esc_html__( 'Google API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="bt_google_key" name="<?php echo esc_attr( $opt ); ?>[google_api_key]" value="<?php echo esc_attr( (string) $settings['google_api_key'] ); ?>" autocomplete="new-password" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Options', 'budget-translator' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enable_rewrites]" value="1" <?php checked( ! empty( $settings['enable_rewrites'] ) ); ?> /> <?php echo esc_html__( 'Enable /lang/ URL prefixes', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[on_the_fly]" value="1" <?php checked( ! empty( $settings['on_the_fly'] ) ); ?> /> <?php echo esc_html__( 'Translate missing segments on the fly', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[language_switcher]" value="1" <?php checked( ! empty( $settings['language_switcher'] ) ); ?> /> <?php echo esc_html__( 'Show language switcher (footer / primary menu)', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_queue_on_save]" value="1" <?php checked( ! empty( $settings['auto_queue_on_save'] ) ); ?> /> <?php echo esc_html__( 'Auto-queue changed posts/menus for translation on save', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[show_partial_notice]" value="1" <?php checked( ! empty( $settings['show_partial_notice'] ) ); ?> /> <?php echo esc_html__( 'Show incomplete-translation notice to visitors', 'budget-translator' ); ?></label>
							<p class="description"><?php echo esc_html__( 'Admins always see a subtle notice when on-the-fly budget skipped segments.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Source auto-corrections', 'budget-translator' ); ?></th>
						<td>
							<?php
							$ac_rules = isset( $settings['source_autocorrect_rules'] ) && is_array( $settings['source_autocorrect_rules'] )
								? wp_parse_args( $settings['source_autocorrect_rules'], \BudgetTranslator\Translation\SourceAutocorrect::default_rule_flags() )
								: \BudgetTranslator\Translation\SourceAutocorrect::default_rule_flags();
							?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[source_autocorrect]" value="1" <?php checked( ! empty( $settings['source_autocorrect'] ) ); ?> />
								<?php echo esc_html__( 'Enable source auto-corrections', 'budget-translator' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Applied when extracting/caching text for translation (does not rewrite the post in the editor). Default: on.', 'budget-translator' ); ?></p>
							<fieldset class="bt-lang-checks" style="margin-top:8px">
								<?php foreach ( \BudgetTranslator\Translation\SourceAutocorrect::rules() as $rule_id => $rule_label ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[source_autocorrect_rules][<?php echo esc_attr( $rule_id ); ?>]" value="1" <?php checked( ! empty( $ac_rules[ $rule_id ] ) ); ?> />
										<?php echo esc_html( $rule_label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_do_not_translate"><?php echo esc_html__( 'Do not translate', 'budget-translator' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="5" id="bt_do_not_translate" name="<?php echo esc_attr( $opt ); ?>[do_not_translate]"><?php echo esc_textarea( (string) ( $settings['do_not_translate'] ?? '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'One phrase per line (brands, codes, shortcodes). Never sent to the API.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<?php foreach ( $target_langs as $lang_code ) : ?>
						<tr>
							<th scope="row"><label for="bt_glossary_<?php echo esc_attr( $lang_code ); ?>"><?php echo esc_html( sprintf( /* translators: %s: language code */ __( 'Glossary (%s)', 'budget-translator' ), $lang_code ) ); ?></label></th>
							<td>
								<textarea class="large-text code" rows="5" id="bt_glossary_<?php echo esc_attr( $lang_code ); ?>" name="<?php echo esc_attr( $opt ); ?>[glossary][<?php echo esc_attr( $lang_code ); ?>]"><?php echo esc_textarea( (string) ( $glossary[ $lang_code ] ?? '' ) ); ?></textarea>
								<p class="description"><?php echo esc_html__( 'One entry per line: Source = Translation', 'budget-translator' ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Exclude post types', 'budget-translator' ); ?></th>
						<td>
							<fieldset class="bt-lang-checks">
								<?php foreach ( $post_types as $type ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[excluded_post_types][]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $excluded_types, true ) ); ?> />
										<?php echo esc_html( $type->labels->singular_name . ' (' . $type->name . ')' ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_excluded_ids"><?php echo esc_html__( 'Exclude post IDs', 'budget-translator' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bt_excluded_ids" name="<?php echo esc_attr( $opt ); ?>[excluded_post_ids]" value="<?php echo esc_attr( (string) ( $settings['excluded_post_ids'] ?? '' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Comma-separated IDs.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_excluded_paths"><?php echo esc_html__( 'Exclude URL paths', 'budget-translator' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="3" id="bt_excluded_paths" name="<?php echo esc_attr( $opt ); ?>[excluded_paths]"><?php echo esc_textarea( (string) ( $settings['excluded_paths'] ?? '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'One path prefix per line, e.g. /shop', 'budget-translator' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'budget-translator' ) ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Translate website', 'budget-translator' ); ?></h2>
			<p><?php echo esc_html__( 'Queues all published posts, pages, menus, SEO meta and image alts for the selected target languages. Identical passages are translated only once.', 'budget-translator' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bt_start_translate_job" />
				<?php wp_nonce_field( 'bt_start_translate_job' ); ?>
				<?php submit_button( __( 'Translate website now', 'budget-translator' ), 'secondary', 'submit', false ); ?>
				<button type="button" class="button" id="bt-process-job"><?php echo esc_html__( 'Process next chunk', 'budget-translator' ); ?></button>
			</form>
			<div id="bt-job-status" class="bt-job-status" data-state="<?php echo esc_attr( (string) ( $job['state'] ?? '' ) ); ?>">
				<?php
				$done      = (int) ( $job['done'] ?? 0 );
				$total_job = (int) ( $job['total'] ?? 0 );
				$pct       = $total_job > 0 ? (int) round( ( $done / $total_job ) * 100 ) : 0;
				$state     = (string) ( $job['state'] ?? '-' );
				?>
				<?php if ( ! empty( $job ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: done, 2: total, 3: percent */
							esc_html__( 'Progress: %1$d / %2$d (%3$d%%)', 'budget-translator' ),
							$done,
							$total_job,
							$pct
						);
						echo ' — ' . esc_html( $state );
						?>
					</p>
					<div class="bt-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $pct ); ?>">
						<span class="bt-progress__bar" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span>
					</div>
				<?php endif; ?>
			</div>

			<hr />

			<h2><?php echo esc_html__( 'Export / Import', 'budget-translator' ); ?></h2>
			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bt_export_translations' ), 'bt_export_translations' ) ); ?>">
					<?php echo esc_html__( 'Export translations (JSON)', 'budget-translator' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="bt_import_translations" />
				<?php wp_nonce_field( 'bt_import_translations' ); ?>
				<p>
					<input type="file" name="bt_import_file" accept="application/json,.json" required />
				</p>
				<p>
					<label>
						<input type="checkbox" name="bt_import_skip_confirmed" value="1" checked />
						<?php echo esc_html__( 'Do not overwrite confirmed entries', 'budget-translator' ); ?>
					</label>
				</p>
				<?php submit_button( __( 'Import JSON', 'budget-translator' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<aside class="bt-admin__side">
			<div class="bt-card">
				<h2><?php echo esc_html__( 'Cache statistics', 'budget-translator' ); ?></h2>
				<ul class="bt-stats">
					<li><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong> <?php echo esc_html__( 'segments cached', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['confirmed'] ); ?></strong> <?php echo esc_html__( 'confirmed', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['edited'] ); ?></strong> <?php echo esc_html__( 'edited', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['auto'] ); ?></strong> <?php echo esc_html__( 'auto', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $api_calls ); ?></strong> <?php echo esc_html__( 'API calls', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $cache_hits ); ?></strong> <?php echo esc_html__( 'cache hits', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $hit_rate ); ?>%</strong> <?php echo esc_html__( 'cache hit rate', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $cache_hits ); ?></strong> <?php echo esc_html__( 'API calls saved (≈ cache hits)', 'budget-translator' ); ?></li>
				</ul>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=budget-translator' ) ); ?>"><?php echo esc_html__( 'Review translations', 'budget-translator' ); ?></a></p>
			</div>
		</aside>
	</div>
</div>
