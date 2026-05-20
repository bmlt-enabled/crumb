<?php
/**
 * Plugin Name: Crumb
 * Plugin URI: https://wordpress.org/plugins/crumb/
 * Description: Embeds the Crumb meeting finder widget on any page or post using a shortcode.
 * Version: 1.8.1
 * Author: bmltenabled
 * Author URI: https://bmlt.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crumb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRUMB_VERSION', '1.8.1' );

class Crumb {

	private static ?self $instance = null;
	private static ?bool $shortcode_geolocation        = null;
	private static ?int $shortcode_geolocation_radius = null;
	private static ?string $shortcode_language        = null;
	private static ?array $crouton_options             = null;
	private static array $compat_tags                  = [];

	const DEFAULT_CDN_URL = 'https://cdn.aws.bmlt.app/crumb-widget.js';
	const REWRITE_VERSION = '1';

	/** Languages the widget supports (kept in sync with src/stores/localization.ts). */
	const SUPPORTED_LANGUAGES = [ 'en', 'es', 'fr', 'de', 'pt', 'it', 'sv', 'da', 'el', 'fa', 'pl', 'ru', 'ja' ];

	/**
	 * Tag → forced view for crouton-named shortcodes.
	 * Map-flavored tags become "both" (map + list) so users don't lose the list view
	 * they previously saw next to the map. Tabs become plain list.
	 */
	const CROUTON_VIEW_MAP = [
		'crouton_map'  => 'both',
		'crouton_tabs' => 'list',
		'bmlt_map'     => 'both',
		'bmlt_tabs'    => 'list',
	];

	/**
	 * Crouton helper shortcodes with no crumb equivalent.
	 * Registered as empty-string stubs so pages don't show the literal shortcode
	 * text after crouton is deactivated.
	 */
	const CROUTON_NOOP_TAGS = [
		'init_crouton',
		'service_body_names',
		'root_service_body',
		'bmlt_handlebar',
	];

	/**
	 * Crouton count shortcodes → which count to render.
	 * Server-side; results cached in a transient.
	 */
	const CROUTON_COUNT_TAGS = [
		'meeting_count' => 'meetings',
		'bmlt_count'    => 'meetings',
		'group_count'   => 'groups',
	];

	const COUNTS_CACHE_TTL         = HOUR_IN_SECONDS;
	const COUNTS_CACHE_TTL_FAILURE = MINUTE_IN_SECONDS;
	const COUNTS_FETCH_TIMEOUT     = 3;

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
		// Priority 20 so this runs after crouton (if active) has registered its own shortcodes.
		add_action( 'init', [ static::class, 'register_crouton_shortcodes' ], 20 );
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
	// Crouton Compatibility
	//
	// Lets sites running the crouton plugin switch to crumb without editing
	// pages. Crouton-named shortcodes are mapped to [crumb] output, and crouton's
	// saved settings (bmlt_tabs_options) are used as a fallback whenever the
	// matching crumb_* option is empty.
	// -------------------------------------------------------------------------

	private static function crouton_options(): array {
		if ( null === self::$crouton_options ) {
			$opts                   = get_option( 'bmlt_tabs_options', [] );
			self::$crouton_options  = is_array( $opts ) ? $opts : [];
		}
		return self::$crouton_options;
	}

	/**
	 * Map a crumb option key to the matching value from crouton's bmlt_tabs_options.
	 * Returns '' if there is no equivalent or it's unset.
	 */
	private static function crouton_fallback_value( string $crumb_key ): string {
		$opts = self::crouton_options();
		switch ( $crumb_key ) {
			case 'crumb_server':
				return isset( $opts['root_server'] ) ? (string) $opts['root_server'] : '';
			case 'crumb_service_body':
				if ( ! empty( $opts['service_bodies'] ) && is_array( $opts['service_bodies'] ) ) {
					// Crouton stores each entry as a 4-part CSV string built in
					// crouton/js/bmlt_tabs_admin.js: "name,id,parent_id,parent_name".
					// The service body ID lives at index [1]. Bare-integer entries
					// (legacy / hand-edited) are treated as the ID directly.
					$ids = [];
					foreach ( $opts['service_bodies'] as $entry ) {
						$entry = (string) $entry;
						if ( false !== strpos( $entry, ',' ) ) {
							$parts = explode( ',', $entry );
							$id    = isset( $parts[1] ) ? (int) trim( $parts[1] ) : 0;
						} else {
							$id = (int) trim( $entry );
						}
						if ( 0 !== $id ) {
							$ids[] = $id;
						}
					}
					if ( ! empty( $ids ) ) {
						return implode( ',', $ids );
					}
				}
				return isset( $opts['service_body'] ) ? (string) $opts['service_body'] : '';
			case 'crumb_format_ids':
				return isset( $opts['formats'] ) ? (string) $opts['formats'] : '';
			case 'crumb_update_url':
				return isset( $opts['report_update_url'] ) ? (string) $opts['report_update_url'] : '';
		}
		return '';
	}

	/**
	 * Like get_option(), but falls back to the equivalent key in bmlt_tabs_options
	 * when the crumb option is empty/missing. Final fallback is $default.
	 */
	public static function get_option_or_crouton( string $crumb_key, string $default = '' ): string {
		$value = get_option( $crumb_key, '' );
		if ( '' !== trim( (string) $value ) ) {
			return (string) $value;
		}
		$fallback = self::crouton_fallback_value( $crumb_key );
		return '' !== $fallback ? $fallback : $default;
	}

	public static function register_crouton_shortcodes(): void {
		foreach ( self::CROUTON_VIEW_MAP as $tag => $view ) {
			if ( shortcode_exists( $tag ) ) {
				continue;
			}
			self::$compat_tags[] = $tag;
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $view ) {
					return self::crouton_compat_shortcode( $atts, $view );
				}
			);
		}
		// Helper shortcodes with no crumb equivalent — register as empty-string stubs
		// so pages don't render the literal "[bmlt_count]" text after crouton is removed.
		// These do NOT trigger widget enqueue, so they're kept out of $compat_tags.
		foreach ( self::CROUTON_NOOP_TAGS as $tag ) {
			if ( ! shortcode_exists( $tag ) ) {
				add_shortcode( $tag, '__return_empty_string' );
			}
		}
		// Count shortcodes — server-side, cached. Also kept out of $compat_tags so they
		// don't enqueue the widget JS when used on a page without [crumb].
		foreach ( self::CROUTON_COUNT_TAGS as $tag => $type ) {
			if ( shortcode_exists( $tag ) ) {
				continue;
			}
			add_shortcode(
				$tag,
				static function ( $atts ) use ( $tag, $type ) {
					return self::count_shortcode( $atts, $tag, $type );
				}
			);
		}
	}

	public static function crouton_compat_shortcode( $atts, string $view ): string {
		$atts       = is_array( $atts ) ? $atts : [];
		$translated = [ 'view' => $view ];

		// Crouton's show_map="1" explicitly asks for a map alongside the listing;
		// honor it by upgrading the tag's default view to "both".
		if ( isset( $atts['show_map'] ) && '1' === (string) $atts['show_map'] ) {
			$translated['view'] = 'both';
		}

		if ( isset( $atts['root_server'] ) ) {
			$translated['server'] = $atts['root_server'];
		}
		if ( isset( $atts['service_body'] ) ) {
			$translated['service_body'] = $atts['service_body'];
		} elseif ( isset( $atts['service_body_1'] ) ) {
			$translated['service_body'] = $atts['service_body_1'];
		}
		if ( isset( $atts['formats'] ) ) {
			$translated['format_ids'] = $atts['formats'];
		}
		if ( isset( $atts['report_update_url'] ) ) {
			$translated['update_url'] = $atts['report_update_url'];
		}
		// Crouton's query_string is a raw BMLT query appended to searches; the equivalent in
		// the Crumb widget is data-query, which routes through bmlt-query-client's rawQuery().
		if ( isset( $atts['query_string'] ) ) {
			$translated['query'] = $atts['query_string'];
		}

		// Crouton's has_areas / has_regions toggles surface the originating service body in
		// the listing. Emit the default columns plus service_body so that information stays visible.
		$wants_service_body = (
			( isset( $atts['has_areas'] ) && filter_var( $atts['has_areas'], FILTER_VALIDATE_BOOLEAN ) ) ||
			( isset( $atts['has_regions'] ) && filter_var( $atts['has_regions'], FILTER_VALIDATE_BOOLEAN ) )
		);
		if ( $wants_service_body && ! isset( $translated['columns'] ) ) {
			$translated['columns'] = 'time,distance,name,location,address,service_body';
		}

		return self::setup_shortcode( $translated );
	}

	public static function compat_tags(): array {
		return self::$compat_tags;
	}

	// -------------------------------------------------------------------------
	// Count Shortcodes ([meeting_count], [bmlt_count], [group_count])
	//
	// Server-side counts fetched from BMLT GetSearchResults and cached in a
	// transient. Output matches crouton's <span id='bmlt_tabs_*'>N</span>
	// structure so themes that style those IDs keep working post-migration.
	// -------------------------------------------------------------------------

	public static function count_shortcode( $atts, string $tag, string $type ): string {
		$atts = shortcode_atts(
			[
				'server'       => null,
				'service_body' => null,
				'format_ids'   => null,
			],
			is_array( $atts ) ? $atts : [],
			$tag
		);

		$server       = esc_url_raw( trim( (string) ( $atts['server'] ?? self::get_option_or_crouton( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ) ) ) );
		$service_body = trim( (string) ( $atts['service_body'] ?? self::get_option_or_crouton( 'crumb_service_body', '' ) ) );
		$format_ids   = trim( (string) ( $atts['format_ids'] ?? self::get_option_or_crouton( 'crumb_format_ids', '' ) ) );

		if ( '' === $server ) {
			return '';
		}

		$counts = self::fetch_counts( $server, $service_body, $format_ids );
		$value  = isset( $counts[ $type ] ) ? (int) $counts[ $type ] : 0;

		return '<span id="bmlt_tabs_' . esc_attr( $tag ) . '">' . esc_html( (string) $value ) . '</span>';
	}

	/**
	 * Fetch meeting + group counts from BMLT, with transient caching.
	 *
	 * Group definition matches crouton: distinct tuples of
	 * (service_body_bigint, meeting_name, lat+lng for in-person/hybrid OR
	 * virtual_meeting_link + virtual_meeting_additional_info for virtual).
	 *
	 * @return array{meetings:int, groups:int}
	 */
	private static function fetch_counts( string $server, string $service_body, string $format_ids ): array {
		$cache_key = 'crumb_counts_' . md5( $server . '|' . $service_body . '|' . $format_ids );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['meetings'], $cached['groups'] ) ) {
			return $cached;
		}

		$url    = self::build_counts_url( $server, $service_body, $format_ids );
		$result = wp_remote_get(
			$url,
			[
				'timeout'    => self::COUNTS_FETCH_TIMEOUT,
				'user-agent' => 'Crumb/' . CRUMB_VERSION . '; ' . home_url(),
			]
		);

		if ( is_wp_error( $result ) || 200 !== (int) wp_remote_retrieve_response_code( $result ) ) {
			$counts = [
				'meetings' => 0,
				'groups'   => 0,
			];
			set_transient( $cache_key, $counts, self::COUNTS_CACHE_TTL_FAILURE );
			return $counts;
		}

		$meetings = json_decode( wp_remote_retrieve_body( $result ), true );
		if ( ! is_array( $meetings ) ) {
			$meetings = [];
		}

		$counts = [
			'meetings' => count( $meetings ),
			'groups'   => self::count_groups( $meetings ),
		];
		set_transient( $cache_key, $counts, self::COUNTS_CACHE_TTL );
		return $counts;
	}

	private static function build_counts_url( string $server, string $service_body, string $format_ids ): string {
		// BMLT expects repeated `services[]=N` / `formats[]=N` params, which add_query_arg
		// can't produce; build the query string manually.
		$parts = [
			'switcher=GetSearchResults',
			'data_field_key=' . rawurlencode( 'service_body_bigint,meeting_name,venue_type,latitude,longitude,virtual_meeting_link,virtual_meeting_additional_info' ),
		];

		$service_ids = self::csv_to_ints( $service_body );
		foreach ( $service_ids as $id ) {
			$parts[] = 'services%5B%5D=' . $id;
		}
		// Match the widget: recurse into child service bodies whenever any are filtered.
		if ( ! empty( $service_ids ) ) {
			$parts[] = 'recursive=1';
		}

		foreach ( self::csv_to_ints( $format_ids ) as $id ) {
			$parts[] = 'formats%5B%5D=' . $id;
		}

		return rtrim( $server, '/' ) . '/client_interface/json/?' . implode( '&', $parts );
	}

	private static function csv_to_ints( string $csv ): array {
		if ( '' === $csv ) {
			return [];
		}
		$ids = [];
		foreach ( explode( ',', $csv ) as $part ) {
			$n = (int) trim( $part );
			if ( 0 !== $n ) {
				$ids[] = $n;
			}
		}
		return $ids;
	}

	private static function count_groups( array $meetings ): int {
		$seen = [];
		foreach ( $meetings as $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$sb   = $m['service_body_bigint'] ?? '';
			$name = $m['meeting_name'] ?? '';
			if ( 2 === (int) ( $m['venue_type'] ?? 0 ) ) {
				$tail = ( $m['virtual_meeting_link'] ?? '' ) . '|' . ( $m['virtual_meeting_additional_info'] ?? '' );
			} else {
				$tail = number_format( (float) ( $m['latitude'] ?? 0 ), 6, '.', '' ) . '|' . number_format( (float) ( $m['longitude'] ?? 0 ), 6, '.', '' );
			}
			$seen[ $sb . '|' . $name . '|' . $tail ] = true;
		}
		return count( $seen );
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
				'update_url'         => null,
				'columns'            => null,
				'language'           => null,
				'query'              => null,
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

		// Language must be one of the codes the widget bundles; otherwise we drop it
		// and let the widget auto-detect from navigator.language.
		if ( null !== $atts['language'] ) {
			$lang = strtolower( trim( (string) $atts['language'] ) );
			if ( in_array( $lang, self::SUPPORTED_LANGUAGES, true ) ) {
				self::$shortcode_language = $lang;
			}
		}

		// Shortcode attribute takes precedence; fall back to saved option only when not provided.
		// Crouton settings (bmlt_tabs_options) are used as a fallback when the crumb option is empty.
		$server = esc_url( trim( $atts['server'] ?? self::get_option_or_crouton( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ) ) );

		if ( empty( $server ) ) {
			return '<p style="color:red"><strong>Crumb:</strong> a <code>server</code> URL is required.</p>';
		}

		// null  → not in shortcode, use saved option.
		// ''    → explicitly set to empty in shortcode, omit data-service-body (show all meetings).
		$service_body = $atts['service_body'] ?? self::get_option_or_crouton( 'crumb_service_body', '' );

		// null → not in shortcode, use saved option. '' → omit (no format lock).
		$format_ids = $atts['format_ids'] ?? self::get_option_or_crouton( 'crumb_format_ids', '' );

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

		// null → not in shortcode, use saved option. '' → omit (no update link).
		$update_url = $atts['update_url'] ?? self::get_option_or_crouton( 'crumb_update_url', '' );
		if ( '' !== $update_url ) {
			$div .= ' data-update-url="' . esc_attr( trim( $update_url ) ) . '"';
		}

		// Comma-separated list of columns; widget validates the values.
		if ( null !== $atts['columns'] && '' !== trim( (string) $atts['columns'] ) ) {
			$div .= ' data-columns="' . esc_attr( trim( (string) $atts['columns'] ) ) . '"';
		}

		// Raw BMLT query string. When set, the widget routes through rawQuery() and disables
		// geolocation, so service_body / format_ids are ignored by the widget — the embedder's
		// query string is authoritative. Shortcode-only (no admin setting).
		if ( null !== $atts['query'] && '' !== trim( (string) $atts['query'] ) ) {
			$div .= ' data-query="' . esc_attr( trim( (string) $atts['query'] ) ) . '"';
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
		if ( ! $post ) {
			return;
		}

		// Check for [crumb] plus any crouton-named tags this plugin actually registered.
		// (Tags claimed by an active crouton plugin won't be in self::$compat_tags.)
		$tags  = array_merge( [ 'crumb' ], self::$compat_tags );
		$found = false;
		foreach ( $tags as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
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

		// Shortcode language overrides; otherwise fall back to the saved option.
		// Empty string in either path means "let the widget auto-detect".
		if ( null !== self::$shortcode_language ) {
			$config['language'] = self::$shortcode_language;
		} elseif ( ! isset( $config['language'] ) ) {
			$saved_language = get_option( 'crumb_language', '' );
			if ( '' !== $saved_language && in_array( $saved_language, self::SUPPORTED_LANGUAGES, true ) ) {
				$config['language'] = $saved_language;
			}
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

		// Dedicated geolocation field — JSON config takes precedence if already set.
		$geolocation_opt = get_option( 'crumb_geolocation', '' );
		if ( '' !== $geolocation_opt && ! isset( $config['geolocation'] ) ) {
			$config['geolocation'] = ( '1' === $geolocation_opt );
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

	public static function sanitize_geolocation( string $input ): string {
		$trimmed = trim( $input );
		if ( '' === $trimmed ) {
			return '';
		}
		return filter_var( $trimmed, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0';
	}

	public static function sanitize_language( string $input ): string {
		$lang = strtolower( trim( $input ) );
		return in_array( $lang, self::SUPPORTED_LANGUAGES, true ) ? $lang : '';
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
		register_setting( $group, 'crumb_update_url', 'sanitize_text_field' );
		register_setting(
			$group,
			'crumb_language',
			[
				'type'              => 'string',
				'sanitize_callback' => [ static::class, 'sanitize_language' ],
			]
		);
		register_setting(
			$group,
			'crumb_geolocation',
			[
				'type'              => 'string',
				'sanitize_callback' => [ static::class, 'sanitize_geolocation' ],
			]
		);
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
				'updateUrl'         => 'https://example.org/meeting-update-form/?meeting_id={meeting_id}',
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
								   value="<?php echo esc_attr( self::get_option_or_crouton( 'crumb_server', 'https://latest.aws.bmlt.app/main_server/' ) ); ?>"
								   class="regular-text" placeholder="https://your-server/main_server" />
							<p class="description">Required. The full URL to your BMLT Server.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_service_body">Service Body IDs</label></th>
						<td>
							<input type="text" id="crumb_service_body" name="crumb_service_body"
								   value="<?php echo esc_attr( self::get_option_or_crouton( 'crumb_service_body', '' ) ); ?>"
								   class="regular-text" placeholder="42 or 42,57,103" />
							<p class="description">Optional. Single ID or comma-separated list. Leave empty to show all meetings. Child service bodies are always included.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_format_ids">Format IDs</label></th>
						<td>
							<input type="text" id="crumb_format_ids" name="crumb_format_ids"
								   value="<?php echo esc_attr( self::get_option_or_crouton( 'crumb_format_ids', '' ) ); ?>"
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
						<th scope="row"><label for="crumb_language">Language</label></th>
						<td>
							<?php
							$current_language = get_option( 'crumb_language', '' );
							$language_names   = [
								'en' => 'English',
								'es' => 'Español',
								'fr' => 'Français',
								'de' => 'Deutsch',
								'pt' => 'Português',
								'it' => 'Italiano',
								'sv' => 'Svenska',
								'da' => 'Dansk',
								'el' => 'Ελληνικά',
								'fa' => 'فارسی',
								'pl' => 'Polski',
								'ru' => 'Русский',
								'ja' => '日本語',
							];
							?>
							<select id="crumb_language" name="crumb_language">
								<option value="" <?php selected( $current_language, '' ); ?>><?php esc_html_e( '— Auto-detect from browser —', 'crumb' ); ?></option>
								<?php foreach ( self::SUPPORTED_LANGUAGES as $code ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_language, $code ); ?>>
										<?php echo esc_html( $language_names[ $code ] ?? $code ); ?> (<?php echo esc_html( $code ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">Optional. Forces the widget UI language. Default behavior is to detect from the visitor's browser (<code>navigator.language</code>). Can be overridden per-page via the shortcode <code>language</code> attribute.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="crumb_geolocation">Geolocation</label></th>
						<td>
							<?php $current_geolocation = get_option( 'crumb_geolocation', '' ); ?>
							<select id="crumb_geolocation" name="crumb_geolocation">
								<option value="" <?php selected( $current_geolocation, '' ); ?>><?php esc_html_e( '— Widget Default —', 'crumb' ); ?></option>
								<option value="1" <?php selected( $current_geolocation, '1' ); ?>><?php esc_html_e( 'On', 'crumb' ); ?></option>
								<option value="0" <?php selected( $current_geolocation, '0' ); ?>><?php esc_html_e( 'Off', 'crumb' ); ?></option>
							</select>
							<p class="description">
								Optional. Enable or disable location-based search (the <strong>Near Me</strong> button and typed-location search).
								Widget defaults to <strong>off</strong> for most servers, and <strong>on</strong> when <code>data-server</code> points at the unconstrained aggregator with no service body set.
								Can be overridden per-page via the shortcode <code>geolocation</code> attribute.
								Overridden by a <code>geolocation</code> key in Widget Configuration below.
							</p>
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
					<tr>
						<th scope="row"><label for="crumb_update_url">Update Meeting URL</label></th>
						<td>
							<input type="text" id="crumb_update_url" name="crumb_update_url"
								   value="<?php echo esc_attr( self::get_option_or_crouton( 'crumb_update_url', '' ) ); ?>"
								   class="large-text" placeholder="https://example.org/meeting-update-form/?meeting_id={meeting_id}" />
							<p class="description">
								Optional. URL template for the <strong>Update Meeting Info</strong> link shown at the bottom of the meeting detail panel.
								Supported tokens (URL-encoded on substitution): <code>{meeting_id}</code>, <code>{meeting_name}</code>, <code>{server_url}</code>, <code>{return_url}</code>.
								Works with <a href="https://github.com/bmlt-enabled/bmlt-workflow" target="_blank">bmlt-workflow</a>, any hosted form, or a <code>mailto:</code> URL. Leave empty to hide the link.
								Can be overridden per-page via the shortcode <code>update_url</code> attribute.
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
				<code>[crumb server="https://your-server/main_server" service_body="42" format_ids="17,54" view="map" geolocation="true" geolocation_radius="-50" language="es"]</code>
				<p><?php esc_html_e( 'Raw BMLT query (replaces the default load, disables geolocation). Encode brackets as %5B / %5D — WordPress shortcodes can\'t contain literal brackets:', 'crumb' ); ?></p>
				<code>[crumb query="meeting_key=location_nation&amp;meeting_key_value%5B%5D=USA"]</code>
				<p><?php esc_html_e( 'Inline counts (server-rendered, cached for one hour). Use anywhere — no widget needed on the page:', 'crumb' ); ?></p>
				<code>[meeting_count] [group_count]</code>
				<p class="description"><?php esc_html_e( 'Uses the BMLT Server URL, Service Body IDs, and Format IDs above by default. Override per-instance with server, service_body, or format_ids attributes.', 'crumb' ); ?></p>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

register_activation_hook( __FILE__, [ 'Crumb', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Crumb', 'deactivate' ] );
Crumb::get_instance();
