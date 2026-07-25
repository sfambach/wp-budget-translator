<?php
/**
 * Translation provider contract.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation\Providers;

/**
 * Interface ProviderInterface
 */
interface ProviderInterface {

	/**
	 * Provider slug.
	 */
	public function get_slug(): string;

	/**
	 * Translate a single text segment.
	 *
	 * @param string $text        Source text.
	 * @param string $source_lang Source language code.
	 * @param string $target_lang Target language code.
	 * @return string Translated text.
	 *
	 * @throws \RuntimeException On API failure.
	 */
	public function translate( string $text, string $source_lang, string $target_lang ): string;
}
