<?php
/**
 * Elementor widget. Only ever loaded from SDNL_Elementor::register_widget(),
 * which is itself only called by Elementor, so \Elementor\* is guaranteed to
 * be available here.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Toggle controls mirrored from SDNL_Shortcode, mapped to their default
	 * (on/off) state.
	 *
	 * @return array
	 */
	protected static function toggle_fields() {
		return array(
			'countdown'   => array( __( 'Countdown', 'next-launch' ), true ),
			'image'       => array( __( 'Rocket image', 'next-launch' ), true ),
			'provider'    => array( __( 'Provider & rocket name', 'next-launch' ), true ),
			'pad'         => array( __( 'Pad & location', 'next-launch' ), true ),
			'orbit'       => array( __( 'Target orbit', 'next-launch' ), false ),
			'status'      => array( __( 'Status badge', 'next-launch' ), true ),
			'description' => array( __( 'Mission description', 'next-launch' ), false ),
		);
	}

	public function get_name() {
		return 'next_launch';
	}

	public function get_title() {
		return __( 'Next Launch', 'next-launch' );
	}

	public function get_icon() {
		return 'eicon-countdown';
	}

	public function get_categories() {
		return array( 'next-launch' );
	}

	public function get_keywords() {
		return array( 'rocket', 'launch', 'spacex', 'countdown', 'space' );
	}

	/**
	 * Elementor renders widget updates (e.g. after a settings change) through
	 * an isolated AJAX fragment with no wp_head(), so the wp_enqueue_style()
	 * call inside SDNL_Shortcode::render() has nowhere to print. Declaring the
	 * dependency here instead makes Elementor load it for that fragment too.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return SDNL_Settings::get( 'load_css' ) ? array( 'sdnl' ) : array();
	}

	/**
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'sdnl' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'sdnl_section_content',
			array(
				'label' => __( 'Content', 'next-launch' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'next-launch' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Optional heading', 'next-launch' ),
			)
		);

		$this->add_control(
			'location',
			array(
				'label'       => __( 'Locations', 'next-launch' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => SDNL_Settings::get( 'default_locations' ),
				'placeholder' => '12,27',
				'description' => __( 'Comma separated launch location IDs. Leave empty to show launches from anywhere. Find IDs on Settings > Next Launch.', 'next-launch' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of launches', 'next-launch' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 10,
				'step'    => 1,
				'default' => SDNL_Settings::get( 'default_limit' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'next-launch' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'card',
				'options' => array(
					'card'    => __( 'Card', 'next-launch' ),
					'list'    => __( 'List', 'next-launch' ),
					'compact' => __( 'Compact', 'next-launch' ),
				),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Color surface', 'next-launch' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'  => __( 'Auto', 'next-launch' ),
					'light' => __( 'Light', 'next-launch' ),
					'dark'  => __( 'Dark', 'next-launch' ),
				),
			)
		);

		$this->add_control(
			'slider',
			array(
				'label'        => __( 'Slider mode', 'next-launch' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'next-launch' ),
				'label_off'    => __( 'No', 'next-launch' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Shows one launch at a time with prev/next arrows. Only takes effect when more than one launch is showing.', 'next-launch' ),
			)
		);

		$this->add_control(
			'timezone',
			array(
				'label'   => __( 'Launch time shown in', 'next-launch' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'site',
				'options' => array(
					'site'   => __( 'Site timezone', 'next-launch' ),
					'viewer' => __( "Visitor's timezone", 'next-launch' ),
					'utc'    => __( 'UTC', 'next-launch' ),
				),
			)
		);

		$this->add_control(
			'link',
			array(
				'label'       => __( 'Mission link URL', 'next-launch' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'default'     => array(
					'url' => '',
				),
				'description' => __( 'Optional. Wraps the mission name in a link to a page of your choosing.', 'next-launch' ),
			)
		);

		$this->add_control(
			'empty_text',
			array(
				'label'   => __( 'Empty message', 'next-launch' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'No upcoming launches scheduled.', 'next-launch' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'sdnl_section_fields',
			array(
				'label' => __( 'Fields to show', 'next-launch' ),
			)
		);

		foreach ( self::toggle_fields() as $key => $field ) {
			list( $label, $default_on ) = $field;

			$this->add_control(
				$key,
				array(
					'label'        => $label,
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'next-launch' ),
					'label_off'    => __( 'Hide', 'next-launch' ),
					'return_value' => 'yes',
					'default'      => $default_on ? 'yes' : '',
				)
			);
		}

		$this->end_controls_section();
	}

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

		echo SDNL_Shortcode::render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SDNL_Shortcode::render() escapes its own output.
	}
}
