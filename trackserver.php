<?php

/*
Plugin Name: Trackserver
Plugin Script: trackserver.php
Plugin URI: https://www.grendelman.net/wp/trackserver-wordpress-plugin/
Description: GPS Track Server for TrackMe, OruxMaps and others
Version: 0.9
Author: Martijn Grendelman
Author URI: http://www.grendelman.net/
License: GPL2

=== RELEASE NOTES ===
2015-01-02 - v1.0 - first version
*/

	if (!defined('ABSPATH')) {
		die("No, sorry.");
	}

	if (!class_exists('trackserver')) {

		define('TRACKSERVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
		define('TRACKSERVER_PLUGIN_URL', plugin_dir_url(__FILE__));
		define('TRACKSERVER_JSLIB', TRACKSERVER_PLUGIN_URL . 'lib/');

		class trackserver {

			var $db_version = "7";

			// Option defaults. More in the constructor
			var $option_defaults = array (
				'trackme_slug' => 'trackme',
				'trackme_extension' => 'z',
				'mapmytracks_tag' => 'mapmytracks',
				'upload_tag' => 'tsupload',
				'normalize_tripnames' => 'yes',
				'tripnames_format' => '%F %T',
			);

			/**
			 * Constructor
			 */
			function __construct ()
			{
				global $wpdb;
				$this -> tbl_tracks = $wpdb->prefix . "ts_tracks";
				$this -> tbl_locations = $wpdb->prefix . "ts_locations";
				$this -> options = get_option('trackserver_options');
				$this -> option_defaults ["db_version"] = $this -> db_version;
				// Should be a configuration option
				$this -> options['gettrack_slug'] = 'trackserver/gettrack';
				$this -> shortcode = 'tsmap';
				$this -> use_mapbox = false;
				$this -> mapbox_token = 'pk.eyJ1IjoidGludXp6IiwiYSI6IlVXYUYwcG8ifQ.pe5iF9bAH3zx3ztc6PzHFA';
				$this -> mapdata = array ();

				// Bootstrap
				$this -> add_actions ();
				if (is_admin ()) {
					$this -> add_admin_actions ();
				}
			}

			/**
			 * Add actions and filters.
			 */
			function add_actions ()
			{
				// This hook is called upon activation of the plugin
				register_activation_hook(__FILE__, array (&$this, 'trackserver_install'));

				// Custom request parser; core protocol handler
				add_action ('parse_request', array (&$this, 'parse_request'));

				// Add handler for TrackMe server via WP AJAX interface for both logged-in and not-logged-in users
				add_action ('wp_ajax_trackserver_trackme', array (&$this, 'handle_trackme_request'));
				add_action ('wp_ajax_nopriv_trackserver_trackme', array (&$this, 'handle_trackme_request'));

				// Front-end JavaScript and CSS
				add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));

				// Shortcode
				add_shortcode('tsmap', array(&$this, 'handle_shortcode'));
				add_action('loop_end', array(&$this, 'loop_end'));
			}

			/**
			 * Add actions for the admin pages
			 */
			function add_admin_actions ()
			{
				add_action ('admin_menu', array (&$this, 'admin_menu'));
				add_action ('admin_init', array (&$this, 'admin_init'));
				add_filter('plugin_action_links_' .plugin_basename(__FILE__), array(&$this, 'add_settings_link'));
				add_action ('admin_head', array (&$this, 'admin_head'));  // CSS for table styling
				//add_action ('admin_footer-trackserver_page_trackserver-tracks', array (&$this, 'admin_footer'));  // Javascript for Thickbox manipulation
				//add_action ('admin_footer-toplevel_page_trackserver-options', array (&$this, 'admin_footer'));  // Javascript for Thickbox manipulation
				add_action ('admin_post_trackserver_save_track', array (&$this, 'admin_post_save_track'));

				// Backend JavaScript and CSS
				add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
			}

			function admin_head ()
			{
				echo <<<EOF
					<style type="text/css">
						.wp-list-table .column-id { width: 50px; }
						.wp-list-table .column-tstart { width: 150px; }
						.wp-list-table .column-tend { width: 150px; }
						.wp-list-table .column-numpoints { width: 50px; }
						.wp-list-table .column-edit { width: 50px; }
						.wp-list-table .column-view { width: 50px; }
						#addtrack { margin: 1px 8px 0 0; }
					</style>\n
EOF;
			}

			/**
			 * Function to update options. Set the value in the options array and
			 * write the array to the database.
			 */
			function update_option ($option, $value)
			{
				$this -> options [$option] = $value;
				update_option ('trackserver_options', $this -> options);
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load scripts.
			 */
			function load_common_scripts ()
			{
				wp_enqueue_style('leaflet', TRACKSERVER_JSLIB . 'leaflet-0.7.3/leaflet.css');
				wp_enqueue_script('leaflet', TRACKSERVER_JSLIB . 'leaflet-0.7.3/leaflet.js', array(), false, true);
				wp_enqueue_style('leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-0.0.4/Leaflet.fullscreen.css');
				wp_enqueue_script('leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-0.0.4/Leaflet.fullscreen.min.js', array(), false, true);
				wp_enqueue_script('leaflet-omnivore', TRACKSERVER_PLUGIN_URL . 'trackserver-omnivore.js', array(), false, true);

				// To be localized in the shortcode and enqueued in loop_end
				// Also localized and enqueued in admin_enqueue_scripts
				wp_register_script ('trackserver', TRACKSERVER_PLUGIN_URL .'trackserver.js');
			}

			/**
			 * Handler for 'wp_enqueue_scripts'.
			 * Load javascript and stylesheets on the front-end.
			 */
			function wp_enqueue_scripts ()
			{
				if ($this -> detect_shortcode ()) {
					$this -> load_common_scripts ();

					// Live-update only on the front-end, not in admin
					wp_enqueue_style('leaflet-messagebox', TRACKSERVER_JSLIB .'leaflet-messagebox-1.0/leaflet-messagebox.css');
					wp_enqueue_script('leaflet-messagebox', TRACKSERVER_JSLIB .'leaflet-messagebox-1.0/leaflet-messagebox.js', array(), false, true);
					wp_enqueue_style('leaflet-liveupdate', TRACKSERVER_JSLIB .'leaflet-liveupdate-1.0/leaflet-liveupdate.css');
					wp_enqueue_script('leaflet-liveupdate', TRACKSERVER_JSLIB .'leaflet-liveupdate-1.0/leaflet-liveupdate.js', array(), false, true);
				}
			}

			function admin_enqueue_scripts ($hook)
			{

				switch ($hook) {
					case 'trackserver_page_trackserver-tracks':

						$this -> load_common_scripts ();

						// The is_ssl() check should not be necessary, but somehow, get_home_url() doesn't correctly return a https URL by itself
						$track_base_url = get_home_url (null, '/' . $this -> options ['gettrack_slug'] . "/?", (is_ssl()  ? 'https' : 'http'));
						wp_localize_script('trackserver', 'track_base_url', $track_base_url);
						wp_enqueue_script ('trackserver');

						// no break!

					case 'toplevel_page_trackserver-options':

						// Enqueue the admin js (Thickbox overrides) in the footer
						wp_enqueue_script ('trackserver-admin', TRACKSERVER_PLUGIN_URL . 'trackserver-admin.js', array ('thickbox'), null, true);

						break;
				}
			}

			/**
			 * Handler for 'admin_print_scripts'.
			 * Load admin scripts.
			 */
			function admin_print_scripts ()
			{
				$this -> load_admin_scripts ();
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load admin scripts.
			 */
			function load_admin_scripts ()
			{
			}

			/**
			 * Handler for 'wp_print_scripts'.
			 * Load option page scripts.
			 */
			function load_optionpage_scripts ()
			{
				wp_enqueue_script ('nextgen-admin', plugins_url('nextgen-options.js', __FILE__));
			}

			/**
			 * Installer function. This runs when the plugin in activated and installs
			 * the database table and sets default option values
			 */
			function trackserver_install ()
			{
				global $wpdb;

				$sql = "CREATE TABLE IF NOT EXISTS ". $this -> tbl_locations. " (
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

				$wpdb->query ($sql);

				$sql = "CREATE TABLE IF NOT EXISTS ". $this -> tbl_tracks. " (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`name` varchar(255) NOT NULL,
					`update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
					`source` varchar(255) NOT NULL,
					`comment` varchar(255) NOT NULL,
					PRIMARY KEY (`id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

				$wpdb->query ($sql);

				// Update database schema
				$this -> check_update_db ();

				// Add options to the database
				$this -> add_options ();
			}

			/**
			 * Check if the database schema is the correct version and upgrade if necessary.
			 */
			function check_update_db ()
			{
				global $wpdb;

				// Upgrade table if necessary. Add upgrade SQL statements here, and
				// update $db_version at the top of the file
				$upgrade_sql = array ();
				$upgrade_sql[5] = "ALTER TABLE ". $this -> tbl_tracks ." CHANGE `created` `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
				$upgrade_sql[6] = "ALTER TABLE ". $this -> tbl_tracks ." ADD `created` TIMESTAMP NOT NULL AFTER `updated`";
				$upgrade_sql[7] = "ALTER TABLE ". $this -> tbl_tracks ." ADD `source` VARCHAR( 255 ) NOT NULL AFTER `created`";

				$installed_version = $this -> options ['db_version'];
				if ($installed_version != $this -> db_version) {
					for ($i = $installed_version + 1; $i <= $this -> db_version; $i++) {
						if (array_key_exists ($i, $upgrade_sql)) {
							$wpdb->query ($upgrade_sql [$i]);
						}
					}
				}
				$this -> update_option ('db_version', $this -> db_version);
			}


			function add_options()
			{
				add_option('trackserver_options', $this -> option_defaults);
				$this -> options = get_option('trackserver_options');
			}

			function options_page_html ()
			{
				if (!current_user_can('manage_options')) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}

				echo <<<EOF
		<div class="wrap">
			<h2>Trackserver Options</h2>
			<hr />
			<form id="trackserver-options" name="trackserver-options" action="options.php" method="post">
EOF;

				settings_fields( 'trackserver-options' );
				do_settings_sections('trackserver');

				echo <<<EOF
				<p class="submit">
					<input type="submit" name="submit" value="Update Options" />
				</p>
			</form>
			<hr />
		</div>
EOF;

			}

			/**
			 * Filter callback to add a link to the plugin's settings.
			 */
			public function add_settings_link ($links)
			{
				$settings_link = '<a href="admin.php?page=trackserver-options">Settings</a>';
				array_unshift ($links, $settings_link);
				return $links;
			}

			function general_settings_html ()
			{
				add_settings_field ('trackserver_gettrack_slug','Gettrack URL slug',
						array (&$this, 'gettrack_slug_html'), 'trackserver', 'trackserver-general');
				/*
				add_settings_field ('trackserver_normalize_tripnames','Normalize trip names',
						array (&$this, 'normalize_tripnames_html'), 'trackserver', 'trackserver-trackme');
				add_settings_field ('trackserver_tripnames_format','Trip name format',
						array (&$this, 'tripnames_format_html'), 'trackserver', 'trackserver-trackme');
				*/
			}

			function trackme_settings_html ()
			{
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

				add_settings_field ('trackserver_trackme_slug','TrackMe URL slug',
						array (&$this, 'trackme_slug_html'), 'trackserver', 'trackserver-trackme');
				add_settings_field ('trackserver_trackme_extension','TrackMe server extension',
						array (&$this, 'trackme_extension_html'), 'trackserver', 'trackserver-trackme');
			}

			function mapmytracks_settings_html ()
			{
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
				add_settings_field ('trackserver_mapmytracks_tag','MapMyTracks URL slug',
						array (&$this, 'mapmytracks_tag_html'), 'trackserver', 'trackserver-mapmytracks');
			}

			function httppost_settings_html ()
			{
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
				add_settings_field ('trackserver_upload_tag','HTTP POST URL slug',
						array (&$this, 'upload_tag_html'), 'trackserver', 'trackserver-httppost');
			}

			function gettrack_slug_html ()
			{
				$val = $this -> options ['gettrack_slug'];
				$url = site_url(null);
				echo <<<EOF
					The URL slug for the 'gettrack' API, used by Trackserver's shortcode [tsmap] ($url/<b>&lt;slug&gt;</b>/) <br />
					There is generally no need to change this.<br />
					<input type="text" size="25" name="trackserver_options[gettrack_slug]" id="trackserver_gettrack_slug" value="$val" autocomplete="off" /><br /><br />
EOF;

			}
			function trackme_slug_html ()
			{
				$val = $this -> options ['trackme_slug'];
				$url = site_url(null);
				echo <<<EOF
					The URL slug for TrackMe, used in 'URL Header' setting in TrackMe ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[trackme_slug]" id="trackserver_trackme_slug" value="$val" autocomplete="off" /><br /><br />
					<strong>Full URL header:</strong> $url/$val<br /><br />
					Note about HTTPS: TrackMe as of v1.11.1 does not support <a href="http://en.wikipedia.org/wiki/Server_Name_Indication">SNI</a> for HTTPS connections.
					If your Wordpress install is hosted on a HTTPS URL that depends on SNI, please use HTTP. This is a problem with TrackMe that Trackserver cannot fix.
EOF;

			}

			function trackme_extension_html ()
			{
				$tag = $this -> options ['trackme_slug'];
				$val = $this -> options ['trackme_extension'];
				$url = site_url(null, 'http');
				echo <<<EOF
					The Server extension for TrackMe<br />
					<input type="text" size="25" name="trackserver_options[trackme_extension]" id="trackserver_trackme_extension" value="$val" autocomplete="off" /><br />
					<br />
					<b>WARNING</b>: the default value in TrackMe is 'php', but this will most likely NOT work, so better change it to something else. Anything will do,
					as long as the request is handled by Wordpress' index.php, so it's better to not use any known file type extension, like 'html' or 'jpg'. A single
					character like 'z' (the default) should work just fine. Change the 'Server extension' setting in TrackMe to match the value you put here.<br /><br />
EOF;

			}

			function mapmytracks_tag_html ()
			{
				$val = $this -> options ['mapmytracks_tag'];
				$url = site_url(null);
				echo <<<EOF
					The URL slug for MapMyTracks, used in 'Custom Url' setting in OruxMaps ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[mapmytracks_tag]" id="trackserver_mapmytracks_tag" value="$val" autocomplete="off" /><br /><br />
					<strong>Full custom URL:</strong> $url/$val<br /><br />
					Note about HTTPS: OruxMaps as of v6.0.5 does not support <a href="http://en.wikipedia.org/wiki/Server_Name_Indication">SNI</a> for HTTPS connections.
					If your Wordpress install is hosted on a HTTPS URL that depends on SNI, please use HTTP. This is a problem with OruxMaps that Trackserver cannot fix.
EOF;
			}

			function upload_tag_html ()
			{
				$val = $this -> options ['upload_tag'];
				$url = site_url(null, 'http');
				echo <<<EOF
					The URL slug for upload via HTTP POST ($url/<b>&lt;slug&gt;</b>/) <br />
					<input type="text" size="25" name="trackserver_options[upload_tag]" id="trackserver_upload_tag" value="$val" autocomplete="off" /><br />
EOF;
			}

			function normalize_tripnames_html ()
			{
				$val = (isset ($this -> options ['normalize_tripnames']) ? $this -> options ['normalize_tripnames'] : "");
				$ch = "";
				if ($val == 'yes') $ch = "checked";
				echo <<<EOF
					<input type="checkbox" name="trackserver_options[normalize_tripnames]" id="trackserver_normalize_tripnames" value="yes" autocomplete="off" $ch />
					Check this to normalize trip names according to the format below. The original name will be stored in the comment field.
EOF;
			}

			function tripnames_format_html ()
			{
				$val = $this -> options ['tripnames_format'];
				echo <<<EOF
					Normalized trip name format, in <a href="http://php.net/strftime" target="_blank">strftime()</a> format, applied to the first location's timestamp.<br />
					<input type="text" size="25" name="trackserver_options[tripnames_format]" id="trackserver_tripnames_format" value="$val" autocomplete="off" /><br />
EOF;
			}

			function admin_init ()
			{
				$this -> register_settings ();
			}

			function register_settings ()
			{
				// All options in one array
				register_setting ('trackserver-options', 'trackserver_options');
				// Add settings and settings sections
				add_settings_section('trackserver-general', 'General settings', array (&$this, 'general_settings_html'),  'trackserver');
				add_settings_section('trackserver-trackme', 'TrackMe settings', array (&$this, 'trackme_settings_html'),  'trackserver');
				add_settings_section('trackserver-mapmytracks', 'OruxMaps / MapMyTracks settings', array (&$this, 'mapmytracks_settings_html'),  'trackserver');
				add_settings_section('trackserver-httppost', 'HTTP upload settings', array (&$this, 'httppost_settings_html'),  'trackserver');
			}

			function admin_menu ()
			{
				$page = add_options_page('Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-admin-menu', array (&$this, 'options_page_html'));
				$page = str_replace('admin_page_', '', $page);
				$this -> options_page = str_replace('settings_page_', '', $page);
				$this -> options_page_url = menu_page_url ($this -> options_page, false);

				// A dedicated menu in the main tree
				add_menu_page ('Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-options', array (&$this, 'options_page_html'),
					TRACKSERVER_PLUGIN_URL . 'img/trackserver.png');
				add_submenu_page ('trackserver-options', 'Trackserver options', 'Options', 'manage_options', 'trackserver-options',
					array (&$this, 'options_page_html'));
				/*
				add_submenu_page ('trackserver-options', 'Trackserver profiles', 'Map profiles', 'manage_options', 'trackserver-profiles',
					array (&$this, 'profiles_html'));
				*/
				add_submenu_page ('trackserver-options', 'Manage tracks', 'Manage tracks', 'manage_options', 'trackserver-tracks',
					array (&$this, 'manage_tracks_html'));
			}

			function detect_shortcode ()
			{

				global $wp_query;
				$posts = $wp_query->posts;
				$pattern = get_shortcode_regex ();

				foreach ($posts as $post){
					if (preg_match_all ('/'. $pattern .'/s', $post -> post_content, $matches)
						&& array_key_exists (2, $matches)
						&& in_array ($this -> shortcode, $matches[2]))
					{
						return true;
					}
				}
				return false;
			}

			public function handle_shortcode ($atts)
			{
				global $wpdb;

				$defaults = array (
					'width' => '640px',
					'height' => '480px',
					'track' => false,
					'align' => '',
					'class' => ''
				);

				$atts = shortcode_atts($defaults, $atts, $this -> shortcode);

				static $num_maps = 0;
				$div_id = 'tsmap_' . ++$num_maps;

				$classes = array();
				if ($atts ['class']) $classes[] = $atts ['class'];
				if (in_array ($atts ['align'], array ('left', 'center', 'right', 'none'))) {
					$classes[] = 'align' . $atts ['align'];
				}

				$class_str = '';
				if (count ($classes)) {
					$class_str = 'class="' . implode (' ', $classes) .'"';
				}

				$track_url = null;
				if ($atts['track']) {

					// Check if the author of the current post is the ower of the track
					$author_id = get_the_author_meta ('ID');
					$post_id = get_the_ID ();

					if ($atts ['track'] == 'live') {
						$is_live = true;
						$sql = $wpdb -> prepare ('SELECT id FROM '. $this -> tbl_tracks .' WHERE user_id=%d ORDER BY created DESC LIMIT 0,1', $author_id);
					}
					else {
						$is_live = false;
						$sql = $wpdb -> prepare ('SELECT id FROM '. $this -> tbl_tracks .' WHERE id=%d AND user_id=%d;', $atts['track'], $author_id);
					}
					$validated_id = $wpdb -> get_var ($sql);
					if ($validated_id) {
						// Use wp_create_nonce() instead of wp_nonce_url() due to escaping issues
						// https://core.trac.wordpress.org/ticket/4221
						$nonce = wp_create_nonce('gettrack_'. $validated_id ."_p". $post_id);
						$track_url = get_home_url (null, '/' . $this -> options ['gettrack_slug'] . "/?id=$validated_id&p=$post_id&_wpnonce=$nonce");
					}
				}

				$mapdata = array (
					'div_id'       => $div_id,
					'track_url'    => $track_url,
					'default_lat'  => '51.443168',
					'default_lon'  => '5.447200',
					'default_zoom' => '16',
					'fullscreen'   => true,
					'is_live'      => $is_live,
				);

				$this -> mapdata [] = $mapdata;
				$out = '<div id="' .$div_id .'" '. $class_str .' style="width: '. $atts ['width'] .'; height: '. $atts ['height'] .'; max-width: 100%"></div>';

				return $out;
			}

			/**
			 * Fucntion to enqueue the localized JavaScript that initializes the map(s)
			 */
			function loop_end ()
			{
				wp_localize_script('trackserver', 'trackserver_mapdata', $this -> mapdata);
				wp_enqueue_script ('trackserver');
			}

			/**
			 * Function to handle the request. This does a simple string comparison on the request URI to see
			 * if we need to handle the request. If so, it does. If not, it passes on the request.
			 */
			function parse_request ($wp)
			{
				$url = site_url(null, 'http');
				$tag = $this -> options ['trackme_slug'];
				$ext = $this -> options ['trackme_extension'];
				$base_uri = preg_replace('/^http:\/\/[^\/]+/', '', $url);
				$trackme_uri = $base_uri . "/" . $tag . "/requests." . $ext;
				$trackme_export_uri = $base_uri . "/" . $tag . "/export." . $ext;
				$request_uri = strtok ($_SERVER['REQUEST_URI'], '?');     // Strip querystring off request URI

				if ($request_uri == $trackme_uri) {
					$this -> handle_trackme_request();
					die();
				}

				if ($request_uri == $trackme_export_uri) {
					$this -> handle_trackme_export();
					die();
				}

				$tag = $this -> options ['mapmytracks_tag'];
				$uri = $base_uri . "/" . $tag ;

				if ($request_uri == $uri || $request_uri == $uri . '/') {
					$this -> handle_mapmytracks_request();
					die();
				}

				$tag = $this -> options ['upload_tag'];
				$uri = $base_uri . "/" . $tag ;

				if ($request_uri == $uri || $request_uri == $uri . '/') {
					$this -> handle_upload();
					die();
				}

				$tag = $this -> options ['gettrack_slug'];
				$uri = $base_uri . "/" . $tag ;

				if ($request_uri == $uri || $request_uri == $uri . '/') {
					$this -> handle_gettrack();
					die();
				}

				return $wp;
			}

			function validate_trackme_login ()
			{
				$username = urldecode ($_GET['u']);
				$password = urldecode ($_GET['p']);

				if ($username == '' || $password == '') {
					$this -> trackme_result (3);
				}
				else {
					$user = get_user_by('login', $username);

					if ($user) {
						$hash = $user -> data -> user_pass;
						$user_id = intval ($user -> data -> ID);

						if (wp_check_password ($password, $hash, $user_id )) {
							return $user_id;
						}
						else {
							$this -> trackme_result (1);  // Password incorrect
						}
					}
					else {
						$this -> trackme_result (2);  // User not found
					}
				}
			}

			/**
			 * Function to handle TrackMe GET requests. It validates the user and password and
			 * delegates the requested action to a dedicated function
			 */
			function handle_trackme_request ()
			{
				// If this function returns, we're OK
				$user_id = $this -> validate_trackme_login ();

				// Delegate the action to another function
				switch ($_GET['a']) {
					case 'upload':
						$this -> handle_trackme_upload ($user_id);
						break;
					case 'gettriplist':
						$this -> handle_trackme_gettriplist ($user_id);
						break;
					case 'deletetrip':
						$this -> handle_trackme_deletetrip ($user_id);
						break;
				}
			}

			/**
			 * Function to handle TrackMe export requests. Not currently implemented.
			 */
			function handle_trackme_export ()
			{
				http_response_code(501);
				echo "Export is not supported by the server.";

			}

			function handle_mapmytracks_request ()
			{
				// If this function returns, we're OK
				$user_id = $this -> validate_http_basicauth ();

				switch ($_POST['request']) {
					case 'start_activity':
						$this -> handle_mapmytracks_start_activity ($user_id);
						break;
					case 'update_activity':
						$this -> handle_mapmytracks_update_activity ($user_id);
						break;
					case 'stop_activity':
						$this -> handle_mapmytracks_stop_activity ($user_id);
						break;
					case 'get_activities':
						break;
					case 'get_activity':
						break;
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
			function handle_trackme_upload ($user_id)
			{
				global $wpdb;

				$trip_name = urldecode($_GET['tn']);
				$occurred = urldecode($_GET["do"]);

				if ($trip_name != '') {

					// Try to find the trip
					$sql = $wpdb -> prepare ("SELECT id FROM ". $this -> tbl_tracks ." WHERE user_id=%d AND name=%s", $user_id, $trip_name);
					$trip_id = $wpdb -> get_var ($sql);

					if ($trip_id == null) {

						if (!$this -> validate_timestamp ($occurred)) {
							$occurred = current_time ('Y-m-d H:i:s');
						}

						$data = array ('user_id' => $user_id, 'name' => $trip_name, 'created' => $occurred, 'source' => 'TrackMe');
						$format = array ('%d', '%s', '%s', '%s');

						if ($wpdb -> insert ($this -> tbl_tracks, $data, $format)) {
							$trip_id = $wpdb -> insert_id;
						}
						else {
							$this -> trackme_result (6); // Unable to create trip
						}
					}

					if (intval ($trip_id) > 0)  {

						$latitude = $_GET["lat"];
						$longitude = $_GET["long"];
						$altitude = urldecode($_GET["alt"]);
						$speed = urldecode($_GET["sp"]);
						$heading = urldecode($_GET["ang"]);
						//$comment = urldecode($_GET["comments"]);
						//$batterystatus = urldecode($_GET["bs"]);
						$now = current_time ('Y-m-d H:i:s');

						if ($latitude != '' && $longitude != '' && $this -> validate_timestamp ($occurred)) {
							$data = array (
								'trip_id' => $trip_id,
								'latitude' => $latitude,
								'longitude' => $longitude,
								'created' => $now,
								'occurred' => $occurred,
							);
							$format = array ('%d', '%s', '%s', '%s', '%s');

							if ($altitude != '') {
								$data['altitude'] = $altitude;
								$format[] = '%s';
							}
							if ($speed != '') {
								$data['speed'] = $speed;
								$format[] = '%s';
							}
							if ($heading != '') {
								$data['heading'] = $heading;
								$format[] = '%s';
							}

							if ($wpdb -> insert ($this -> tbl_locations, $data, $format)) {
								$this -> trackme_result (0);
							}
							else {
								$this -> trackme_result (7, $wpdb -> last_error);
							}
						}
					}
				}
				else {
					$this -> trackme_result (6); // No trip name specified. This should not happen.
				}

			}

			/**
			 * Function to handle the 'gettriplist' action from a TrackMe GET request. It prints a list of all trips
			 * currently in the database, containing name and creation timestamp
			 */
			function handle_trackme_gettriplist ($user_id)
			{
				global $wpdb;

				$sql = $wpdb -> prepare ("SELECT name,created FROM ". $this -> tbl_tracks ." WHERE user_id=%d ORDER BY name ", $user_id);
				$trips = $wpdb -> get_results ($sql, ARRAY_A);
				$triplist = "";
				foreach ($trips as $row) {
					$triplist .= htmlspecialchars ($row ['name']). "|" .htmlspecialchars ($row ['created']) ."\n";
				}
				$triplist = substr($triplist, 0, -1);
				$this -> trackme_result (0, $triplist);
			}

			/**
			 * Function to handle the 'deletetrip' action from a TrackMe GET request. If a trip ID can be found from the
			 * supplied name, all locations and the trip record for the ID are deleted from the database.
			 */
			function handle_trackme_deletetrip ($user_id)
			{
				global $wpdb;

				$trip_name = urldecode($_GET['tn']);

				if ($trip_name != '') {

					// Try to find the trip
					$sql = $wpdb -> prepare ("SELECT id FROM ". $this -> tbl_tracks ." WHERE user_id=%d AND name=%s", $user_id, $trip_name);
					$trip_id = $wpdb -> get_var ($sql);

					if ($trip_id == null) {
						$this -> trackme_result (7);   // Trip not found
					}
					else {
						$loc_where = array ('trip_id' => $trip_id);
						$trip_where = array ('id' => $trip_id);
						$wpdb -> delete ($this -> tbl_locations, $loc_where);
						$wpdb -> delete ($this -> tbl_tracks, $trip_where);
						$this -> trackme_result (0);   // Trip deleted
					}
				}
				else {
					$this -> trackme_result (6); // No trip name specified. This should not happen.
				}
			}

			/**
			 * Function to print a result for the TrackMe client. It prints a result code and optionally a message.
			 */
			function trackme_result ($rc, $message = false)
			{
				echo "Result:$rc";
				if ($message) {
					echo "|$message";
				}
				die();
			}

			/**
			 * Function to validate a timestamp supplied by a client. It checks if the timestamp is in the required
			 * format and if the timestamp is unchanged after parsing.
			 */
			function validate_timestamp ($ts)
			{
			    $d = DateTime::createFromFormat('Y-m-d H:i:s', $ts);
			    return $d && $d->format('Y-m-d H:i:s') == $ts;
			}

			/**
			 * Function to validate Wordpress credentials for basic HTTP authentication. If no crededtials are received,
			 * we send a 401 status code. If the username or password are incorrect, we terminate.
			 */
			function validate_http_basicauth ()
			{
				if (!isset($_SERVER['PHP_AUTH_USER'])) {

					header ('WWW-Authenticate: Basic realm="Authentication Required"');
					header ('HTTP/1.0 401 Unauthorized');
					die ("Authentication required\n");
				}

				$user = get_user_by('login', $_SERVER['PHP_AUTH_USER']);

				if ($user) {
					$hash = $user -> data -> user_pass;
					$user_id = intval ($user -> data -> ID);

					if (wp_check_password ($_SERVER['PHP_AUTH_PW'], $hash, $user_id )) {
						return $user_id;
					}
				}
				die ("Username or password incorrect\n");
			}

			/**
			 * Function to handle the 'start_activity' request for the MapMyTracks protocol. If no
			 * title / trip name is received, nothing is done. Received points are validated. Trip
 			 * is inserted with the first point's timstamp as start time, or the current time if no
			 * valid points are received. Valid points are inserted and and the new trip ID is
			 * returned in an XML message.
			 */
			function handle_mapmytracks_start_activity ($user_id)
			{
				global $wpdb;

				$trip_name = $_POST['title'];
				if ($trip_name != '') {
					$points = $this -> mapmytracks_parse_points ($_POST['points']);

					if ($points) {
						$ts = $points[0]['timestamp'];
						// Stolen from 'current_time' in wp-include/functions.php
						$occurred = date( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ));
					}
					else {
						$occurred = current_time ('Y-m-d H:i:s');
					}

					$source = $this -> mapmytracks_get_source ();
					$data = array ('user_id' => $user_id, 'name' => $trip_name, 'created' => $occurred, 'source' => $source);
					$format = array ('%d', '%s', '%s', '%s');

					if ($wpdb -> insert ($this -> tbl_tracks, $data, $format)) {
						$trip_id = $wpdb -> insert_id;
						if ($this -> mapmytracks_insert_points ($points, $trip_id)) {
							$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><message />');
							$xml->addChild('type', 'activity_started');
							$xml->addChild('activity_id', (string)$trip_id);
							echo $xml->asXML();
						}
					}
				}
			}

			/**
			 * Function to handle 'update_activity' request for the MapMyTracks protocol. It checks if the supplied
			 * activity_id is valid and owned by the current user before inserting the received points into the database.
			 */
			function handle_mapmytracks_update_activity ($user_id)
			{
				global $wpdb;

				$sql = $wpdb -> prepare ("SELECT id, user_id FROM ". $this -> tbl_tracks. " WHERE id=%d;", $_POST['activity_id']);
				$trip_id = $wpdb -> get_var ($sql);
				if ($trip_id) {
					// Get the user ID for the trip from the cached result of previous query
					$trip_owner = $wpdb -> get_var (null, 1);

					// Check if the current login is actually the owner of the trip
					if ($trip_owner == $user_id) {
						$points = $this -> mapmytracks_parse_points ($_POST['points']);
						if ($this -> mapmytracks_insert_points ($points, $trip_id)) {
							$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><message />');
							$xml->addChild('type', 'activity_updated');
							echo $xml->asXML();
						}
						// Absence of a valid XML response causes OruxMaps to re-send the data in a subsequent request
					}
				}
			}

			/**
			 * Function to handle 'stop_activity' request for the MapMyTracks protocol. This doesn't
			 * do anything, except return a bogus (but appropriate) XML message.
			 */
			function handle_mapmytracks_stop_activity ($user_id)
			{
				$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><message />');
				$xml->addChild('type', 'activity_stopped');
				echo $xml->asXML();
			}

			function mapmytracks_parse_points ($points)
			{
				// Check the points syntax. It should match groups of four items, each containing only
				// numbers, some also dots and dashes
				$pattern = '/^([\d.]+ [\d.]+ [\d.-]+ [\d]+ ?)*$/';
				$n = preg_match ($pattern, $points);

				if ($n == 1) {
					$parsed = array();
					$all = explode(' ', $points);
					for ($i=0; $i < count($all); $i+=4) {
						if ($all [$i]) {
							$parsed[] = array(
								'latitude' => $all [$i],
								'longitude' => $all [$i+1],
								'altitude' => $all [$i+2],
								'timestamp' => $all [$i+3],
							);
						}
					}
					return $parsed;
				}
				else {
					return false;  // Invalid input
				}
			}

			function mapmytracks_insert_points ($points, $trip_id)
			{
				global $wpdb;

				if ($points) {
					$now = current_time ('Y-m-d H:i:s');
					foreach ($points as $p) {
						$ts = $p['timestamp'];
						$occurred = date( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ));
						$data = array (
							'trip_id' => $trip_id,
							'latitude' => $p['latitude'],
							'longitude' => $p['longitude'],
							'altitude' => $p['altitude'],
							'created' => $now,
							'occurred' => $occurred
						);
						$format = array ('%d', '%s', '%s', '%s', '%s', '%s');

						// If insertion fails, return false immediately
						if (!$wpdb -> insert ($this -> tbl_locations, $data, $format)) {
							return false;
						}
					}
				}
				return true;
			}

			function mapmytracks_get_source ()
			{
					$source = '';
					if (array_key_exists ('source', $_POST)) {
						$source .= $_POST['source'];
					}
					if (array_key_exists ('version', $_POST)) {
						$source .= ' v' .$_POST['version'];
					}
					if ($source != '') {
						$source .= ' / ';
					}
					$source .= 'MapMyTracks';
					return $source;
			}

			function handle_upload ()
			{
				header ('Content-Type: text/plain');
				$user_id = $this -> validate_http_basicauth ();

				$tmp = $this -> get_temp_dir ();
				$schema = plugin_dir_path( __FILE__ ) .'/gpx-1.1.xsd';

				foreach ($_FILES as $f) {
					$filename = $tmp .'/'. uniqid ();

					// Check the filename extension case-insensitively
					if (strcasecmp (substr ($f ['name'], -4), '.gpx') == 0) {

						if ($f ['error'] == 0 && move_uploaded_file ($f ['tmp_name'], $filename)) {
							$xml = new DOMDocument();
							$xml -> load ($filename);
							if ($xml -> schemaValidate ($schema)) {
								if ($result = $this -> process_gpx ($xml, $user_id)) {
									echo "OK: File '". $f ['name'] . "'. Imported ".
										$result ['num_trkpt'] ." points from ". $result ['num_trk'] ." tracks\n";
								}
							}
							else {
								echo "ERROR: File '". $f ['name'] . "' could not be validated as GPX 1.1.\n";
							}
						}
						else {
							echo "ERROR: Upload '". $f ['name'] . "' failed (rc=". $f ['error'] . ")\n";
						}
					}
					else {
						echo "ERROR: Only .gpx files accepted\n";
					}
					unlink ($filename);
				}
			}

			/**
			 * Function to parse the XML from a GPX file and insert it into the database. It first converts the
			 * provided DOMDocument to SimpleXML for easier processing and uses the same intermediate format
			 * as the MapMyTracks import, so it can use the same function for inserting the locations
			 */
			function process_gpx ($dom, $user_id)
			{
				global $wpdb;

				$gpx = simplexml_import_dom ($dom);
				$source = $gpx ['creator'];
				$trip_start = false;

				$ntrk = 0;
				$ntrkpt = 0;
				foreach ($gpx -> trk as $trk) {
					$points = array ();
					$trip_name = $trk -> name;
					foreach ($trk -> trkseg -> trkpt as $trkpt) {
						if (!$trip_start) {
							$trip_start = date ('Y-m-d H:i:s', $this -> parse_iso_date ((string) $trkpt -> time));
						}
						$points[] = array(
							'latitude' => $trkpt ['lat'],
							'longitude' => $trkpt ['lon'],
							'altitude' => (string) $trkpt -> ele,
							'timestamp' => $this -> parse_iso_date ((string) $trkpt -> time)
						);
						$ntrkpt++;
					}

					$data = array ('user_id' => $user_id, 'name' => $trip_name, 'created' => $trip_start, 'source' => $source);
					$format = array ('%d', '%s', '%s', '%s');
					if ($wpdb -> insert ($this -> tbl_tracks, $data, $format)) {
						$trip_id = $wpdb -> insert_id;
						$this -> mapmytracks_insert_points ($points, $trip_id);
					}
					$ntrk++;
				}

				return array('num_trk' => $ntrk, 'num_trkpt' => $ntrkpt);
			}

			function get_temp_dir ()
			{
				$tmp = get_temp_dir () .'/trackserver';
				if (!file_exists($tmp)) {
					mkdir($tmp);
				}
				return $tmp;
			}

			function parse_iso_date ($ts)
			{
				//$i = new DateInterval ('PT' .strval (get_option( 'gmt_offset' ) * HOUR_IN_SECONDS) .'S');
				$d = new DateTime ($ts);
				//$d = $d -> add($i);
				return $d->format('U');
			}

			function get_author( $post_id )
			{
     		$post = get_post( $post_id );
     		return $post -> post_author;
			}

			function handle_gettrack ()
			{
				// Include polyline encoder
				require_once TRACKSERVER_PLUGIN_DIR . 'Polyline.php';

				global $wpdb;

				$post_id = intval($_REQUEST ['p']);
				$track_id = $_REQUEST ['id'];

				if ($track_id != 'live') {
					$track_id = intval ($track_id);
					$author_id = $this -> get_author($post_id);
				}

				// Refuse to serve the track without a valid nonce. Admin screen uses a different nonce.
				if (
					(array_key_exists('admin', $_REQUEST) &&
					!wp_verify_nonce ($_REQUEST['_wpnonce'], 'manage_track_'.$track_id) ) ||
					((!array_key_exists('admin', $_REQUEST)) &&
					!wp_verify_nonce ($_REQUEST['_wpnonce'], 'gettrack_'.$track_id ."_p". $post_id ))
				) {
					header('HTTP/1.1 403 Forbidden');
					echo "Access denied (t=$track_id).\n";
					die();
				}

				if ($track_id) {
					if ($track_id == 'live') {
						$sql = $wpdb -> prepare ('SELECT id FROM '. $this -> tbl_tracks .' WHERE user_id=%d ORDER BY created DESC LIMIT 0,1', $author_id);
					}
					else {
						$sql = $wpdb -> prepare ('SELECT id FROM '. $this -> tbl_tracks .' WHERE id=%d', $track_id);
					}
					$trip_id = $wpdb -> get_var ($sql);

					if ($trip_id) {

						$sql = $wpdb -> prepare ('SELECT latitude, longitude, occurred FROM '. $this -> tbl_locations .
							' WHERE trip_id=%d ORDER BY occurred;', $trip_id);
						$res = $wpdb -> get_results ($sql, ARRAY_A);

						$points = array();
						foreach ($res as $row) {
							$points[] = array ($row ['latitude'], $row ['longitude']);
						}
						$encoded = Polyline::Encode($points);
						$metadata = array (
							'last_trkpt_time' => $row ['occurred'],
							'last_trkpt_lat'  => $row ['latitude'],
							'last_trkpt_lon'  => $row ['longitude']
						);
						$data = array ('track' => $encoded, 'metadata' => $metadata);

						header ('Content-Type: application/json');
						echo json_encode ($data);
					}
				}
				else {
					echo "ENOID\n";
				}
			}

			function manage_tracks_html ()
			{
				if (!current_user_can('manage_options')) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}

				// Load prerequisites
				if (!class_exists ('WP_List_Table')) {
					require_once (ABSPATH .'wp-admin/includes/class-wp-list-table.php');
				}
				require_once (TRACKSERVER_PLUGIN_DIR .'tracks-list-table.php');
				add_thickbox ();

				$list_table_options = array (
					'tbl_tracks' => $this -> tbl_tracks,
					'tbl_locations' => $this -> tbl_locations,
				);

				$list_table = new Tracks_List_Table ($list_table_options);
				$list_table -> prepare_items ();

				$url = admin_url() . 'admin-post.php';

				?>
					<div id="ts-edit-modal" style="display:none;">
						<p>
							<form method="post" action="<?=$url?>">
								<table>
									<?php wp_nonce_field('manage_track'); ?>
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
						<p>
							<div id="tsadminmapcontainer">
								<div id="tsadminmap" style="width: 100%; height: 100%;"></div>
							</div>
						</p>
					</div>
        	<form id="trackserver-tracks" method="post">
						<input type="hidden" name="page" value="trackserver-tracks" />
						<div class="wrap">
							<h2>Manage tracks</h2>
							<?php $list_table -> display () ?>
						</div>
					</form>
				<?php
			}

			function profiles_html ()
			{
				if (!current_user_can('manage_options')) {
					wp_die( __('You do not have sufficient permissions to access this page.') );
				}
				echo "<h2>Trackserver map profiles</h2>";
			}

			function admin_post_save_track ()
			{
				global $wpdb;
				check_admin_referer ('manage_track_' . $_REQUEST ['track_id']);

				// Save track
				$data = array (
					'name' => $_REQUEST['name'],
					'source' => $_REQUEST['source'],
					'comment' => $_REQUEST['comment']
				);
				$where = array ('id' => $_REQUEST ['track_id']);
				$wpdb -> update ($this -> tbl_tracks, $data, $where, '%s', '%d');

				// Back to the admin page. This should be safe.
				wp_redirect ($_REQUEST ['_wp_http_referer']);
			}

			/**
			 * A function to load some javascript to hook into the Thickbox used for editing
			 * in the admin backend.
			 * http://stackoverflow.com/questions/6091998/how-would-you-trigger-an-event-when-a-thickbox-closes
			 * This is tied to the action 'admin_footer-trackserver_page_trackserver-tracks', where the
			 * suffix is obtained from $GLOBALS['hook_suffix'].
			 * http://codex.wordpress.org/Plugin_API/Action_Reference/admin_footer-%28hookname%29
			 * This action is run really late (just before the </body> tag) and is page-specific.
			 */
			function admin_footer ()
			{
				/* This is NOT the right way to print scripts, but using wp_register_script / wp_enqueue_script
				 * I could not get WP to load this script in the footer AFTER thickbox, which is necessary
				 * because we override some of its functions
         */
				echo "<script type='text/javascript' src='" .TRACKSERVER_PLUGIN_URL ."trackserver-admin.js'></script>";
			}

		} // class
	} // if !class_exists

	// Main
	$trackserver = new trackserver ();

?>
