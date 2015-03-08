<?php

/*
Plugin Name: Trackserver
Plugin Script: trackserver.php
Plugin URI: https://www.grendelman.net/wp/trackserver-wordpress-plugin/
Description: GPS Track Server for TrackMe, OruxMaps and others
Version: 1.4
Author: Martijn Grendelman
Author URI: http://www.grendelman.net/
License: GPL2

=== RELEASE NOTES ===
2015-03-08 - v1.4 - OsmAnd support, other features and bugfixes
2015-02-28 - v1.3 - features and bugfixes
2015-02-20 - v1.2 - features, performance enhancements and bugfixes
2015-02-12 - v1.1 - features and bugfixes
2015-02-10 - v1.0 - first official release
2015-01-02 - v0.9 - first release on wordpress.org
*/

	if ( ! defined( 'ABSPATH' ) ) {
		die( "No, sorry." );
	}

	if ( ! class_exists( 'Trackserver' ) ) {

		define( 'TRACKSERVER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'TRACKSERVER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'TRACKSERVER_JSLIB', TRACKSERVER_PLUGIN_URL . 'lib/' );

		/**
		 * The main plugin class.
		 */
		class Trackserver {

			/**
			 * Database version that this code base needs.
			 *
			 * @since 1.0
			 * @access private
			 * @var int $db_version
			 */
			var $db_version = 8;

			/**
			 * Default values for options. See class constructor for more.
			 *
			 * @since 1.0
			 * @access private
			 * @var array $option_defaults
			 */
			var $option_defaults = array(
				'trackme_slug' => 'trackme',
				'trackme_extension' => 'z',
				'mapmytracks_tag' => 'mapmytracks',
				'osmand_slug' => 'osmand',
				'osmand_trackname_format' => '%F %H',
				'upload_tag' => 'tsupload',
				'gettrack_slug' => 'trackserver/gettrack',
				'normalize_tripnames' => 'yes',
				'tripnames_format' => '%F %T',
				'tile_url' => 'http://otile3.mqcdn.com/tiles/1.0.0/osm/{z}/{x}/{y}.png',
			);

			/**
			 * Class constructor.
			 *
			 * @since 1.0
			 */
			function __construct () {
				global $wpdb;
				$this -> tbl_tracks = $wpdb->prefix . "ts_tracks";
				$this -> tbl_locations = $wpdb->prefix . "ts_locations";
				$this -> options = get_option( 'trackserver_options' );
				$this -> option_defaults['db_version'] = $this -> db_version;
				$this -> option_defaults['osmand_key'] = substr( uniqid(), 0, 8 );
				$this -> add_missing_options();
				$this -> shortcode = 'tsmap';
				$this -> mapdata = array();
				$this -> tracks_list_table = false;
				$this -> bulk_action_result_msg = false;
				$this -> url_prefix = '';

				// Bootstrap
				$this -> add_actions();
				if ( is_admin() ) {
					$this -> add_admin_actions();
				}
			}

			/**
			 * Fill in missing default options.
			 *
			 * @since 1.1
			 */
			function add_missing_options() {
				foreach ( $this -> option_defaults as $option => $value ) {
					if ( ! array_key_exists( $option, $this -> options ) ) {
						$this -> update_option( $option, $value );
					}
				}
			}

			/**
			 * Add common actions and filters.
			 *
			 * @since 1.0
			 */
			function add_actions() {

				// This hook is called upon activation of the plugin
				register_activation_hook( __FILE__, array( &$this, 'trackserver_install' ) );

				// Set up permalink-related values
				add_action( 'wp_loaded', array( &$this, 'wp_loaded' ) );

				// Custom request parser; core protocol handler
				add_action( 'parse_request', array( &$this, 'parse_request' ) );

				// Add handler for TrackMe server via WP AJAX interface for both logged-in and not-logged-in users
				add_action( 'wp_ajax_trackserver_trackme', array( &$this, 'handle_trackme_request' ) );
				add_action( 'wp_ajax_nopriv_trackserver_trackme', array( &$this, 'handle_trackme_request' ) );

				// Front-end JavaScript and CSS
				add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );

				// Shortcode
				add_shortcode( 'tsmap', array( &$this, 'handle_shortcode' ) );
				add_action( 'loop_end', array( &$this, 'loop_end' ) );

				// Media upload
				add_filter( 'upload_mimes', array( &$this, 'upload_mimes' ) );
				add_filter( 'media_send_to_editor', array( &$this, 'media_send_to_editor' ), 10, 3 );
			}

			/**
			 * Add actions for the admin pages.
			 *
			 * @since 1.0
			 */
			function add_admin_actions() {

				add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
				add_action( 'admin_init', array( &$this, 'admin_init' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'add_settings_link' ) );
				add_action( 'admin_head', array( &$this, 'admin_head' ) );  // CSS for table styling
				add_action( 'admin_post_trackserver_save_track', array( &$this, 'admin_post_save_track' ) );
				add_action( 'admin_post_trackserver_upload_track', array( &$this, 'admin_post_upload_track' ) );
				add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );

			}

			/**
			 * Handler for 'wp_loaded' action. Sets up the URL prefix for all
			 * Trackserver-generated URLs based on the use of permalinks
			 *
			 * If permalinks are not in use, or the main index (index.php) is used in
			 * the permalink structure, the main index should also be used in all
			 * URLs used and generated by Trackserver.
			 *
			 * @since 1.3
			 */
			function wp_loaded() {
				global $wp_rewrite;
				if ( ! $wp_rewrite -> using_permalinks() || $wp_rewrite -> using_index_permalinks() ) {
					$this -> url_prefix = '/' . $wp_rewrite -> index;
				}
			}

			/**
			 * Print some CSS in the header of the admin panel.
			 *
			 * @since 1.0
			 */
			function admin_head() {
				echo <<<EOF
					<style type="text/css">
						.wp-list-table .column-id { width: 50px; }
						.wp-list-table .column-tstart { width: 150px; }
						.wp-list-table .column-tend { width: 150px; }
						.wp-list-table .column-numpoints { width: 50px; }
						.wp-list-table .column-distance { width: 60px; }
						.wp-list-table .column-edit { width: 50px; }
						.wp-list-table .column-view { width: 50px; }
						#addtrack { margin: 1px 8px 0 0; }
					</style>\n
EOF;
			}

			/**
			 * Update options.
			 *
			 * Set the value in the options array and write the array to the database.
			 *
			 * @since 1.0
			 *
			 * @see update_option()
			 *
			 * @param string $option Option name
			 * @param string $value Option value
			 */
			function update_option( $option, $value ) {
				$this -> options[ $option ] = $value;
				update_option( 'trackserver_options', $this -> options );
			}

			/**
			 * Handler for 'wp_print_scripts'. Load scripts.
			 *
			 * @since 1.0
			 */
			function load_common_scripts() {

				wp_enqueue_style( 'leaflet', TRACKSERVER_JSLIB . 'leaflet-0.7.3/leaflet.css' );
				wp_enqueue_script( 'leaflet', TRACKSERVER_JSLIB . 'leaflet-0.7.3/leaflet.js', array(), false, true );
				wp_enqueue_style( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-0.0.4/Leaflet.fullscreen.css' );
				wp_enqueue_script( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-0.0.4/Leaflet.fullscreen.min.js', array(), false, true );
				wp_enqueue_script( 'leaflet-omnivore', TRACKSERVER_PLUGIN_URL . 'trackserver-omnivore.js', array(), false, true );

				// To be localized in the shortcode and enqueued in loop_end
				// Also localized and enqueued in admin_enqueue_scripts
				wp_register_script( 'trackserver', TRACKSERVER_PLUGIN_URL .'trackserver.js' );

				$settings = array(
						'iconpath' => TRACKSERVER_PLUGIN_URL . 'img/',
						'tile_url' => $this -> options['tile_url'],
				);
				wp_localize_script( 'trackserver', 'trackserver_settings', $settings );
			}

			/**
			 * Handler for 'wp_enqueue_scripts'. Load javascript and stylesheets on
			 * the front-end.
			 *
			 * @since 1.0
			 */
			function wp_enqueue_scripts() {
				if ( $this -> detect_shortcode() ) {
					$this -> load_common_scripts();

					// Live-update only on the front-end, not in admin
					wp_enqueue_style( 'leaflet-messagebox', TRACKSERVER_JSLIB .'leaflet-messagebox-1.0/leaflet-messagebox.css' );
					wp_enqueue_script( 'leaflet-messagebox', TRACKSERVER_JSLIB .'leaflet-messagebox-1.0/leaflet-messagebox.js', array(), false, true );
					wp_enqueue_style( 'leaflet-liveupdate', TRACKSERVER_JSLIB .'leaflet-liveupdate-1.0/leaflet-liveupdate.css' );
					wp_enqueue_script( 'leaflet-liveupdate', TRACKSERVER_JSLIB .'leaflet-liveupdate-1.0/leaflet-liveupdate.js', array(), false, true );
				}
			}

			/**
			 * Handler for 'admin_enqueue_scripts'. Load javascript and stylesheets
			 * in the admin panel.
			 *
			 * @since 1.0
			 *
			 * @param string $hook The hook suffix for the current admin page.
			 */
			function admin_enqueue_scripts( $hook ) {

				switch ( $hook ) {
					case 'trackserver_page_trackserver-tracks':

						$this -> load_common_scripts();

						// The is_ssl() check should not be necessary, but somehow, get_home_url() doesn't correctly return a https URL by itself
						$track_base_url = get_home_url( null, $this -> url_prefix . '/' . $this -> options['gettrack_slug'] . "/?", ( is_ssl()  ? 'https' : 'http' ) );
						wp_localize_script( 'trackserver', 'track_base_url', $track_base_url );
						wp_enqueue_script( 'trackserver', TRACKSERVER_PLUGIN_URL . 'trackserver.js', array(), false, true );

						// No break! The following goes for both hooks.

					case 'toplevel_page_trackserver-options':

						// Enqueue the admin js (Thickbox overrides) in the footer
						wp_enqueue_script( 'trackserver-admin', TRACKSERVER_PLUGIN_URL . 'trackserver-admin.js', array( 'thickbox' ), null, true );
						break;
				}
			}

			/**
			 * Installer function.
			 *
			 * This runs when the plugin in activated and installs the database table
			 * and sets default option values
			 *
			 * @since 1.0
			 *
			 * @global object $wpdb The WordPress database interface
			 */
			function trackserver_install() {
				global $wpdb;

				$sql = "CREATE TABLE IF NOT EXISTS " . $this -> tbl_locations . " (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`trip_id` int(11) NOT NULL,
					`latitude` double NOT NULL,
					`longitude` double NOT NULL,
					`altitude` double NOT NULL,
					`speed` double NOT NULL,
					`heading` double NOT NULL,
					`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
					`occurred` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
					`comment` varchar(255) NOT NULL,
					PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

				$wpdb->query( $sql );

				$sql = "CREATE TABLE IF NOT EXISTS " . $this -> tbl_tracks . " (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`name` varchar(255) NOT NULL,
					`update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
					`source` varchar(255) NOT NULL,
					`comment` varchar(255) NOT NULL,
					`distance` int(11) NOT NULL,
					PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

				$wpdb->query( $sql );

				// Update database schema
				$this -> check_update_db();

				// Add options and capabilities to the database
				$this -> add_options();
				$this -> set_capabilities();
			}

			/**
			 * Check if the database schema is the correct version and upgrade if necessary.
			 *
			 * @since 1.0
			 *
			 * @global object $wpdb The WordPress database interface
			 */
			function check_update_db() {
				global $wpdb;

				// Upgrade table if necessary. Add upgrade SQL statements here, and
				// update $db_version at the top of the file
				$upgrade_sql = array();
				$upgrade_sql[5] = "ALTER TABLE " . $this -> tbl_tracks . " CHANGE `created` `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
				$upgrade_sql[6] = "ALTER TABLE " . $this -> tbl_tracks . " ADD `created` TIMESTAMP NOT NULL AFTER `updated`";
				$upgrade_sql[7] = "ALTER TABLE " . $this -> tbl_tracks . " ADD `source` VARCHAR( 255 ) NOT NULL AFTER `created`";
				$upgrade_sql[8] = "ALTER TABLE " . $this -> tbl_tracks . " ADD `distance` INT( 11 ) NOT NULL AFTER `comment`";

				$installed_version = (int) $this -> options['db_version'];
				if ( $installed_version != $this -> db_version ) {
					for ($i = $installed_version + 1; $i <= $this -> db_version; $i++ ) {
						if ( array_key_exists( $i, $upgrade_sql ) ) {
							$wpdb -> query( $upgrade_sql[ $i ] );
						}
					}
				}
				$this -> update_option( 'db_version', $this -> db_version );
			}

			/**
			 * Add the Trackserver options array to the database and read it back.
			 *
			 * @since 1.0
			 */
			function add_options() {
				add_option( 'trackserver_options', $this -> option_defaults );
				$this -> options = get_option( 'trackserver_options' );
			}

			/**
			 * Add capabilities for using Trackserver to WordPress roles.
			 *
			 * @since 1.3
			 */
			function set_capabilities() {
				$roles = array( 'administrator', 'editor', 'author' );
				foreach ( $roles as $rolename ) {
					$role = get_role( $rolename );
					$role -> add_cap( 'use_trackserver' );
				}
			}

			/**
			 * Output HTML for the Trackserver options page.
			 *
			 * @since 1.0
			 */
			function options_page_html() {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}
				?>
				<div class="wrap">
					<h2>Trackserver Options</h2>

				<?php
					if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
				?>
					<div class="updated">
						<p>Settings updated</p>
					</div>
				<?php
					}
				?>
					<hr />
					<form id="trackserver-options" name="trackserver-options" action="options.php" method="post">
				<?php

				settings_fields( 'trackserver-options' );
				do_settings_sections( 'trackserver' );
				submit_button( 'Update options', 'primary', 'submit' );

				?>
					</form>
					<hr />
				</div>
				<?php
			}

			/**
			 * Filter callback to add a link to the plugin's settings.
			 */
			function add_settings_link( $links ) {
				$settings_link = '<a href="admin.php?page=trackserver-options">Settings</a>';
				array_unshift( $links, $settings_link );
				return $links;
			}

			function trackme_settings_html() {
				$trackme_settings_img = TRACKSERVER_PLUGIN_URL . 'img/trackme-settings.png';

				echo <<<EOF
					<a class="thickbox" href="#TB_inline?width=&inlineId=ts-trackmehowto-modal"
						data-action="howto" title="TrackMe settings">How to use TrackMe</a> &nbsp; &nbsp;
					<a href="https://play.google.com/store/apps/details?id=LEM.TrackMe" target="tsexternal">Download TrackMe</a>
					<br />
					<div id="ts-trackmehowto-modal" style="display:none;">
						<p>
								<img src="$trackme_settings_img" alt="TrackMe settings" />
						</p>
					</div>
EOF;
			}

			function mapmytracks_settings_html() {
				$mapmytracks_settings_img = TRACKSERVER_PLUGIN_URL . 'img/oruxmaps-mapmytracks.png';

				echo <<<EOF
					<a class="thickbox" href="#TB_inline?width=&inlineId=ts-oruxmapshowto-modal"
						data-action="howto" title="OruxMaps MapMyTracks settings">How to use OruxMaps MapMyTracks</a> &nbsp; &nbsp;
					<a href="https://play.google.com/store/apps/details?id=com.orux.oruxmaps" target="tsexternal">Download OruxMaps</a>
					<br />
					<div id="ts-oruxmapshowto-modal" style="display:none;">
						<p>
								<img src="$mapmytracks_settings_img" alt="OruxMaps MapMyTracks settings" />
						</p>
					</div>
EOF;
			}

			function osmand_settings_html() {
			}

			function httppost_settings_html() {
				$autoshare_settings_img = TRACKSERVER_PLUGIN_URL . 'img/autoshare-settings.png';

				echo <<<EOF
					<a class="thickbox" href="#TB_inline?width=&inlineId=ts-autosharehowto-modal"
						data-action="howto" title="AutoShare settings">How to use AutoShare</a> &nbsp; &nbsp;
					<a href="https://play.google.com/store/apps/details?id=com.dngames.autoshare" target="tsexternal">Download AutoShare</a>
					<br />
					<div id="ts-autosharehowto-modal" style="display:none;">
						<p>
								<img src="$autoshare_settings_img" alt="AutoShare settings" />
						</p>
					</div>
EOF;
			}

			function shortcode_settings_html() {
			}

			function advanced_settings_html() {
			}

			function tile_url_html() {
				$val = $this -> options['tile_url'];
				echo <<<EOF
					<input type="text" size="50" name="trackserver_options[tile_url]" id="trackserver_tile_url" value="$val" autocomplete="off" /><br /><br />
EOF;
			}

			function gettrack_slug_html() {
				$val = $this -> options['gettrack_slug'];
				$url = site_url( null ) . $this -> url_prefix;
				echo <<<EOF
					The URL slug for the 'gettrack' API, used by Trackserver's shortcode [tsmap] ($url/<b>&lt;slug&gt;</b>/) <br />
					There is generally no need to change this.<br />
					<input type="text" size="25" name="trackserver_options[gettrack_slug]" id="trackserver_gettrack_slug" value="$val" autocomplete="off" /><br /><br />
EOF;
			}

			function trackme_slug_html() {
				$val = $this -> options['trackme_slug'];
				$url = site_url( null ) . $this -> url_prefix;
				echo <<<EOF
					The URL slug for TrackMe, used in 'URL Header' setting in TrackMe ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[trackme_slug]" id="trackserver_trackme_slug" value="$val" autocomplete="off" /><br /><br />
					<strong>Full URL header:</strong> $url/$val<br /><br />
					Note about HTTPS: TrackMe as of v1.11.1 does not support <a href="http://en.wikipedia.org/wiki/Server_Name_Indication">SNI</a> for HTTPS connections.
					If your Wordpress install is hosted on a HTTPS URL that depends on SNI, please use HTTP. This is a problem with TrackMe that Trackserver cannot fix.
EOF;
			}

			function trackme_extension_html() {
				$tag = $this -> options['trackme_slug'];
				$val = $this -> options['trackme_extension'];
				echo <<<EOF
					The Server extension for TrackMe<br />
					<input type="text" size="25" name="trackserver_options[trackme_extension]" id="trackserver_trackme_extension" value="$val" autocomplete="off" /><br />
					<br />
					<b>WARNING</b>: the default value in TrackMe is 'php', but this will most likely NOT work, so better change it to something else. Anything will do,
					as long as the request is handled by Wordpress' index.php, so it's better to not use any known file type extension, like 'html' or 'jpg'. A single
					character like 'z' (the default) should work just fine. Change the 'Server extension' setting in TrackMe to match the value you put here.<br /><br />
EOF;
			}

			function mapmytracks_tag_html() {
				$val = $this -> options['mapmytracks_tag'];
				$url = site_url( null ) . $this -> url_prefix;
				echo <<<EOF
					The URL slug for MapMyTracks, used in 'Custom Url' setting in OruxMaps ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[mapmytracks_tag]" id="trackserver_mapmytracks_tag" value="$val" autocomplete="off" /><br /><br />
					<strong>Full custom URL:</strong> $url/$val<br /><br />
					Note about HTTPS: OruxMaps as of v6.0.5 does not support <a href="http://en.wikipedia.org/wiki/Server_Name_Indication">SNI</a> for HTTPS connections.
					If your Wordpress install is hosted on a HTTPS URL that depends on SNI, please use HTTP. This is a problem with OruxMaps that Trackserver cannot fix.
EOF;
			}

			function osmand_slug_html() {
				$val = $this -> options['osmand_slug'];
				$url = site_url( null ) . $this -> url_prefix;
				$suffix = htmlspecialchars( '/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}&username=<username>&key=<access key>' );
				echo <<<EOF
					The URL slug for OsmAnd, used in 'Online tracking' settings in OsmAnd ($url/<b>&lt;slug&gt;</b>/?...) <br />
					<input type="text" size="25" name="trackserver_options[osmand_slug]" id="trackserver_osmand_slug" value="$val" autocomplete="off" /><br /><br />
					<strong>Full URL:</strong> $url/$val$suffix<br />
EOF;
			}

			function osmand_key_html() {
				$val = $this -> options['osmand_key'];
				echo <<<EOF
					An access key for online tracking. We do not use WordPress password
					here for security reasons. The key should be added, together with
					your WordPress username, as a URL parameter to the online tracking
					URL set in OsmAnd, as displayed above. Change this regularly.<br />
					<input type="text" size="25" name="trackserver_options[osmand_key]" id="trackserver_osmand_key" value="$val" autocomplete="off" /><br /><br />
EOF;
			}

			function osmand_trackname_format_html() {
				$val = $this -> options['osmand_trackname_format'];
				echo <<<EOF
					Generated track name in <a href="http://php.net/manual/en/function.strftime.php" target="_blank">strftime()</a>
					format.  OsmAnd online tracking does not support the concept of
					'tracks', there are only locations.  Trackserver needs to group these
					in tracks and automatically generates new tracks based on the
					location's timestamp. The format to use (and thus, how often to start
					a new track) can be specified here.  If you specify a constant
					string, without any strftime() format placeholders, one and the same
					track will be used forever and all locations.
					<br /><br />
					<input type="text" size="25" name="trackserver_options[osmand_trackname_format]" id="trackserver_osmand_trackname_format" value="$val" autocomplete="off" /><br />
					%Y = year, %m = month, %d = day, %H = hour, %F = %Y-%m-%d
					<br />
EOF;
			}

			function upload_tag_html() {
				$val = $this -> options['upload_tag'];
				$url = site_url( null, 'http' ) . $this -> url_prefix;
				echo <<<EOF
					The URL slug for upload via HTTP POST ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[upload_tag]" id="trackserver_upload_tag" value="$val" autocomplete="off" /><br />
EOF;
			}

			function normalize_tripnames_html() {
				$val = ( isset( $this -> options['normalize_tripnames'] ) ? $this -> options['normalize_tripnames'] : '' );
				$ch = '';
				if ( $val == 'yes' ) {
					$ch = 'checked';
				}
				echo <<<EOF
					<input type="checkbox" name="trackserver_options[normalize_tripnames]" id="trackserver_normalize_tripnames" value="yes" autocomplete="off" $ch />
					Check this to normalize trip names according to the format below. The original name will be stored in the comment field.
EOF;
			}

			function tripnames_format_html() {
				$val = $this -> options['tripnames_format'];
				echo <<<EOF
					Normalized trip name format, in <a href="http://php.net/strftime" target="_blank">strftime()</a> format, applied to the first location's timestamp.<br />
					<input type="text" size="25" name="trackserver_options[tripnames_format]" id="trackserver_tripnames_format" value="$val" autocomplete="off" /><br />
EOF;
			}

			function admin_init() {
				$this -> register_settings();
			}

			function register_settings () {
				// All options in one array
				register_setting( 'trackserver-options', 'trackserver_options' );

				// Add sections
				add_settings_section( 'trackserver-trackme', 'TrackMe settings', array( &$this, 'trackme_settings_html' ), 'trackserver' );
				add_settings_section( 'trackserver-mapmytracks', 'OruxMaps / MapMyTracks settings', array( &$this, 'mapmytracks_settings_html' ), 'trackserver' );
				add_settings_section( 'trackserver-osmand', 'OsmAnd online tracking settings', array( &$this, 'osmand_settings_html' ),  'trackserver' );
				add_settings_section( 'trackserver-httppost', 'HTTP upload settings', array( &$this, 'httppost_settings_html' ),  'trackserver' );
				add_settings_section( 'trackserver-shortcode', 'Shortcode / map settings', array( &$this, 'shortcode_settings_html' ),  'trackserver' );
				add_settings_section( 'trackserver-advanced', 'Advanced settings', array( &$this, 'advanced_settings_html' ),  'trackserver' );

				// Settings for section 'trackserver-trackme'
				add_settings_field( 'trackserver_trackme_slug','TrackMe URL slug',
						array( &$this, 'trackme_slug_html' ), 'trackserver', 'trackserver-trackme' );
				add_settings_field( 'trackserver_trackme_extension','TrackMe server extension',
						array( &$this, 'trackme_extension_html' ), 'trackserver', 'trackserver-trackme' );

				// Settings for section 'trackserver-mapmytracks'
				add_settings_field( 'trackserver_mapmytracks_tag', 'MapMyTracks URL slug',
						array( &$this, 'mapmytracks_tag_html' ), 'trackserver', 'trackserver-mapmytracks' );

				// Settings for section 'trackserver-osmand'
				add_settings_field( 'trackserver_osmand_slug', 'OsmAnd URL slug',
						array( &$this, 'osmand_slug_html' ), 'trackserver', 'trackserver-osmand' );
				add_settings_field( 'trackserver_osmand_key', 'OsmAnd access key',
						array( &$this, 'osmand_key_html' ), 'trackserver', 'trackserver-osmand' );
				add_settings_field( 'trackserver_osmand_trackname_format', 'OsmAnd trackname format',
						array( &$this, 'osmand_trackname_format_html' ), 'trackserver', 'trackserver-osmand' );

				// Settings for section 'trackserver-httppost'
				add_settings_field( 'trackserver_upload_tag', 'HTTP POST URL slug',
						array( &$this, 'upload_tag_html' ), 'trackserver', 'trackserver-httppost' );

				// Settings for section 'trackserver-shortcode'
				add_settings_field( 'trackserver_tile_url', 'OSM/Google tile server URL',
						array( &$this, 'tile_url_html' ), 'trackserver', 'trackserver-shortcode' );

				// Settings for section 'trackserver-advanced'
				add_settings_field( 'trackserver_gettrack_slug','Gettrack URL slug',
						array( &$this, 'gettrack_slug_html' ), 'trackserver', 'trackserver-advanced' );
			}

			function admin_menu() {
				$page = add_options_page( 'Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-admin-menu', array( &$this, 'options_page_html' ) );
				$page = str_replace( 'admin_page_', '', $page );
				$this -> options_page = str_replace( 'settings_page_', '', $page );
				$this -> options_page_url = menu_page_url( $this -> options_page, false );

				// A dedicated menu in the main tree
				add_menu_page( 'Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-options', array( &$this, 'options_page_html' ),
					TRACKSERVER_PLUGIN_URL . 'img/trackserver.png' );

				add_submenu_page( 'trackserver-options', 'Trackserver options', 'Options', 'manage_options', 'trackserver-options',
					array( &$this, 'options_page_html' ) );
				$page2 = add_submenu_page( 'trackserver-options', 'Manage tracks', 'Manage tracks', 'use_trackserver', 'trackserver-tracks',
					array( &$this, 'manage_tracks_html' ) );
				/*
				add_submenu_page( 'trackserver-options', 'Trackserver profiles', 'Map profiles', 'manage_options', 'trackserver-profiles',
					array( &$this, 'profiles_html' ) );
				*/

				// Early action to set up the 'Manage tracks' page and handle bulk actions.
				add_action( 'load-' . $page2, array( &$this, 'load_manage_tracks' ) );
			}

			function detect_shortcode() {
				global $wp_query;
				$posts = $wp_query -> posts;
				$pattern = get_shortcode_regex();

				foreach ( $posts as $post ){
					if ( preg_match_all( '/' . $pattern . '/s', $post -> post_content, $matches )
						&& array_key_exists( 2, $matches )
						&& in_array( $this -> shortcode, $matches[2] ) )
					{
						return true;
					}
				}
				return false;
			}

			function handle_shortcode( $atts ) {
				global $wpdb;

				$defaults = array(
					'width'   => '640px',
					'height'  => '480px',
					'align'   => '',
					'class'   => '',
					'track'   => false,
					'gpx'     => false,
					'markers' => true,
					'color'   => false,
					'weight'  => false,
					'opacity' => false
				);

				$atts = shortcode_atts( $defaults, $atts, $this -> shortcode );

				static $num_maps = 0;
				$div_id = 'tsmap_' . ++$num_maps;

				$classes = array();
				if ( $atts['class'] ) {
					$classes[] = $atts['class'];
				}
				if ( in_array( $atts['align'], array( 'left', 'center', 'right', 'none' ) ) ) {
					$classes[] = 'align' . $atts['align'];
				}

				$class_str = '';
				if ( count( $classes ) ) {
					$class_str = 'class="' . implode( ' ', $classes ) . '"';
				}

				$track_url = null;
				if ( $atts['track'] ) {

					// Check if the author of the current post is the ower of the track
					$author_id = get_the_author_meta( 'ID' );
					$post_id = get_the_ID();

					if ( $atts['track'] == 'live' ) {
						$is_live = true;
						$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE user_id=%d ORDER BY created DESC LIMIT 0,1', $author_id );
					}
					else {
						$is_live = false;
						$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE id=%d AND user_id=%d;', $atts['track'], $author_id );
					}
					$validated_id = $wpdb -> get_var( $sql );
					if ( $validated_id ) {
						// Use wp_create_nonce() instead of wp_nonce_url() due to escaping issues
						// https://core.trac.wordpress.org/ticket/4221
						$nonce = wp_create_nonce( 'gettrack_' . $validated_id . "_p" . $post_id );
						$track_url = get_home_url( null, $this -> url_prefix . '/' . $this -> options['gettrack_slug'] . "/?id=$validated_id&p=$post_id&_wpnonce=$nonce" );
						$track_type = 'polyline';
					}
				}
				elseif ( $atts['gpx'] ) {
					$track_url = $atts['gpx'];
					$track_type = 'gpx';
				}

				$markers = ( in_array( $atts['markers'], array( 'false', 'f', 'no', 'n' ), true ) ? false : true );

				$mapdata = array(
					'div_id'       => $div_id,
					'track_url'    => $track_url,
					'track_type'   => $track_type,
					'default_lat'  => '51.443168',
					'default_lon'  => '5.447200',
					'default_zoom' => '16',
					'fullscreen'   => true,
					'is_live'      => $is_live,
					'markers'      => $markers,
				);

				$style = array();
				if ( $atts['color'] )   { $style['color']   = (string) $atts['color']; }
				if ( $atts['weight'] )  { $style['weight']  = (int) $atts['weight']; }
				if ( $atts['opacity'] ) { $style['opacity'] = (float) $atts['opacity']; }
				if ( count( $style ) > 0 ) {
					$mapdata['style'] = $style;
				}

				$this -> mapdata[] = $mapdata;
				$out = '<div id="' . $div_id . '" ' . $class_str . ' style="width: ' . $atts['width'] . '; height: ' . $atts['height'] . '; max-width: 100%"></div>';

				return $out;
			}

			/**
			 * Function to enqueue the localized JavaScript that initializes the map(s)
			 */
			function loop_end() {
				wp_localize_script( 'trackserver', 'trackserver_mapdata', $this -> mapdata );
				wp_enqueue_script( 'trackserver' );
			}

			/**
			 * Function to handle the request. This does a simple string comparison on the request URI to see
			 * if we need to handle the request. If so, it does. If not, it passes on the request.
			 */
			function parse_request( $wp ) {

				$url = site_url( null, 'http' ) . $this -> url_prefix;
				$tag = $this -> options['trackme_slug'];
				$ext = $this -> options['trackme_extension'];
				$base_uri = preg_replace( '/^http:\/\/[^\/]+/', '', $url );
				$trackme_uri = $base_uri . "/" . $tag . "/requests." . $ext;
				$trackme_export_uri = $base_uri . "/" . $tag . "/export." . $ext;
				$request_uri = strtok( $_SERVER['REQUEST_URI'], '?' );     // Strip querystring off request URI

				if ( $request_uri == $trackme_uri ) {
					$this -> handle_trackme_request();
					die();
				}

				if ( $request_uri == $trackme_export_uri ) {
					$this -> handle_trackme_export();
					die();
				}

				$tag = $this -> options['mapmytracks_tag'];
				$uri = $base_uri . "/" . $tag;

				if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
					$this -> handle_mapmytracks_request();
					die();
				}

				$slug = $this -> options['osmand_slug'];
				$uri = $base_uri . "/" . $slug;

				if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
					$this -> handle_osmand_request();
					die();
				}

				$tag = $this -> options['upload_tag'];
				$uri = $base_uri . "/" . $tag ;

				if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
					$this -> handle_upload();
					die();
				}

				$tag = $this -> options['gettrack_slug'];
				$uri = $base_uri . "/" . $tag ;

				if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
					$this -> handle_gettrack();
					die();
				}

				return $wp;
			}

			function validate_trackme_login() {

				$username = urldecode( $_GET['u'] );
				$password = urldecode( $_GET['p'] );

				if ( $username == '' || $password == '' ) {
					$this -> trackme_result( 3 );
				}
				else {
					$user = get_user_by( 'login', $username );

					if ( $user ) {
						$hash = $user -> data -> user_pass;
						$user_id = intval( $user -> data -> ID );

						if ( wp_check_password( $password, $hash, $user_id ) && user_can( $user_id, 'use_trackserver' ) ) {
							return $user_id;
						}
						else {
							$this -> trackme_result( 1 );  // Password incorrect or insufficient permissions
						}
					}
					else {
						$this -> trackme_result( 2 );  // User not found
					}
				}
			}

			/**
			 * Function to handle TrackMe GET requests. It validates the user and password and
			 * delegates the requested action to a dedicated function
			 */
			function handle_trackme_request() {

				// If this function returns, we're OK
				$user_id = $this -> validate_trackme_login();

				// Delegate the action to another function
				switch ( $_GET['a'] ) {
					case 'upload':
						$this -> handle_trackme_upload( $user_id );
						break;
					case 'gettriplist':
						$this -> handle_trackme_gettriplist( $user_id );
						break;
					case 'deletetrip':
						$this -> handle_trackme_deletetrip( $user_id );
						break;
				}
			}

			/**
			 * Function to handle TrackMe export requests. Not currently implemented.
			 */
			function handle_trackme_export() {
				http_response_code( 501 );
				echo "Export is not supported by the server.";

			}

			function handle_mapmytracks_request() {
				// If this function returns, we're OK
				$user_id = $this -> validate_http_basicauth();

				switch ( $_POST['request'] ) {
					case 'start_activity':
						$this -> handle_mapmytracks_start_activity( $user_id );
						break;
					case 'update_activity':
						$this -> handle_mapmytracks_update_activity( $user_id );
						break;
					case 'stop_activity':
						$this -> handle_mapmytracks_stop_activity( $user_id );
						break;
					case 'get_activities':
						break;
					case 'get_activity':
						break;
					default:
						http_response_code( 501 );
						echo "Illegal request.";
				}
			}

			/**
			 * Function to handle the 'upload' action from a TrackMe GET request. It tries to get the trip ID
			 * for the specified trip name, and if that is not found, it creates a new trip. When a minimal
			 * set of parameters is present, it inserts the location into the database.
			 *
			 * Sample request:
			 * /wp/trackme/requests.z?a=upload&u=martijn&p=xxx&lat=51.44820629&long=5.47286778&do=2015-01-03%2022:22:15&db=8&tn=Auto_2015.01.03_10.22.06&sp=0.000&alt=55.000
			 */
			function handle_trackme_upload( $user_id ) {
				global $wpdb;

				$trip_name = urldecode( $_GET['tn'] );
				$occurred = urldecode( $_GET["do"] );

				if ( $trip_name != '' ) {

					// Try to find the trip
					$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE user_id=%d AND name=%s', $user_id, $trip_name );
					$trip_id = $wpdb -> get_var( $sql );

					if ( $trip_id == null ) {

						if ( ! $this -> validate_timestamp( $occurred ) ) {
							$occurred = current_time( 'Y-m-d H:i:s' );
						}

						$data = array( 'user_id' => $user_id, 'name' => $trip_name, 'created' => $occurred, 'source' => 'TrackMe' );
						$format = array( '%d', '%s', '%s', '%s' );

						if ( $wpdb -> insert( $this -> tbl_tracks, $data, $format ) ) {
							$trip_id = $wpdb -> insert_id;
						}
						else {
							$this -> trackme_result( 6 ); // Unable to create trip
						}
					}

					if ( intval( $trip_id ) > 0 ) {

						$latitude = $_GET['lat'];
						$longitude = $_GET['long'];
						$altitude = urldecode( $_GET['alt'] );
						$speed = urldecode( $_GET['sp'] );
						$heading = urldecode( $_GET['ang'] );
						//$comment = urldecode( $_GET['comments'] );
						//$batterystatus = urldecode( $_GET['bs'] );
						$now = current_time( 'Y-m-d H:i:s' );

						if ( $latitude != '' && $longitude != '' && $this -> validate_timestamp( $occurred ) ) {
							$data = array(
								'trip_id' => $trip_id,
								'latitude' => $latitude,
								'longitude' => $longitude,
								'created' => $now,
								'occurred' => $occurred,
							);
							$format = array( '%d', '%s', '%s', '%s', '%s' );

							if ( $altitude != '' ) {
								$data['altitude'] = $altitude;
								$format[] = '%s';
							}
							if ( $speed != '' ) {
								$data['speed'] = $speed;
								$format[] = '%s';
							}
							if ( $heading != '' ) {
								$data['heading'] = $heading;
								$format[] = '%s';
							}

							if ($wpdb -> insert( $this -> tbl_locations, $data, $format ) ) {
								$this -> calculate_distance( $trip_id );
								$this -> trackme_result( 0 );
							}
							else {
								$this -> trackme_result( 7, $wpdb -> last_error );
							}
						}
					}
				}
				else {
					$this -> trackme_result( 6 ); // No trip name specified. This should not happen.
				}

			}

			/**
			 * Function to handle the 'gettriplist' action from a TrackMe GET request. It prints a list of all trips
			 * currently in the database, containing name and creation timestamp
			 */
			function handle_trackme_gettriplist( $user_id ) {
				global $wpdb;

				$sql = $wpdb -> prepare( 'SELECT name,created FROM ' . $this -> tbl_tracks . ' WHERE user_id=%d ORDER BY name', $user_id );
				$trips = $wpdb -> get_results( $sql, ARRAY_A );
				$triplist = '';
				foreach ( $trips as $row ) {
					$triplist .= htmlspecialchars( $row['name'] ) . "|" . htmlspecialchars( $row['created'] ) . "\n";
				}
				$triplist = substr( $triplist, 0, -1 );
				$this -> trackme_result( 0, $triplist );
			}

			/**
			 * Function to handle the 'deletetrip' action from a TrackMe GET request. If a trip ID can be found from the
			 * supplied name, all locations and the trip record for the ID are deleted from the database.
			 */
			function handle_trackme_deletetrip( $user_id ) {
				global $wpdb;

				$trip_name = urldecode( $_GET['tn'] );

				if ( $trip_name != '' ) {

					// Try to find the trip
					$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE user_id=%d AND name=%s', $user_id, $trip_name );
					$trip_id = $wpdb -> get_var( $sql );

					if ( $trip_id == null ) {
						$this -> trackme_result( 7 );   // Trip not found
					}
					else {
						$loc_where = array( 'trip_id' => $trip_id );
						$trip_where = array( 'id' => $trip_id );
						$wpdb -> delete( $this -> tbl_locations, $loc_where );
						$wpdb -> delete( $this -> tbl_tracks, $trip_where );
						$this -> trackme_result( 0 );   // Trip deleted
					}
				}
				else {
					$this -> trackme_result( 6 ); // No trip name specified. This should not happen.
				}
			}

			/**
			 * Function to print a result for the TrackMe client. It prints a result code and optionally a message.
			 */
			function trackme_result( $rc, $message = false ) {
				echo "Result:$rc";
				if ( $message ) {
					echo "|$message";
				}
				die();
			}

			function osmand_terminate( $http = '403', $message = 'Access denied' ) {
				http_response_code( $http );
				header( 'Content-Type: text/plain' );
				echo $message . "\n";
				die();
			}

			function validate_osmand_login() {

				$username = urldecode( $_GET['username'] );
				$key = urldecode( $_GET['key'] );

				if ( $key != $this -> options['osmand_key'] ) {
					$this -> osmand_terminate();
				}

				if ( $username == '') {
					$this -> osmand_terminate();
				}

				$user = get_user_by( 'login', $username );
				if ( $user ) {
					$user_id = intval( $user -> data -> ID );

					if ( user_can( $user_id, 'use_trackserver' ) ) {
						return $user_id;
					}
				}
				$this -> osmand_terminate();
			}

			/*
			 * Sample request:
			 * /wp/osmand/?lat=51.448334&lon=5.4725113&timestamp=1425238292902&hdop=11.0&altitude=80.0&speed=0.0
			 */
			function handle_osmand_request() {
				global $wpdb;

				// If this function returns, we're OK
				$user_id = $this -> validate_osmand_login();

				// Timestamp is sent in milliseconds, and in UTC
				$ts = round( (int) urldecode( $_GET["timestamp"] ) / 1000 );
				$ts += $this -> utc_to_local_offset( $ts );
				$occurred = date( 'Y-m-d H:i:s', $ts );

				if ( $ts > 0 ) {

					// Get track name from strftime format string
					$trackname = strftime( $this -> options['osmand_trackname_format'], $ts );

					if ( $trackname != '' ) {

						// Try to find the trip
						$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE user_id=%d AND name=%s', $user_id, $trackname );
						$track_id = $wpdb -> get_var( $sql );

						if ( $track_id == null ) {
							$data = array( 'user_id' => $user_id, 'name' => $trackname, 'created' => $occurred, 'source' => 'OsmAnd' );
							$format = array( '%d', '%s', '%s', '%s' );

							if ( $wpdb -> insert( $this -> tbl_tracks, $data, $format ) ) {
								$track_id = $wpdb -> insert_id;
							}
							else {
								$this -> osmand_terminate( 501, 'Database error' );
							}
						}

						$latitude = $_GET['lat'];
						$longitude = $_GET['lon'];
						$altitude = urldecode( $_GET['altitude'] );
						$speed = urldecode( $_GET['speed'] );
						$heading = urldecode( $_GET['bearing'] );
						$now = current_time( 'Y-m-d H:i:s' );

						if ( $latitude != '' && $longitude != '' ) {
							$data = array(
								'trip_id' => $track_id,
								'latitude' => $latitude,
								'longitude' => $longitude,
								'created' => $now,
								'occurred' => $occurred,
							);
							$format = array( '%d', '%s', '%s', '%s', '%s' );

							if ( $altitude != '' ) {
								$data['altitude'] = $altitude;
								$format[] = '%s';
							}
							if ( $speed != '' ) {
								$data['speed'] = $speed;
								$format[] = '%s';
							}
							if ( $heading != '' ) {
								$data['heading'] = $heading;
								$format[] = '%s';
							}

							if ($wpdb -> insert( $this -> tbl_locations, $data, $format ) ) {
								$this -> calculate_distance( $track_id );
								$this -> osmand_terminate( 200, 'OK, track ID = ' . $track_id );
							}
							else {
								$this -> osmand_terminate( 500, $wpdb -> last_error );
							}
						}
					}
				}
				$this -> osmand_terminate( 400, 'Bad request' );
			}

			/**
			 * Function to validate a timestamp supplied by a client. It checks if the timestamp is in the required
			 * format and if the timestamp is unchanged after parsing.
			 */
			function validate_timestamp( $ts ) {
			    $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $ts );
			    return $d && ( $d -> format( 'Y-m-d H:i:s' ) == $ts );
			}

			/**
			 * Function to validate Wordpress credentials for basic HTTP authentication. If no crededtials are received,
			 * we send a 401 status code. If the username or password are incorrect, we terminate.
			 */
			function validate_http_basicauth() {

				if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
					header( 'WWW-Authenticate: Basic realm="Authentication Required"' );
					header( 'HTTP/1.0 401 Unauthorized' );
					die( "Authentication required\n" );
				}

				$user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );

				if ( $user ) {
					$hash = $user -> data -> user_pass;
					$user_id = intval( $user -> data -> ID );

					if ( wp_check_password( $_SERVER['PHP_AUTH_PW'], $hash, $user_id ) ) {
						if ( user_can( $user_id, 'use_trackserver' ) ) {
							return $user_id;
						}
						else {
							die( "User has insufficient permissions\n" );
						}
					}
				}
				die( "Username or password incorrect\n" );
			}

			/**
			 * Function to handle the 'start_activity' request for the MapMyTracks protocol. If no
			 * title / trip name is received, nothing is done. Received points are validated. Trip
			 * is inserted with the first point's timstamp as start time, or the current time if no
			 * valid points are received. Valid points are inserted and and the new trip ID is
			 * returned in an XML message.
			 */
			function handle_mapmytracks_start_activity( $user_id ) {
				global $wpdb;

				$trip_name = $_POST['title'];
				if ( $trip_name != '' ) {
					$points = $this -> mapmytracks_parse_points( $_POST['points'] );

					if ( $points ) {
						$ts = $points[0]['timestamp'];
						// Stolen from 'current_time' in wp-include/functions.php
						$occurred = date( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
					}
					else {
						$occurred = current_time( 'Y-m-d H:i:s' );
					}

					$source = $this -> mapmytracks_get_source();
					$data = array( 'user_id' => $user_id, 'name' => $trip_name, 'created' => $occurred, 'source' => $source );
					$format = array( '%d', '%s', '%s', '%s' );

					if ( $wpdb -> insert( $this -> tbl_tracks, $data, $format ) ) {
						$trip_id = $wpdb -> insert_id;
						if ( $this -> mapmytracks_insert_points( $points, $trip_id ) ) {
							$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
							$xml -> addChild( 'type', 'activity_started' );
							$xml -> addChild( 'activity_id', (string) $trip_id );
							echo $xml -> asXML();
						}
					}
				}
			}

			/**
			 * Function to handle 'update_activity' request for the MapMyTracks protocol. It checks if the supplied
			 * activity_id is valid and owned by the current user before inserting the received points into the database.
			 */
			function handle_mapmytracks_update_activity( $user_id ) {
				global $wpdb;

				$sql = $wpdb -> prepare( 'SELECT id, user_id FROM ' . $this -> tbl_tracks . ' WHERE id=%d', $_POST['activity_id'] );
				$trip_id = $wpdb -> get_var( $sql );
				if ( $trip_id ) {
					// Get the user ID for the trip from the cached result of previous query
					$trip_owner = $wpdb -> get_var( null, 1 );

					// Check if the current login is actually the owner of the trip
					if ( $trip_owner == $user_id ) {
						$points = $this -> mapmytracks_parse_points( $_POST['points'] );
						if ( $this -> mapmytracks_insert_points( $points, $trip_id ) ) {
							$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
							$xml -> addChild( 'type', 'activity_updated' );
							echo $xml -> asXML();
						}
						// Absence of a valid XML response causes OruxMaps to re-send the data in a subsequent request
					}
				}
			}

			/**
			 * Function to handle 'stop_activity' request for the MapMyTracks protocol. This doesn't
			 * do anything, except return a bogus (but appropriate) XML message.
			 */
			function handle_mapmytracks_stop_activity( $user_id ) {
				$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
				$xml -> addChild( 'type', 'activity_stopped' );
				echo $xml -> asXML();
			}

			function mapmytracks_parse_points( $points ) {

				// Check the points syntax. It should match groups of four items, each containing only
				// numbers, some also dots and dashes
				$pattern = '/^([\d.]+ [\d.]+ [\d.-]+ [\d]+ ?)*$/';
				$n = preg_match( $pattern, $points );

				if ( $n == 1 ) {
					$parsed = array();
					$all = explode( ' ', $points );
					for ( $i = 0; $i < count( $all ); $i += 4 ) {
						if ( $all[ $i ] ) {
							$parsed[] = array(
								'latitude' => $all[ $i ],
								'longitude' => $all[ $i + 1 ],
								'altitude' => $all[ $i + 2 ],
								'timestamp' => $all[ $i + 3 ],
							);
						}
					}
					return $parsed;
				}
				else {
					return false;  // Invalid input
				}
			}

			/**
			 * Calculate the local time offset and correct for DST.
			 *
			 * We use the same method as 'wp_timezone_override_offset', which serves
			 * as a default override for the 'gmt_offset' option, except we use a
			 * given timestamp for the calculation instead of the current time.
			 *
			 * The timezone used is WP's configured timezone if any, or else PHP's
			 * system timezone if set. Ultimately, we fall back to 'Europe/London',
			 * which is just like UTC/GMT, but with DST.
			 *
			 * Ideally, we would lookup the timezone from the actual coordinates of
			 * the track points, but we don't have a the necessary data. We could use
			 * a service like the Google Time Zone API, but those usually require
			 * registration, so we don't do that (yet). So the calculation may be
			 * incorrect if your track is from a location that has different DST
			 * rules than the chosen time zone.
			 *
			 * We calculate the offset once, so even if a DST transition took place
			 * during your track, it remains continuous.
			 *
			 * @since 1.3
			 *
			 * @param int $ts The unix timestamp to calculate an offset for.
			 */
			function utc_to_local_offset( $ts ) {

				if ( ! $localtz = get_option( 'timezone_string' ) ) {
					if ( ! $localtz = ini_get( 'date.timezone' ) ) {
						$localtz = 'Europe/London';
					}
				}
				$timezone_object = timezone_open( $localtz );
				$datetime_object = date_create(); // DateTime object with UTC timezone

				if ( false === $timezone_object || false === $datetime_object ) {
					return 0;
				}
				date_timestamp_set( $datetime_object, $ts );
				return timezone_offset_get( $timezone_object, $datetime_object );
			}

			function mapmytracks_insert_points( $points, $trip_id ) {
				global $wpdb;

				if ( $points ) {
					$now = current_time( 'Y-m-d H:i:s' );
					$offset = $this -> utc_to_local_offset( $points[0]['timestamp'] );
					$sqldata = array();

					foreach ( $points as $p ) {
						$ts = $p['timestamp'] + $offset;
						$occurred = date( 'Y-m-d H:i:s', $ts );
						$sqldata[] = $wpdb -> prepare( '(%d, %s, %s, %s, %s, %s)',
							$trip_id, $p['latitude'], $p['longitude'], $p['altitude'], $now, $occurred );
					}

					// Let's see how many rows we can put in a single MySQL INSERT query.
					// A row is roughly 86 bytes, so lets's use 100 to be on the safe side.
					$sql = "SHOW VARIABLES LIKE 'max_allowed_packet'";  // returns 2 columns
					$max_bytes = intval( $wpdb -> get_var( $sql, 1 ) ); // we need the 2nd

					if ( $max_bytes ) {
						$max_rows = (int) ( $max_bytes / 100 );
					}
					else {
						$max_rows = 10000;   // max_allowed_packet is 1MB by default
					}
					$sqldata = array_chunk( $sqldata, $max_rows );

					// Insert the data into the databae in chunks of $max_rows.
					// If insertion fails, return false immediately
					foreach ($sqldata as $chunk) {
						$sql = 'INSERT INTO ' . $this -> tbl_locations .
						 ' (trip_id, latitude, longitude, altitude, created, occurred) VALUES ';
						$sql .= implode( ',', $chunk );
						if ( $wpdb -> query( $sql ) === false ) {
							return false;
						}
					}

					// Update the track's distance in the database
					$this -> calculate_distance( $trip_id );
				}
				return true;
			}

			function mapmytracks_get_source() {
					$source = '';
					if ( array_key_exists( 'source', $_POST ) ) {
						$source .= $_POST['source'];
					}
					if ( array_key_exists( 'version', $_POST ) ) {
						$source .= ' v' . $_POST['version'];
					}
					if ( $source != '' ) {
						$source .= ' / ';
					}
					$source .= 'MapMyTracks';
					return $source;
			}

			/**
			 * Function to rearrange the $_FILES array. It handles multiple postvars
			 * and it works with both single and multiple files in a single postvar.
			 */
			function rearrange( $files ) {
				$j = 0;
				foreach ( $files as $postvar => $arr ) {
					foreach ( $arr as $key => $list ) {
						if ( is_array( $list ) ) {               // name="userfile[]"
							foreach( $list as $i => $val ) {
									$new[ $j + $i ][ $key ] = $val;
							}
						}
						else {                                   // name="userfile"
							$new[ $j ][ $key ] = $list;
						}
					}
					$j += $i + 1;
				}
				return $new;
			}

			function validate_gpx( $filename ) {
				$schema = plugin_dir_path( __FILE__ ) . '/gpx-1.1.xsd';
				$xml = new DOMDocument();
				$xml -> load( $filename );
				if ( $xml -> schemaValidate( $schema ) ) {
					return $xml;
				}
				return false;
			}

			function handle_uploaded_files( $user_id ) {

				$tmp = $this -> get_temp_dir();
				$schema = plugin_dir_path( __FILE__ ) . '/gpx-1.1.xsd';

				$message = '';
				$files = $this -> rearrange( $_FILES );

				foreach ( $files as $f ) {
					$filename = $tmp . '/' . uniqid();

					// Check the filename extension case-insensitively
					if ( strcasecmp( substr( $f['name'], -4 ), '.gpx' ) == 0 ) {
						if ( $f['error'] == 0 && move_uploaded_file( $f['tmp_name'], $filename ) ) {
							if ( $xml = $this -> validate_gpx( $filename ) ) {
								$result = $this -> process_gpx( $xml, $user_id );
								$message .= "File '" . $f['name'] . "': imported ".
									$result['num_trkpt'] . ' points from ' . $result['num_trk'] .
								 	' track(s) in '. $result['exec_time'] . " seconds.\n";
							}
							else {
								$message .= "ERROR: File '" . $f['name'] . "' could not be validated as GPX 1.1.\n";
							}
						}
						else {
							$message .= "ERROR: Upload '" . $f['name'] . "' failed (rc=" . $f['error'] . ")\n";
						}
					}
					else {
						$message .= "ERROR: Only .gpx files accepted; discarding '" . $f['name'] . "'\n";
				}
					unlink( $filename );
				}
				return $message;
			}

			/**
			 * Function to handle file uploads from a (mobile) client to the 'upload' slug
			 */
			function handle_upload() {
				header( 'Content-Type: text/plain' );
				$user_id = $this -> validate_http_basicauth();
				$msg = $this -> handle_uploaded_files( $user_id );
				echo $msg;
			}

			/**
			 * Function to handle file uploads from the WordPress admin
			 */
			function handle_admin_upload() {
				$user_id = get_current_user_id();
				if ( user_can( $user_id, 'use_trackserver' ) ) {
					return $this -> handle_uploaded_files( $user_id );
				}
				else {
					return "User has insufficient permissions.";
				}
			}

			/**
			 * Function to get a track ID by name. Used to find duplicates.
			 */
			function get_track_id_by_name( $name, $user_id ) {
				global $wpdb;
				$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks .
					' WHERE name=%s AND user_id=%d LIMIT 0,1', $name, $user_id );
				$trip_id = $wpdb -> get_var( $sql );
				return ( $trip_id ? $trip_id : false );
			}

			/**
			 * Function to parse the XML from a GPX file and insert it into the database. It first converts the
			 * provided DOMDocument to SimpleXML for easier processing and uses the same intermediate format
			 * as the MapMyTracks import, so it can use the same function for inserting the locations
			 */
			function process_gpx( $dom, $user_id, $skip_existing = false ) {
				global $wpdb;

				$gpx = simplexml_import_dom( $dom );
				$source = $gpx['creator'];
				$trip_start = false;
				$ntrk = 0;
				$ntrkpt = 0;
				$track_ids = array();

				$exec_t0 = microtime( true );
				foreach ( $gpx -> trk as $trk ) {
					$points = array();
					$trip_name = $trk -> name;

					if ( $skip_existing && ( $trk_id = $this -> get_track_id_by_name( $trip_name, $user_id ) ) ) {
						$track_ids[] = $trk_id;
					}
					else {

						foreach ( $trk -> trkseg -> trkpt as $trkpt ) {
							if ( ! $trip_start ) {
								$trip_start = date( 'Y-m-d H:i:s', $this -> parse_iso_date( (string) $trkpt -> time ) );
							}
							$points[] = array(
								'latitude' => $trkpt['lat'],
								'longitude' => $trkpt['lon'],
								'altitude' => (string) $trkpt -> ele,
								'timestamp' => $this -> parse_iso_date( (string) $trkpt -> time )
							);
							$ntrkpt++;
						}

						$data = array( 'user_id' => $user_id, 'name' => $trip_name, 'created' => $trip_start, 'source' => $source );
						$format = array( '%d', '%s', '%s', '%s' );
						if ( $wpdb -> insert( $this -> tbl_tracks, $data, $format ) ) {
							$trip_id = $wpdb -> insert_id;
							$this -> mapmytracks_insert_points( $points, $trip_id );
							$track_ids[] = $trip_id;
							$ntrk++;
						}
					}
				}
				$exec_time = round( microtime( true ) - $exec_t0, 1);
				return array( 'num_trk' => $ntrk, 'num_trkpt' => $ntrkpt, 'track_ids' => $track_ids, 'exec_time' => $exec_time );
			}

			function get_temp_dir() {
				$tmp = get_temp_dir() . '/trackserver';
				if ( ! file_exists( $tmp ) ) {
					mkdir( $tmp );
				}
				return $tmp;
			}

			function parse_iso_date( $ts ) {
				//$i = new DateInterval('PT' .strval (get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) .'S' );
				$d = new DateTime( $ts );
				//$d = $d -> add( $i );
				return $d -> format( 'U' );
			}

			/**
			 * Function to return the author ID for a given post ID
			 */
			function get_author( $post_id ) {
				$post = get_post( $post_id );
				return $post -> post_author;
			}

			function handle_gettrack() {

				// Include polyline encoder
				require_once TRACKSERVER_PLUGIN_DIR . 'Polyline.php';

				global $wpdb;

				$post_id = intval( $_REQUEST['p'] );
				$track_id = $_REQUEST['id'];

				if ( $track_id != 'live' ) {
					$track_id = intval( $track_id );
					$author_id = $this -> get_author( $post_id );
				}

				// Refuse to serve the track without a valid nonce. Admin screen uses a different nonce.
				if (
					( array_key_exists( 'admin', $_REQUEST ) &&
					! wp_verify_nonce( $_REQUEST['_wpnonce'], 'manage_track_' . $track_id ) ) ||
					( ( ! array_key_exists( 'admin', $_REQUEST ) ) &&
					! wp_verify_nonce( $_REQUEST['_wpnonce'], 'gettrack_' . $track_id . "_p" . $post_id ) )
				) {
					header( 'HTTP/1.1 403 Forbidden' );
					echo "Access denied (t=$track_id).\n";
					die();
				}

				if ( $track_id ) {
					if ( $track_id == 'live' ) {
						$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks .
								' WHERE user_id=%d ORDER BY created DESC LIMIT 0,1', $author_id );
					}
					else {
						$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . ' WHERE id=%d', $track_id );
					}
					$trip_id = $wpdb -> get_var( $sql );

					if ( $trip_id ) {
						$sql = $wpdb -> prepare( 'SELECT latitude, longitude, occurred FROM ' . $this -> tbl_locations .
							' WHERE trip_id=%d ORDER BY occurred', $trip_id );
						$res = $wpdb -> get_results( $sql, ARRAY_A );

						$points = array();
						foreach ( $res as $row ) {
							$p = array( $row['latitude'], $row['longitude'] );  // We need this below
							$points[] = $p;
						}
						$encoded = Polyline::Encode( $points );
						$metadata = array(
							'first_trkpt' => $points[0],
							'last_trkpt' => $p,
							'last_trkpt_time' => $row['occurred']
						);
						$data = array( 'track' => $encoded, 'metadata' => $metadata );

						header( 'Content-Type: application/json' );
						echo json_encode( $data );
					}
				}
				else {
					echo "ENOID\n";
				}
			}

			function setup_tracks_list_table() {

				// Do this only once.
				if ( $this -> tracks_list_table ) {
					return;
				}

				// Load prerequisites
				if ( ! class_exists( 'WP_List_Table' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
				}
				require_once( TRACKSERVER_PLUGIN_DIR . 'tracks-list-table.php' );

				$list_table_options = array(
					'tbl_tracks' => $this -> tbl_tracks,
					'tbl_locations' => $this -> tbl_locations,
				);

				$this -> tracks_list_table = new Tracks_List_Table( $list_table_options );
			}

			function manage_tracks_html() {

				if ( ! current_user_can( 'use_trackserver' ) ) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}

				add_thickbox();
				$this -> setup_tracks_list_table();
				$this -> tracks_list_table -> prepare_items();

				$url = admin_url() . 'admin-post.php';

				?>
					<div id="ts-edit-modal" style="display:none;">
						<p>
							<form method="post" action="<?=$url?>">
								<table>
									<?php wp_nonce_field( 'manage_track' ); ?>
									<input type="hidden" name="action" value="trackserver_save_track" />
									<input type="hidden" id="track_id" name="track_id" value="" />
									<tr>
										<th style="width: 150px;">Name</th>
										<td><input id="input-track-name" name="name" type="text" style="width: 400px" /></td>
									</tr>
									<tr>
										<th>Source</th>
										<td><input id="input-track-source" name="source" type="text" style="width: 400px" /></td>
									</tr>
									<tr>
										<th>Comment</th>
										<td><textarea id="input-track-comment" name="comment" rows="3" style="width: 400px; resize: none;"></textarea></td>
									</tr>
								</table>
								<br />
								<input class="button action" type="submit" value="Save" name="save_track">
								<input class="button action" type="button" value="Cancel" onClick="tb_remove(); return false;">
							</form>
						</p>
					</div>
					<div id="ts-view-modal" style="display:none;">
							<div id="tsadminmapcontainer">
								<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
							</div>
					</div>
					<div id="ts-merge-modal" style="display:none;">
						<p>
							Merge all points of multiple tracks into one track. Please specify the name for the merged track.
							<form method="post" action="<?=$url?>">
								<table>
									<?php wp_nonce_field( 'manage_track' ); ?>
									<tr>
										<th style="width: 150px;">Merged track name</th>
										<td><input id="input-merged-name" name="name" type="text" style="width: 400px" /></td>
									</tr>
								</table>
								<br />
								<span class="aligncenter"><i>Warning: this action cannot be undone!</i></span><br />
								<div class="alignright">
									<input class="button action" type="button" value="Save" id="merge-submit-button">
									<input class="button action" type="button" value="Cancel" onClick="tb_remove(); return false;">
								</div>
							</form>
						</p>
					</div>
					<div id="ts-upload-modal" style="display:none;">
						<div style="padding: 15px 0">
							<form id="ts-upload-form" method="post" action="<?=$url?>" enctype="multipart/form-data">
								<?php wp_nonce_field( 'upload_track' ); ?>
								<input type="hidden" name="action" value="trackserver_upload_track" />
								<input type="file" name="gpxfile[]" multiple="multiple" style="display: none" id="ts-file-input" />
								<input type="button" class="button button-hero" value="Select files" id="ts-select-files-button" />
								<!-- <input type="button" class="button button-hero" value="Upload" id="ts-upload-files-button" disabled="disabled" /> -->
								<button type="button" class="button button-hero" value="Upload" id="ts-upload-files-button" disabled="disabled">Upload</button>
							</form>
							<br />
							<br />
							Selected files:<br />
							<div id="ts-upload-filelist" style="height: 200px; max-height: 200px; overflow-y: auto; border: 1px solid #dddddd; padding-left: 5px;"></div>
							<br />
							<div id="ts-upload-warning"></div>
						</div>
					</div>
					<form id="trackserver-tracks" method="post">
						<input type="hidden" name="page" value="trackserver-tracks" />
						<div class="wrap">
							<h2>Manage tracks</h2>
							<?php $this -> notice_bulk_action_result() ?>
							<?php $this -> tracks_list_table -> display() ?>
						</div>
					</form>
				<?php
			}

			function profiles_html() {

				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}
				echo "<h2>Trackserver map profiles</h2>";
			}

			function admin_post_save_track() {
				global $wpdb;

				check_admin_referer( 'manage_track_' . $_REQUEST['track_id'] );

				// Save track. Use stripslashes() on the data, because WP magically escapes it.
				$name = stripslashes( $_REQUEST['name'] );
				$source = stripslashes( $_REQUEST['source'] );
				$comment = stripslashes( $_REQUEST['comment'] );

				$data = array(
					'name' => $name,
					'source' => $source,
					'comment' => $comment
				);
				$where = array( 'id' => $_REQUEST['track_id'] );
				$wpdb -> update( $this -> tbl_tracks, $data, $where, '%s', '%d' );

				$message = 'Track "' . $name . '" (ID=' . $_REQUEST['track_id'] . ') saved';
				setcookie( 'ts_bulk_result', $message, time() + 300 );

				// Redirect back to the admin page. This should be safe.
				wp_redirect( $_REQUEST['_wp_http_referer'] );
				exit;
			}

			/**
			 * Handler for the admin_post_trackserver_upload_track action
			 */
			function admin_post_upload_track() {
				check_admin_referer( 'upload_track' );
				$message = $this -> handle_admin_upload();
				setcookie( 'ts_bulk_result', $message, time() + 300 );
				// Redirect back to the admin page. This should be safe.
				wp_redirect( $_REQUEST['_wp_http_referer'] );
				exit;
			}

			/**
			 * Handler for the load-$hook for the 'Manage tracks' page
			 * It sets up the list table and processes any bulk actions
			 */
			function load_manage_tracks() {
				$this -> setup_tracks_list_table();
				if ( $action = $this -> tracks_list_table -> get_current_action() ) {
					$this -> process_bulk_action( $action );
				}
				// Set it up bulk action result notice
				$this -> setup_bulk_action_result_msg();
			}

			/**
			 * Function to set up a bulk action result message to be displayed later.
			 */
			function setup_bulk_action_result_msg() {
				if ( isset( $_COOKIE['ts_bulk_result'] ) ) {
					$this -> bulk_action_result_msg = stripslashes( $_COOKIE['ts_bulk_result'] );
					setcookie( 'ts_bulk_result', '', time() - 3600 );
				}
			}

			/**
			 * Filter an array of track IDs for tracks that don't belong to the current user
			 *
			 * @since 1.3
			 *
			 * @global object $wpdb The WordPress database interface
			 *
			 * @param array $track_ids Track IDs to filter.
			 */
			function filter_current_user_tracks( $track_ids ) {
				global $wpdb;

				$user_id = get_current_user_id();
				// Convert to int, remove value '0'.
				$track_ids = array_diff( array_map( 'intval', (array) $track_ids ), array( 0 ) );
				if ( count( $track_ids ) > 0 ) {
					$in = '(' . implode( ',', $track_ids ) . ')';
					$sql = $wpdb -> prepare( 'SELECT id FROM ' . $this -> tbl_tracks . " WHERE user_id=%d AND id IN $in", $user_id );
					return $wpdb -> get_col( $sql );
				}
				return array();
			}

			/**
			 * Function to process any bulk action from the tracks_list_table
			 */
			function process_bulk_action( $action ) {
				global $wpdb;

				// The action name is 'bulk-' + plural form of items in WP_List_Table
				check_admin_referer( 'bulk-tracks' );

				if ( $action === 'delete' ) {
					$track_ids = $this -> filter_current_user_tracks( $_REQUEST['track'] );
					if ( count( $track_ids ) > 0 ) {
						$in = '(' . implode( ',', $track_ids ) . ')';
						$sql = 'DELETE FROM ' . $this -> tbl_locations . " WHERE trip_id IN $in";
						$nl = $wpdb -> query( $sql );
						$sql = 'DELETE FROM ' . $this -> tbl_tracks . " WHERE id IN $in";
						$nt = $wpdb -> query( $sql );
						$message = 'Deleted ' . intval( $nl ) . ' location(s) in ' . intval( $nt ) . ' track(s).';
					}
					else {
						$message = "No tracks deleted";
					}
					setcookie( 'ts_bulk_result', $message, time() + 300 );
					wp_redirect( $_REQUEST['_wp_http_referer'] );
					exit;
				}

				if ( $action === 'merge' ) {
					$track_ids = $this -> filter_current_user_tracks( $_REQUEST['track'] );
					// Need at least 2 tracks
					if ( ( $n = count( $track_ids ) ) > 1 ) {
						$id = min( $track_ids );
						$rest = array_diff( $track_ids, array( $id ) );
						// How useful is it to escape integers?
						array_walk( $rest, array( $wpdb, 'escape_by_ref' ) );
						$in = '(' . implode( ',', $rest ) . ')';
						$sql = $wpdb -> prepare( 'UPDATE ' . $this -> tbl_locations . " SET trip_id=%d WHERE trip_id IN $in", $id );
						$nl = $wpdb -> query( $sql );
						$sql = 'DELETE FROM ' . $this -> tbl_tracks . " WHERE id IN $in";
						$nt = $wpdb -> query( $sql );
						$sql = $wpdb -> prepare( 'UPDATE ' . $this -> tbl_tracks . ' SET name=%s WHERE id=%d',
							 ( $name = stripslashes( $_REQUEST['merged_name'] ) ), $id );
						$wpdb -> query( $sql );
						$message = "Merged " . intval( $nl ) . " location(s) from " . intval( $nt ) . ' track(s) into "' . $name . '"';
					}
					else {
						$message = "Need >= 2 tracks to merge, got only $n";
					}
					setcookie( 'ts_bulk_result', $message, time() + 300 );
					wp_redirect( $_REQUEST['_wp_http_referer'] );
					exit;
				}

				if ( $action === 'recalc' ) {
					$track_ids = $this -> filter_current_user_tracks( $_REQUEST['track'] );
					if ( count( $track_ids ) > 0 ) {
						$exec_t0 = microtime( true );
						foreach ( $track_ids as $id ) {
							$this -> calculate_distance( $id );
						}
						$exec_time = round( microtime( true ) - $exec_t0, 1);
						$message = 'Recalculated track stats for ' . count( $track_ids ) . " tracks in $exec_time seconds";
					}
					else {
						$message = "No tracks found to recalculate";
					}
					setcookie( 'ts_bulk_result', $message, time() + 300 );
					wp_redirect( $_REQUEST['_wp_http_referer'] );
					exit;
				}
			}

			/**
			 * Function to display the bulk_action_result_msg
			 */
			function notice_bulk_action_result() {
				if ( $this -> bulk_action_result_msg ) {
					?>
						<div class="updated">
							<p><?= nl2br( htmlspecialchars( $this -> bulk_action_result_msg ) ) ?></p>
						</div>
					<?php
				}
			}

			/**
			 * Filter callback to allow .gpx file uploads.
			 *
			 * @param array $existing_mimes the existing mime types.
			 * @return array the allowed mime types.
			 */
			function upload_mimes( $existing_mimes = array() ) {

					// Add file extension 'extension' with mime type 'mime/type'
					$existing_mimes['gpx'] = 'application/gpx+xml';

					// and return the new full result
					return $existing_mimes;
			}

			/**
			 * Filter function that inserts tracks from the media library into the
			 * database and returns shortcodes for the added tracks.
			 */
			function media_send_to_editor( $html, $id, $attachment ) {

				$type = get_post_mime_type( $id );

				// Only act on GPX files
				if ( $type == 'application/gpx+xml' ) {
					$user_id = $this -> get_author( $id );
					$filename = get_attached_file( $id );
					if ( $xml = $this -> validate_gpx( $filename ) ) {

						// Call 'process_gpx' with 'skip_existing' == true, to prevent
						// uploaded files being processed more than once
						$result = $this -> process_gpx( $xml, $user_id, true );

						if ( count( $result['track_ids'] ) > 0 ) {
							$html = '';
							foreach ( $result['track_ids'] as $trk) {
								$html .= "[tsmap track=$trk]\n";
							}
						}
						else {
							$html = "Error: no tracks found in GPX.";
						}
					}
					else {
						$html =  'Error: file could not be parsed as valid GPX 1.1.';
					}
				}
				return $html;
			}

			function distance( $lat1, $lon1, $lat2, $lon2 ) {
				$radius = 6371000; // meter
				list( $lat1, $lon1, $lat2, $lon2 ) = array_map( 'deg2rad', array( $lat1, $lon1, $lat2, $lon2 ) );

				$dlat = $lat2 - $lat1;
				$dlon = $lon2 - $lon1;
				$a = pow ( sin( $dlat / 2 ), 2 ) + cos( $lat1 ) * cos( $lat2 ) * pow( sin( $dlon / 2 ), 2 );
				$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
				$d = $radius * $c;
				return (int) $d;
			}

			function calculate_distance( $track_id ) {
				global $wpdb;

				$sql = $wpdb -> prepare( 'SELECT latitude, longitude FROM ' . $this -> tbl_locations .
					' WHERE trip_id=%d ORDER BY occurred', $track_id );
				$res = $wpdb -> get_results( $sql, ARRAY_A );

				$oldlat = false;
				$distance = 0;
				foreach ( $res as $row ) {
					if ($oldlat) {
						$distance += $this -> distance( $oldlat, $oldlon, $row['latitude'], $row['longitude'] );
					}
					$oldlat = $row['latitude'];
					$oldlon = $row['longitude'];
				}

				if ( $distance > 0 ) {
					$wpdb -> update( $this -> tbl_tracks, array( 'distance' => $distance ), array( 'id' => $track_id ), '%d', '%d' );
				}
			}

		} // class
	} // if !class_exists

	// Main
	$trackserver = new Trackserver();

	// For 4.3.0 <= PHP <= 5.4.0
	if ( ! function_exists( 'http_response_code' ) ) {
		function http_response_code( $newcode = NULL ) {
			static $code = 200;
			if ( $newcode !== NULL ) {
				header( 'X-PHP-Response-Code: ' . $newcode, true, $newcode );
				if( ! headers_sent() ) {
					$code = $newcode;
				}
			}
			return $code;
		}
	}
