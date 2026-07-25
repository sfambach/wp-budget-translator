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
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$target_langs = isset( $settings['target_langs'] ) && is_array( $settings['target_langs'] ) ? $settings['target_langs'] : array();
?>
<div class="wrap bt-admin">
	<h1><?php echo esc_html__( 'Budget Translator', 'budget-translator' ); ?></h1>

	<?php if ( isset( $_GET['bt_job'] ) && 'queued' === $_GET['bt_job'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Translation job queued. Progress updates below.', 'budget-translator' ); ?></p></div>
	<?php endif; ?>

	<div class="bt-admin__grid">
		<div class="bt-admin__main">
			<form method="post" action="options.php">
				<?php settings_fields( 'bt_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bt_source_lang"><?php echo esc_html__( 'Source language', 'budget-translator' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[source_lang]" id="bt_source_lang">
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
										<input type="checkbox" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[target_langs][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $target_langs, true ) ); ?> />
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
							<select name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[provider]" id="bt_provider">
								<?php foreach ( $providers as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['provider'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_mymemory_email"><?php echo esc_html__( 'MyMemory email (optional)', 'budget-translator' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="bt_mymemory_email" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[mymemory_email]" value="<?php echo esc_attr( (string) $settings['mymemory_email'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Raises the free daily quota.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_libre_url"><?php echo esc_html__( 'LibreTranslate URL', 'budget-translator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="bt_libre_url" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[libretranslate_url]" value="<?php echo esc_attr( (string) $settings['libretranslate_url'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_libre_key"><?php echo esc_html__( 'LibreTranslate API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="bt_libre_key" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[libretranslate_key]" value="<?php echo esc_attr( (string) $settings['libretranslate_key'] ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_deepl_key"><?php echo esc_html__( 'DeepL API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="bt_deepl_key" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[deepl_api_key]" value="<?php echo esc_attr( (string) $settings['deepl_api_key'] ); ?>" autocomplete="new-password" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_deepl_url"><?php echo esc_html__( 'DeepL API URL', 'budget-translator' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="bt_deepl_url" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[deepl_api_url]" value="<?php echo esc_attr( (string) $settings['deepl_api_url'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Use https://api.deepl.com for Pro accounts.', 'budget-translator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bt_google_key"><?php echo esc_html__( 'Google API key', 'budget-translator' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="bt_google_key" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[google_api_key]" value="<?php echo esc_attr( (string) $settings['google_api_key'] ); ?>" autocomplete="new-password" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Options', 'budget-translator' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[enable_rewrites]" value="1" <?php checked( ! empty( $settings['enable_rewrites'] ) ); ?> /> <?php echo esc_html__( 'Enable /lang/ URL prefixes', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[on_the_fly]" value="1" <?php checked( ! empty( $settings['on_the_fly'] ) ); ?> /> <?php echo esc_html__( 'Translate missing segments on the fly', 'budget-translator' ); ?></label><br />
							<label><input type="checkbox" name="<?php echo esc_attr( \BudgetTranslator\Settings::OPTION_KEY ); ?>[language_switcher]" value="1" <?php checked( ! empty( $settings['language_switcher'] ) ); ?> /> <?php echo esc_html__( 'Show language switcher', 'budget-translator' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save settings', 'budget-translator' ) ); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Translate website', 'budget-translator' ); ?></h2>
			<p><?php echo esc_html__( 'Queues all published posts, pages and menu labels for the selected target languages. Identical passages are translated only once.', 'budget-translator' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bt_start_translate_job" />
				<?php wp_nonce_field( 'bt_start_translate_job' ); ?>
				<?php submit_button( __( 'Translate website now', 'budget-translator' ), 'secondary', 'submit', false ); ?>
				<button type="button" class="button" id="bt-process-job"><?php echo esc_html__( 'Process next chunk', 'budget-translator' ); ?></button>
			</form>
			<div id="bt-job-status" class="bt-job-status" data-state="<?php echo esc_attr( (string) ( $job['state'] ?? '' ) ); ?>">
				<?php if ( ! empty( $job ) ) : ?>
					<p>
						<?php
						printf(
							/* translators: 1: done count, 2: total count, 3: state */
							esc_html__( 'Progress: %1$d / %2$d (%3$s)', 'budget-translator' ),
							(int) ( $job['done'] ?? 0 ),
							(int) ( $job['total'] ?? 0 ),
							esc_html( (string) ( $job['state'] ?? '-' ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<aside class="bt-admin__side">
			<div class="bt-card">
				<h2><?php echo esc_html__( 'Cache statistics', 'budget-translator' ); ?></h2>
				<ul class="bt-stats">
					<li><strong><?php echo esc_html( (string) $stats['total'] ); ?></strong> <?php echo esc_html__( 'segments cached', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['confirmed'] ); ?></strong> <?php echo esc_html__( 'confirmed', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['edited'] ); ?></strong> <?php echo esc_html__( 'edited', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['auto'] ); ?></strong> <?php echo esc_html__( 'auto', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['api_calls'] ); ?></strong> <?php echo esc_html__( 'API calls', 'budget-translator' ); ?></li>
					<li><strong><?php echo esc_html( (string) $stats['cache_hits'] ); ?></strong> <?php echo esc_html__( 'cache hits', 'budget-translator' ); ?></li>
				</ul>
				<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=budget-translator-review' ) ); ?>"><?php echo esc_html__( 'Review translations', 'budget-translator' ); ?></a></p>
			</div>
		</aside>
	</div>
</div>
