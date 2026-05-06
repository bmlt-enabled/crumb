<?php
/**
 * Plugin Name: Crumb
 * Plugin URI: https://wordpress.org/plugins/crumb/
 * Description: Embeds the Crumb meeting finder widget on any page or post using a shortcode.
 * Version: 1.2.2
 * Author: bmltenabled
 * Author URI: https://bmlt.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crumb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRUMB_VERSION', '1.2.2' );

class Crumb {

	private static ?self $instance = null;
	private static ?bool $shortcode_geolocation        = null;
	private static ?int $shortcode_geolocation_radius = null;

	const DEFAULT_CDN_URL = 'https://cdn.aws.bmlt.app/crumb-widget.js';
	const REWRITE_VERSION = '1';

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'crumb', [ static::class, 'setup_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ static::class, 'assets' ] );
		add_action( 'wp_footer', [ static::class, 'localize_config' ], 1 );
		add_action( 'admin_menu', [ static::class, 'admin_menu' ] );
		add_action( 'admin_init', [ static::class, 'register_settings' ] );
		add_action( 'init', [ static::class, 'init_rewrite_rules' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ static::class, 'settings_link' ] );
	}

	// -------------------------------------------------------------------------
	// Activation / Deactivation
	// -------------------------------------------------------------------------

	public static function activate(): void {
		$base_path = get_option( 'crumb_base_path', '' );
		if ( ! empty( $base_path ) ) {
			self::register_rewrite_rules( $base_path );
			flush_rewrite_rules();
		}
		update_option( 'crumb_rewrite_version', self::REWRITE_VERSION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
		delete_option( 'crumb_rewrite_version' );
	}

	// -------------------------------------------------------------------------
	// Rewrite Rules
	// -------------------------------------------------------------------------

	private static function register_rewrite_rules( string $base_path ): void {
		if ( empty( $base_path ) ) {
			return;
		}
		$base_path = trim( $base_path, '/' );
		add_rewrite_rule(
			'^' . preg_quote( $base_path, '/' ) . '(/.*)?$',
			'index.php?pagename=' . $base_path,
			'top'
		);
	}

	public static function init_rewrite_rules(): void {
		$base_path = get_option( 'crumb_base_path', '' );

		if ( ! empty( $base_path ) ) {
			self::register_rewrite_rules( $base_path );

			if ( get_option( 'crumb_rewrite_version' ) !== self::REWRITE_VERSION ) {
				flush_rewrite_rules();
				update_option( 'crumb_rewrite_version', self::REWRITE_VERSION );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public static function setup_shortcode( array $atts ): string {
		// Use null as sentinel so we can distinguish "not provided" from explicit empty string.
		$atts = shortcode_atts(
			[
				'server'             => null,
				'service_body'       => null,
				'format_ids'         => null,
				'view'               => null,
				'geolocation'        => null,
				'geolocation_radius' => null,
			],
			$atts,
			'crumb'
		);

		// Store geolocation for merging into CrumbWidgetConfig during wp_footer.
		if ( null !== $atts['geolocation'] ) {
			self::$shortcode_geolocation = filter_var( $atts['geolocation'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( null !== $atts['geolocation_radius'] ) {
			$radius = (int) $atts['geolocation_radius'];
			if ( 0 !== $radius ) {
				self::$shortcode_geolocation_radius = $radius;
			}
		}

		// Shortcode attribute takes precedence; fall back to saved option only when not provided.
		$server = esc_url( trim( $atts['server'] ?? get_option( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ) ) );

		if ( empty( $server ) ) {
			return '<p style="color:red"><strong>Crumb:</strong> a <code>server</code> URL is required.</p>';
		}

		// null  → not in shortcode, use saved option.
		// ''    → explicitly set to empty in shortcode, omit data-service-body (show all meetings).
		$service_body = $atts['service_body'] ?? get_option( 'crumb_service_body', '1047,1048' );

		// null → not in shortcode, use saved option. '' → omit (no format lock).
		$format_ids = $atts['format_ids'] ?? get_option( 'crumb_format_ids', '' );

		// Resolve view: shortcode attr → saved option → omit (widget uses its own default).
		$view_raw     = $atts['view'] ?? get_option( 'crumb_view', '' );
		$allowed_views = [ 'list', 'map', 'both' ];
		$view          = in_array( $view_raw, $allowed_views, true ) ? $view_raw : '';

		$div = '<div id="crumb-widget" data-server="' . $server . '"';

		if ( ! empty( $service_body ) ) {
			$div .= ' data-service-body="' . esc_attr( trim( $service_body ) ) . '"';
		}

		if ( ! empty( $format_ids ) ) {
			$div .= ' data-format-ids="' . esc_attr( trim( $format_ids ) ) . '"';
		}

		if ( ! empty( $view ) ) {
			$div .= ' data-view="' . esc_attr( $view ) . '"';
		}

		$base_path = get_option( 'crumb_base_path', '' );
		if ( '' !== $base_path ) {
			$div .= ' data-path="/' . esc_attr( trim( $base_path, '/' ) ) . '"';
		}

		$div .= '></div>';

		$template = get_option( 'crumb_css_template', '' );

		if ( 'full_width' === $template ) {
			return '<div class="crumb-full-width">' . $div . '</div>';
		}

		if ( 'full_width_force' === $template ) {
			return '<div class="crumb-full-width-force">' . $div . '</div>';
		}

		return $div;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public static function assets(): void {
		global $post;
		if ( ! $post || ! has_shortcode( $post->post_content, 'crumb' ) ) {
			return;
		}

		wp_enqueue_script(
			'crumb',
			self::DEFAULT_CDN_URL,
			[],
			CRUMB_VERSION,
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);

		wp_register_style( 'crumb-style', false, [], CRUMB_VERSION );
		wp_enqueue_style( 'crumb-style' );
		wp_add_inline_style( 'crumb-style', wp_strip_all_tags( self::build_css() ) );
	}

	private static function build_css(): string {
		$template = get_option( 'crumb_css_template', '' );

		if ( 'full_width' === $template ) {
			return '.crumb-full-width { width: 100%; }';
		}

		if ( 'full_width_force' === $template ) {
			return '.crumb-full-width-force { width: 100vw !important; position: relative !important; left: 50% !important; margin-left: -50vw !important; box-sizing: border-box !important; max-width: none !important; }';
		}

		return '';
	}

	/**
	 * Output CrumbWidgetConfig via wp_localize_script.
	 *
	 * Hooked to wp_footer (priority 1) so it runs after shortcode processing
	 * but before the deferred script prints.
	 */
	public static function localize_config(): void {
		if ( ! wp_script_is( 'crumb', 'enqueued' ) ) {
			return;
		}

		// Build config: stored option → filter → shortcode geolocation override.
		$config = self::get_config();

		/**
		 * Filter the CrumbWidgetConfig passed to the widget.
		 *
		 * Add this to your theme's functions.php to configure the widget:
		 *
		 *   add_filter( 'crumb_config', function( $config ) {
		 *       return array_merge( $config, [
		 *           'language'          => 'es',
		 *           'geolocation'       => true,
		 *           'geolocationRadius' => -50,
		 *           'height'            => 800,
		 *           'columns'           => [ 'time', 'name', 'location', 'address', 'service_body' ],
		 *       ] );
		 *   } );
		 *
		 * See https://crumb.bmlt.app/ for all available options.
		 *
		 * @param array $config Configuration array passed to CrumbWidgetConfig.
		 */
		$config = (array) apply_filters( 'crumb_config', $config );

		if ( null !== self::$shortcode_geolocation ) {
			$config['geolocation'] = self::$shortcode_geolocation;
		}

		if ( null !== self::$shortcode_geolocation_radius ) {
			$config['geolocationRadius'] = self::$shortcode_geolocation_radius;
		}

		if ( ! empty( $config ) ) {
			wp_add_inline_script( 'crumb', 'var CrumbWidgetConfig = ' . wp_json_encode( $config ) . ';', 'before' );
		}
	}

	private static function get_config(): array {
		$config_json = get_option( 'crumb_widget_config', '' );

		if ( ! empty( $config_json ) ) {
			$decoded = json_decode( $config_json, true );
			$config  = ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : [];
		} else {
			$config = [];
		}

		// Dedicated geolocation radius field — JSON config takes precedence if already set.
		$radius_opt = get_option( 'crumb_geolocation_radius', '' );
		if ( '' !== $radius_opt && ! isset( $config['geolocationRadius'] ) ) {
			$config['geolocationRadius'] = (int) $radius_opt;
		}

		return $config;
	}

	public static function sanitize_base_path( string $input ): string {
		$old_value = get_option( 'crumb_base_path', '' );
		$new_value = sanitize_text_field( trim( $input, '/' ) );

		if ( $old_value !== $new_value ) {
			update_option( 'crumb_rewrite_version', '' );
		}

		return $new_value;
	}

	public static function sanitize_geolocation_radius( string $input ): string {
		$trimmed = trim( $input );
		if ( '' === $trimmed ) {
			return '';
		}
		$val = (int) $trimmed;
		return ( 0 !== $val ) ? (string) $val : '';
	}

	public static function sanitize_config( string $input ): string {
		$input = trim( $input );

		if ( '' === $input ) {
			return '';
		}

		$decoded = json_decode( $input, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'crumb_widget_config',
				'invalid_json',
				'Widget Configuration must be valid JSON. Your previous value has been preserved.',
				'error'
			);
			return get_option( 'crumb_widget_config', '' );
		}

		return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

	public static function settings_link( array $links ): array {
		$settings_url = admin_url( 'options-general.php?page=crumb' );
		$links[]      = "<a href='{$settings_url}'>Settings</a>";
		return $links;
	}

	public static function admin_menu(): void {
		add_options_page(
			'Crumb Settings',
			'Crumb',
			'manage_options',
			'crumb',
			[ static::class, 'settings_page' ]
		);
	}

	public static function register_settings(): void {
		$group = 'crumb-group';

		register_setting( $group, 'crumb_server', 'esc_url_raw' );
		register_setting( $group, 'crumb_service_body', 'sanitize_text_field' );
		register_setting( $group, 'crumb_format_ids', 'sanitize_text_field' );
		register_setting( $group, 'crumb_css_template', 'sanitize_text_field' );
		register_setting( $group, 'crumb_view', 'sanitize_text_field' );
		register_setting(
			$group,
			'crumb_geolocation_radius',
			[
				'type'              => 'string',
				'sanitize_callback' => [ static::class, 'sanitize_geolocation_radius' ],
			]
		);
		register_setting(
			$group,
			'crumb_base_path',
			[
				'type'              => 'string',
				'sanitize_callback' => [ static::class, 'sanitize_base_path' ],
			]
		);
		register_setting(
			$group,
			'crumb_widget_config',
			[
				'type'              => 'string',
				'sanitize_callback' => [ static::class, 'sanitize_config' ],
			]
		);
	}

	public static function settings_page(): void {
		$example_config = wp_json_encode(
			[
				'language'          => 'en',
				'geolocation'       => true,
				'geolocationRadius' => -50,
				'height'            => 800,
				'darkMode'          => 'auto',
				'nowOffset'         => 10,
				'hideHeader'        => false,
				'columns'           => [ 'time', 'name', 'location', 'address' ],
				'map'               => [
					'tiles'   => [
						'url'         => '...',
						'attribution' => '...',
					],
					'markers' => [
						'location' => [
							'html'   => '...',
							'width'  => 23,
							'height' => 33,
						],
					],
				],
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);
		?>
		<div class="wrap">
			<h1>Crumb Settings</h1>

			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'crumb-group' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="crumb_server">BMLT Server URL</label></th>
						<td>
							<input type="url" id="crumb_server" name="crumb_server"
								   value="<?php echo esc_attr( get_option( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ) ); ?>"
								   class="regular-text" placeholder="https://your-server/main_server" />
							<p class="description">Required. The full URL to your BMLT Server.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_service_body">Service Body IDs</label></th>
						<td>
							<input type="text" id="crumb_service_body" name="crumb_service_body"
								   value="<?php echo esc_attr( get_option( 'crumb_service_body', '1047,1048' ) ); ?>"
								   class="regular-text" placeholder="42 or 42,57,103" />
							<p class="description">Optional. Single ID or comma-separated list. Leave empty to show all meetings. Child service bodies are always included.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_format_ids">Format IDs</label></th>
						<td>
							<input type="text" id="crumb_format_ids" name="crumb_format_ids"
								   value="<?php echo esc_attr( get_option( 'crumb_format_ids', '' ) ); ?>"
								   class="regular-text" placeholder="17 or 17,54,78" />
							<p class="description">Optional. Single ID or comma-separated list of BMLT format IDs to lock the widget to. Leave empty to show all formats. Can be overridden per-page via the shortcode <code>format_ids</code> attribute.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_css_template">CSS Template</label></th>
						<td>
							<select id="crumb_css_template" name="crumb_css_template">
								<option value="" <?php selected( get_option( 'crumb_css_template', '' ), '' ); ?>><?php esc_html_e( '— None —', 'crumb' ); ?></option>
								<option value="full_width" <?php selected( get_option( 'crumb_css_template', '' ), 'full_width' ); ?>>Full Width</option>
								<option value="full_width_force" <?php selected( get_option( 'crumb_css_template', '' ), 'full_width_force' ); ?>>Full Width (Force Viewport)</option>
							</select>
							<p class="description">Full Width fits the content area. Full Width (Force Viewport) breaks out to span the full browser width.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_base_path">Base Path for Pretty URLs</label></th>
						<td>
							<input type="text" id="crumb_base_path" name="crumb_base_path"
								   value="<?php echo esc_attr( get_option( 'crumb_base_path', '' ) ); ?>"
								   class="regular-text" placeholder="meetings" />
							<p class="description">
								Optional. The page slug where the widget lives (e.g. <code>meetings</code>).
								Enables clean URLs like <code>/meetings/monday-night-meeting-42</code> instead of hash-based routing.
								Leave empty to use default hash-based routing (<code>#/monday-night-meeting-42</code>).
							</p>
							<p class="description">
								After changing this value, go to <strong>Settings &rarr; Permalinks</strong> and click <strong>Save Changes</strong> to update rewrite rules.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_view">Default View</label></th>
						<td>
							<select id="crumb_view" name="crumb_view">
								<option value="" <?php selected( get_option( 'crumb_view', '' ), '' ); ?>><?php esc_html_e( '— Widget Default (list) —', 'crumb' ); ?></option>
								<option value="list" <?php selected( get_option( 'crumb_view', '' ), 'list' ); ?>>List</option>
								<option value="map" <?php selected( get_option( 'crumb_view', '' ), 'map' ); ?>>Map</option>
								<option value="both" <?php selected( get_option( 'crumb_view', '' ), 'both' ); ?>>Both (map above list)</option>
							</select>
							<p class="description">Optional. Sets the default view when the widget loads. Can be overridden at runtime via the <code>?view=</code> query parameter, or per-page via the shortcode <code>view</code> attribute.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_geolocation_radius">Geolocation Radius</label></th>
						<td>
							<input type="number" id="crumb_geolocation_radius" name="crumb_geolocation_radius"
								   value="<?php echo esc_attr( get_option( 'crumb_geolocation_radius', '' ) ); ?>"
								   class="small-text" placeholder="-50" />
							<p class="description">
								Optional. Controls the search radius when geolocation is enabled.
								A <strong>positive</strong> value sets a fixed radius in miles (or km, per server settings).
								A <strong>negative</strong> value uses BMLT auto-radius mode — the server expands the search until it finds roughly that many meetings (e.g. <code>-50</code> finds ~50 nearby meetings).
								Leave empty to use the widget default.
								Can be overridden per-page via the shortcode <code>geolocation_radius</code> attribute.
								Overridden by a <code>geolocationRadius</code> key in Widget Configuration below.
							</p>
						</td>
					</tr>
				</table>

				<h2>Advanced Configuration</h2>
				<p><a href="https://crumb.bmlt.app/" target="_blank"><?php esc_html_e( 'See documentation for all available options.', 'crumb' ); ?></a></p>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="crumb_widget_config">Widget Configuration</label></th>
						<td>
							<?php $widget_config = get_option( 'crumb_widget_config', '' ); ?>
							<textarea id="crumb_widget_config" name="crumb_widget_config"
									  rows="15" cols="80"
									  style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $widget_config ); ?></textarea>
							<p class="description">
								Optional. CrumbWidgetConfig in JSON format. Leave empty to use defaults.
							</p>
							<details>
								<summary><strong>Available Options</strong></summary>
								<pre style="background:#f6f7f7;padding:12px;overflow:auto;margin-top:10px;font-size:11px;"><?php echo esc_html( $example_config ); ?></pre>
							</details>
							<p class="description" style="margin-top:8px;">
								<?php esc_html_e( 'You can also use the crumb_config filter in your theme\'s functions.php for programmatic configuration.', 'crumb' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2>Shortcode Usage</h2>
				<p><?php esc_html_e( 'Place this shortcode on any page or post:', 'crumb' ); ?></p>
				<code>[crumb]</code>
				<p><?php esc_html_e( 'Override settings per page:', 'crumb' ); ?></p>
				<code>[crumb server="https://your-server/main_server" service_body="42" format_ids="17,54" view="map" geolocation="true" geolocation_radius="-50"]</code>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

register_activation_hook( __FILE__, [ 'Crumb', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Crumb', 'deactivate' ] );
Crumb::get_instance();
