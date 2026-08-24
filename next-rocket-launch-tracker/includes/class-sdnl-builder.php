<?php
/**
 * Shortcode builder screen.
 *
 * @package sdnl
 */

defined( 'ABSPATH' ) || exit;

class SDNL_Builder {

	const PAGE = 'sdnl-builder';

	/**
	 * Boolean attributes rendered as toggle switches, with their shortcode default.
	 *
	 * @var array
	 */
	const TOGGLES = array(
		'countdown'   => array( 'label' => 'Countdown clock', 'default' => true ),
		'image'       => array( 'label' => 'Rocket image', 'default' => true ),
		'description' => array( 'label' => 'Mission description', 'default' => false ),
		'provider'    => array( 'label' => 'Rocket & provider names', 'default' => true ),
		'pad'         => array( 'label' => 'Pad & location names', 'default' => true ),
		'orbit'       => array( 'label' => 'Target orbit', 'default' => false ),
		'status'      => array( 'label' => 'Go / TBD status badge', 'default' => true ),
	);

	/**
	 * Hook the admin screen.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_sdnl_popular_locations', array( __CLASS__, 'ajax_popular_locations' ) );
	}

	/**
	 * AJAX handler that pre-populates the location picker before any search.
	 */
	public static function ajax_popular_locations() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'next-rocket-launch-tracker' ) ), 403 );
		}

		check_ajax_referer( 'sdnl_locations', 'nonce' );

		$results = SDNL_API::popular_locations();

		if ( is_wp_error( $results ) ) {
			wp_send_json_error( array( 'message' => $results->get_error_message() ) );
		}

		wp_send_json_success( array( 'locations' => $results ) );
	}

	/**
	 * Add the submenu page under Settings, alongside the main options screen.
	 */
	public static function add_page() {
		add_submenu_page(
			'options-general.php',
			__( 'Next Rocket Launch Tracker Shortcode Builder', 'next-rocket-launch-tracker' ),
			__( 'Next Rocket Launch Tracker Builder', 'next-rocket-launch-tracker' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue the builder's own assets, only on its screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'sdnl-builder',
			SDNL_URL . 'assets/sdnl-builder.js',
			array(),
			SDNL_VERSION,
			true
		);

		wp_localize_script(
			'sdnl-builder',
			'sdnlBuilder',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'sdnl_locations' ),
				'strings' => array(
					'searching' => __( 'Searching…', 'next-rocket-launch-tracker' ),
					'loading'   => __( 'Loading launch sites…', 'next-rocket-launch-tracker' ),
					'noResults' => __( 'No matching launch sites.', 'next-rocket-launch-tracker' ),
					'failed'    => __( 'Could not load launch sites.', 'next-rocket-launch-tracker' ),
					'copied'    => __( 'Copied!', 'next-rocket-launch-tracker' ),
					'copy'      => __( 'Copy shortcode', 'next-rocket-launch-tracker' ),
				),
			)
		);

		wp_enqueue_style(
			'sdnl-builder',
			SDNL_URL . 'assets/sdnl-builder.css',
			array(),
			SDNL_VERSION
		);
	}

	/**
	 * Render the builder page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = SDNL_Settings::get_all();
		?>
		<div class="wrap sdnl-builder">
			<h1><?php esc_html_e( 'Next Rocket Launch Tracker Shortcode Builder', 'next-rocket-launch-tracker' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: link to the plugin settings screen. */
					esc_html__( 'Pick the options below to build a %1$s shortcode. Defaults come from the %2$s screen.', 'next-rocket-launch-tracker' ),
					'<code>[next_launch]</code>',
					'<a href="' . esc_url( admin_url( 'options-general.php?page=' . SDNL_Admin::PAGE ) ) . '">' . esc_html__( 'Next Rocket Launch Tracker settings', 'next-rocket-launch-tracker' ) . '</a>'
				);
				?>
			</p>

			<div class="sdnl-builder__layout">
				<div class="sdnl-builder__form">

					<h2><?php esc_html_e( 'Content', 'next-rocket-launch-tracker' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="sdnl-b-location"><?php esc_html_e( 'Locations', 'next-rocket-launch-tracker' ); ?></label></th>
							<td>
								<input type="search" id="sdnl-b-loc-search" class="regular-text" placeholder="<?php esc_attr_e( 'Filter by name, e.g. Cape Canaveral', 'next-rocket-launch-tracker' ); ?>" />
								<select id="sdnl-b-location" multiple size="8" class="sdnl-builder__locations" data-sdnl-field="location"></select>
								<p class="description">
									<?php esc_html_e( 'Ctrl/Cmd-click to select more than one. Nothing selected uses the site default.', 'next-rocket-launch-tracker' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sdnl-b-limit"><?php esc_html_e( 'Number of launches', 'next-rocket-launch-tracker' ); ?></label></th>
							<td><input type="number" id="sdnl-b-limit" min="1" max="10" data-sdnl-field="limit" placeholder="<?php echo esc_attr( $settings['default_limit'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="sdnl-b-title"><?php esc_html_e( 'Heading', 'next-rocket-launch-tracker' ); ?></label></th>
							<td><input type="text" id="sdnl-b-title" class="regular-text" data-sdnl-field="title" placeholder="<?php esc_attr_e( 'Optional text shown above the results', 'next-rocket-launch-tracker' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="sdnl-b-link"><?php esc_html_e( 'Link URL', 'next-rocket-launch-tracker' ); ?></label></th>
							<td>
								<input type="url" id="sdnl-b-link" class="regular-text" data-sdnl-field="link" placeholder="https://" />
								<p class="description"><?php esc_html_e( 'Wraps the mission name in a link, for example to a launch schedule page.', 'next-rocket-launch-tracker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sdnl-b-empty"><?php esc_html_e( 'Empty state text', 'next-rocket-launch-tracker' ); ?></label></th>
							<td><input type="text" id="sdnl-b-empty" class="regular-text" data-sdnl-field="empty_text" placeholder="<?php esc_attr_e( 'No upcoming launches scheduled.', 'next-rocket-launch-tracker' ); ?>" /></td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Presentation', 'next-rocket-launch-tracker' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Layout', 'next-rocket-launch-tracker' ); ?></th>
							<td>
								<select id="sdnl-b-layout" data-sdnl-field="layout">
									<option value="card"><?php esc_html_e( 'Card', 'next-rocket-launch-tracker' ); ?></option>
									<option value="list"><?php esc_html_e( 'List', 'next-rocket-launch-tracker' ); ?></option>
									<option value="compact"><?php esc_html_e( 'Compact', 'next-rocket-launch-tracker' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Color surface', 'next-rocket-launch-tracker' ); ?></th>
							<td>
								<select id="sdnl-b-theme" data-sdnl-field="theme">
									<option value="auto"><?php esc_html_e( 'Auto (blend into the page)', 'next-rocket-launch-tracker' ); ?></option>
									<option value="light"><?php esc_html_e( 'Light card', 'next-rocket-launch-tracker' ); ?></option>
									<option value="dark"><?php esc_html_e( 'Dark card', 'next-rocket-launch-tracker' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Force a light or dark background regardless of the surrounding page.', 'next-rocket-launch-tracker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Slider', 'next-rocket-launch-tracker' ); ?></th>
							<td>
								<label class="sdnl-toggle">
									<input type="checkbox" id="sdnl-b-slider" data-sdnl-toggle="slider" />
									<span class="sdnl-toggle__track" aria-hidden="true"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Show one launch at a time with prev/next arrows instead of stacking them. Only takes effect with more than one launch.', 'next-rocket-launch-tracker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Launch time shown in', 'next-rocket-launch-tracker' ); ?></th>
							<td>
								<select id="sdnl-b-timezone" data-sdnl-field="timezone">
									<option value="site"><?php esc_html_e( 'Site timezone', 'next-rocket-launch-tracker' ); ?></option>
									<option value="viewer"><?php esc_html_e( "Visitor's local timezone (needs JavaScript)", 'next-rocket-launch-tracker' ); ?></option>
									<option value="utc"><?php esc_html_e( 'UTC', 'next-rocket-launch-tracker' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="sdnl-b-class"><?php esc_html_e( 'Extra CSS class', 'next-rocket-launch-tracker' ); ?></label></th>
							<td><input type="text" id="sdnl-b-class" class="regular-text" data-sdnl-field="class" /></td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Show or hide', 'next-rocket-launch-tracker' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php foreach ( self::TOGGLES as $key => $toggle ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $toggle['label'] ); ?></th>
								<td>
									<label class="sdnl-toggle">
										<input
											type="checkbox"
											id="sdnl-b-<?php echo esc_attr( $key ); ?>"
											data-sdnl-toggle="<?php echo esc_attr( $key ); ?>"
											<?php checked( $toggle['default'] ); ?> />
										<span class="sdnl-toggle__track" aria-hidden="true"></span>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>

				<div class="sdnl-builder__preview">
					<h2><?php esc_html_e( 'Shortcode', 'next-rocket-launch-tracker' ); ?></h2>
					<textarea id="sdnl-b-output" class="large-text code" rows="4" readonly></textarea>
					<p>
						<button type="button" class="button button-primary" id="sdnl-b-copy"><?php esc_html_e( 'Copy shortcode', 'next-rocket-launch-tracker' ); ?></button>
						<span id="sdnl-b-copy-status" class="sdnl-builder__copy-status" role="status"></span>
					</p>
					<p class="description">
						<?php esc_html_e( 'Paste this into any post, page, or widget area that supports shortcodes.', 'next-rocket-launch-tracker' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
