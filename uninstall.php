<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$crumb_options = [
	'crumb_server',
	'crumb_service_body',
	'crumb_css_template',
];

foreach ( $crumb_options as $crumb_option ) {
	delete_option( $crumb_option );
}
