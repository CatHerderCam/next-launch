<?php
/**
 * Plugin Name:       Next Rocket Launch Tracker
 * Description:       Shortcode that displays upcoming rocket launches from the launch locations you choose, using The Space Devs Launch Library 2 API.
 * Version:           1.1.5
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            catherdercam
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       next-rocket-launch-tracker
 */

defined( 'ABSPATH' ) || exit;

define( 'SDNL_VERSION', '1.1.5' );
define( 'SDNL_FILE', __FILE__ );
define( 'SDNL_PATH', plugin_dir_path( __FILE__ ) );
define( 'SDNL_URL', plugin_dir_url( __FILE__ ) );

require_once SDNL_PATH . 'includes/class-sdnl-settings.php';
require_once SDNL_PATH . 'includes/class-sdnl-api.php';
require_once SDNL_PATH . 'includes/class-sdnl-shortcode.php';
require_once SDNL_PATH . 'includes/class-sdnl-cron.php';
require_once SDNL_PATH . 'includes/class-sdnl-elementor.php';

if ( is_admin() ) {
	require_once SDNL_PATH . 'includes/class-sdnl-admin.php';
	require_once SDNL_PATH . 'includes/class-sdnl-builder.php';
}

/**
 * Boot the plugin.
 */
function sdnl_init() {
	SDNL_Shortcode::init();
	SDNL_Cron::init();
	SDNL_Elementor::init();

	if ( is_admin() ) {
		SDNL_Admin::init();
		SDNL_Builder::init();
	}
}
add_action( 'plugins_loaded', 'sdnl_init' );

/**
 * Register front-end assets. Enqueued on demand by the shortcode.
 */
function sdnl_register_assets() {
	wp_register_style(
		'sdnl',
		SDNL_URL . 'assets/sdnl.css',
		array(),
		SDNL_VERSION
	);

	wp_register_script(
		'sdnl',
		SDNL_URL . 'assets/sdnl.js',
		array(),
		SDNL_VERSION,
		true
	);
}
add_action( 'init', 'sdnl_register_assets' );

/**
 * Activation: schedule the background refresh.
 */
function sdnl_activate() {
	SDNL_Cron::schedule();
}
register_activation_hook( __FILE__, 'sdnl_activate' );

/**
 * Deactivation: clear the schedule and cached responses.
 */
function sdnl_deactivate() {
	SDNL_Cron::unschedule();
	SDNL_API::flush_cache();
}
register_deactivation_hook( __FILE__, 'sdnl_deactivate' );
