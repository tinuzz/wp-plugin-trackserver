<?php

/*
Plugin Name: Trackserver
Plugin Script: trackserver.php
Plugin URI: https://www.grendelman.net/wp/trackserver-wordpress-plugin/
Description: GPS Track Server for TrackMe, OruxMaps and others
Version: 5.0.2
Author: Martijn Grendelman
Author URI: http://www.grendelman.net/
Text Domain: trackserver
Domain path: /lang
License: GPL2

=== RELEASE HISTORY ===
2023-03-05 - v5.0.2 - Bugfix
2023-02-06 - v5.0  - new features, code refactoring, leaflet 1.9.3
2019-09-10 - v4.3.1 - bugfix release
2019-09-06 - v4.3   - new features, bugfixes, leaflet 1.5.1
2019-08-21 - v4.2.3 - Bugfix release
2018-10-18 - v4.2.2 - fix critical bug in 4.2/4.2.1
2018-10-18 - v4.2.1 - dutch translation update
2018-10-18 - v4.2 - small improvements, leaflet 1.3.4
2018-10-08 - v4.1 - some small new features and some bugfixes
2018-02-23 - v4.0.2 - bugfix release
2018-02-23 - v4.0.1 - bugfix release
2018-02-22 - v4.0 - new features, bugfixes, leaflet 1.3.1, coding style
2017-02-28 - v3.0.1 - cache busters for JavaScript files
2017-02-27 - v3.0 - many new features, bugfixes, leaflet 1.0.3
2016-12-23 - v2.3 - Bugfixes, KML file support
2016-07-19 - v2.2 - new features, default tile URL update
2016-06-06 - v2.1 - MapMyTracks upload support, new admin capabilities, bugfixes
2015-12-23 - v2.0.2 - Bugfix release
2015-12-23 - v2.0.1 - Bugfix release
2015-12-22 - v2.0 - multiple tracks support, other features, leaflet 0.7.7
2015-09-01 - v1.9 - fixes, user profiles, leaflet 0.7.4, FAQs
2015-07-29 - v1.8 - a critical bugfix for MapMyTracks protocol
2015-06-15 - v1.7 - TrackMe 2.0 compatibility, i18n and bugfixes
2015-04-29 - v1.6 - Map data / tile attribution and bugfix
2015-04-15 - v1.5 - Bugfixes
2015-03-08 - v1.4 - OsmAnd support, other features and bugfixes
2015-02-28 - v1.3 - features and bugfixes
2015-02-20 - v1.2 - features, performance enhancements and bugfixes
2015-02-12 - v1.1 - features and bugfixes
2015-02-10 - v1.0 - first official release
2015-01-02 - v0.9 - first release on wordpress.org
*/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No, sorry.' );
}

define( 'TRACKSERVER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRACKSERVER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TRACKSERVER_JSLIB', TRACKSERVER_PLUGIN_URL . 'lib/' );
define( 'TRACKSERVER_VERSION', '4.3-20190906' );

require_once( TRACKSERVER_PLUGIN_DIR . 'class-trackserver.php' );

// For 4.3.0 <= PHP <= 5.4.0
if ( ! function_exists( 'http_response_code' ) ) {
	function http_response_code( $newcode = null ) {
		static $code = 200;
		if ( $newcode !== null ) {
			header( 'X-PHP-Response-Code: ' . $newcode, true, $newcode );
			if ( ! headers_sent() ) {
				$code = $newcode;
			}
		}
		return $code;
	}
}
