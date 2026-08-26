<?php
/**
 * Plugin Name:       Next Rocket Launch Tracker
 * Description:       Shortcode that displays upcoming rocket launches from the launch locations you choose, using The Space Devs Launch Library 2 API.
 * Version:           1.1.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            catherdercam
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       next-rocket-launch-tracker
 *
 * @package nextrlt
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXTRLT_VERSION', '1.1.6' );
define( 'NEXTRLT_FILE', __FILE__ );
define( 'NEXTRLT_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXTRLT_URL', plugin_dir_url( __FILE__ ) );

require_once NEXTRLT_PATH . 'includes/class-nextrlt-settings.php';
require_once NEXTRLT_PATH . 'includes/class-nextrlt-api.php';
require_once NEXTRLT_PATH . 'includes/class-nextrlt-shortcode.php';
require_once NEXTRLT_PATH . 'includes/class-nextrlt-cron.php';
require_once NEXTRLT_PATH . 'includes/class-nextrlt-elementor.php';

if ( is_admin() ) {
	require_once NEXTRLT_PATH . 'includes/class-nextrlt-admin.php';
	require_once NEXTRLT_PATH . 'includes/class-nextrlt-builder.php';
}

/**
 * Boot the plugin.
 */
function nextrlt_init() {
	NEXTRLT_Shortcode::init();
	NEXTRLT_Cron::init();
	NEXTRLT_Elementor::init();

	if ( is_admin() ) {
		NEXTRLT_Admin::init();
		NEXTRLT_Builder::init();
	}
}
add_action( 'plugins_loaded', 'nextrlt_init' );

/**
 * Register front-end assets. Enqueued on demand by the shortcode.
 */
function nextrlt_register_assets() {
	wp_register_style(
		'nextrlt',
		NEXTRLT_URL . 'assets/nextrlt.css',
		array(),
		NEXTRLT_VERSION
	);

	wp_register_script(
		'nextrlt',
		NEXTRLT_URL . 'assets/nextrlt.js',
		array(),
		NEXTRLT_VERSION,
		true
	);
}
add_action( 'init', 'nextrlt_register_assets' );

/**
 * Activation: schedule the background refresh.
 */
function nextrlt_activate() {
	NEXTRLT_Cron::schedule();
}
register_activation_hook( __FILE__, 'nextrlt_activate' );

/**
 * Deactivation: clear the schedule and cached responses.
 */
function nextrlt_deactivate() {
	NEXTRLT_Cron::unschedule();
	NEXTRLT_API::flush_cache();
}
register_deactivation_hook( __FILE__, 'nextrlt_deactivate' );
