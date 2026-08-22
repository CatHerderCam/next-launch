<?php
/**
 * Background cache refresh.
 *
 * Visitors always read from cache. This job repopulates it in the background so
 * nobody waits on an HTTP request to an external API during a page load.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Cron {

	const HOOK = 'sdnl_refresh_cache';

	/**
	 * The free API tier is rate limited. Never refresh more than this many
	 * distinct queries in one run.
	 */
	const MAX_PER_RUN = 4;

	/**
	 * Hook the job.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
	}

	/**
	 * Schedule the job.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', self::HOOK );
		}
	}

	/**
	 * Remove the job.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Refresh the most recently used queries.
	 */
	public static function run() {
		$registry = SDNL_API::get_registry();

		if ( empty( $registry ) ) {
			return;
		}

		uasort(
			$registry,
			static function ( $a, $b ) {
				$at = isset( $a['last_used'] ) ? (int) $a['last_used'] : 0;
				$bt = isset( $b['last_used'] ) ? (int) $b['last_used'] : 0;

				return $bt <=> $at;
			}
		);

		$done = 0;

		foreach ( $registry as $entry ) {
			if ( $done >= self::MAX_PER_RUN ) {
				break;
			}

			$locations = isset( $entry['locations'] ) ? $entry['locations'] : '';
			$limit     = isset( $entry['limit'] ) ? (int) $entry['limit'] : 1;

			SDNL_API::get_upcoming( $locations, $limit, true );

			$done++;

			// Be a polite API consumer.
			if ( $done < self::MAX_PER_RUN ) {
				sleep( 2 );
			}
		}
	}
}
