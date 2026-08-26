<?php
/**
 * Plugin settings storage.
 *
 * @package nextrlt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings storage.
 */
class NEXTRLT_Settings {

	const OPTION = 'nextrlt_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Cape Canaveral SFS and Kennedy Space Center. Verify these against
			// the Locations browser on the settings screen before relying on them.
			'default_locations' => '12,27',
			'default_limit'     => 1,
			'cache_ttl'         => 900,
			'use_dev_endpoint'  => 0,
			'load_css'          => 1,
		);
	}

	/**
	 * Get all settings, merged over defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback if the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::get_all();

		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Sanitize settings coming from the options screen.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = self::defaults();

		if ( ! is_array( $input ) ) {
			return $out;
		}

		if ( isset( $input['default_locations'] ) ) {
			$out['default_locations'] = NEXTRLT_API::sanitize_id_list( $input['default_locations'] );
		}

		if ( isset( $input['default_limit'] ) ) {
			$out['default_limit'] = max( 1, min( 10, absint( $input['default_limit'] ) ) );
		}

		if ( isset( $input['cache_ttl'] ) ) {
			// Never allow a TTL under five minutes. The free API tier is rate
			// limited and a short TTL will get the site throttled.
			$out['cache_ttl'] = max( 300, min( DAY_IN_SECONDS, absint( $input['cache_ttl'] ) ) );
		}

		$out['use_dev_endpoint'] = empty( $input['use_dev_endpoint'] ) ? 0 : 1;
		$out['load_css']         = empty( $input['load_css'] ) ? 0 : 1;

		return $out;
	}
}
