<?php
/**
 * Settings screen.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Admin {

	const PAGE = 'sdnl-settings';

	/**
	 * Hook the admin screens.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_sdnl_flush_cache', array( __CLASS__, 'handle_flush' ) );
		add_action( 'wp_ajax_sdnl_search_locations', array( __CLASS__, 'ajax_search_locations' ) );
	}

	/**
	 * Add the options page.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Next Launch', 'spacedevs-next-launch' ),
			__( 'Next Launch', 'spacedevs-next-launch' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the setting.
	 */
	public static function register_settings() {
		register_setting(
			'sdnl_settings_group',
			SDNL_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'SDNL_Settings', 'sanitize' ),
				'default'           => SDNL_Settings::defaults(),
			)
		);
	}

	/**
	 * Clear cached API responses.
	 */
	public static function handle_flush() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'spacedevs-next-launch' ) );
		}

		check_admin_referer( 'sdnl_flush_cache' );

		SDNL_API::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'flushed' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);

		exit;
	}

	/**
	 * AJAX handler for the location search box.
	 */
	public static function ajax_search_locations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'spacedevs-next-launch' ) ), 403 );
		}

		check_ajax_referer( 'sdnl_locations', 'nonce' );

		$term    = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$results = SDNL_API::search_locations( $term );

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_message() ) );
		}

		wp_send_json_success( array( 'locations' => $results ) );
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = SDNL_Settings::get_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag.
		$flushed = isset( $_GET['flushed'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['flushed'] ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Next Launch', 'spacedevs-next-launch' ); ?></h1>

			<?php if ( $flushed ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Cached launch data cleared.', 'spacedevs-next-launch' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'sdnl_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="sdnl-locations"><?php esc_html_e( 'Default locations', 'spacedevs-next-launch' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="sdnl-locations"
								class="regular-text"
								name="<?php echo esc_attr( SDNL_Settings::OPTION ); ?>[default_locations]"
								value="<?php echo esc_attr( $settings['default_locations'] ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Comma separated location IDs. Leave empty to show launches from anywhere. Use the search below to find IDs.', 'spacedevs-next-launch' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sdnl-limit"><?php esc_html_e( 'Default number of launches', 'spacedevs-next-launch' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="sdnl-limit"
								min="1"
								max="10"
								name="<?php echo esc_attr( SDNL_Settings::OPTION ); ?>[default_limit]"
								value="<?php echo esc_attr( $settings['default_limit'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="sdnl-ttl"><?php esc_html_e( 'Cache lifetime', 'spacedevs-next-launch' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="sdnl-ttl"
								min="300"
								step="60"
								name="<?php echo esc_attr( SDNL_Settings::OPTION ); ?>[cache_ttl]"
								value="<?php echo esc_attr( $settings['cache_ttl'] ); ?>" />
							<?php esc_html_e( 'seconds', 'spacedevs-next-launch' ); ?>
							<p class="description">
								<?php esc_html_e( 'Minimum 300. The free API tier allows roughly 15 requests per hour, so short lifetimes will get the site throttled.', 'spacedevs-next-launch' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Development endpoint', 'spacedevs-next-launch' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									value="1"
									name="<?php echo esc_attr( SDNL_Settings::OPTION ); ?>[use_dev_endpoint]"
									<?php checked( $settings['use_dev_endpoint'], 1 ); ?> />
								<?php esc_html_e( 'Use lldev.thespacedevs.com instead of the production API', 'spacedevs-next-launch' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'The development endpoint serves cached, possibly stale data but has a looser rate limit. Useful while building a page. Turn this off before launch.', 'spacedevs-next-launch' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stylesheet', 'spacedevs-next-launch' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									value="1"
									name="<?php echo esc_attr( SDNL_Settings::OPTION ); ?>[load_css]"
									<?php checked( $settings['load_css'], 1 ); ?> />
								<?php esc_html_e( 'Load the bundled stylesheet', 'spacedevs-next-launch' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Turn this off if you are styling the markup in your theme.', 'spacedevs-next-launch' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Find location IDs', 'spacedevs-next-launch' ); ?></h2>
			<p><?php esc_html_e( 'Search the API for a launch site, then copy its ID into the field above or into a shortcode.', 'spacedevs-next-launch' ); ?></p>

			<p>
				<input type="search" id="sdnl-loc-search" class="regular-text" placeholder="<?php esc_attr_e( 'Cape Canaveral', 'spacedevs-next-launch' ); ?>" />
				<button type="button" class="button" id="sdnl-loc-go"><?php esc_html_e( 'Search', 'spacedevs-next-launch' ); ?></button>
			</p>

			<div id="sdnl-loc-results"></div>

			<hr />

			<h2><?php esc_html_e( 'Shortcode', 'spacedevs-next-launch' ); ?></h2>
			<p><code>[next_launch]</code> <?php esc_html_e( 'uses the defaults above. Every default can be overridden per shortcode:', 'spacedevs-next-launch' ); ?></p>
			<p><code>[next_launch location="12,27" limit="3" layout="list" countdown="yes" image="no"]</code></p>

			<table class="widefat striped" style="max-width:820px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'spacedevs-next-launch' ); ?></th>
						<th><?php esc_html_e( 'Accepts', 'spacedevs-next-launch' ); ?></th>
						<th><?php esc_html_e( 'What it does', 'spacedevs-next-launch' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$rows = array(
						array( 'location', '12,27', __( 'Which launch sites to include. Empty means worldwide.', 'spacedevs-next-launch' ) ),
						array( 'limit', '1-10', __( 'How many upcoming launches to show.', 'spacedevs-next-launch' ) ),
						array( 'layout', 'card, list, compact', __( 'Presentation style.', 'spacedevs-next-launch' ) ),
						array( 'theme', 'auto, light, dark', __( 'Color surface. "auto" blends into the page and follows the visitor\'s OS preference; "light" or "dark" force a matching card background regardless of where it\'s placed.', 'spacedevs-next-launch' ) ),
						array( 'slider', 'yes, no', __( 'Show one launch at a time with prev/next arrows instead of stacking them. Only takes effect when more than one launch is showing.', 'spacedevs-next-launch' ) ),
						array( 'title', __( 'any text', 'spacedevs-next-launch' ), __( 'Optional heading above the results.', 'spacedevs-next-launch' ) ),
						array( 'countdown', 'yes, no', __( 'Live countdown clock.', 'spacedevs-next-launch' ) ),
						array( 'image', 'yes, no', __( 'Rocket photo from the API.', 'spacedevs-next-launch' ) ),
						array( 'description', 'yes, no', __( 'Mission summary, trimmed to 45 words.', 'spacedevs-next-launch' ) ),
						array( 'provider', 'yes, no', __( 'Rocket and launch provider names.', 'spacedevs-next-launch' ) ),
						array( 'pad', 'yes, no', __( 'Pad and location names.', 'spacedevs-next-launch' ) ),
						array( 'orbit', 'yes, no', __( 'Target orbit.', 'spacedevs-next-launch' ) ),
						array( 'status', 'yes, no', __( 'Go / TBD status badge.', 'spacedevs-next-launch' ) ),
						array( 'link', __( 'a URL', 'spacedevs-next-launch' ), __( 'Wraps the mission name in a link to a page of your choosing.', 'spacedevs-next-launch' ) ),
						array( 'timezone', 'site, viewer, utc', __( 'Whose clock the launch time is shown in. "viewer" needs JavaScript.', 'spacedevs-next-launch' ) ),
						array( 'empty_text', __( 'any text', 'spacedevs-next-launch' ), __( 'Shown when nothing is scheduled or the API is unreachable.', 'spacedevs-next-launch' ) ),
						array( 'class', __( 'a CSS class', 'spacedevs-next-launch' ), __( 'Extra class on the wrapper for theme styling.', 'spacedevs-next-launch' ) ),
					);

					foreach ( $rows as $row ) {
						printf(
							'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$s</td></tr>',
							esc_html( $row[0] ),
							esc_html( $row[1] ),
							esc_html( $row[2] )
						);
					}
					?>
				</tbody>
			</table>

			<hr />

			<h2><?php esc_html_e( 'Cache', 'spacedevs-next-launch' ); ?></h2>
			<p>
				<?php
				$next = wp_next_scheduled( SDNL_Cron::HOOK );

				if ( $next ) {
					printf(
						/* translators: %s: formatted date and time. */
						esc_html__( 'Next background refresh: %s', 'spacedevs-next-launch' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
					);
				} else {
					esc_html_e( 'No background refresh is scheduled. Deactivate and reactivate the plugin to restore it.', 'spacedevs-next-launch' );
				}
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sdnl_flush_cache" />
				<?php wp_nonce_field( 'sdnl_flush_cache' ); ?>
				<?php submit_button( __( 'Clear cached launch data', 'spacedevs-next-launch' ), 'secondary', 'submit', false ); ?>
			</form>

			<p class="description" style="margin-top:1em">
				<?php
				printf(
					/* translators: %s: link to the Launch Library documentation. */
					esc_html__( 'Data from The Space Devs Launch Library 2. Review their terms and attribution guidance at %s.', 'spacedevs-next-launch' ),
					'<a href="https://thespacedevs.com/llapi" target="_blank" rel="noopener noreferrer">thespacedevs.com/llapi</a>'
				);
				?>
			</p>
		</div>

		<script>
		( function () {
			var input   = document.getElementById( 'sdnl-loc-search' );
			var button  = document.getElementById( 'sdnl-loc-go' );
			var results = document.getElementById( 'sdnl-loc-results' );

			if ( ! input || ! button || ! results ) {
				return;
			}

			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'sdnl_locations' ) ); ?>;

			function escapeHtml( value ) {
				var div = document.createElement( 'div' );
				div.appendChild( document.createTextNode( String( value ) ) );
				return div.innerHTML;
			}

			function search() {
				var term = input.value.trim();

				if ( ! term ) {
					return;
				}

				results.textContent = <?php echo wp_json_encode( __( 'Searching…', 'spacedevs-next-launch' ) ); ?>;

				var body = new URLSearchParams();
				body.append( 'action', 'sdnl_search_locations' );
				body.append( 'nonce', nonce );
				body.append( 'term', term );

				fetch( ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				} ).then( function ( response ) {
					return response.json();
				} ).then( function ( payload ) {
					if ( ! payload || ! payload.success ) {
						results.textContent = ( payload && payload.data && payload.data.message )
							? payload.data.message
							: <?php echo wp_json_encode( __( 'Search failed.', 'spacedevs-next-launch' ) ); ?>;
						return;
					}

					var list = payload.data.locations || [];

					if ( ! list.length ) {
						results.textContent = <?php echo wp_json_encode( __( 'No matching launch sites.', 'spacedevs-next-launch' ) ); ?>;
						return;
					}

					var html = '<table class="widefat striped" style="max-width:640px"><thead><tr>' +
						'<th style="width:80px"><?php echo esc_js( __( 'ID', 'spacedevs-next-launch' ) ); ?></th>' +
						'<th><?php echo esc_js( __( 'Launch site', 'spacedevs-next-launch' ) ); ?></th>' +
						'<th style="width:90px"><?php echo esc_js( __( 'Country', 'spacedevs-next-launch' ) ); ?></th>' +
						'</tr></thead><tbody>';

					list.forEach( function ( item ) {
						html += '<tr><td><code>' + escapeHtml( item.id ) + '</code></td><td>' +
							escapeHtml( item.name ) + '</td><td>' +
							escapeHtml( item.country ) + '</td></tr>';
					} );

					html += '</tbody></table>';
					results.innerHTML = html;
				} ).catch( function () {
					results.textContent = <?php echo wp_json_encode( __( 'Search failed.', 'spacedevs-next-launch' ) ); ?>;
				} );
			}

			button.addEventListener( 'click', search );

			input.addEventListener( 'keydown', function ( event ) {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					search();
				}
			} );
		} )();
		</script>
		<?php
	}
}
