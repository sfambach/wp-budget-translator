<?php
/**
 * Provider factory.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator\Translation;

use BudgetTranslator\Settings;
use BudgetTranslator\Translation\Providers\DeepLProvider;
use BudgetTranslator\Translation\Providers\GoogleProvider;
use BudgetTranslator\Translation\Providers\LibreTranslateProvider;
use BudgetTranslator\Translation\Providers\MyMemoryProvider;
use BudgetTranslator\Translation\Providers\ProviderInterface;

/**
 * Class ProviderFactory
 */
final class ProviderFactory {

	/**
	 * Create provider by slug or from settings.
	 *
	 * @param string|null $slug Provider slug.
	 */
	public static function make( ?string $slug = null ): ProviderInterface {
		$slug = $slug ?: (string) Settings::get( 'provider', 'mymemory' );

		return match ( $slug ) {
			'libretranslate' => new LibreTranslateProvider(),
			'deepl'          => new DeepLProvider(),
			'google'         => new GoogleProvider(),
			default          => new MyMemoryProvider(),
		};
	}
}
