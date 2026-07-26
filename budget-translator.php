<?php
/**
 * Plugin Name:       Budget Translator
 * Plugin URI:        https://github.com/stefan/wp-budget-translator
 * Description:       Affordable automatic website translation with local segment caching, review, and optional premium API providers.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Stefan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       budget-translator
 * Domain Path:       /languages
 *
 * @package BudgetTranslator
 */

declare(strict_types=1);

namespace BudgetTranslator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BT_VERSION', '1.0.3' );
define( 'BT_PLUGIN_FILE', __FILE__ );
define( 'BT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once BT_PLUGIN_DIR . 'includes/Autoloader.php';

Autoloader::register();

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Activator::class, 'deactivate' ) );

Plugin::instance()->boot();
