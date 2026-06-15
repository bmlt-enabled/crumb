<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$crumb_options = [
	'crumb_server',
	'crumb_service_body',
	'crumb_format_ids',
	'crumb_css_template',
	'crumb_view',
	'crumb_update_url',
	'crumb_show_formats',
	'crumb_inline_formats',
	'crumb_geolocation',
	'crumb_geolocation_radius',
	'crumb_hide_header',
	'crumb_base_path',
	'crumb_language',
	'crumb_widget_config',
];

foreach ( $crumb_options as $crumb_option ) {
	delete_option( $crumb_option );
}
