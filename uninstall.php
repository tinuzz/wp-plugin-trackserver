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

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	global $wpdb;
	$old_blog =  $wpdb->blogid;
	$blogids =  $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
	foreach ( $blogids as $blog_id ) {
		switch_to_blog($blog_id);
		delete_tables();
	}
	switch_to_blog( $old_blog );
} else {
	delete_tables();
}
