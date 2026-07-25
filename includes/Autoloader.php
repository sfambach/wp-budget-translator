<?php
/**
 * PSR-4 style autoloader for Budget Translator.
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator;

/**
 * Class Autoloader
 */
final class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file by FQCN.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = BT_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
