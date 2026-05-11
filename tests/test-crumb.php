<?php
/**
 * Integration tests for the Crumb plugin.
 */

class Test_Crumb extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Reset wp_scripts global so inline script data doesn't leak between tests.
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		// Reset private statics on Crumb.
		$ref = new ReflectionClass( Crumb::class );
		foreach ( [ 'shortcode_geolocation', 'shortcode_geolocation_radius', 'crouton_options' ] as $prop ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, null );
		}
	}

	/**
	 * The singleton should always return the same instance.
	 */
	public function test_singleton_returns_same_instance() {
		$a = Crumb::get_instance();
		$b = Crumb::get_instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * The [crumb] shortcode should be registered.
	 */
	public function test_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'crumb' ) );
	}

	// -------------------------------------------------------------------------
	// Shortcode output
	// -------------------------------------------------------------------------

	public function test_shortcode_default_output() {
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'id="crumb-widget"', $html );
		$this->assertStringContainsString( 'data-server="https://latest.aws.bmlt.app/main_server/"', $html );
		$this->assertStringContainsString( 'data-service-body="1047,1048"', $html );
	}

	public function test_shortcode_server_attribute_overrides_option() {
		$html = do_shortcode( '[crumb server="https://example.com/main_server/"]' );
		$this->assertStringContainsString( 'data-server="https://example.com/main_server/"', $html );
	}

	public function test_shortcode_service_body_attribute_overrides_option() {
		$html = do_shortcode( '[crumb service_body="99"]' );
		$this->assertStringContainsString( 'data-service-body="99"', $html );
	}

	public function test_shortcode_empty_service_body_omits_attribute() {
		$html = do_shortcode( '[crumb service_body=""]' );
		$this->assertStringNotContainsString( 'data-service-body', $html );
	}

	public function test_shortcode_view_list() {
		$html = do_shortcode( '[crumb view="list"]' );
		$this->assertStringContainsString( 'data-view="list"', $html );
	}

	public function test_shortcode_view_map() {
		$html = do_shortcode( '[crumb view="map"]' );
		$this->assertStringContainsString( 'data-view="map"', $html );
	}

	public function test_shortcode_view_both() {
		$html = do_shortcode( '[crumb view="both"]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_shortcode_format_ids_attribute_adds_data_attribute() {
		$html = do_shortcode( '[crumb format_ids="17"]' );
		$this->assertStringContainsString( 'data-format-ids="17"', $html );
	}

	public function test_shortcode_format_ids_comma_separated() {
		$html = do_shortcode( '[crumb format_ids="17,54,78"]' );
		$this->assertStringContainsString( 'data-format-ids="17,54,78"', $html );
	}

	public function test_shortcode_empty_format_ids_omits_attribute() {
		$html = do_shortcode( '[crumb format_ids=""]' );
		$this->assertStringNotContainsString( 'data-format-ids', $html );
	}

	public function test_shortcode_format_ids_overrides_saved_option() {
		update_option( 'crumb_format_ids', '99' );
		$html = do_shortcode( '[crumb format_ids="17"]' );
		$this->assertStringContainsString( 'data-format-ids="17"', $html );
		$this->assertStringNotContainsString( 'data-format-ids="99"', $html );
		delete_option( 'crumb_format_ids' );
	}

	public function test_shortcode_uses_saved_format_ids_option() {
		update_option( 'crumb_format_ids', '42,57' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-format-ids="42,57"', $html );
		delete_option( 'crumb_format_ids' );
	}

	public function test_shortcode_no_format_ids_option_omits_attribute() {
		delete_option( 'crumb_format_ids' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringNotContainsString( 'data-format-ids', $html );
	}

	public function test_shortcode_columns_attribute_emits_data_columns() {
		$html = do_shortcode( '[crumb columns="time,name,location,address,service_body"]' );
		$this->assertStringContainsString( 'data-columns="time,name,location,address,service_body"', $html );
	}

	public function test_shortcode_columns_trimmed() {
		$html = do_shortcode( '[crumb columns="  time,name  "]' );
		$this->assertStringContainsString( 'data-columns="time,name"', $html );
	}

	public function test_shortcode_empty_columns_omits_attribute() {
		$html = do_shortcode( '[crumb columns=""]' );
		$this->assertStringNotContainsString( 'data-columns', $html );
	}

	public function test_shortcode_no_columns_attribute_omits_data_attribute() {
		$html = do_shortcode( '[crumb]' );
		$this->assertStringNotContainsString( 'data-columns', $html );
	}

	// -------------------------------------------------------------------------
	// update_url shortcode attribute and option
	// -------------------------------------------------------------------------

	public function test_shortcode_update_url_attribute_adds_data_attribute() {
		$html = do_shortcode( '[crumb update_url="https://example.org/form/?meeting_id={meeting_id}"]' );
		$this->assertStringContainsString( 'data-update-url="https://example.org/form/?meeting_id={meeting_id}"', $html );
	}

	public function test_shortcode_update_url_mailto_attribute() {
		$html = do_shortcode( '[crumb update_url="mailto:webservant@example.org?subject=Update%20{meeting_name}"]' );
		$this->assertStringContainsString( 'data-update-url="mailto:webservant@example.org?subject=Update%20{meeting_name}"', $html );
	}

	public function test_shortcode_empty_update_url_omits_attribute() {
		$html = do_shortcode( '[crumb update_url=""]' );
		$this->assertStringNotContainsString( 'data-update-url', $html );
	}

	public function test_shortcode_update_url_overrides_saved_option() {
		update_option( 'crumb_update_url', 'https://saved.example.org/form/?meeting_id={meeting_id}' );
		$html = do_shortcode( '[crumb update_url="https://override.example.org/form/?meeting_id={meeting_id}"]' );
		$this->assertStringContainsString( 'data-update-url="https://override.example.org/form/?meeting_id={meeting_id}"', $html );
		$this->assertStringNotContainsString( 'saved.example.org', $html );
		delete_option( 'crumb_update_url' );
	}

	public function test_shortcode_uses_saved_update_url_option() {
		update_option( 'crumb_update_url', 'https://example.org/form/?meeting_id={meeting_id}' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-update-url="https://example.org/form/?meeting_id={meeting_id}"', $html );
		delete_option( 'crumb_update_url' );
	}

	public function test_shortcode_no_update_url_option_omits_attribute() {
		delete_option( 'crumb_update_url' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringNotContainsString( 'data-update-url', $html );
	}

	public function test_shortcode_update_url_escapes_html_attributes() {
		$html = do_shortcode( '[crumb update_url=\'"><script>alert(1)</script>\']' );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	public function test_shortcode_invalid_view_is_ignored() {
		$html = do_shortcode( '[crumb view="calendar"]' );
		$this->assertStringNotContainsString( 'data-view', $html );
	}

	public function test_shortcode_empty_server_shows_error() {
		$html = do_shortcode( '[crumb server=""]' );
		$this->assertStringContainsString( 'style="color:red"', $html );
		$this->assertStringContainsString( 'server', $html );
	}

	// -------------------------------------------------------------------------
	// CSS template wrapping
	// -------------------------------------------------------------------------

	public function test_full_width_template_wraps_widget() {
		update_option( 'crumb_css_template', 'full_width' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'class="crumb-full-width"', $html );
		delete_option( 'crumb_css_template' );
	}

	public function test_full_width_force_template_wraps_widget() {
		update_option( 'crumb_css_template', 'full_width_force' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'class="crumb-full-width-force"', $html );
		delete_option( 'crumb_css_template' );
	}

	// -------------------------------------------------------------------------
	// Options fallback
	// -------------------------------------------------------------------------

	public function test_shortcode_uses_saved_server_option() {
		update_option( 'crumb_server', 'https://custom.example.com/main_server/' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-server="https://custom.example.com/main_server/"', $html );
		delete_option( 'crumb_server' );
	}

	public function test_shortcode_uses_saved_view_option() {
		update_option( 'crumb_view', 'map' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-view="map"', $html );
		delete_option( 'crumb_view' );
	}

	public function test_shortcode_uses_saved_both_view_option() {
		update_option( 'crumb_view', 'both' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
		delete_option( 'crumb_view' );
	}

	// -------------------------------------------------------------------------
	// Base path / pretty URLs
	// -------------------------------------------------------------------------

	public function test_shortcode_no_base_path_omits_data_path() {
		delete_option( 'crumb_base_path' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringNotContainsString( 'data-path', $html );
	}

	public function test_shortcode_base_path_adds_data_path_attribute() {
		update_option( 'crumb_base_path', 'meetings' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-path="/meetings"', $html );
		delete_option( 'crumb_base_path' );
	}

	public function test_shortcode_base_path_strips_slashes() {
		update_option( 'crumb_base_path', '/meetings/' );
		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-path="/meetings"', $html );
		delete_option( 'crumb_base_path' );
	}

	public function test_sanitize_base_path_trims_slashes() {
		$result = Crumb::sanitize_base_path( '/meetings/' );
		$this->assertSame( 'meetings', $result );
	}

	public function test_sanitize_base_path_empty_returns_empty() {
		$this->assertSame( '', Crumb::sanitize_base_path( '' ) );
	}

	public function test_sanitize_base_path_change_triggers_rewrite_flush() {
		update_option( 'crumb_base_path', 'old-path' );
		update_option( 'crumb_rewrite_version', Crumb::REWRITE_VERSION );
		Crumb::sanitize_base_path( 'new-path' );
		$this->assertSame( '', get_option( 'crumb_rewrite_version' ) );
		delete_option( 'crumb_base_path' );
		delete_option( 'crumb_rewrite_version' );
	}

	public function test_sanitize_base_path_same_value_no_flush() {
		update_option( 'crumb_base_path', 'meetings' );
		update_option( 'crumb_rewrite_version', Crumb::REWRITE_VERSION );
		Crumb::sanitize_base_path( 'meetings' );
		$this->assertSame( Crumb::REWRITE_VERSION, get_option( 'crumb_rewrite_version' ) );
		delete_option( 'crumb_base_path' );
		delete_option( 'crumb_rewrite_version' );
	}

	public function test_activate_sets_rewrite_version() {
		Crumb::activate();
		$this->assertSame( Crumb::REWRITE_VERSION, get_option( 'crumb_rewrite_version' ) );
		delete_option( 'crumb_rewrite_version' );
	}

	public function test_deactivate_removes_rewrite_version() {
		update_option( 'crumb_rewrite_version', Crumb::REWRITE_VERSION );
		Crumb::deactivate();
		$this->assertFalse( get_option( 'crumb_rewrite_version' ) );
	}

	// -------------------------------------------------------------------------
	// localize_config / wp_add_inline_script type preservation
	// -------------------------------------------------------------------------

	private function get_inline_config(): ?array {
		$raw = wp_scripts()->get_data( 'crumb', 'before' );
		if ( empty( $raw ) ) {
			return null;
		}
		// $raw is an array of script strings; join and extract the JSON.
		$script = implode( "\n", (array) $raw );
		if ( ! preg_match( '/CrumbWidgetConfig\s*=\s*(\{.+\});/', $script, $m ) ) {
			return null;
		}
		return json_decode( $m[1], true );
	}

	private function enqueue_crumb(): void {
		wp_enqueue_script( 'crumb', Crumb::DEFAULT_CDN_URL, [], CRUMB_VERSION, true );
	}

	public function test_localize_config_preserves_numeric_types() {
		$this->enqueue_crumb();
		update_option( 'crumb_widget_config', '{"geolocationRadius":30}' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( 30, $config['geolocationRadius'] );

		delete_option( 'crumb_widget_config' );
	}

	public function test_localize_config_preserves_boolean_types() {
		$this->enqueue_crumb();
		update_option( 'crumb_widget_config', '{"geolocation":true,"darkMode":false}' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertTrue( $config['geolocation'] );
		$this->assertFalse( $config['darkMode'] );

		delete_option( 'crumb_widget_config' );
	}

	public function test_localize_config_empty_config_outputs_nothing() {
		$this->enqueue_crumb();
		delete_option( 'crumb_widget_config' );

		Crumb::localize_config();

		$raw = wp_scripts()->get_data( 'crumb', 'before' );
		$this->assertEmpty( $raw );
	}

	public function test_localize_config_not_enqueued_outputs_nothing() {
		// Do NOT enqueue crumb — localize_config should bail early.
		update_option( 'crumb_widget_config', '{"geolocationRadius":30}' );

		Crumb::localize_config();

		$raw = wp_scripts()->get_data( 'crumb', 'before' );
		$this->assertEmpty( $raw );

		delete_option( 'crumb_widget_config' );
	}

	// -------------------------------------------------------------------------
	// geolocation_radius shortcode attribute
	// -------------------------------------------------------------------------

	public function test_shortcode_geolocation_radius_sets_config() {
		$this->enqueue_crumb();
		do_shortcode( '[crumb geolocation_radius="30"]' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( 30, $config['geolocationRadius'] );
	}

	public function test_shortcode_geolocation_radius_overrides_widget_config() {
		$this->enqueue_crumb();
		update_option( 'crumb_widget_config', '{"geolocationRadius":75}' );
		do_shortcode( '[crumb geolocation_radius="30"]' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( 30, $config['geolocationRadius'] );

		delete_option( 'crumb_widget_config' );
	}

	public function test_shortcode_geolocation_radius_zero_is_ignored() {
		$this->enqueue_crumb();
		do_shortcode( '[crumb geolocation_radius="0"]' );

		Crumb::localize_config();

		$raw = wp_scripts()->get_data( 'crumb', 'before' );
		$this->assertEmpty( $raw );
	}

	public function test_shortcode_geolocation_radius_negative_is_accepted() {
		// Negative geo_width is valid BMLT auto-radius: find roughly N meetings.
		$this->enqueue_crumb();
		do_shortcode( '[crumb geolocation_radius="-50"]' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( -50, $config['geolocationRadius'] );
	}

	// -------------------------------------------------------------------------
	// sanitize_geolocation_radius
	// -------------------------------------------------------------------------

	public function test_sanitize_geolocation_radius_positive() {
		$this->assertSame( '30', Crumb::sanitize_geolocation_radius( '30' ) );
	}

	public function test_sanitize_geolocation_radius_negative() {
		$this->assertSame( '-50', Crumb::sanitize_geolocation_radius( '-50' ) );
	}

	public function test_sanitize_geolocation_radius_zero_returns_empty() {
		$this->assertSame( '', Crumb::sanitize_geolocation_radius( '0' ) );
	}

	public function test_sanitize_geolocation_radius_empty_returns_empty() {
		$this->assertSame( '', Crumb::sanitize_geolocation_radius( '' ) );
	}

	public function test_sanitize_geolocation_radius_trims_whitespace() {
		$this->assertSame( '30', Crumb::sanitize_geolocation_radius( '  30  ' ) );
	}

	// -------------------------------------------------------------------------
	// geolocation radius option merging in get_config / localize_config
	// -------------------------------------------------------------------------

	public function test_dedicated_radius_option_sets_config() {
		$this->enqueue_crumb();
		update_option( 'crumb_geolocation_radius', '-50' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( -50, $config['geolocationRadius'] );

		delete_option( 'crumb_geolocation_radius' );
	}

	public function test_json_config_overrides_dedicated_radius_option() {
		$this->enqueue_crumb();
		update_option( 'crumb_geolocation_radius', '-50' );
		update_option( 'crumb_widget_config', '{"geolocationRadius":30}' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( 30, $config['geolocationRadius'] );

		delete_option( 'crumb_geolocation_radius' );
		delete_option( 'crumb_widget_config' );
	}

	public function test_shortcode_attribute_overrides_dedicated_radius_option() {
		$this->enqueue_crumb();
		update_option( 'crumb_geolocation_radius', '-50' );
		do_shortcode( '[crumb geolocation_radius="25"]' );

		Crumb::localize_config();

		$config = $this->get_inline_config();
		$this->assertNotNull( $config );
		$this->assertSame( 25, $config['geolocationRadius'] );

		delete_option( 'crumb_geolocation_radius' );
	}

	public function test_dedicated_radius_option_empty_does_not_set_config() {
		$this->enqueue_crumb();
		delete_option( 'crumb_geolocation_radius' );

		Crumb::localize_config();

		$raw = wp_scripts()->get_data( 'crumb', 'before' );
		$this->assertEmpty( $raw );
	}

	// -------------------------------------------------------------------------
	// sanitize_config
	// -------------------------------------------------------------------------

	public function test_sanitize_config_valid_json() {
		$input  = '{"language":"es","geolocation":true}';
		$result = Crumb::sanitize_config( $input );
		$this->assertJson( $result );
		$decoded = json_decode( $result, true );
		$this->assertSame( 'es', $decoded['language'] );
		$this->assertTrue( $decoded['geolocation'] );
	}

	public function test_sanitize_config_pretty_prints() {
		$input  = '{"a":1}';
		$result = Crumb::sanitize_config( $input );
		$this->assertStringContainsString( "\n", $result );
	}

	public function test_sanitize_config_empty_string_returns_empty() {
		$this->assertSame( '', Crumb::sanitize_config( '' ) );
	}

	public function test_sanitize_config_invalid_json_preserves_previous() {
		update_option( 'crumb_widget_config', '{"old":"value"}' );
		$result = Crumb::sanitize_config( 'not json{' );
		$this->assertSame( '{"old":"value"}', $result );
		delete_option( 'crumb_widget_config' );
	}

	// -------------------------------------------------------------------------
	// Settings registration
	// -------------------------------------------------------------------------

	public function test_settings_are_registered() {
		// Call register_settings directly to avoid headers-already-sent from admin_init.
		Crumb::register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'crumb_server', $registered );
		$this->assertArrayHasKey( 'crumb_service_body', $registered );
		$this->assertArrayHasKey( 'crumb_format_ids', $registered );
		$this->assertArrayHasKey( 'crumb_css_template', $registered );
		$this->assertArrayHasKey( 'crumb_view', $registered );
		$this->assertArrayHasKey( 'crumb_geolocation_radius', $registered );
		$this->assertArrayHasKey( 'crumb_base_path', $registered );
		$this->assertArrayHasKey( 'crumb_update_url', $registered );
		$this->assertArrayHasKey( 'crumb_widget_config', $registered );
	}

	// -------------------------------------------------------------------------
	// Settings link
	// -------------------------------------------------------------------------

	public function test_settings_link_is_added() {
		$links = Crumb::settings_link( [] );
		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'options-general.php?page=crumb', $links[0] );
	}

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	public function test_crumb_version_constant_defined() {
		$this->assertTrue( defined( 'CRUMB_VERSION' ) );
		$this->assertNotEmpty( CRUMB_VERSION );
	}

	public function test_cdn_url_constant() {
		$this->assertSame( 'https://cdn.aws.bmlt.app/crumb-widget.js', Crumb::DEFAULT_CDN_URL );
	}

	// -------------------------------------------------------------------------
	// Crouton compatibility — shortcode registration
	// -------------------------------------------------------------------------

	public function test_crouton_shortcodes_are_registered() {
		// register_crouton_shortcodes ran on init during bootstrap.
		$this->assertTrue( shortcode_exists( 'crouton_map' ) );
		$this->assertTrue( shortcode_exists( 'crouton_tabs' ) );
		$this->assertTrue( shortcode_exists( 'bmlt_map' ) );
		$this->assertTrue( shortcode_exists( 'bmlt_tabs' ) );
	}

	public function test_crouton_shortcodes_appear_in_compat_tags() {
		$tags = Crumb::compat_tags();
		$this->assertContains( 'crouton_map', $tags );
		$this->assertContains( 'crouton_tabs', $tags );
		$this->assertContains( 'bmlt_map', $tags );
		$this->assertContains( 'bmlt_tabs', $tags );
	}

	public function test_register_crouton_shortcodes_does_not_overwrite_existing() {
		global $shortcode_tags;
		$saved   = $shortcode_tags['bmlt_tabs'] ?? null;
		$sentinel = static function () {
			return 'PRE-EXISTING';
		};
		// Remove crumb's and install a sentinel handler.
		remove_shortcode( 'bmlt_tabs' );
		add_shortcode( 'bmlt_tabs', $sentinel );

		// Calling register again should NOT overwrite the sentinel.
		Crumb::register_crouton_shortcodes();
		$this->assertSame( 'PRE-EXISTING', do_shortcode( '[bmlt_tabs]' ) );

		// Restore crumb's handler for subsequent tests.
		remove_shortcode( 'bmlt_tabs' );
		if ( $saved ) {
			$shortcode_tags['bmlt_tabs'] = $saved;
		}
	}

	// -------------------------------------------------------------------------
	// Crouton compatibility — view mapping
	// -------------------------------------------------------------------------

	public function test_crouton_map_renders_view_both() {
		$html = do_shortcode( '[crouton_map]' );
		$this->assertStringContainsString( 'id="crumb-widget"', $html );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_crouton_tabs_renders_view_list() {
		$html = do_shortcode( '[crouton_tabs]' );
		$this->assertStringContainsString( 'data-view="list"', $html );
	}

	public function test_bmlt_map_renders_view_both() {
		$html = do_shortcode( '[bmlt_map]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_bmlt_tabs_renders_view_list() {
		$html = do_shortcode( '[bmlt_tabs]' );
		$this->assertStringContainsString( 'data-view="list"', $html );
	}

	// -------------------------------------------------------------------------
	// Crouton compatibility — attribute translation
	// -------------------------------------------------------------------------

	public function test_crouton_root_server_attribute_maps_to_data_server() {
		$html = do_shortcode( '[crouton_map root_server="https://example.com/main_server/"]' );
		$this->assertStringContainsString( 'data-server="https://example.com/main_server/"', $html );
	}

	public function test_crouton_service_body_attribute_maps_through() {
		$html = do_shortcode( '[crouton_tabs service_body="42"]' );
		$this->assertStringContainsString( 'data-service-body="42"', $html );
	}

	public function test_crouton_service_body_1_attribute_maps_through() {
		$html = do_shortcode( '[bmlt_tabs service_body_1="99"]' );
		$this->assertStringContainsString( 'data-service-body="99"', $html );
	}

	public function test_crouton_formats_attribute_maps_to_format_ids() {
		$html = do_shortcode( '[crouton_map formats="17,54"]' );
		$this->assertStringContainsString( 'data-format-ids="17,54"', $html );
	}

	public function test_crouton_show_map_1_upgrades_tabs_to_both() {
		$html = do_shortcode( '[bmlt_tabs show_map="1"]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_crouton_show_map_1_upgrades_crouton_tabs_to_both() {
		$html = do_shortcode( '[crouton_tabs show_map="1"]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_crouton_show_map_0_keeps_default_tabs_view() {
		$html = do_shortcode( '[bmlt_tabs show_map="0"]' );
		$this->assertStringContainsString( 'data-view="list"', $html );
	}

	public function test_crouton_no_show_map_attr_keeps_default_view() {
		$html = do_shortcode( '[bmlt_tabs]' );
		$this->assertStringContainsString( 'data-view="list"', $html );
	}

	public function test_crouton_show_map_1_on_map_shortcode_stays_both() {
		$html = do_shortcode( '[bmlt_map show_map="1"]' );
		$this->assertStringContainsString( 'data-view="both"', $html );
	}

	public function test_crouton_report_update_url_attribute_maps_to_update_url() {
		$html = do_shortcode( '[crouton_tabs report_update_url="https://example.org/form/?meeting_id={meeting_id}"]' );
		$this->assertStringContainsString( 'data-update-url="https://example.org/form/?meeting_id={meeting_id}"', $html );
	}

	public function test_crouton_has_areas_adds_service_body_column() {
		$html = do_shortcode( '[bmlt_tabs has_areas="1"]' );
		$this->assertStringContainsString( 'data-columns="time,distance,name,location,address,service_body"', $html );
	}

	public function test_crouton_has_regions_adds_service_body_column() {
		$html = do_shortcode( '[crouton_map has_regions="1"]' );
		$this->assertStringContainsString( 'data-columns="time,distance,name,location,address,service_body"', $html );
	}

	public function test_crouton_has_areas_true_adds_service_body_column() {
		$html = do_shortcode( '[bmlt_tabs has_areas="true"]' );
		$this->assertStringContainsString( 'data-columns="time,distance,name,location,address,service_body"', $html );
	}

	public function test_crouton_has_areas_zero_does_not_add_columns() {
		$html = do_shortcode( '[bmlt_tabs has_areas="0"]' );
		$this->assertStringNotContainsString( 'data-columns', $html );
	}

	public function test_crouton_no_has_areas_or_has_regions_omits_columns() {
		$html = do_shortcode( '[bmlt_tabs]' );
		$this->assertStringNotContainsString( 'data-columns', $html );
	}

	public function test_crouton_has_areas_on_map_shortcode_adds_service_body_column() {
		$html = do_shortcode( '[bmlt_map has_areas="1"]' );
		$this->assertStringContainsString( 'data-columns="time,distance,name,location,address,service_body"', $html );
	}

	// -------------------------------------------------------------------------
	// Crouton compatibility — bmlt_tabs_options fallback
	// -------------------------------------------------------------------------

	public function test_fallback_server_from_crouton_options_when_crumb_unset() {
		delete_option( 'crumb_server' );
		update_option(
			'bmlt_tabs_options',
			[ 'root_server' => 'https://crouton.example.org/main_server' ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-server="https://crouton.example.org/main_server"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_crumb_option_takes_precedence_over_crouton_fallback() {
		update_option( 'crumb_server', 'https://crumb.example.org/main_server/' );
		update_option(
			'bmlt_tabs_options',
			[ 'root_server' => 'https://crouton.example.org/main_server' ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-server="https://crumb.example.org/main_server/"', $html );
		$this->assertStringNotContainsString( 'crouton.example.org', $html );

		delete_option( 'crumb_server' );
		delete_option( 'bmlt_tabs_options' );
	}

	public function test_fallback_service_bodies_array_is_joined() {
		delete_option( 'crumb_service_body' );
		update_option(
			'bmlt_tabs_options',
			[ 'service_bodies' => [ 42, 57, 103 ] ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-service-body="42,57,103"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_fallback_service_body_string() {
		delete_option( 'crumb_service_body' );
		update_option(
			'bmlt_tabs_options',
			[ 'service_body' => '42' ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-service-body="42"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_fallback_service_bodies_preferred_over_service_body() {
		delete_option( 'crumb_service_body' );
		update_option(
			'bmlt_tabs_options',
			[
				'service_bodies' => [ 1, 2 ],
				'service_body'   => '99',
			]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-service-body="1,2"', $html );
		$this->assertStringNotContainsString( 'data-service-body="99"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_fallback_formats_to_format_ids() {
		delete_option( 'crumb_format_ids' );
		update_option(
			'bmlt_tabs_options',
			[ 'formats' => '17,54' ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-format-ids="17,54"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_fallback_report_update_url_to_update_url() {
		delete_option( 'crumb_update_url' );
		update_option(
			'bmlt_tabs_options',
			[ 'report_update_url' => 'https://example.org/form/?meeting_id={meeting_id}' ]
		);

		$html = do_shortcode( '[crumb]' );
		$this->assertStringContainsString( 'data-update-url="https://example.org/form/?meeting_id={meeting_id}"', $html );

		delete_option( 'bmlt_tabs_options' );
	}

	public function test_get_option_or_crouton_returns_default_when_both_unset() {
		delete_option( 'crumb_server' );
		delete_option( 'bmlt_tabs_options' );
		$this->assertSame(
			'https://fallback.example.org/main_server/',
			Crumb::get_option_or_crouton( 'crumb_server', 'https://fallback.example.org/main_server/' )
		);
	}

	public function test_get_option_or_crouton_with_unmapped_key_returns_default() {
		// crumb_view has no crouton equivalent — fallback should be default.
		delete_option( 'crumb_view' );
		update_option( 'bmlt_tabs_options', [ 'root_server' => 'https://x/main_server' ] );
		$this->assertSame( 'default-val', Crumb::get_option_or_crouton( 'crumb_view', 'default-val' ) );
		delete_option( 'bmlt_tabs_options' );
	}

	// -------------------------------------------------------------------------
	// Crouton compatibility — no-op helper shortcodes
	// -------------------------------------------------------------------------

	public function test_crouton_noop_shortcodes_are_registered() {
		foreach ( Crumb::CROUTON_NOOP_TAGS as $tag ) {
			$this->assertTrue( shortcode_exists( $tag ), "Expected {$tag} to be registered" );
		}
	}

	public function test_crouton_noop_shortcodes_render_empty() {
		foreach ( Crumb::CROUTON_NOOP_TAGS as $tag ) {
			$this->assertSame( '', do_shortcode( "[{$tag}]" ), "Expected [{$tag}] to render empty" );
		}
	}

	public function test_crouton_noop_shortcodes_render_empty_with_attributes() {
		// Attributes should be ignored; output stays empty.
		$this->assertSame( '', do_shortcode( '[bmlt_count live="1"]' ) );
		$this->assertSame( '', do_shortcode( '[root_service_body field="name"]' ) );
		$this->assertSame( '', do_shortcode( '[bmlt_handlebar template="foo"]' ) );
	}

	public function test_register_crouton_shortcodes_does_not_overwrite_noop_tag() {
		global $shortcode_tags;
		$saved    = $shortcode_tags['bmlt_count'] ?? null;
		$sentinel = static function () {
			return 'PRE-EXISTING';
		};
		remove_shortcode( 'bmlt_count' );
		add_shortcode( 'bmlt_count', $sentinel );

		Crumb::register_crouton_shortcodes();
		$this->assertSame( 'PRE-EXISTING', do_shortcode( '[bmlt_count]' ) );

		// Restore.
		remove_shortcode( 'bmlt_count' );
		if ( $saved ) {
			$shortcode_tags['bmlt_count'] = $saved;
		}
	}

	public function test_crouton_noop_tags_not_in_compat_tags() {
		// No-op tags don't render a widget, so they shouldn't trigger script enqueue.
		$compat = Crumb::compat_tags();
		foreach ( Crumb::CROUTON_NOOP_TAGS as $tag ) {
			$this->assertNotContains( $tag, $compat, "{$tag} should not be in compat_tags" );
		}
	}

	public function test_crouton_shortcode_combines_attribute_and_fallback() {
		// root_server from crouton fallback; service_body from shortcode att.
		delete_option( 'crumb_server' );
		delete_option( 'crumb_service_body' );
		update_option(
			'bmlt_tabs_options',
			[ 'root_server' => 'https://fallback.example.org/main_server' ]
		);

		$html = do_shortcode( '[crouton_map service_body="7"]' );
		$this->assertStringContainsString( 'data-server="https://fallback.example.org/main_server"', $html );
		$this->assertStringContainsString( 'data-service-body="7"', $html );
		$this->assertStringContainsString( 'data-view="both"', $html );

		delete_option( 'bmlt_tabs_options' );
	}
}
