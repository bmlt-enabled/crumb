<?php
/**
 * Plugin Name: BMLT Client
 * Plugin URI: https://github.com/bmlt-enabled/bmlt-client
 * Description: Embeds the BMLT Client meeting finder widget on any page or post using a shortcode.
 * Version: 1.0.0
 * Author: BMLT Enabled
 * Author URI: https://bmlt.app
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: bmlt-client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMLTCLIENT_VERSION', '1.0.0' );

class BmltClient {

	private static ?self $instance = null;

	const DEFAULT_CDN_URL = 'https://cdn.aws.bmlt.app/bmlt-client/app.js';

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'bmlt_client', [ static::class, 'setup_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ static::class, 'assets' ] );
		add_action( 'admin_menu', [ static::class, 'admin_menu' ] );
		add_action( 'admin_init', [ static::class, 'register_settings' ] );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function setup_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			[
				'root_server'  => get_option( 'bmltclient_root_server', '' ),
				'service_body' => get_option( 'bmltclient_service_body', '' ),
				'view'         => get_option( 'bmltclient_default_view', '' ),
			],
			$atts,
			'bmlt_client'
		);

		$root_server = esc_url( trim( $atts['root_server'] ) );

		if ( empty( $root_server ) ) {
			return '<p style="color:red"><strong>BMLT Client:</strong> a <code>root_server</code> URL is required.</p>';
		}

		$div = '<div id="bmlt-meeting-list" data-root-server="' . $root_server . '"';

		if ( ! empty( $atts['service_body'] ) ) {
			$div .= ' data-service-body="' . esc_attr( trim( $atts['service_body'] ) ) . '"';
		}

		if ( ! empty( $atts['view'] ) ) {
			$div .= ' data-view="' . esc_attr( $atts['view'] ) . '"';
		}

		$div .= '></div>';

		$template = get_option( 'bmltclient_css_template', 'full_width' );
		$class    = 'full_width_force' === $template ? 'bmltclient-full-width-force' : 'bmltclient-full-width';

		return '<div class="' . $class . '">' . $div . '</div>';
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function assets(): void {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'bmlt_client' ) ) {
			return;
		}

		$cdn_url = get_option( 'bmltclient_cdn_url', '' );
		$cdn_url = ! empty( $cdn_url ) ? esc_url( $cdn_url ) : self::DEFAULT_CDN_URL;

		wp_enqueue_script( 'bmlt-client', $cdn_url, [], BMLTCLIENT_VERSION, [ 'strategy' => 'defer' ] );

		$config = self::build_config();
		if ( ! empty( $config ) ) {
			wp_localize_script( 'bmlt-client', 'BmltMeetingListConfig', $config );
		}

		wp_register_style( 'bmlt-client-style', false, [], BMLTCLIENT_VERSION );
		wp_enqueue_style( 'bmlt-client-style' );
		wp_add_inline_style( 'bmlt-client-style', self::build_css() );
	}

	private static function build_config(): array {
		$config = [];

		$default_view = get_option( 'bmltclient_default_view', '' );
		if ( ! empty( $default_view ) ) {
			$config['defaultView'] = $default_view;
		}

		$language = get_option( 'bmltclient_language', '' );
		if ( ! empty( $language ) ) {
			$config['language'] = $language;
		}

		if ( get_option( 'bmltclient_geolocation', '0' ) === '1' ) {
			$config['geolocation'] = true;
		}

		$radius = get_option( 'bmltclient_geolocation_radius', '' );
		if ( '' !== $radius ) {
			$config['geolocationRadius'] = (int) $radius;
		}

		$columns = get_option( 'bmltclient_columns', '' );
		if ( ! empty( $columns ) ) {
			$config['columns'] = array_values( array_filter( array_map( 'trim', explode( ',', $columns ) ) ) );
		}

		// Merge raw advanced JSON last so it can override anything above.
		$raw = get_option( 'bmltclient_config', '' );
		if ( ! empty( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$config = array_merge( $config, $decoded );
			}
		}

		return $config;
	}

	private static function build_css(): string {
		$template = get_option( 'bmltclient_css_template', 'full_width' );
		$custom   = get_option( 'bmltclient_custom_css', '' );

		$base = '';
		if ( 'full_width' === $template ) {
			$base = '.bmltclient-full-width { width: 100%; }';
		} elseif ( 'full_width_force' === $template ) {
			$base = '.bmltclient-full-width-force { width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; box-sizing: border-box !important; max-width: none !important; }';
		}

		return $base . ' ' . $custom;
	}

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

	public static function admin_menu(): void {
		add_options_page(
			'BMLT Client Settings',
			'BMLT Client',
			'manage_options',
			'bmlt-client',
			[ static::class, 'settings_page' ]
		);
	}

	public static function register_settings(): void {
		$group = 'bmltclient-group';

		register_setting( $group, 'bmltclient_root_server', 'esc_url_raw' );
		register_setting( $group, 'bmltclient_service_body', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_default_view', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_language', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_geolocation', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_geolocation_radius', 'absint' );
		register_setting( $group, 'bmltclient_columns', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_cdn_url', 'esc_url_raw' );
		register_setting( $group, 'bmltclient_config', [ static::class, 'sanitize_json' ] );
		register_setting( $group, 'bmltclient_css_template', 'sanitize_text_field' );
		register_setting( $group, 'bmltclient_custom_css', [ static::class, 'sanitize_css' ] );
	}

	public static function sanitize_json( string $input ): string {
		$input = trim( $input );
		if ( empty( $input ) ) {
			return '';
		}
		json_decode( $input );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error( 'bmltclient_config', 'invalid_json', 'Advanced Configuration must be valid JSON.' );
			return get_option( 'bmltclient_config', '' );
		}
		return $input;
	}

	public static function sanitize_css( string $input ): string {
		return wp_strip_all_tags( $input );
	}

	public static function settings_page(): void {
		?>
		<div class="wrap">
			<h1>BMLT Client Settings</h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bmltclient-group' ); ?>

				<h2>Basic Settings</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="bmltclient_root_server">Root Server URL</label></th>
						<td>
							<input type="url" id="bmltclient_root_server" name="bmltclient_root_server"
								   value="<?php echo esc_attr( get_option( 'bmltclient_root_server', '' ) ); ?>"
								   class="regular-text" placeholder="https://your-server/main_server" />
							<p class="description">Required. The full URL to your BMLT root server.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_service_body">Service Body IDs</label></th>
						<td>
							<input type="text" id="bmltclient_service_body" name="bmltclient_service_body"
								   value="<?php echo esc_attr( get_option( 'bmltclient_service_body', '' ) ); ?>"
								   class="regular-text" placeholder="42 or 42,57,103" />
							<p class="description">Optional. Single ID or comma-separated list. Leave empty to show all meetings on the server. Child service bodies are always included.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_default_view">Default View</label></th>
						<td>
							<select id="bmltclient_default_view" name="bmltclient_default_view">
								<option value=""><?php esc_html_e( '— Use widget default (list) —', 'bmlt-client' ); ?></option>
								<option value="list" <?php selected( get_option( 'bmltclient_default_view' ), 'list' ); ?>>List</option>
								<option value="map" <?php selected( get_option( 'bmltclient_default_view' ), 'map' ); ?>>Map</option>
							</select>
						</td>
					</tr>
				</table>

				<h2>Display Settings</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="bmltclient_language">Language</label></th>
						<td>
							<input type="text" id="bmltclient_language" name="bmltclient_language"
								   value="<?php echo esc_attr( get_option( 'bmltclient_language', '' ) ); ?>"
								   class="small-text" placeholder="en" />
							<p class="description">Optional. Supported: <code>en</code>, <code>es</code>, <code>fr</code>, <code>de</code>, <code>pt</code>, <code>it</code>, <code>sv</code>, <code>da</code>. Defaults to the visitor&rsquo;s browser language.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Near Me Button</th>
						<td>
							<label>
								<input type="checkbox" id="bmltclient_geolocation" name="bmltclient_geolocation" value="1"
									   <?php checked( get_option( 'bmltclient_geolocation', '0' ), '1' ); ?> />
								Enable geolocation
							</label>
							<p class="description">Shows a Near Me button that loads meetings near the visitor&rsquo;s location. Requires HTTPS.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_geolocation_radius">Geolocation Radius</label></th>
						<td>
							<input type="number" id="bmltclient_geolocation_radius" name="bmltclient_geolocation_radius"
								   value="<?php echo esc_attr( get_option( 'bmltclient_geolocation_radius', '10' ) ); ?>"
								   class="small-text" min="1" /> miles
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_columns">Columns</label></th>
						<td>
							<input type="text" id="bmltclient_columns" name="bmltclient_columns"
								   value="<?php echo esc_attr( get_option( 'bmltclient_columns', '' ) ); ?>"
								   class="regular-text" placeholder="time,name,location,address" />
							<p class="description">Optional. Comma-separated list. Available: <code>time</code>, <code>name</code>, <code>location</code>, <code>address</code>, <code>service_body</code>.</p>
						</td>
					</tr>
				</table>

				<h2>Advanced Settings</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="bmltclient_cdn_url">Widget Script URL</label></th>
						<td>
							<input type="url" id="bmltclient_cdn_url" name="bmltclient_cdn_url"
								   value="<?php echo esc_attr( get_option( 'bmltclient_cdn_url', '' ) ); ?>"
								   class="regular-text" placeholder="<?php echo esc_attr( self::DEFAULT_CDN_URL ); ?>" />
							<p class="description">Optional. Override the CDN URL for the widget script. Leave empty to use the default.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_config">Advanced Configuration</label></th>
						<td>
							<textarea id="bmltclient_config" name="bmltclient_config" rows="8" class="large-text code"><?php echo esc_textarea( get_option( 'bmltclient_config', '' ) ); ?></textarea>
							<p class="description">Optional. Raw <code>BmltMeetingListConfig</code> JSON for custom map tiles, markers, and other advanced options. See the <a href="https://client.bmlt.app/" target="_blank">documentation</a>. Values here override the settings above.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_css_template">CSS Template</label></th>
						<td>
							<select id="bmltclient_css_template" name="bmltclient_css_template">
								<option value="full_width" <?php selected( get_option( 'bmltclient_css_template', 'full_width' ), 'full_width' ); ?>>Full Width</option>
								<option value="full_width_force" <?php selected( get_option( 'bmltclient_css_template', 'full_width' ), 'full_width_force' ); ?>>Full Width (Force Viewport)</option>
								<option value="custom" <?php selected( get_option( 'bmltclient_css_template', 'full_width' ), 'custom' ); ?>>Custom Only</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmltclient_custom_css">Custom CSS</label></th>
						<td>
							<textarea id="bmltclient_custom_css" name="bmltclient_custom_css" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'bmltclient_custom_css', '' ) ); ?></textarea>
						</td>
					</tr>
				</table>

				<h2>Shortcode Usage</h2>
				<p>Place this shortcode on any page or post:</p>
				<code>[bmlt_client]</code>
				<p>You can override the root server, service body, or view per page:</p>
				<code>[bmlt_client root_server="https://your-server/main_server" service_body="42" view="map"]</code>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

BmltClient::get_instance();
