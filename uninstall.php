<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

function delete_tables() {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ts_tracks" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ts_locations" );
}

delete_option( 'trackserver_options' );
delete_site_option( 'trackserver_options' );
delete_tables();
