<?php
/**
 * Launch Library 2 API client.
 *
 * All requests go through a transient cache. If the API is unreachable or rate
 * limited, the last good response is served instead so the widget never
 * collapses to an empty box on a client site.
 *
 * @package nextrlt
 */

defined( 'ABSPATH' ) || exit;

/**
 * Launch Library 2 API client.
 */
class NEXTRLT_API {

	const BASE_PROD      = 'https://ll.thespacedevs.com/2.2.0/';
	const BASE_DEV       = 'https://lldev.thespacedevs.com/2.2.0/';
	const REGISTRY       = 'nextrlt_query_registry';
	const STALE_STORE    = 'nextrlt_stale_store';
	const LOCATION_CACHE = 'nextrlt_locations_';
	const MAX_STALE      = 20;

	/**
	 * Active API base URL.
	 *
	 * @return string
	 */
	public static function base() {
		return NEXTRLT_Settings::get( 'use_dev_endpoint' ) ? self::BASE_DEV : self::BASE_PROD;
	}

	/**
	 * Normalize a comma separated list of numeric IDs.
	 *
	 * @param string $raw Raw list.
	 * @return string Comma separated, deduplicated, ordered list.
	 */
	public static function sanitize_id_list( $raw ) {
		$parts = preg_split( '/[^0-9]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $parts ) ) {
			return '';
		}

		$parts = array_map( 'absint', $parts );
		$parts = array_filter( $parts );
		$parts = array_values( array_unique( $parts ) );

		sort( $parts );

		return implode( ',', $parts );
	}

	/**
	 * Build the cache key for a query.
	 *
	 * @param string $locations Location ID list.
	 * @param int    $limit     Result count.
	 * @return string
	 */
	protected static function cache_key( $locations, $limit ) {
		return 'nextrlt_' . md5( self::base() . '|' . $locations . '|' . $limit );
	}

	/**
	 * Fetch upcoming launches for the given locations.
	 *
	 * @param string $locations Comma separated location IDs. Empty means all.
	 * @param int    $limit     How many launches to return, 1-10.
	 * @param bool   $force     Bypass the fresh cache check.
	 * @return array|WP_Error Array of launch arrays, or WP_Error.
	 */
	public static function get_upcoming( $locations, $limit, $force = false ) {
		$locations = self::sanitize_id_list( $locations );
		$limit     = max( 1, min( 10, absint( $limit ) ) );
		$ttl       = absint( NEXTRLT_Settings::get( 'cache_ttl' ) );
		$key       = self::cache_key( $locations, $limit );

		self::register_query( $key, $locations, $limit );

		if ( ! $force ) {
			$cached = get_transient( $key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		// Back off after a failure so a broken API does not mean a request on
		// every single page view.
		if ( ! $force && get_transient( $key . '_fail' ) ) {
			return self::stale( $key, new WP_Error( 'nextrlt_backoff', __( 'Waiting before retrying the launch API.', 'next-rocket-launch-tracker' ) ) );
		}

		$args = array(
			// The upstream "upcoming" list keeps a just-launched item around for a
			// while after liftoff, so over-fetch and filter those out below rather
			// than trusting the endpoint to only return launches still ahead of us.
			'limit' => min( 50, $limit + 5 ),
		);

		if ( '' !== $locations ) {
			$args['location__ids'] = $locations;
		}

		$url = add_query_arg( $args, self::base() . 'launch/upcoming/' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( $key . '_fail', 1, 5 * MINUTE_IN_SECONDS );

			return self::stale( $key, $response );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 429 === $code ) {
			// Rate limited. Sit out for a while.
			set_transient( $key . '_fail', 1, 20 * MINUTE_IN_SECONDS );

			return self::stale(
				$key,
				new WP_Error( 'nextrlt_rate_limited', __( 'The launch API rate limit was reached.', 'next-rocket-launch-tracker' ) )
			);
		}

		if ( 200 !== $code ) {
			set_transient( $key . '_fail', 1, 5 * MINUTE_IN_SECONDS );

			return self::stale(
				$key,
				new WP_Error(
					'nextrlt_http_error',
					sprintf(
						/* translators: %d: HTTP status code. */
						__( 'The launch API returned status %d.', 'next-rocket-launch-tracker' ),
						$code
					)
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['results'] ) || ! is_array( $body['results'] ) ) {
			set_transient( $key . '_fail', 1, 5 * MINUTE_IN_SECONDS );

			return self::stale(
				$key,
				new WP_Error( 'nextrlt_bad_payload', __( 'The launch API response could not be read.', 'next-rocket-launch-tracker' ) )
			);
		}

		$launches = array();

		foreach ( $body['results'] as $result ) {
			if ( is_array( $result ) && ! self::already_flown( $result ) ) {
				$launches[] = self::shape( $result );
			}
		}

		$launches = array_slice( $launches, 0, $limit );

		delete_transient( $key . '_fail' );
		set_transient( $key, $launches, $ttl );
		self::store_stale( $key, $launches );

		return $launches;
	}

	/**
	 * Whether a launch has already resolved (flown, one way or another).
	 *
	 * The upstream "upcoming" list keeps recently launched items in its results
	 * for a while after the fact, identified only by a terminal status, not by
	 * NET falling in the past (a hold can push the real liftoff past NET while
	 * the launch is still very much upcoming).
	 *
	 * @param array $r Raw result.
	 * @return bool
	 */
	protected static function already_flown( $r ) {
		$abbrev = isset( $r['status']['abbrev'] ) ? (string) $r['status']['abbrev'] : '';

		return in_array( $abbrev, array( 'Success', 'Failure', 'Partial Failure' ), true );
	}

	/**
	 * Reduce an API result to the fields the template needs.
	 *
	 * @param array $r Raw result.
	 * @return array
	 */
	protected static function shape( $r ) {
		$get = static function ( $arr, $path, $fallback = '' ) {
			$node = $arr;

			foreach ( explode( '.', $path ) as $segment ) {
				if ( ! is_array( $node ) || ! isset( $node[ $segment ] ) ) {
					return $fallback;
				}

				$node = $node[ $segment ];
			}

			return ( null === $node || '' === $node ) ? $fallback : $node;
		};

		return array(
			'id'           => (string) $get( $r, 'id' ),
			'name'         => (string) $get( $r, 'name' ),
			'net'          => (string) $get( $r, 'net' ),
			'window_start' => (string) $get( $r, 'window_start' ),
			'window_end'   => (string) $get( $r, 'window_end' ),
			'status'       => (string) $get( $r, 'status.name' ),
			'status_abbr'  => (string) $get( $r, 'status.abbrev' ),
			'status_desc'  => (string) $get( $r, 'status.description' ),
			'probability'  => $get( $r, 'probability', null ),
			'provider'     => (string) $get( $r, 'launch_service_provider.name' ),
			'rocket'       => (string) $get( $r, 'rocket.configuration.full_name', $get( $r, 'rocket.configuration.name' ) ),
			'mission'      => (string) $get( $r, 'mission.name' ),
			'mission_desc' => (string) $get( $r, 'mission.description' ),
			'orbit'        => (string) $get( $r, 'mission.orbit.name' ),
			'pad'          => (string) $get( $r, 'pad.name' ),
			'pad_map'      => (string) $get( $r, 'pad.map_url' ),
			'location'     => (string) $get( $r, 'pad.location.name' ),
			'location_id'  => (int) $get( $r, 'pad.location.id', 0 ),
			'image'        => (string) $get( $r, 'image' ),
			'webcast'      => (bool) $get( $r, 'webcast_live', false ),
			'url'          => (string) $get( $r, 'url' ),
			'slug'         => (string) $get( $r, 'slug' ),
		);
	}

	/**
	 * Search launch locations by name.
	 *
	 * @param string $term Search term.
	 * @return array|WP_Error
	 */
	public static function search_locations( $term ) {
		$term = trim( wp_strip_all_tags( (string) $term ) );

		if ( '' === $term ) {
			return array();
		}

		return self::query_locations(
			array(
				'search' => rawurlencode( $term ),
				'limit'  => 25,
			),
			'search_' . strtolower( $term )
		);
	}

	/**
	 * The most active launch locations, for pre-populating a picker before the
	 * visitor has searched for anything.
	 *
	 * @param int $limit How many locations to return.
	 * @return array|WP_Error
	 */
	public static function popular_locations( $limit = 25 ) {
		return self::query_locations(
			array(
				'ordering' => '-total_launch_count',
				'limit'    => max( 1, min( 50, absint( $limit ) ) ),
			),
			'popular_' . absint( $limit )
		);
	}

	/**
	 * Fetch and shape a list of locations from the location/ endpoint.
	 *
	 * @param array  $args         Query args for the endpoint.
	 * @param string $cache_suffix Suffix distinguishing this query in the cache.
	 * @return array|WP_Error
	 */
	protected static function query_locations( $args, $cache_suffix ) {
		$key    = self::LOCATION_CACHE . md5( self::base() . '|' . $cache_suffix );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg( $args, self::base() . 'location/' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'nextrlt_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The launch API returned status %d.', 'next-rocket-launch-tracker' ),
					$code
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['results'] ) || ! is_array( $body['results'] ) ) {
			return new WP_Error( 'nextrlt_bad_payload', __( 'The location list could not be read.', 'next-rocket-launch-tracker' ) );
		}

		$locations = array();

		foreach ( $body['results'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id'] ) ) {
				continue;
			}

			$locations[] = array(
				'id'      => absint( $row['id'] ),
				'name'    => isset( $row['name'] ) ? (string) $row['name'] : '',
				'country' => isset( $row['country_code'] ) ? (string) $row['country_code'] : '',
				'pads'    => isset( $row['total_launch_count'] ) ? absint( $row['total_launch_count'] ) : 0,
			);
		}

		set_transient( $key, $locations, WEEK_IN_SECONDS );

		return $locations;
	}

	/**
	 * Record a query so the cron job knows what to keep warm.
	 *
	 * @param string $key       Cache key.
	 * @param string $locations Location list.
	 * @param int    $limit     Result count.
	 */
	protected static function register_query( $key, $locations, $limit ) {
		$registry = get_option( self::REGISTRY, array() );

		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		$now      = time();
		$existing = isset( $registry[ $key ] ) ? $registry[ $key ] : array();

		// Only write when something meaningful changed, to avoid an option
		// write on every page view.
		$last_used = isset( $existing['last_used'] ) ? (int) $existing['last_used'] : 0;

		if ( $now - $last_used < HOUR_IN_SECONDS ) {
			return;
		}

		$registry[ $key ] = array(
			'locations' => $locations,
			'limit'     => $limit,
			'last_used' => $now,
		);

		// Drop queries no page has used in a week.
		foreach ( $registry as $hash => $entry ) {
			$used = isset( $entry['last_used'] ) ? (int) $entry['last_used'] : 0;

			if ( $now - $used > WEEK_IN_SECONDS ) {
				unset( $registry[ $hash ] );
			}
		}

		update_option( self::REGISTRY, $registry, false );
	}

	/**
	 * Registered queries.
	 *
	 * @return array
	 */
	public static function get_registry() {
		$registry = get_option( self::REGISTRY, array() );

		return is_array( $registry ) ? $registry : array();
	}

	/**
	 * Keep a copy of the last good response outside the transient lifetime.
	 *
	 * @param string $key      Cache key.
	 * @param array  $launches Launch data.
	 */
	protected static function store_stale( $key, $launches ) {
		$store = get_option( self::STALE_STORE, array() );

		if ( ! is_array( $store ) ) {
			$store = array();
		}

		$store[ $key ] = array(
			'data' => $launches,
			'time' => time(),
		);

		if ( count( $store ) > self::MAX_STALE ) {
			uasort(
				$store,
				static function ( $a, $b ) {
					$at = isset( $a['time'] ) ? (int) $a['time'] : 0;
					$bt = isset( $b['time'] ) ? (int) $b['time'] : 0;

					return $bt <=> $at;
				}
			);

			$store = array_slice( $store, 0, self::MAX_STALE, true );
		}

		update_option( self::STALE_STORE, $store, false );
	}

	/**
	 * Serve the last good response, or pass the error through.
	 *
	 * @param string   $key   Cache key.
	 * @param WP_Error $error Original error.
	 * @return array|WP_Error
	 */
	protected static function stale( $key, $error ) {
		$store = get_option( self::STALE_STORE, array() );

		if ( is_array( $store ) && isset( $store[ $key ]['data'] ) && is_array( $store[ $key ]['data'] ) ) {
			return $store[ $key ]['data'];
		}

		return $error;
	}

	/**
	 * Clear every cached response.
	 */
	public static function flush_cache() {
		foreach ( array_keys( self::get_registry() ) as $key ) {
			delete_transient( $key );
			delete_transient( $key . '_fail' );
		}

		delete_option( self::STALE_STORE );
	}
}
