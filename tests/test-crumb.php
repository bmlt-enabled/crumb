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
		foreach ( [ 'shortcode_geolocation', 'shortcode_geolocation_radius' ] as $prop ) {
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
}
