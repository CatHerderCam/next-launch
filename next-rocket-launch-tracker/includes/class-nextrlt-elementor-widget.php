<?php
/**
 * Elementor widget. Only ever loaded from NEXTRLT_Elementor::register_widget(),
 * which is itself only called by Elementor, so \Elementor\* is guaranteed to
 * be available here.
 *
 * @package nextrlt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elementor widget exposing the same options as the shortcode.
 */
class NEXTRLT_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Toggle controls mirrored from NEXTRLT_Shortcode, mapped to their default
	 * (on/off) state.
	 *
	 * @return array
	 */
	protected static function toggle_fields() {
		return array(
			'countdown'   => array( __( 'Countdown', 'next-rocket-launch-tracker' ), true ),
			'image'       => array( __( 'Rocket image', 'next-rocket-launch-tracker' ), true ),
			'provider'    => array( __( 'Provider & rocket name', 'next-rocket-launch-tracker' ), true ),
			'pad'         => array( __( 'Pad & location', 'next-rocket-launch-tracker' ), true ),
			'orbit'       => array( __( 'Target orbit', 'next-rocket-launch-tracker' ), false ),
			'status'      => array( __( 'Status badge', 'next-rocket-launch-tracker' ), true ),
			'description' => array( __( 'Mission description', 'next-rocket-launch-tracker' ), false ),
		);
	}

	/**
	 * Unique widget identifier.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'nextrlt_next_launch';
	}

	/**
	 * Widget panel display title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Next Rocket Launch Tracker', 'next-rocket-launch-tracker' );
	}

	/**
	 * Widget panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-countdown';
	}

	/**
	 * Elementor category this widget appears under.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'next-rocket-launch-tracker' );
	}

	/**
	 * Search keywords for the widget panel.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'rocket', 'launch', 'spacex', 'countdown', 'space' );
	}

	/**
	 * Elementor renders widget updates (e.g. after a settings change) through
	 * an isolated AJAX fragment with no wp_head(), so the wp_enqueue_style()
	 * call inside NEXTRLT_Shortcode::render() has nowhere to print. Declaring the
	 * dependency here instead makes Elementor load it for that fragment too.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return NEXTRLT_Settings::get( 'load_css' ) ? array( 'nextrlt' ) : array();
	}

	/**
	 * Script handles this widget depends on.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'nextrlt' );
	}

	/**
	 * Register the widget's Elementor controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'nextrlt_section_content',
			array(
				'label' => __( 'Content', 'next-rocket-launch-tracker' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'next-rocket-launch-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Optional heading', 'next-rocket-launch-tracker' ),
			)
		);

		$this->add_control(
			'location',
			array(
				'label'       => __( 'Locations', 'next-rocket-launch-tracker' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => NEXTRLT_Settings::get( 'default_locations' ),
				'placeholder' => '12,27',
				'description' => __( 'Comma separated launch location IDs. Leave empty to show launches from anywhere. Find IDs on Settings > Next Rocket Launch Tracker.', 'next-rocket-launch-tracker' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of launches', 'next-rocket-launch-tracker' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 10,
				'step'    => 1,
				'default' => NEXTRLT_Settings::get( 'default_limit' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'next-rocket-launch-tracker' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'card',
				'options' => array(
					'card'    => __( 'Card', 'next-rocket-launch-tracker' ),
					'list'    => __( 'List', 'next-rocket-launch-tracker' ),
					'compact' => __( 'Compact', 'next-rocket-launch-tracker' ),
				),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Color surface', 'next-rocket-launch-tracker' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'  => __( 'Auto', 'next-rocket-launch-tracker' ),
					'light' => __( 'Light', 'next-rocket-launch-tracker' ),
					'dark'  => __( 'Dark', 'next-rocket-launch-tracker' ),
				),
			)
		);

		$this->add_control(
			'slider',
			array(
				'label'        => __( 'Slider mode', 'next-rocket-launch-tracker' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'next-rocket-launch-tracker' ),
				'label_off'    => __( 'No', 'next-rocket-launch-tracker' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Shows one launch at a time with prev/next arrows. Only takes effect when more than one launch is showing.', 'next-rocket-launch-tracker' ),
			)
		);

		$this->add_control(
			'timezone',
			array(
				'label'   => __( 'Launch time shown in', 'next-rocket-launch-tracker' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'site',
				'options' => array(
					'site'   => __( 'Site timezone', 'next-rocket-launch-tracker' ),
					'viewer' => __( "Visitor's timezone", 'next-rocket-launch-tracker' ),
					'utc'    => __( 'UTC', 'next-rocket-launch-tracker' ),
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'       => __( 'Mission link URL', 'next-rocket-launch-tracker' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'default'     => array(
					'url' => '',
				),
				'description' => __( 'Optional. Wraps the mission name in a link to a page of your choosing.', 'next-rocket-launch-tracker' ),
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'   => __( 'Empty message', 'next-rocket-launch-tracker' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'No upcoming launches scheduled.', 'next-rocket-launch-tracker' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'nextrlt_section_fields',
			array(
				'label' => __( 'Fields to show', 'next-rocket-launch-tracker' ),
			)
		);

		foreach ( self::toggle_fields() as $key => $field ) {
			list( $label, $default_on ) = $field;

			$this->add_control(
				$key,
				array(
					'label'        => $label,
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'next-rocket-launch-tracker' ),
					'label_off'    => __( 'Hide', 'next-rocket-launch-tracker' ),
					'return_value' => 'yes',
					'default'      => $default_on ? 'yes' : '',
				)
			);
		}

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the front end.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$atts = array(
			'location'   => $settings['location'],
			'limit'      => $settings['limit'],
			'layout'     => $settings['layout'],
			'theme'      => $settings['theme'],
			'slider'     => ! empty( $settings['slider'] ) ? 'yes' : 'no',
			'title'      => $settings['title'],
			'timezone'   => $settings['timezone'],
			'link'       => ! empty( $settings['link']['url'] ) ? $settings['link']['url'] : '',
			'empty_text' => $settings['empty_text'],
		);

		foreach ( array_keys( self::toggle_fields() ) as $key ) {
			$atts[ $key ] = ! empty( $settings[ $key ] ) ? 'yes' : 'no';
		}

		echo NEXTRLT_Shortcode::render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- NEXTRLT_Shortcode::render() escapes its own output.
	}
}
