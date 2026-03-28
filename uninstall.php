<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bmltclient_options = [
	'bmltclient_root_server',
	'bmltclient_service_body',
	'bmltclient_default_view',
	'bmltclient_language',
	'bmltclient_geolocation',
	'bmltclient_geolocation_radius',
	'bmltclient_columns',
	'bmltclient_cdn_url',
	'bmltclient_config',
	'bmltclient_css_template',
	'bmltclient_custom_css',
];

foreach ( $bmltclient_options as $bmltclient_option ) {
	delete_option( $bmltclient_option );
}
