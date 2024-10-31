<?php
 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * RevLifter
 *
 *
 * @link              https://revlifter.com
 * @since             1.0.0
 * @package           RevLifter
 *
 * Plugin Name:       RevLifter
 * Plugin URI:        http://wordpress.org/plugins/revlifter/
 * Description:       This plugin is used to enhanced commerce activity by RevLifter
 * Version:           1.0.0
 * Author:            RevLifter
 * Author URI:        https://revlifter.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       revlifter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0
 */
define( 'REVLIFTER_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-settings-page-activator.php
 */
function revlifter_activate_settings_page() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-revlifter-activator.php';
	Revlifter_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-settings-page-deactivator.php
 */
function revlifter_deactivate_settings_page() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-revlifter-deactivator.php';
	REVLIFTER_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'revlifter_activate_settings_page' );
register_deactivation_hook( __FILE__, 'revlifter_deactivate_settings_page' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-revlifter.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function revlifter_run() {

	$plugin = new REVLIFTER();
	$plugin->run();

}
revlifter_run();
