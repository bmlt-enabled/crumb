<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$crumb_options = [
	'crumb_server',
	'crumb_service_body',
	'crumb_css_template',
	'crumb_view',
	'crumb_geolocation',
	'crumb_hide_header',
	'crumb_widget_config',
];

foreach ( $crumb_options as $crumb_option ) {
	delete_option( $crumb_option );
}
