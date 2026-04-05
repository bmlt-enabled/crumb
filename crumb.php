<?php
/**
 * Plugin Name: Crumb
 * Plugin URI: https://wordpress.org/plugins/crumb/
 * Description: Embeds the Crumb meeting finder widget on any page or post using a shortcode.
 * Version: 1.0.0
 * Author: bmltenabled
 * Author URI: https://bmlt.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crumb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRUMB_VERSION', '1.0.0' );

class Crumb {

	private static ?self $instance = null;

	const DEFAULT_CDN_URL = 'https://cdn.aws.bmlt.app/crumb-widget.js';

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'crumb', [ static::class, 'setup_shortcode' ] );
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
				'server'  => get_option( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ),
				'service_body' => get_option( 'crumb_service_body', '1047,1048' ),
			],
			$atts,
			'crumb'
		);

		$server = esc_url( trim( $atts['server'] ) );

		if ( empty( $server ) ) {
			return '<p style="color:red"><strong>Crumb:</strong> a <code>server</code> URL is required.</p>';
		}

		$div = '<div id="crumb-widget" data-server="' . $server . '"';

		if ( ! empty( $atts['service_body'] ) ) {
			$div .= ' data-service-body="' . esc_attr( trim( $atts['service_body'] ) ) . '"';
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

		wp_enqueue_script( 'crumb', self::DEFAULT_CDN_URL, [], CRUMB_VERSION, [ 'strategy' => 'defer' ] );

		/**
		 * Filter the CrumbWidgetConfig passed to the widget.
		 *
		 * Add this to your theme's functions.php to configure the widget:
		 *
		 *   add_filter( 'crumb_config', function( $config ) {
		 *       return array_merge( $config, [
		 *           'language'          => 'es',
		 *           'geolocation'       => true,
		 *           'geolocationRadius' => 20,
		 *           'height'            => 800,
		 *           'columns'           => [ 'time', 'name', 'location', 'address', 'service_body' ],
		 *       ] );
		 *   } );
		 *
		 * See https://crumb.bmlt.app/ for all available options.
		 *
		 * @param array $config Configuration array passed to CrumbWidgetConfig.
		 */
		$config = (array) apply_filters( 'crumb_config', [] );
		if ( ! empty( $config ) ) {
			wp_localize_script( 'crumb', 'CrumbWidgetConfig', $config );
		}

		wp_register_style( 'crumb-style', false, [], CRUMB_VERSION );
		wp_enqueue_style( 'crumb-style' );
		wp_add_inline_style( 'crumb-style', self::build_css() );
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

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

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
		register_setting( $group, 'crumb_css_template', 'sanitize_text_field' );
	}

	public static function settings_page(): void {
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
				</table>

				<h2>Advanced Configuration</h2>
				<p>
					<?php esc_html_e( 'To configure language, geolocation, columns, map tiles, and other options, add a filter to your theme\'s', 'crumb' ); ?>
					<code>functions.php</code>:
				</p>
				<pre style="background:#f6f7f7;padding:12px;overflow:auto"><code>add_filter( 'crumb_config', function( $config ) {
	return array_merge( $config, [
		'language'          => 'en',
		'geolocation'       => true,
		'geolocationRadius' => 20,
		'height'            => 800,
		'columns'           => [ 'time', 'name', 'location', 'address', 'service_body' ],
	] );
} );</code></pre>
				<p><a href="https://crumb.bmlt.app/" target="_blank"><?php esc_html_e( 'See documentation for all available options.', 'crumb' ); ?></a></p>

				<h2>Shortcode Usage</h2>
				<p><?php esc_html_e( 'Place this shortcode on any page or post:', 'crumb' ); ?></p>
				<code>[crumb]</code>
				<p><?php esc_html_e( 'Override server or service body per page:', 'crumb' ); ?></p>
				<code>[crumb server="https://your-server/main_server" service_body="42"]</code>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

Crumb::get_instance();
