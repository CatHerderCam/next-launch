<?php
/**
 * Elementor integration.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Elementor {

	/**
	 * Hook Elementor. Both hooks only ever fire when Elementor itself is
	 * active, so it's safe to add these unconditionally on sites that don't
	 * have it installed.
	 */
	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Add a "Next Rocket Launch Tracker" category to the Elementor widget panel.
	 *
	 * @param object $elements_manager Elementor's Elements_Manager instance.
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'next-rocket-launch-tracker',
			array(
				'title' => __( 'Next Rocket Launch Tracker', 'next-rocket-launch-tracker' ),
				'icon'  => 'eicon-countdown',
			)
		);
	}

	/**
	 * Register the widget.
	 *
	 * @param object $widgets_manager Elementor's Widgets_Manager instance.
	 */
	public static function register_widget( $widgets_manager ) {
		require_once SDNL_PATH . 'includes/class-sdnl-elementor-widget.php';

		$widgets_manager->register( new SDNL_Elementor_Widget() );
	}
}
