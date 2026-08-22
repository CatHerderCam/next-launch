<?php
/**
 * Front-end shortcode.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Shortcode {

	/**
	 * Hook the shortcode.
	 */
	public static function init() {
		add_shortcode( 'next_launch', array( __CLASS__, 'render' ) );
	}

	/**
	 * Interpret a shortcode boolean attribute.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	protected static function is_true( $value ) {
		return in_array(
			strtolower( trim( (string) $value ) ),
			array( '1', 'true', 'yes', 'on' ),
			true
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$defaults = array(
			'location'    => SDNL_Settings::get( 'default_locations' ),
			'limit'       => SDNL_Settings::get( 'default_limit' ),
			'layout'      => 'card',
			'theme'       => 'auto',
			'slider'      => 'no',
			'title'       => '',
			'countdown'   => 'yes',
			'image'       => 'yes',
			'description' => 'no',
			'provider'    => 'yes',
			'pad'         => 'yes',
			'orbit'       => 'no',
			'status'      => 'yes',
			// A URL of your own, for example a launch schedule page. Empty
			// renders plain text. The API does not expose a public detail page.
			'link'        => '',
			'timezone'    => 'site',
			'empty_text'  => __( 'No upcoming launches scheduled.', 'next-launch' ),
			'class'       => '',
		);

		$atts = shortcode_atts( $defaults, $atts, 'next_launch' );

		$locations = SDNL_API::sanitize_id_list( $atts['location'] );
		$limit     = max( 1, min( 10, absint( $atts['limit'] ) ) );
		$layout    = in_array( $atts['layout'], array( 'card', 'list', 'compact' ), true ) ? $atts['layout'] : 'card';
		$theme     = in_array( $atts['theme'], array( 'auto', 'light', 'dark' ), true ) ? $atts['theme'] : 'auto';
		$tz_mode   = in_array( $atts['timezone'], array( 'site', 'viewer', 'utc' ), true ) ? $atts['timezone'] : 'site';

		$launches = SDNL_API::get_upcoming( $locations, $limit );
		$slider   = self::is_true( $atts['slider'] ) && is_array( $launches ) && count( $launches ) > 1;

		if ( SDNL_Settings::get( 'load_css' ) ) {
			wp_enqueue_style( 'sdnl' );
		}

		if ( self::is_true( $atts['countdown'] ) || 'viewer' === $tz_mode || $slider ) {
			wp_enqueue_script( 'sdnl' );
		}

		$classes = array( 'sdnl', 'sdnl--' . $layout );

		if ( 'auto' !== $theme ) {
			$classes[] = 'sdnl--theme-' . $theme;
		}

		if ( $slider ) {
			$classes[] = 'sdnl--slider';
		}

		if ( '' !== trim( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( trim( $atts['class'] ) );
		}

		ob_start();

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';

		if ( '' !== trim( $atts['title'] ) ) {
			echo '<h3 class="sdnl__heading">' . esc_html( $atts['title'] ) . '</h3>';
		}

		if ( is_wp_error( $launches ) ) {
			// Never surface an API error to a site visitor. Log it and show the
			// same neutral message as an empty result.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'SpaceDevs Next Launch: ' . $launches->get_error_message() ); // phpcs:ignore
			}

			echo '<p class="sdnl__empty">' . esc_html( $atts['empty_text'] ) . '</p>';
			echo '</div>';

			return ob_get_clean();
		}

		if ( empty( $launches ) ) {
			echo '<p class="sdnl__empty">' . esc_html( $atts['empty_text'] ) . '</p>';
			echo '</div>';

			return ob_get_clean();
		}

		if ( $slider ) {
			echo '<div class="sdnl__viewport">';
			printf(
				'<button type="button" class="sdnl__arrow sdnl__arrow--prev" aria-label="%s">&#8249;</button>',
				esc_attr__( 'Previous launch', 'next-launch' )
			);
		}

		echo '<ul class="sdnl__list">';

		foreach ( $launches as $launch ) {
			self::render_launch( $launch, $atts, $tz_mode );
		}

		echo '</ul>';

		if ( $slider ) {
			printf(
				'<button type="button" class="sdnl__arrow sdnl__arrow--next" aria-label="%s">&#8250;</button>',
				esc_attr__( 'Next launch', 'next-launch' )
			);
			echo '</div>';
		}

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Render a single launch item.
	 *
	 * @param array  $launch  Shaped launch data.
	 * @param array  $atts    Shortcode attributes.
	 * @param string $tz_mode site, viewer or utc.
	 */
	protected static function render_launch( $launch, $atts, $tz_mode ) {
		$timestamp = ! empty( $launch['net'] ) ? strtotime( $launch['net'] ) : 0;
		$status    = isset( $launch['status_abbr'] ) ? $launch['status_abbr'] : '';

		echo '<li class="sdnl__item">';

		if ( self::is_true( $atts['image'] ) && ! empty( $launch['image'] ) ) {
			printf(
				'<div class="sdnl__media"><img class="sdnl__image" src="%1$s" alt="%2$s" loading="lazy" decoding="async" /></div>',
				esc_url( $launch['image'] ),
				esc_attr( $launch['rocket'] ? $launch['rocket'] : $launch['name'] )
			);
		}

		echo '<div class="sdnl__body">';

		if ( self::is_true( $atts['status'] ) && '' !== $status ) {
			printf(
				'<span class="sdnl__status sdnl__status--%1$s">%2$s</span>',
				esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) ) ),
				esc_html( $launch['status'] ? $launch['status'] : $status )
			);
		}

		$headline = $launch['mission'] ? $launch['mission'] : $launch['name'];

		echo '<h4 class="sdnl__name">';

		if ( '' !== trim( (string) $atts['link'] ) ) {
			printf(
				'<a class="sdnl__link" href="%1$s">%2$s</a>',
				esc_url( $atts['link'] ),
				esc_html( $headline )
			);
		} else {
			echo esc_html( $headline );
		}

		echo '</h4>';

		$meta = array();

		if ( self::is_true( $atts['provider'] ) ) {
			if ( ! empty( $launch['rocket'] ) ) {
				$meta[] = $launch['rocket'];
			}

			if ( ! empty( $launch['provider'] ) ) {
				$meta[] = $launch['provider'];
			}
		}

		if ( self::is_true( $atts['orbit'] ) && ! empty( $launch['orbit'] ) ) {
			$meta[] = $launch['orbit'];
		}

		if ( ! empty( $meta ) ) {
			echo '<p class="sdnl__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		}

		if ( $timestamp ) {
			self::render_time( $timestamp, $launch['net'], $tz_mode );
		}

		if ( self::is_true( $atts['countdown'] ) && $timestamp ) {
			printf(
				'<p class="sdnl__countdown" data-sdnl-countdown data-net="%1$s"><span class="sdnl__countdown-value">%2$s</span></p>',
				esc_attr( gmdate( 'c', $timestamp ) ),
				esc_html( self::static_countdown( $timestamp ) )
			);
		}

		if ( self::is_true( $atts['pad'] ) ) {
			$where = array();

			if ( ! empty( $launch['pad'] ) ) {
				$where[] = $launch['pad'];
			}

			if ( ! empty( $launch['location'] ) ) {
				$where[] = $launch['location'];
			}

			if ( ! empty( $where ) ) {
				echo '<p class="sdnl__pad">' . esc_html( implode( ', ', $where ) ) . '</p>';
			}
		}

		if ( self::is_true( $atts['description'] ) && ! empty( $launch['mission_desc'] ) ) {
			echo '<p class="sdnl__description">' . esc_html( wp_trim_words( $launch['mission_desc'], 45 ) ) . '</p>';
		}

		echo '</div>';
		echo '</li>';
	}

	/**
	 * Render the launch time.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $iso       Raw ISO string from the API.
	 * @param string $tz_mode   site, viewer or utc.
	 */
	protected static function render_time( $timestamp, $iso, $tz_mode ) {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		if ( 'utc' === $tz_mode ) {
			$text = gmdate( $format, $timestamp ) . ' UTC';
		} else {
			$text = wp_date( $format, $timestamp );
		}

		$viewer_attr = ( 'viewer' === $tz_mode ) ? ' data-sdnl-localtime' : '';

		printf(
			'<p class="sdnl__time"><time datetime="%1$s"%2$s>%3$s</time></p>',
			esc_attr( gmdate( 'c', $timestamp ) ),
			$viewer_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
			esc_html( $text )
		);
	}

	/**
	 * Server-side countdown text, used before JavaScript takes over and as the
	 * no-JS fallback.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	protected static function static_countdown( $timestamp ) {
		$diff = $timestamp - time();

		if ( $diff <= 0 ) {
			return __( 'Launching now', 'next-launch' );
		}

		return sprintf(
			/* translators: %s: human readable time difference, for example "2 days". */
			__( 'T-minus %s', 'next-launch' ),
			human_time_diff( time(), $timestamp )
		);
	}
}
