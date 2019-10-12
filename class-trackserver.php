<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Trackserver' ) ) {

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
		var $db_version = 29;

		/**
		 * LeafletJS version that Trackserver will use.
		 *
		 * @since 4.4
		 * @access private
		 * @var str $leaflet_version
		 */
		var $leaflet_version = '1.5.1';

		/**
		 * Default values for options. See class constructor for more.
		 *
		 * @since 1.0
		 * @access private
		 * @var array $option_defaults
		 */
		var $option_defaults = array(
			'trackserver_slug'              => 'trackserver',
			'trackme_slug'                  => 'trackme',
			'trackme_extension'             => 'z',
			'mapmytracks_tag'               => 'mapmytracks',
			'osmand_slug'                   => 'osmand',
			'osmand_trackname_format'       => 'OsmAnd %F %H',
			'sendlocation_slug'             => 'sendlocation',
			'sendlocation_trackname_format' => 'SendLocation %F %H',
			'owntracks_slug'                => 'owntracks',
			'owntracks_trackname_format'    => 'Owntracks %F',
			'upload_tag'                    => 'tsupload',
			'embedded_slug'                 => 'tsmap',
			'gettrack_slug'                 => 'trackserver/gettrack',
			'enable_proxy'                  => false,
			'tile_url'                      => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			'attribution'                   => '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			'db_version'                    => false,
			'fetchmode_all'                 => true,
		);

		var $user_meta_defaults = array(
			'ts_infobar_template' => '{displayname} - {lat},{lon} - {timestamp}',
		);

		var $shortcode    = 'tsmap';
		var $shortcode2   = 'tsscripts';
		var $shortcode3   = 'tslink';
		var $track_format = 'polyline';  // 'polyline' or 'geojson'

		/**
		 * Class constructor.
		 *
		 * @since 1.0
		 */
		function __construct() {
			global $wpdb;
			$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
			$this->tbl_locations = $wpdb->prefix . 'ts_locations';
			$this->init_options();

			$this->user_meta_defaults['ts_trackme_key']       = substr( md5( uniqid() ), -8 );
			$this->user_meta_defaults['ts_osmand_key']        = substr( md5( uniqid() ), -8 );
			$this->user_meta_defaults['ts_sendlocation_key']  = substr( md5( uniqid() ), -8 );
			$this->user_meta_defaults['ts_tracks_admin_view'] = '0';
			$this->user_meta_defaults['ts_owntracks_share']   = '';
			$this->user_meta_defaults['ts_owntracks_follow']  = '';
			$this->user_meta_defaults['ts_geofences']         = array(
				array(
					'lat'    => 0,
					'lon'    => 0,
					'radius' => 0,
					'action' => 'hide',
				),
			);

			$this->mapdata                = array();
			$this->tracks_list_table      = false;
			$this->bulk_action_result_msg = false;
			$this->url_prefix             = '';
			$this->have_scripts           = false;
			$this->need_scripts           = false;

			// Bootstrap
			$this->add_actions();
			if ( is_admin() ) {
				require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-admin.php';
				$this->admin = new Trackserver_Admin( $this );
				$this->admin->add_actions();
				$this->add_admin_actions();
			}
		}

		/**
		 * Initialize Trackserver options, merging new options on the fly.
		 *
		 * @since 3.0
		 */
		function init_options() {
			$options = get_option( 'trackserver_options' );
			if ( ! $options ) {
				$options = array();
			}
			$this->options = array_merge( $this->option_defaults, $options );
			update_option( 'trackserver_options', $this->options );

			// Remove options that are no longer in use.
			$this->delete_option( 'osmand_key' );
			$this->delete_option( 'normalize_tripnames' );
			$this->delete_option( 'tripnames_format' );
		}

		/**
		 * Write a line to the PHP error log, trying to be smart about complex values
		 *
		 * @since 3.0
		 */
		function debug( $log ) {
			if ( true === WP_DEBUG ) {
				if ( is_array( $log ) || is_object( $log ) ) {
					error_log( print_r( $log, true ) );
				} else {
					error_log( $log );
				}
			}
		}

		/**
		 * Add missing default user meta values
		 * WARNING: this function will run on every request, so keep it lean.
		 *
		 * @since 1.9
		 */
		function init_user_meta() {
			// Set default profile values for the currently logged-in user
			if ( current_user_can( 'use_trackserver' ) ) {
				$user_id = get_current_user_id();
				foreach ( $this->user_meta_defaults as $key => $value ) {
					if ( get_user_meta( $user_id, $key, true ) == '' ) {
						update_user_meta( $user_id, $key, $value );
					}
				}
			}
		}

		/**
		 * Add common actions and filters.
		 *
		 * @since 1.0
		 */
		function add_actions() {

			// Set up permalink-related values
			add_action( 'wp_loaded', array( &$this, 'wp_loaded' ) );

			// Custom request parser; core protocol handler
			add_action( 'parse_request', array( &$this, 'parse_request' ) );

			// Add handler for TrackMe server via WP AJAX interface for both logged-in and not-logged-in users
			add_action( 'wp_ajax_trackserver_trackme', array( &$this, 'handle_trackme_request' ) );
			add_action( 'wp_ajax_nopriv_trackserver_trackme', array( &$this, 'handle_trackme_request' ) );

			// Front-end JavaScript and CSS
			add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_footer', array( &$this, 'wp_footer' ) );

			// Shortcodes
			add_shortcode( $this->shortcode, array( &$this, 'handle_shortcode' ) );
			add_shortcode( $this->shortcode2, array( &$this, 'handle_shortcode2' ) );
			add_shortcode( $this->shortcode3, array( &$this, 'handle_shortcode3' ) );

			// Media upload
			add_filter( 'upload_mimes', array( &$this, 'upload_mimes' ) );
			add_filter( 'media_send_to_editor', array( &$this, 'media_send_to_editor' ), 10, 3 );

			// Custom post type for embedded maps
			add_action( 'init', array( &$this, 'wp_init' ) );
			add_filter( 'single_template', array( &$this, 'get_tsmap_single_template' ) );
			add_filter( '404_template', array( &$this, 'get_tsmap_404_template' ) );

		}

		/**
		 * Add actions for the admin pages.
		 *
		 * @since 1.0
		 */
		function add_admin_actions() {

			add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_menu', array( &$this, 'admin_menu' ), 9 );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( &$this, 'add_settings_link' ) );
			add_action( 'admin_post_trackserver_save_track', array( &$this, 'admin_post_save_track' ) );
			add_action( 'admin_post_trackserver_upload_track', array( &$this, 'admin_post_upload_track' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
			add_action( 'wp_ajax_trackserver_save_track', array( &$this, 'admin_ajax_save_modified_track' ) );

			// WordPress MU
			add_action( 'wpmu_new_blog', array( &$this, 'wpmu_new_blog' ) );
			add_filter( 'wpmu_drop_tables', array( &$this, 'wpmu_drop_tables' ) );
		}

		/**
		 * Handler for 'init' action.
		 *
		 * @since 4.3
		 */
		function wp_init() {
			$this->load_textdomain();
			$this->register_tsmap_post_type();
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
			if ( ! $wp_rewrite->using_permalinks() || $wp_rewrite->using_index_permalinks() ) {
				$this->url_prefix = '/' . $wp_rewrite->index;
			}
			$this->init_user_meta();
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
			$this->options[ $option ] = $value;
			update_option( 'trackserver_options', $this->options );
		}

		/**
		 * Remove an option
		 *
		 * Remove a key from the options array and write the array to the database.
		 *
		 * @since 1.9
		 *
		 * @param string $option Option name
		 */
		function delete_option( $option ) {
			if ( array_key_exists( $option, $this->options ) ) {
				unset( $this->options[ $option ] );
				update_option( 'trackserver_options', $this->options );
			}
		}

		/**
		 * Function to load scripts for both front-end and admin. It is called from the
		 * 'wp_enqueue_scripts' and 'admin_enqueue_scripts' handlers.
		 *
		 * @since 1.0
		 */
		function load_common_scripts() {

			wp_enqueue_style( 'leaflet_stylesheet', TRACKSERVER_JSLIB . 'leaflet-' . $this->leaflet_version . '/leaflet.css' );
			wp_enqueue_script( 'leaflet_js', TRACKSERVER_JSLIB . 'leaflet-' . $this->leaflet_version . '/leaflet.js', array(), false, true );
			wp_enqueue_style( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-1.0.2/leaflet.fullscreen.css' );
			wp_enqueue_script( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-1.0.2/Leaflet.fullscreen.min.js', array(), false, true );
			wp_enqueue_script( 'leaflet-omnivore', TRACKSERVER_PLUGIN_URL . 'trackserver-omnivore.js', array(), TRACKSERVER_VERSION, true );
			wp_enqueue_style( 'trackserver', TRACKSERVER_PLUGIN_URL . 'trackserver.css', array(), TRACKSERVER_VERSION );
			wp_enqueue_script( 'promise-polyfill', TRACKSERVER_JSLIB . 'promise-polyfill-6.0.2/promise.min.js', array(), false, true );

			// To be localized in wp_footer() with data from the shortcode(s). Enqueued last, in wp_enqueue_scripts.
			// Also localized and enqueued in admin_enqueue_scripts
			wp_register_script( 'trackserver', TRACKSERVER_PLUGIN_URL . 'trackserver.js', array(), TRACKSERVER_VERSION, true );

			$settings = array(
				'tile_url'        => $this->options['tile_url'],
				'attribution'     => $this->options['attribution'],
				'leaflet_version' => $this->leaflet_version,
			);
			wp_localize_script( 'trackserver', 'trackserver_settings', $settings );

			$i18n = array(
				'no_tracks_to_display' => __( 'No tracks to display.', 'trackserver' ),
			);
			wp_localize_script( 'trackserver', 'trackserver_i18n', $i18n );
		}

		/**
		 * Handler for 'wp_enqueue_scripts'. Load javascript and stylesheets on
		 * the front-end.
		 *
		 * @since 1.0
		 */
		function wp_enqueue_scripts( $force = false ) {
			if ( $force || $this->detect_shortcode() ) {
				$this->load_common_scripts();

				// Live-update only on the front-end, not in admin
				wp_enqueue_style( 'leaflet-messagebox', TRACKSERVER_JSLIB . 'leaflet-messagebox-1.0/leaflet-messagebox.css' );
				wp_enqueue_script( 'leaflet-messagebox', TRACKSERVER_JSLIB . 'leaflet-messagebox-1.0/leaflet-messagebox.js', array(), false, true );
				wp_enqueue_style( 'leaflet-liveupdate', TRACKSERVER_JSLIB . 'leaflet-liveupdate-1.1/leaflet-liveupdate.css' );
				wp_enqueue_script( 'leaflet-liveupdate', TRACKSERVER_JSLIB . 'leaflet-liveupdate-1.1/leaflet-liveupdate.js', array(), false, true );

				// Enqueue the main script last
				wp_enqueue_script( 'trackserver' );

				// Instruct wp_footer() that we already have the scripts.
				$this->have_scripts = true;
			}
		}

		/**
		 * Handler for 'admin_enqueue_scripts'. Load javascript and stylesheets
		 * for the admin panel.
		 *
		 * @since 1.0
		 *
		 * @param string $hook The hook suffix for the current admin page.
		 */
		function admin_enqueue_scripts( $hook ) {

			switch ( $hook ) {
				case 'trackserver_page_trackserver-tracks':
				case 'trackserver_page_trackserver-yourprofile':
					$this->load_common_scripts();

					// The is_ssl() check should not be necessary, but somehow, get_home_url() doesn't correctly return a https URL by itself
					$track_base_url = get_home_url( null, $this->url_prefix . '/' . $this->options['gettrack_slug'] . '/?', ( is_ssl() ? 'https' : 'http' ) );
					wp_localize_script( 'trackserver', 'track_base_url', $track_base_url );

					// Enqueue the main script last
					wp_enqueue_script( 'trackserver' );

					// No break! The following goes for both hooks.
					// The options page only has 'trackserver-admin.js'.

				case 'toplevel_page_trackserver-options':
					$settings = array(
						'msg'  => array(
							'areyousure'     => __( 'Are you sure?', 'trackserver' ),
							'delete'         => __( 'deletion', 'trackserver' ),
							'merge'          => __( 'merging', 'trackserver' ),
							'recalc'         => __( 'recalculation', 'trackserver' ),
							'dlgpx'          => __( 'downloading', 'trackserver' ),
							'dlkml'          => __( 'downloading', 'trackserver' ),
							'track'          => __( 'track', 'trackserver' ),
							'tracks'         => __( 'tracks', 'trackserver' ),
							'edittrack'      => __( 'Edit track', 'trackserver' ),
							'deletepoint'    => __( 'Delete point', 'trackserver' ),
							'splittrack'     => __( 'Split track here', 'trackserver' ),
							'savechanges'    => __( 'Save changes', 'trackserver' ),
							'unsavedchanges' => __( 'There are unsaved changes. Save?', 'trackserver' ),
							'save'           => __( 'Save', 'trackserver' ),
							'discard'        => __( 'Discard', 'trackserver' ),
							'cancel'         => __( 'Cancel', 'trackserver' ),
							/* translators: %1$s = action, %2$s = number and %3$s is 'track' or 'tracks' */
							'selectminimum'  => __( 'For %1$s, select %2$s %3$s at minimum', 'trackserver' ),
						),
						'urls' => array(
							'adminpost'    => admin_url() . 'admin-post.php',
							'managetracks' => admin_url() . 'admin.php?page=trackserver-tracks',
						),
					);

					// Enqueue leaflet-editable
					wp_enqueue_script( 'leaflet-editable', TRACKSERVER_JSLIB . 'leaflet-editable-1.1.0/Leaflet.Editable.min.js', array(), false, true );

					// Enqueue the admin js (Thickbox overrides) in the footer
					wp_register_script( 'trackserver-admin', TRACKSERVER_PLUGIN_URL . 'trackserver-admin.js', array( 'thickbox' ), TRACKSERVER_VERSION, true );
					wp_localize_script( 'trackserver-admin', 'trackserver_admin_settings', $settings );
					wp_enqueue_script( 'trackserver-admin' );
					break;
			}
		}

		/**
		 * Create database tables
		 */
		function create_tables() {
			global $wpdb;

			if ( ! $this->options['db_version'] ) {

				$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->tbl_locations . " (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`trip_id` int(11) NOT NULL,
					`latitude` double NOT NULL,
					`longitude` double NOT NULL,
					`altitude` double NOT NULL,
					`speed` double NOT NULL,
					`heading` double NOT NULL,
					`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`created` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
					`occurred` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
					`comment` varchar(255) NOT NULL,
					`hidden` tinyint(1) NOT NULL DEFAULT '0',
					PRIMARY KEY (`id`),
					KEY `occurred` (`occurred`),
					KEY `trip_id` (`trip_id`),
					KEY `trip_id_occurred` (`trip_id`,`occurred`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->tbl_tracks . " (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`user_id` int(11) NOT NULL,
					`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
					`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					`created` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
					`source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
					`comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
					`distance` int(11) NOT NULL,
					PRIMARY KEY (`id`),
					KEY `user_id` (`user_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				$this->update_option( 'db_version', $this->db_version );
			}
		}

		/**
		 * Update the DB table properties on $this. Admin actions that can be called
		 * from the context of a different blog (network admin actions) need to call
		 * this before using the 'tbl_*' properties
		 */
		function set_table_refs() {
			global $wpdb;
			$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
			$this->tbl_locations = $wpdb->prefix . 'ts_locations';
		}

		/**
		/* Wrapper for switch_to_blog() that sets properties on $this
		 */
		function switch_to_blog( $blog_id ) {
			switch_to_blog( $blog_id );
			$this->set_table_refs();
		}

		/**
		 * Wrapper for restore_current_blog() that sets properties on $this
		 */
		function restore_current_blog() {
			restore_current_blog();
			$this->set_table_refs();
		}

		/**
		 * Installer function.
		 *
		 * This runs on every admin request. It installs/update the database
		 * tables and sets capabilities for user roles.
		 *
		 * @since 1.0
		 *
		 * @global object $wpdb The WordPress database interface
		 */
		function trackserver_install() {
			global $wpdb;

			$this->create_tables();
			$this->check_update_db();
			$this->set_capabilities();
		}

		/**
		 * Handler for 'wpmu_new_blog'. Only accepts one argument.
		 *
		 * This action is called when a new blog is created in a WordPress
		 * network. This function switches to the new blog, stores options with
		 * default values and calls the installer function to create the database
		 * tables and set user capabilities.
		 *
		 * @since 3.0
		 */
		function wpmu_new_blog( $blog_id ) {
			if ( is_plugin_active_for_network( 'trackserver/trackserver.php' ) ) {
				$this->switch_to_blog( $blog_id );
				$this->init_options();
				$this->trackserver_install();
				$this->restore_current_blog();
			}
		}

		/**
		 * Handler for 'wpmu_drop_tables'
		 *
		 * This filter adds Trackserver's database tables to the list of tables
		 * to be dropped when a blog is deleted from a WordPress network.
		 *
		 * @since 3.0
		 */
		function wpmu_drop_tables( $tables ) {
			$this->set_table_refs();
			$tables[] = $this->tbl_tracks;
			$tables[] = $this->tbl_locations;
			return $tables;
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

			$installed_version = (int) $this->options['db_version'];
			if ( $installed_version > 0 && $installed_version != $this->db_version ) {

				// Get a list of column names for the tracks table
				$sql = 'SELECT * FROM ' . $this->tbl_tracks . ' LIMIT 0,1';
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$colnames = $wpdb->get_col_info( 'name' );

				// Get a list of column names for the locations table
				$sql = 'SELECT * FROM ' . $this->tbl_tracks . ' LIMIT 0,1';
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$colnames_locations = $wpdb->get_col_info( 'name' );

				// Upgrade table if necessary. Add upgrade SQL statements here, and
				// update $db_version at the top of the file
				$upgrade_sql     = array();
				$upgrade_sql[5]  = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `created` `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
				$upgrade_sql[6]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `created` TIMESTAMP NOT NULL AFTER `updated`';
				$upgrade_sql[7]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `source` VARCHAR( 255 ) NOT NULL AFTER `created`';
				$upgrade_sql[8]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `distance` INT( 11 ) NOT NULL AFTER `comment`';
				$upgrade_sql[9]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD INDEX ( `user_id` )';
				$upgrade_sql[10] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX ( `trip_id` )';
				$upgrade_sql[11] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX ( `occurred` )';

				// Fix the 'update'/'updated' mess in the tracks table
				if ( in_array( 'update', $colnames ) ) {
					$upgrade_sql[13] = 'ALTER TABLE ' . $this->tbl_tracks . ' DROP `update`';
				}
				if ( ! in_array( 'updated', $colnames ) ) {
					$upgrade_sql[14] = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() AFTER `name`';
				}
				$upgrade_sql[15] = 'UPDATE ' . $this->tbl_tracks . ' t SET t.updated=(SELECT max(occurred) FROM `' . $this->tbl_locations . '` WHERE trip_id = t.id)';
				$upgrade_sql[16] = 'ALTER TABLE ' . $this->tbl_locations . " ADD `hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `comment`";

				// Fix the missing 'hidden' column in fresh installs of v4.0
				if ( ! in_array( 'hidden', $colnames_locations ) ) {
					$upgrade_sql[17] = 'ALTER TABLE ' . $this->tbl_locations . " ADD `hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `comment`";
				}

				// Change the default value for timestamps to be compatible with MySQL 5.7+
				$upgrade_sql[18] = 'ALTER TABLE ' . $this->tbl_locations . " ALTER occurred SET DEFAULT '1971-01-01 00:00:00', ALTER created SET DEFAULT '1971-01-01 00:00:00'";
				$upgrade_sql[19] = 'ALTER TABLE ' . $this->tbl_tracks . " ALTER created SET DEFAULT '1971-01-01 00:00:00'";

				// Change the charset of the text columns in the tracks table, to be able to hold unicode content.
				$upgrade_sql[25] = 'ALTER TABLE ' . $this->tbl_tracks . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
				$upgrade_sql[26] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';
				$upgrade_sql[27] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `source` `source` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';
				$upgrade_sql[28] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `comment` `comment` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';

				// Add multi-column index on the locations table if missing. This index can be missing on new installs between 4.3 and 4.3.2.
				$sql     = 'SHOW INDEX FROM ' . $this->tbl_locations . " WHERE Key_name='trip_id_occurred'";
				$indexes = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( count( $indexes ) === 0 ) {
					$upgrade_sql[29] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX `trip_id_occurred` (`trip_id`, `occurred`)';
				}

				for ( $i = $installed_version + 1; $i <= $this->db_version; $i++ ) {
					if ( array_key_exists( $i, $upgrade_sql ) ) {
						$wpdb->query( $upgrade_sql[ $i ] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}
				}
				$this->update_option( 'db_version', $this->db_version );
			}
		}

		/**
		 * Add capabilities for using Trackserver to WordPress roles.
		 *
		 * @since 1.3
		 */
		function set_capabilities() {
			$roles = array(
				'administrator' => array( 'use_trackserver', 'trackserver_publish', 'trackserver_admin' ),
				'editor'        => array( 'use_trackserver', 'trackserver_publish' ),
				'author'        => array( 'use_trackserver' ),
			);

			foreach ( $roles as $rolename => $capnames ) {
				$role = get_role( $rolename );
				foreach ( $capnames as $cap ) {
					if ( ! ( $role->has_cap( $cap ) ) ) {
						$role->add_cap( $cap );
					}
				}
			}
		}

		/**
		 * Function to load the gettext domain for this plugin. Called from the 'init' hook.
		 *
		 * @since 4.3
		 */
		function load_textdomain() {
			// Load the MO file for the current language.
			load_plugin_textdomain( 'trackserver', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		}

		/**
		 * Output HTML for the Trackserver options page.
		 *
		 * @since 1.0
		 */
		function options_page_html() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
			}

			add_thickbox();

			echo '<div class="wrap"><h2>';
			esc_html_e( 'Trackserver Options', 'trackserver' );
			echo '</h2>';

			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
				echo '<div class="updated"><p>' . esc_html__( 'Settings updated', 'trackserver' ) . '</p></div>';

				// Flush rewrite rules, for when embedded maps slug has been changed
				flush_rewrite_rules();
			}

			?>
				<hr />
				<form id="trackserver-options" name="trackserver-options" action="options.php" method="post">
			<?php

			settings_fields( 'trackserver-options' );
			do_settings_sections( 'trackserver' );
			submit_button( esc_attr__( 'Update options', 'trackserver' ), 'primary', 'submit' );

			?>
				</form>
				<hr />
			</div>
			<?php
			$this->howto_modals_html();
		}

		function howto_modals_html() {
			$trackme_settings_img     = TRACKSERVER_PLUGIN_URL . 'img/trackme-settings.png';
			$trackme_settings         = esc_attr__( 'TrackMe settings', 'trackserver' );
			$mapmytracks_settings_img = TRACKSERVER_PLUGIN_URL . 'img/oruxmaps-mapmytracks.png';
			$mapmytracks_settings     = esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' );
			$osmand_settings_img      = TRACKSERVER_PLUGIN_URL . 'img/osmand-settings.png';
			$osmand_settings          = esc_attr__( 'OsmAnd settings', 'trackserver' );
			$autoshare_settings_img   = TRACKSERVER_PLUGIN_URL . 'img/autoshare-settings.png';
			$autoshare_settings       = esc_attr__( 'AutoShare settings', 'trackserver' );

			echo <<<EOF
				<div id="ts-trackmehowto-modal" style="display:none;">
					<p>
							<img src="$trackme_settings_img" alt="$trackme_settings" />
					</p>
				</div>
				<div id="ts-osmandhowto-modal" style="display:none;">
					<p>
							<img src="$osmand_settings_img" alt="$osmand_settings" />
					</p>
				</div>
				<div id="ts-oruxmapshowto-modal" style="display:none;">
					<p>
							<img src="$mapmytracks_settings_img" alt="$mapmytracks_settings" />
					</p>
				</div>
				<div id="ts-autosharehowto-modal" style="display:none;">
					<p>
							<img src="$autoshare_settings_img" alt="$autoshare_settings" />
					</p>
				</div>
EOF;
		}

		/**
		 * Filter callback to add a link to the plugin's settings.
		 */
		function add_settings_link( $links ) {
			$settings_link = '<a href="admin.php?page=trackserver-options">' . esc_html__( 'Settings', 'trackserver' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}

		/**
		 * Handler for 'admin_init'. Calls trackserver_install() and registers settings.
		 *
		 * @since 3.0
		 */
		function admin_init() {
			$this->trackserver_install();
		}

		function sanitize_option_values( $options ) {
			$options['enable_proxy']  = (bool) $options['enable_proxy'];
			$options['fetchmode_all'] = (bool) $options['fetchmode_all'];
			return $options;
		}

		function admin_menu() {
			$page                   = add_options_page( 'Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-admin-menu', array( &$this, 'options_page_html' ) );
			$page                   = str_replace( 'admin_page_', '', $page );
			$this->options_page     = str_replace( 'settings_page_', '', $page );
			$this->options_page_url = menu_page_url( $this->options_page, false );

			// A dedicated menu in the main tree
			add_menu_page(
				esc_html__( 'Trackserver Options', 'trackserver' ),
				esc_html__( 'Trackserver', 'trackserver' ),
				'manage_options',
				'trackserver-options',
				array( &$this, 'options_page_html' ),
				TRACKSERVER_PLUGIN_URL . 'img/trackserver.png'
			);

			add_submenu_page(
				'trackserver-options',
				esc_html__( 'Trackserver Options', 'trackserver' ),
				esc_html__( 'Options', 'trackserver' ),
				'manage_options',
				'trackserver-options',
				array( &$this, 'options_page_html' )
			);

			$page2 = add_submenu_page(
				'trackserver-options',
				esc_html__( 'Manage tracks', 'trackserver' ),
				esc_html__( 'Manage tracks', 'trackserver' ),
				'use_trackserver',
				'trackserver-tracks',
				array( &$this, 'manage_tracks_html' )
			);

			$page3 = add_submenu_page(
				'trackserver-options',
				esc_html__( 'Your profile', 'trackserver' ),
				esc_html__( 'Your profile', 'trackserver' ),
				'use_trackserver',
				'trackserver-yourprofile',
				array( &$this, 'yourprofile_html' )
			);

			// Early action to set up the 'Manage tracks' page and handle bulk actions.
			add_action( 'load-' . $page2, array( &$this, 'load_manage_tracks' ) );

			// Early action to set up the 'Your profile' page and handle POST
			add_action( 'load-' . $page3, array( &$this, 'load_your_profile' ) );
		}

		/**
		 * Detect shortcode
		 *
		 * This function tries to detect Trackserver's shortcodes [tsmap] or [tsscripts]
		 * in the current query's output and returns true if it is found.
		 *
		 * @since 1.0
		 */

		function detect_shortcode() {
			global $wp_query;
			$posts = $wp_query->posts;

			foreach ( $posts as $post ) {
				if ( $this->has_shortcode( $post ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Function to find the Trackserver shortcodes in the content of a post or page
		 *
		 * @since 2.0
		 */
		function has_shortcode( $post ) {
			$pattern = get_shortcode_regex();
			if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches )
				&& array_key_exists( 2, $matches )
				&& ( in_array( $this->shortcode, $matches[2] ) || in_array( $this->shortcode2, $matches[2] ) ) ) {
					return true;
			}
			return false;
		}

		/**
		 * Function to validate a list of track IDs against user ID and post ID.
		 * It tries to leave the given order of IDs unchanged.
		 *
		 * @since 3.0
		 */
		function validate_track_ids( $track_ids, $author_id ) {
			global $wpdb;

			if ( count( $track_ids ) == 0 ) {
				return array();
			}

			// Remove all non-numeric values from the tracks array and prepare query
			$track_ids = array_map( 'intval', array_filter( $track_ids, 'is_numeric' ) );
			$sql_in    = "('" . implode( "','", $track_ids ) . "')";

			// If the author has the power, don't check the track's owner
			if ( user_can( $author_id, 'trackserver_publish' ) ) {
				$sql = 'SELECT id FROM ' . $this->tbl_tracks . ' WHERE id IN ' . $sql_in;
			} else {
				// Otherwise, filter the list of posts against the author ID
				$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->tbl_tracks . ' WHERE id IN ' . $sql_in . ' AND user_id=%d;', $author_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			$validated_track_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Restore track order as given in the shortcode
			$trk0 = array();
			foreach ( $track_ids as $tid ) {
				if ( in_array( $tid, $validated_track_ids ) ) {
					$trk0[] = $tid;
				}
			}
			return $trk0;
		}

		/**
		 * Function to return a user ID, given a user name or ID. Unknown users return false.
		 *
		 * @since 3.0
		 */
		function get_user_id( $user, $property = 'ID' ) {
			if ( $user == '@' ) {
				$user = get_the_author_meta( 'ID' );
			}
			if ( is_numeric( $user ) ) {
				$field = 'id';
				$user  = (int) $user;
			} else {
				$field = 'login';
			}
			$user = get_user_by( $field, $user );
			if ( $user ) {
				return ( $property == 'ID' ? (int) $user->$property : $user->$property );
			} else {
				return false;
			}
		}

		/**
		 * Validate users against the DB and the author's permission to publish.
		 *
		 * It turns user names into numeric IDs.
		 *
		 * @since 3.0
		 */
		function validate_user_ids( $user_ids, $author_id ) {
			global $wpdb;

			if ( count( $user_ids ) == 0 ) {
				return array();
			}

			$user_ids = array_map( array( $this, 'get_user_id' ), $user_ids );  // Get numeric IDs
			$user_ids = array_filter( $user_ids );   // Get rid of the falses.

			if ( ! user_can( $author_id, 'trackserver_publish' ) ) {
				$user_ids = array_intersect( $user_ids, array( $author_id ) );   // array containing 0 or 1 elements
			}

			if ( count( $user_ids ) > 0 ) {
				$sql_in             = "('" . implode( "','", $user_ids ) . "')";
				$sql                = 'SELECT DISTINCT(user_id) FROM ' . $this->tbl_tracks . ' WHERE user_id IN ' . $sql_in;
				$validated_user_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				// Restore track order as given in the shortcode
				$usr0 = array();
				foreach ( $user_ids as $uid ) {
					if ( in_array( $uid, $validated_user_ids ) ) {
						$usr0[] = $uid;
					}
				}
				return $usr0;
			} else {
				return array();
			}
		}

		/**
		 * Turn shortcode attributes into lists of validated track IDs and user IDs
		 *
		 * @since 3.0
		 **/
		function validate_ids( $atts ) {
			$validated_track_ids = array();     // validated array of tracks to display
			$validated_user_ids  = array();      // validated array of user id's whois live track to display
			$author_id           = get_the_author_meta( 'ID' );

			if ( $atts['track'] ) {
				$track_ids = explode( ',', $atts['track'] );
				// Backward compatibility
				if ( in_array( 'live', $track_ids ) ) {
					$validated_user_ids[] = $author_id;
				}
				$validated_track_ids = $this->validate_track_ids( $track_ids, $author_id );
			}

			if ( $atts['user'] ) {
				$user_ids           = explode( ',', $atts['user'] );
				$validated_user_ids = array_merge( $validated_user_ids, $this->validate_user_ids( $user_ids, $author_id ) );
			}
			return array( $validated_track_ids, $validated_user_ids );
		}

		/**
		 * Return the value of 'points' for a track based on shortcode attribute
		 *
		 * @since 3.0
		 */
		function get_points( $atts = false, $shift = true ) {

			// Initialize if argument is given
			if ( is_array( $atts ) ) {
				$this->points = ( $atts['points'] ? explode( ',', $atts['points'] ) : false );
			}

			$p = false;
			if ( is_array( $this->points ) ) {
				$p = ( $shift ? array_shift( $this->points ) : $this->points[0] );
				if ( empty( $this->points ) ) {
					$this->points[] = $p;
				}
			}

			return ( in_array( $p, array( 'true', 't', 'yes', 'y' ), true ) ? true : false ); // default false
		}

		/**
		 * Return the value of 'markers' for a track based on shortcode attribute
		 *
		 * @since 3.0
		 */
		function get_markers( $atts = false, $shift = true ) {

			// Initialize if argument is given
			if ( is_array( $atts ) ) {
				$this->markers = ( $atts['markers'] ? explode( ',', $atts['markers'] ) : false );
			}

			$p = false;
			if ( is_array( $this->markers ) ) {
				$p = ( $shift ? array_shift( $this->markers ) : $this->markers[0] );
				if ( empty( $this->markers ) ) {
					$this->markers[] = $p;
				}
			}

			$markers = ( in_array( $p, array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
			$markers = ( in_array( $p, array( 'start', 's' ), true ) ? 'start' : $markers );
			$markers = ( in_array( $p, array( 'end', 'e' ), true ) ? 'end' : $markers );
			return $markers;
		}

		/**
		 * Return style object for a track based on shortcode attributes
		 *
		 * A function to return a style object from 'color', 'weight' and 'opacity' parameters.
		 * If the $atts array is passed as an argument, the data is initialized. Then the style
		 * array is created. The default is to shift an element off the beginning of the array.
		 * When an array is empty (meaning there are more tracks than values in the parameter),
		 * the last value is restored to be used for subsequent tracks.
		 *
		 * @since 3.0
		 */
		function get_style( $atts = false, $shift = true ) {

			// Initialize if argument is given
			if ( is_array( $atts ) ) {
				$this->colors    = ( $atts['color'] ? explode( ',', $atts['color'] ) : false );
				$this->weights   = ( $atts['weight'] ? explode( ',', $atts['weight'] ) : false );
				$this->opacities = ( $atts['opacity'] ? explode( ',', $atts['opacity'] ) : false );
				$this->dashes    = ( $atts['dash'] ? explode( ':', $atts['dash'] ) : false );
			}

			$style = array();
			if ( is_array( $this->colors ) ) {
				$style['color'] = ( $shift ? array_shift( $this->colors ) : $this->colors[0] );
				if ( empty( $this->colors ) ) {
					$this->colors[] = $style['color'];
				}
			}
			if ( is_array( $this->weights ) ) {
				$style['weight'] = ( $shift ? array_shift( $this->weights ) : $this->weights[0] );
				if ( empty( $this->weights ) ) {
					$this->weights[] = $style['weight'];
				}
			}
			if ( is_array( $this->opacities ) ) {
				$style['opacity'] = ( $shift ? array_shift( $this->opacities ) : $this->opacities[0] );
				if ( empty( $this->opacities ) ) {
					$this->opacities[] = $style['opacity'];
				}
			}
			if ( is_array( $this->dashes ) ) {
				$style['dashArray'] = ( $shift ? array_shift( $this->dashes ) : $this->dashes[0] );
				if ( empty( $this->dashes ) ) {
					$this->dashes[] = $style['dashArray'];
				}
			}
			return $style;
		}

		/**
		 * Return the value of 'markersize' for a track based on shortcode attribute
		 *
		 * @since 4.3
		 */
		function get_markersize( $atts = false, $shift = true ) {

			// Initialize if argument is given
			if ( is_array( $atts ) ) {
				$this->markersize = ( $atts['markersize'] ? explode( ',', $atts['markersize'] ) : false );
			}

			$p = false;
			if ( is_array( $this->markersize ) ) {
				$p = ( $shift ? array_shift( $this->markersize ) : $this->markersize[0] );
				if ( empty( $this->markersize ) ) {
					$this->markersize[] = $p;
				}
			}

			$markersize = ( (int) $p > 0 ? $p : 5 );    //default: 5
			return $markersize;
		}

		/**
		 * Return maxage in seconds for a time expression
		 *
		 * Takes an expression like 120s, 5m, 3h, 7d and turns it into seconds. No unit equals seconds.
		 *
		 * @since 3.1
		 */
		function get_maxage( $str ) {
			if ( $str === false ) {
				return 0;
			}
			preg_match_all( '/^(\d+)\s*(\w)?$/', $str, $matches );
			$n = (int) $matches[1][0];
			$u = strtolower( $matches[2][0] );
			if ( $u == '' ) {
				return $n;
			}
			$map = array(
				's' => 1,
				'm' => 60,
				'h' => 3600,
				'd' => 86400,
			);
			if ( array_key_exists( $u, $map ) ) {
				return $n * $map[ $u ];
			}
			return $n;
		}

		/**
		 * Handle the main [tsmap] shortcode
		 *
		 * @since 1.0
		 **/
		function handle_shortcode( $atts ) {
			global $wpdb, $post;

			$default_height = '480px';
			if ( $post->post_type == 'tsmap' ) {
				$default_height = '100%';
			}

			$defaults = array(
				'width'      => '100%',
				'height'     => $default_height,
				'align'      => '',
				'class'      => '',
				'id'         => false,
				'track'      => false,
				'user'       => false,
				'live'       => false,
				'gpx'        => false,
				'kml'        => false,
				'markers'    => true,
				'markersize' => false,
				'continuous' => true,
				'color'      => false,
				'weight'     => false,
				'opacity'    => false,
				'dash'       => false,
				'infobar'    => false,
				'points'     => false,
				'zoom'       => false,
				'maxage'     => false,
			);

			$atts      = shortcode_atts( $defaults, $atts, $this->shortcode );
			$author_id = get_the_author_meta( 'ID' );
			$post_id   = get_the_ID();
			$is_live   = false;

			static $num_maps = 0;
			$div_id          = 'tsmap_' . ++$num_maps;

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

			if ( ! $atts['track'] ) {
				$atts['track'] = $atts['id'];
			}

			$style      = $this->get_style( $atts, false );     // result is not used
			$points     = $this->get_points( $atts, false );    // result is not used
			$markers    = $this->get_markers( $atts, false );  // result is not used
			$markersize = $this->get_markersize( $atts, false );  // result is not used
			$maxage     = $this->get_maxage( $atts['maxage'] );

			list( $validated_track_ids, $validated_user_ids ) = $this->validate_ids( $atts );

			if ( count( $validated_user_ids ) > 0 ) {
				$is_live = true;
			}

			$tracks              = array();
			$alltracks_url       = false;
			$default_lat         = '51.443168';
			$default_lon         = '5.447200';
			$gettrack_url_prefix = get_home_url( null, $this->url_prefix . '/' . $this->options['gettrack_slug'] . '/' );

			if ( count( $validated_track_ids ) > 0 || count( $validated_user_ids ) > 0 ) {

				$live_tracks   = $this->get_live_tracks( $validated_user_ids, $maxage );
				$all_track_ids = array_merge( $validated_track_ids, $live_tracks );
				$query         = json_encode(
					array(
						'id'   => $validated_track_ids,
						'live' => $validated_user_ids,
					)
				);
				$query         = base64_encode( $query );
				$query_nonce   = wp_create_nonce( 'gettrack_' . $query . '_p' . $post_id );
				$alltracks_url = $gettrack_url_prefix . '?query=' . rawurlencode( $query ) . "&p=$post_id&format=" .
					$this->track_format . "&maxage=$maxage&_wpnonce=$query_nonce";
				$following     = false;

				//foreach ( $validated_track_ids as $validated_id ) {
				foreach ( $all_track_ids as $validated_id ) {

					// For the first live track, set 'follow' to true
					if ( in_array( $validated_id, $live_tracks ) && ! $following ) {
						$following = true;
						$follow    = true;
					} else {
						$follow = false;
					}

					$trk = array(
						'track_id'   => $validated_id,
						'track_type' => 'polyline',     // the handle_gettrack_query method only supports polyline
						'style'      => $this->get_style(),
						'points'     => $this->get_points(),
						'markers'    => $this->get_markers(),
						'markersize' => $this->get_markersize(),
						'follow'     => $follow,
					);

					// If the 'fetchmode_all' option is false, do not use $query, but fetch each track via its own URL
					if ( ! $this->options['fetchmode_all'] ) {

						// Use wp_create_nonce() instead of wp_nonce_url() due to escaping issues
						// https://core.trac.wordpress.org/ticket/4221
						$nonce = wp_create_nonce( 'gettrack_' . $validated_id . '_p' . $post_id );

						switch ( $this->track_format ) {
							case 'geojson':
								$trk['track_type'] = 'geojson';
								break;
							default:
								$trk['track_type'] = 'polylinexhr';
						}
						$trk['track_url'] = $gettrack_url_prefix . '?id=' . $validated_id . "&p=$post_id&format=" .
							$this->track_format . "&maxage=$maxage&_wpnonce=$nonce";
					}

					$tracks[] = $trk;
				}

				if ( count( $all_track_ids ) ) {
					$sql_in = "('" . implode( "','", $all_track_ids ) . "')";
					$sql    = 'SELECT AVG(latitude) FROM ' . $this->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
					$result = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					if ( $result ) {
						$default_lat = $result;
					}
					$sql    = 'SELECT AVG(longitude) FROM ' . $this->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
					$result = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					if ( $result ) {
						$default_lon = $result;
					}
				}
			}

			if ( $atts['gpx'] ) {
				$urls = explode( ' ', $atts['gpx'] );
				$j    = 0;
				foreach ( $urls as $u ) {
					if ( ! empty( $u ) ) {
						$u        = $this->proxy_url( $u, $post_id );
						$tracks[] = array(
							'track_id'   => 'gpx' . $j,
							'track_url'  => $u,
							'track_type' => 'gpx',
							'style'      => $this->get_style(),
							'points'     => $this->get_points(),
							'markers'    => $this->get_markers(),
							'markersize' => $this->get_markersize(),
						);
						$j++;
					}
				}
			}

			if ( $atts['kml'] ) {
				$urls = explode( ' ', $atts['kml'] );
				$j    = 0;
				foreach ( $urls as $u ) {
					if ( ! empty( $u ) ) {
						$u        = $this->proxy_url( $u, $post_id );
						$tracks[] = array(
							'track_id'   => 'kml' . $j,
							'track_url'  => $u,
							'track_type' => 'kml',
							'style'      => $this->get_style(),
							'points'     => $this->get_points(),
							'markers'    => $this->get_markers(),
							'markersize' => $this->get_markersize(),
						);
						$j++;
					}
				}
			}

			$continuous  = ( in_array( $atts['continuous'], array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
			$infobar     = ( in_array( $atts['infobar'], array( 'false', 'f', 'no', 'n', false ), true ) ? false : true );  // default false, any value is true
			$is_not_live = ( in_array( $atts['live'], array( 'false', 'f', 'no', 'n' ), true ) ? false : $is_live );   // force override
			$is_live     = ( in_array( $atts['live'], array( 'true', 't', 'yes', 'y' ), true ) ? true : $is_not_live );   // force override
			$infobar_tpl = get_user_meta( $author_id, 'ts_infobar_template', true );
			$zoom        = ( $atts['zoom'] !== false ? intval( $atts['zoom'] ) : ( $is_live ? '16' : '6' ) );
			$fit         = ( $atts['zoom'] !== false ? false : true ); // zoom is always set, so we need a signal for altering fitBounds() options

			$mapdata = array(
				'div_id'       => $div_id,
				'tracks'       => $tracks,
				'default_lat'  => $default_lat,
				'default_lon'  => $default_lon,
				'default_zoom' => $zoom,
				'fullscreen'   => true,
				'is_live'      => $is_live,
				'continuous'   => $continuous,
				'infobar'      => $infobar,
				'fit'          => $fit,
			);

			if ( $this->options['fetchmode_all'] ) {
				$mapdata['alltracks'] = $alltracks_url;
			}

			if ( $infobar ) {
				if ( in_array( $atts['infobar'], array( 't', 'true', 'y', 'yes' ) ) ) {
					$mapdata['infobar_tpl'] = htmlspecialchars( $infobar_tpl );
				} else {
					$mapdata['infobar_tpl'] = htmlspecialchars( $atts['infobar'] );
				}
			}

			$this->mapdata[] = $mapdata;
			$out             = '<div id="' . $div_id . '" ' . $class_str . ' style="width: ' . $atts['width'] . '; height: ' . $atts['height'] . '; max-width: 100%"></div>';

			$this->need_scripts = true;

			return $out;
		}

		function proxy_url( $url, $post_id ) {
			if ( substr( $url, 0, 6 ) == 'proxy:' ) {
				$track_base_url = get_home_url( null, $this->url_prefix . '/' . $this->options['gettrack_slug'] . '/?', ( is_ssl() ? 'https' : 'http' ) );
				$proxy          = base64_encode( substr( $url, 6 ) );
				$proxy_nonce    = wp_create_nonce( 'proxy_' . $proxy . '_p' . $post_id );
				$url            = $track_base_url . 'proxy=' . rawurlencode( $proxy ) . "&p=$post_id&_wpnonce=$proxy_nonce";
			}
			return $url;
		}

		/**
		 * Stub function for the 'tsscripts' shortcode. It doesn't do anything.
		 *
		 * @since 2.0
		 */
		function handle_shortcode2( $atts ) {
			// do nothing
		}

		/**
		 * Handle the [tslink] shortcode
		 *
		 * Handler for the 'tslink' shortcode. It returns a link to the specified track(s).
		 * Like the 'tsmap' shortcode, it supports both 'track' and 'user' attributes and
		 * creates the same type of query, to be processed by the 'gettrack' handler.
		 *
		 * @since 3.0
		 */
		function handle_shortcode3( $atts, $content = '' ) {
			global $wpdb;

			$defaults = array(
				'text'   => '',
				'class'  => '',
				'id'     => false,
				'track'  => false,
				'user'   => false,
				'format' => 'gpx',
				'maxage' => false,
			);

			$atts = shortcode_atts( $defaults, $atts, $this->shortcode );

			$class_str = '';
			if ( $atts['class'] ) {
				$class_str = 'class="' . htmlspecialchars( $atts['class'] ) . '"';
			}

			$out = 'ERROR';

			if ( ! $atts['track'] ) {
				$atts['track'] = $atts['id'];
			}

			$maxage = $this->get_maxage( $atts['maxage'] );

			list( $validated_track_ids, $validated_user_ids ) = $this->validate_ids( $atts );

			if ( count( $validated_track_ids ) > 0 || count( $validated_user_ids ) > 0 ) {

				$post_id = get_the_ID();

				$track_format = 'gpx';
				if ( $atts['format'] && in_array( $atts['format'], array( 'gpx' ) ) ) {
					$track_format = $atts['format'];
				}

				$query         = json_encode(
					array(
						'id'   => $validated_track_ids,
						'live' => $validated_user_ids,
					)
				);
				$query         = base64_encode( $query );
				$query_nonce   = wp_create_nonce( 'gettrack_' . $query . '_p' . $post_id );
				$alltracks_url = get_home_url( null, $this->url_prefix . '/' . $this->options['gettrack_slug'] . '/?query=' . rawurlencode( $query ) . "&p=$post_id&format=$track_format&maxage=$maxage&_wpnonce=$query_nonce" );

				$text = $atts['text'] . $content;
				if ( $text == '' ) {
					$text = 'download ' . $track_format;
				}

				$out = '<a href="' . $alltracks_url . '" ' . $class_str . '>' . htmlspecialchars( $text ) . '</a>';
			}

			return $out;
		}

		/**
		 * Provision the JavaScript that initializes the map(s) with settings and data
		 *
		 * @since 2.0
		 */
		function wp_footer() {
			if ( $this->need_scripts && ! $this->have_scripts ) {
				$this->wp_enqueue_scripts( true );
			}
			wp_localize_script( 'trackserver', 'trackserver_mapdata', $this->mapdata );
		}

		/**
		 * Handle the request. This does a simple string comparison on the request URI to see
		 * if we need to handle the request. If so, it does. If not, it passes on the request.
		 *
		 * @since 1.0
		 */
		function parse_request( $wp ) {

			$url         = site_url( null, 'http' ) . $this->url_prefix;
			$tag         = $this->options['trackme_slug'];
			$ext         = $this->options['trackme_extension'];
			$base_uri    = preg_replace( '/^http:\/\/[^\/]+/', '', $url );
			$request_uri = strtok( $_SERVER['REQUEST_URI'], '?' );     // Strip querystring off request URI

			$trackme_uri        = $base_uri . '/' . $tag . '/requests.' . $ext;
			$trackme_export_uri = $base_uri . '/' . $tag . '/export.' . $ext;
			$trackme_cloud_uri  = $base_uri . '/' . $tag . '/cloud.' . $ext;
			$trackme_uri_pttrn  = '/^(?<base_uri>\/.+)\/(?<slug>[^\/]+)\/(?<username>[^\/]+)\/(?<password>[^\/]+)\/(?<method>requests|export|cloud)\.(?<ext>.+)/';

			if ( $request_uri == $trackme_uri ) {
				$this->handle_trackme_request();
				die();
			}

			if ( $request_uri == $trackme_export_uri ) {
				$this->handle_trackme_export();
				die();
			}

			if ( $request_uri == $trackme_cloud_uri ) {
				$this->handle_trackme_cloud_error();
				die();
			}

			$n = preg_match( $trackme_uri_pttrn, $request_uri, $matches );
			if ( $n == 1 ) {

				if (
					$matches['base_uri'] == $base_uri &&
					$matches['slug'] == $tag &&
					$matches['ext'] == $ext
				) {

					if ( $matches['method'] == 'requests' ) {
						$this->handle_trackme_request( $matches['username'], $matches['password'] );
						die();
					}

					if ( $matches['method'] == 'export' ) {
						$this->handle_trackme_export( $matches['username'], $matches['password'] );
						die();
					}

					if ( $matches['method'] == 'cloud' ) {
						$this->handle_trackme_cloud( $matches['username'], $matches['password'] );
						die();
					}

					// No fallback necessary, since the regexp will not match for unknown methods.

				} else {
					// Inform user about incorrect slug or ext.
					$this->http_terminate( 501, 'The configured URL Header or Server Extension are incorrect.' );
				}
			}

			$tag = $this->options['mapmytracks_tag'];
			$uri = $base_uri . '/' . $tag;

			if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
				$this->handle_mapmytracks_request();
				die();
			}

			$slug = $this->options['osmand_slug'];
			$uri  = $base_uri . '/' . $slug;

			if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
				$this->handle_osmand_request();
				die();
			}

			$slug     = $this->options['sendlocation_slug'];
			$base_esc = str_replace( '/', '\\/', $base_uri );

			// <base uri>/<slug>/<username>/<access key>
			$uri_pattern = '/^' . $base_esc . '\/' . $slug . '\/([^\/]+)\/([^\/]+)/';

			$n = preg_match( $uri_pattern, $request_uri, $matches );
			if ( $n == 1 ) {
				$username = $matches[1];
				$key      = $matches[2];
				$this->handle_sendlocation_request( $username, $key );
				die();
			}

			$slug = $this->options['owntracks_slug'];
			$uri  = $base_uri . '/' . $slug;

			if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
				$this->handle_owntracks_request();
				die();
			}

			$tag = $this->options['upload_tag'];
			$uri = $base_uri . '/' . $tag;

			if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
				$this->handle_upload();
				die();
			}

			$tag = $this->options['gettrack_slug'];
			$uri = $base_uri . '/' . $tag;

			if ( $request_uri == $uri || $request_uri == $uri . '/' ) {
				$this->handle_gettrack();
				die();
			}

			return $wp;
		}

		/**
		 * A function to get the real request URI, stripped of all the basics. What
		 * this function returns is meant to be used to match against, for URIs
		 * that Trackserver must handle.
		 *
		 * The code is borrowed from WP's own parse_request() function.
		 *
		 * @since 4.4
		 */
		function get_request_uri() {
			$home_path       = trim( parse_url( home_url(), PHP_URL_PATH ), '/' ) . $this->url_prefix;
			$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );

			$pathinfo         = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo         = trim( $pathinfo, '/' );
			$pathinfo         = preg_replace( $home_path_regex, '', $pathinfo );
			$pathinfo         = trim( $pathinfo, '/' );

			list( $request_uri ) = explode( '?', $_SERVER['REQUEST_URI'] );
			$request_uri         = str_replace( $pathinfo, '', $request_uri );
			$request_uri         = trim( $request_uri, '/' );
			$request_uri         = preg_replace( $home_path_regex, '', $request_uri );
			$request_uri         = trim( $request_uri, '/' );

			// The requested permalink is in $pathinfo for path info requests and $request_uri for other requests.
			if ( ! empty( $pathinfo ) && ! preg_match( '|^.*' . preg_quote( $wp_rewrite->index, '|' ) . '$|', $pathinfo ) ) {
				$requested_path = $pathinfo;
			} else {
				// If the request uri is the index, blank it out so that we don't try to match it against a rule.
				if ( $request_uri == $wp_rewrite->index ) {
					$request_uri = '';
				}
				$requested_path = $request_uri;
			}
			return $requested_path;
		}

		/**
		 * Validate the credentials in a Trackme request aginast the user's key
		 *
		 * @since 1.0
		 */
		function validate_trackme_login( $username, $password ) {

			if ( $username == '' ) {
				$username = urldecode( $_GET['u'] );
				$password = urldecode( $_GET['p'] );
			}

			if ( $username == '' || $password == '' ) {
				$this->trackme_result( 3 );
			} else {
				$user = get_user_by( 'login', $username );

				if ( $user ) {
					$user_id = intval( $user->data->ID );
					$key     = get_user_meta( $user_id, 'ts_trackme_key', true );

					if ( $password == $key && user_can( $user_id, 'use_trackserver' ) ) {
						return $user_id;
					} else {
						$this->trackme_result( 1 );  // Password incorrect or insufficient permissions
					}
				} else {
					$this->trackme_result( 2 );  // User not found
				}
			}
		}

		/**
		 * Handle TrackMe GET requests. It validates the user and password and
		 * delegates the requested action to a dedicated function
		 */
		function handle_trackme_request( $username = '', $password = '' ) {
			require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
			require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

			// If this function returns, we're OK
			$user_id = $this->validate_trackme_login( $username, $password );

			// Delegate the action to another function
			switch ( $_GET['a'] ) {
				case 'upload':
					$this->handle_trackme_upload( $user_id );
					break;
				case 'gettriplist':
					$this->handle_trackme_gettriplist( $user_id );
					break;
				case 'gettripfull':
				case 'gettriphighlights':
					$this->handle_trackme_gettripfull( $user_id );
					break;
				case 'deletetrip':
					$this->handle_trackme_deletetrip( $user_id );
					break;
			}
		}

		/**
		 * Handle TrackMe export requests. Not currently implemented.
		 *
		 * @since 1.0
		 */
		function handle_trackme_export( $username = '', $password = '' ) {
			http_response_code( 501 );
			echo 'Export is not supported by the server.';
		}

		/**
		 * Handle TrackMe cloud requests. Not implemented with 'old style' URL.
		 *
		 * Cloud sharing functions do not send authentication data, only a unique
		 * ID ($_GET['id']) This makes it difficult to implement in Trackserver,
		 * because we cannot match the ID with a WordPress user. An option would
		 * be to (mis)use the displayname ($_GET['dn']) for authentication data.
		 * The 'show' action sends only the ID, so it needs to be stored on the
		 * server, for example in usermeta.
		 *
		 * This isn't very secure, because as far as I can tell, the ID is
		 * auto-generated on the client and cannot be changed, which makes it
		 * unsuitable for any role in authentication.  Altogether, this feels like
		 * a very ugly hack, so I am not going to implement it.
		 *
		 * Error messages are not localized on purpose.
		 *
		 * @since 4.1
		 */
		function handle_trackme_cloud_error() {

			switch ( $_GET['a'] ) {
				case 'update':
					$this->http_terminate( 501, 'For cloud sharing you need to update your server URL, see Trackserver FAQ.' );
					break;
				case 'show':
					$this->http_terminate( 501, 'For "Show Cloud People" you need to update your server URL, see Trackserver FAQ.' );
					break;
			}
			$this->http_terminate( 501, 'The action you requested is not supported by the server.' );
		}

		/**
		 * Handle TrackMe Cloud Sharing 'show' requests.
		 *
		 * This function shares functionality and some code with create_owntracks_response(),
		 * so maybe these functions should be merged into one. The query in this
		 * function only gets tracks that have been updated in the last 3660 seconds,
		 * the 'occurred' date is requested in regular date format and the output format is
		 * different, obviously.
		 *
		 * TrackMe will only display users that have sent an update in the last 60 minutes.
		 *
		 * @since 4.3
		 */
		function handle_trackme_cloud_show( $user_id ) {
			global $wpdb;

			$user      = get_user_by( 'id', $user_id );
			$user_ids  = $this->get_owntracks_friends( $user );
			$track_ids = $this->get_live_tracks( $user_ids, 3660 );
			$message   = '';

			foreach ( $track_ids as $track_id ) {
				// @codingStandardsIgnoreStart
				$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' .
					$this->tbl_locations . ' l INNER JOIN ' . $this->tbl_tracks .
					' t ON l.trip_id = t.id WHERE trip_id=%d AND l.hidden = 0 ORDER BY occurred DESC LIMIT 0,1', $track_id
				);
				$res = $wpdb->get_row( $sql, ARRAY_A );
				// @codingStandardsIgnoreEnd

				$ruser    = get_user_by( 'id', $res['user_id'] );
				$deviceid = substr( md5( $res['user_id'] ), -15 );
				// output.=$row['id']."|".$row['latitude']."|".$row['longitude']."|".$row['dateoccurred']."|".$row['accuracy']."|".$row['distance']."|".$row['displayname']."|".$row['public']."\n";
				$message .= "$deviceid|" . $res['latitude'] . '|' . $res['longitude'] . '|' . $res['occurred'] . '|0|0|' . $ruser->display_name . "|1\n";
			}

			$this->trackme_result( 0, $message );  // This will not return
		}

		/**
		 * Handle TrackMe cloud requests with new URL style, containing username and secret.
		 *
		 * @since 4.3
		 */
		function handle_trackme_cloud( $username, $password ) {

			// If this function returns, we're OK
			$user_id = $this->validate_trackme_login( $username, $password );

			// Delegate the action to another function
			switch ( $_GET['a'] ) {

				// For 'update', we use the same method as the regular 'upload'
				// request, but we have to pass it a generated trip name.  We use a
				// 'strftime' format, like with OsmAnd. Hardcoded for now (see
				// $trip_name_format below), could become an option later.

				case 'update':
					$occurred = urldecode( $_GET['do'] );
					if ( ! $this->validate_timestamp( $occurred ) ) {
						$occurred = current_time( 'Y-m-d H:i:s' );
					}
					$ts               = strtotime( $occurred );  // Is this reliable?
					$trip_name_format = 'TrackMe Cloud %F';
					$trip_name        = strftime( $trip_name_format, $ts );

					$this->handle_trackme_upload( $user_id, $trip_name );
					break;
				case 'show':
					$this->handle_trackme_cloud_show( $user_id );
					break;
			}
		}

		/**
		 * Handle Mapmytracks requests.
		 *
		 * @since 1.0
		 */
		function handle_mapmytracks_request() {

			// Validate with '$return = true' so we can handle the auth failure.
			$user_id = $this->validate_http_basicauth( true );

			if ( $user_id === false ) {
				return $this->mapmytracks_invalid_auth();
			}

			switch ( $_POST['request'] ) {
				case 'start_activity':
					$this->handle_mapmytracks_start_activity( $user_id );
					break;
				case 'update_activity':
					$this->handle_mapmytracks_update_activity( $user_id );
					break;
				case 'stop_activity':
					$this->handle_mapmytracks_stop_activity( $user_id );
					break;
				case 'upload_activity':
					$this->handle_mapmytracks_upload_activity( $user_id );
					break;
				case 'get_activities':
					break;
				case 'get_activity':
					break;
				default:
					http_response_code( 501 );
					echo 'Illegal request.';
			}
		}

		/**
		 * Handle the 'upload' action from a TrackMe GET request. It tries to get the trip ID
		 * for the specified trip name, and if that is not found, it creates a new trip. When a minimal
		 * set of parameters is present, it inserts the location into the database.
		 *
		 * Sample request:
		 * /wp/trackme/requests.z?a=upload&u=martijn&p=xxx&lat=51.44820629&long=5.47286778&do=2015-01-03%2022:22:15&db=8&tn=Auto_2015.01.03_10.22.06&sp=0.000&alt=55.000
		 * /wp/trackme/user/pass/cloud.z?a=update&dn=&id=b58a2ee83df23c6c&lat=56.02039449483999&long=9.892328306551947&do=2019-08-13%2021:29:20&pub=1&db=8&acc=6.0&ang=131.72496&sp=0.0015707234&alt=63.35264873008489
		 *
		 * @since 1.0
		 */
		function handle_trackme_upload( $user_id, $trip_name = '' ) {
			if ( $trip_name === '' ) {
				$trip_name = urldecode( $_GET['tn'] );
			}
			$occurred = urldecode( $_GET['do'] );

			if ( ! empty( $trip_name ) ) {
				$track   = new Trackserver_Track( $this, $trip_name, $user_id, 'name' );
				$trip_id = $track->id;

				if ( is_null( $trip_id ) ) {

					$track->set( 'name', $trip_name );
					$track->set( 'source', 'TrackMe' );

					$trip_id = $track->save();
					if ( empty( $trip_id ) ) {
						$this->trackme_result( 6 ); // Unable to create trip
					}
				}

				if ( intval( $track->id ) > 0 ) {

					if ( ! ( empty( $_GET['lat'] ) || empty( $_GET['long'] ) ) and $this->validate_timestamp( $occurred ) ) {
						$loc = new Trackserver_Location( $this, $track->id, $user_id );
						$loc->set( 'latitude', $_GET['lat'] );
						$loc->set( 'longitude', $_GET['long'] );
						$loc->set( 'occurred', $occurred );

						if ( ! empty( $_GET['alt'] ) ) {
							$loc->set( 'altitude', $_GET['alt'] );
						}
						if ( ! empty( $_GET['sp'] ) ) {
							$loc->set( 'speed', $_GET['sp'] );
						}
						if ( ! empty( $_GET['ang'] ) ) {
							$loc->set( 'heading', $_GET['ang'] );
						}
						if ( $loc->save() ) {
							$this->calculate_distance( $track->id );
							$this->trackme_result( 0 );
						}
					}
				}
				$this->trackme_result( 7, 'Server error' );
			} else {
				$this->trackme_result( 6 ); // No trip name specified. This should not happen.
			}
		}

		/**
		 * Handle the 'gettriplist' action from a TrackMe GET request. It prints a list of all trips
		 * currently in the database, containing name and creation timestamp
		 *
		 * @since 1.0
		 */
		function handle_trackme_gettriplist( $user_id ) {
			global $wpdb;

			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT name,created FROM ' . $this->tbl_tracks . ' WHERE user_id=%d ORDER BY created DESC LIMIT 0,25', $user_id );
			$trips = $wpdb->get_results( $sql, ARRAY_A );
			// @codingStandardsIgnoreEnd
			$triplist = '';
			foreach ( $trips as $row ) {
				$triplist .= htmlspecialchars( $row['name'] ) . '|' . htmlspecialchars( $row['created'] ) . "\n";
			}
			$triplist = substr( $triplist, 0, -1 );
			$this->trackme_result( 0, $triplist );
		}

		/**
		 * Function to handle the 'gettripfull' action from a TrackMe GET request.
		 *
		 * @since 1.7
		 */
		function handle_trackme_gettripfull( $user_id ) {
			$trip_name = urldecode( $_GET['tn'] );
			if ( ! empty( $trip_name ) ) {

				$track = new Trackserver_Track( $this, $trip_name, $user_id, 'name' );
				$trip_id = $track->id;

				if ( is_null( $trip_id ) ) {
					$this->trackme_result( 7 );   // Trip not found
				} else {
					$res    = $track->get_trackdata();
					$output = '';

					foreach ( $res as $row ) {
						$output .= $row['latitude'] . '|' .
							$row['longitude'] . '|' .
							'|' . // ImageURL
							$row['comment'] . '|' .
							'|' . // IconURL
							$row['occurred'] . '|' .
							$row['id'] . '|' .
							$row['altitude'] . '|' .
							$row['speed'] . '|' .
							$row['heading'] . '|' .
							"\n";
					}
					$this->trackme_result( 0, $output );
				}
			} else {
				$this->trackme_result( 6 ); // No trip name specified. This should not happen.
			}
		}

		/**
		 * Handle the 'deletetrip' action from a TrackMe GET request. If a trip ID can be found from the
		 * supplied name, all locations and the trip record for the ID are deleted from the database.
		 *
		 * @since 1.0
		 * @since 4.4 Use Trackserver_Track class
		 */
		function handle_trackme_deletetrip( $user_id ) {
			$trip_name = urldecode( $_GET['tn'] );
			if ( ! empty( $trip_name ) ) {
				$track   = new Trackserver_Track( $this, $trip_name, $user_id, 'name' );
				$trip_id = $track->id;
				if ( is_null( $trip_id ) ) {
					$this->trackme_result( 7 );   // Trip not found
				} else {
					$track->delete();
					$this->trackme_result( 0 );   // Trip deleted
				}
			} else {
				$this->trackme_result( 6 ); // No trip name specified. This should not happen.
			}
		}

		/**
		 * Print a result for the TrackMe client. It prints a result code and optionally a message.
		 *
		 * @since 1.0
		 */
		function trackme_result( $rc, $message = false ) {
			echo "Result:$rc";
			if ( $message ) {
				echo "|$message";
			}
			die();
		}

		/**
		 * Terminate the current script, sending a HTTP status code and
		 * a message. To be used for protocols that do not require a specific
		 * response, like OsmAnd and SendLocation, but unlike Trackme, for example.
		 *
		 * @since 2.0
		 */
		function http_terminate( $http = '403', $message = 'Access denied' ) {
			http_response_code( $http );
			header( 'Content-Type: text/plain' );
			echo $message . "\n";
			die();
		}

		/**
		 * Validate credentials for OsmAnd and SendLocation. It checks the
		 * WordPress username and the access key from the user profile against the values
		 * specified in the request (OsmAnd) or given in the function parameters (SendLocation).
		 *
		 * @since 2.0
		 */
		function validate_user_meta_key( $username = false, $key = false, $meta_key = 'ts_osmand_key' ) {

			if ( ! $username ) {
				$username = urldecode( $_GET['username'] );
				$key      = urldecode( $_GET['key'] );
			}

			if ( $username == '' ) {
				$this->http_terminate();
			}

			$user = get_user_by( 'login', $username );
			if ( $user ) {
				$user_id  = intval( $user->data->ID );
				$user_key = get_user_meta( $user_id, $meta_key, true );

				if ( $key != $user_key ) {
					$this->http_terminate();
				}

				if ( user_can( $user_id, 'use_trackserver' ) ) {
					return $user_id;
				}
			}
			$this->http_terminate();
		}

		/**
		 * Get a track ID from the database given its name and a user ID
		 *
		 * @since 2.0
		 */
		function get_track_by_name( $user_id, $trackname ) {
			global $wpdb;
			$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->tbl_tracks . ' WHERE user_id=%d AND name=%s', $user_id, $trackname ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		/**
		 * Handle OsmAnd request
		 *
		 * Sample request:
		 * /wp/osmand/?lat=51.448334&lon=5.4725113&timestamp=1425238292902&hdop=11.0&altitude=80.0&speed=0.0
		 *
		 * @since 1.4
		 */
		function handle_osmand_request() {
			require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
			require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

			// Try HTTP Basic auth first. This can return a user ID, or NULL.
			$user_id = $this->try_http_basicauth();

			if ( is_null( $user_id ) ) {
				// If this function returns, we're OK
				$user_id = $this->validate_user_meta_key();
			}

			// Timestamp is sent in milliseconds, and in UTC. Use substr() to truncate the timestamp,
			// because dividing by 1000 causes an integer overflow on 32-bit systems.
			$timestamp = urldecode( $_GET['timestamp'] );
			if ( strlen( $timestamp ) > 10 ) {
				$timestamp = substr( $timestamp, 0, -3 );
			}
			$ts = intval( $timestamp );

			// If no timestamp is given, we generate one.
			if ( $ts <= 0 ) {
				$ts = time();
			}

			if ( $ts > 0 ) {

				$ts      += $this->utc_to_local_offset( $ts );
				$occurred = date( 'Y-m-d H:i:s', $ts );

				// Get track name from strftime format string
				$trackname = strftime( $this->options['osmand_trackname_format'], $ts );

				$source = ( isset( $_GET['source'] ) ? urldecode( $_GET['source'] ) : 'OsmAnd' );

				if ( ! empty( $trackname ) ) {
					$track    = new Trackserver_Track( $this, $trackname, $user_id, 'name' );

					if ( is_null( $track->id ) ) {
						$track->set( 'name', $trackname );
						$track->set( 'source', $source );

						$track->save();
						if ( empty( $track->id ) ) {
							$this->http_terminate( 501, 'Database error' );
						}
					}

					if ( ! ( empty( $_GET['lat'] ) || empty( $_GET['lon'] ) ) ) {
						$loc = new Trackserver_Location( $this, $track->id, $user_id );
						$loc->set( 'latitude', $_GET['lat'] );
						$loc->set( 'longitude', $_GET['lon'] );
						$loc->set( 'occurred', $occurred );

						if ( ! empty( $_GET['altitude'] ) ) {
							$loc->set( 'altitude', urldecode( $_GET['altitude'] ) );
						}
						if ( ! empty( $_GET['speed'] ) ) {
							$loc->set( 'speed', urldecode( $_GET['speed'] ) );
						}
						if ( ! empty( $_GET['bearing'] ) ) {
							$loc->set( 'heading', urldecode( $_GET['bearing'] ) );
						}
						if ( $loc->save() ) {
							$this->calculate_distance( $track->id );
							$this->http_terminate( 200, "OK, track ID = $track->id, timestamp = $occurred" );
						} else {
							$this->http_terminate( 501, 'Database error' );
						}
					}
				}
			}
			$this->http_terminate( 400, 'Bad request' );
		}

		/**
		 * Handle SendLocation request
		 *
		 * @since 2.0
		 */
		function handle_sendlocation_request( $username, $key ) {
			global $wpdb;

			// If this function returns, we're OK. We use the same function as OsmAnd.
			$user_id = $this->validate_user_meta_key( $username, $key, 'ts_sendlocation_key' );

			// SendLocation doesn't send a timestamp
			$ts       = current_time( 'timestamp' );
			$occurred = date( 'Y-m-d H:i:s', $ts );

			// Get track name from strftime format string
			$trackname = strftime( $this->options['sendlocation_trackname_format'], $ts );

			if ( $trackname != '' ) {
				$track_id = $this->get_track_by_name( $user_id, $trackname );

				if ( $track_id == null ) {
					$data   = array(
						'user_id' => $user_id,
						'name'    => $trackname,
						'created' => $occurred,
						'source'  => 'SendLocation',
					);
					$format = array( '%d', '%s', '%s', '%s' );

					if ( $wpdb->insert( $this->tbl_tracks, $data, $format ) ) {
						$track_id = $wpdb->insert_id;
					} else {
						$this->http_terminate( 501, 'Database error' );
					}
				}

				// SendLocation sometimes uses commas as decimal separators (issue #12)
				$latitude  = str_replace( ',', '.', $_GET['lat'] );
				$longitude = str_replace( ',', '.', $_GET['lon'] );
				$altitude  = str_replace( ',', '.', urldecode( $_GET['altitude'] ) );
				$speed     = str_replace( ',', '.', urldecode( $_GET['speed'] ) );
				$heading   = str_replace( ',', '.', urldecode( $_GET['heading'] ) );
				$now       = $occurred;

				if ( $latitude != '' && $longitude != '' ) {
					$data   = array(
						'trip_id'   => $track_id,
						'latitude'  => $latitude,
						'longitude' => $longitude,
						'created'   => $now,
						'occurred'  => $occurred,
					);
					$format = array( '%d', '%s', '%s', '%s', '%s' );

					if ( $altitude != '' ) {
						$data['altitude'] = $altitude;
						$format[]         = '%s';
					}
					if ( $speed != '' ) {
						$data['speed'] = $speed;
						$format[]      = '%s';
					}
					if ( $heading != '' ) {
						$data['heading'] = $heading;
						$format[]        = '%s';
					}

					$fenced = $this->is_geofenced( $user_id, $data );
					if ( $fenced == 'discard' ) {
						$this->http_terminate( 200, "OK, track ID = $track_id, timestamp = $occurred" );  // This will not return
					}
					$data['hidden'] = ( $fenced == 'hide' ? 1 : 0 );
					$format[]       = '%d';

					if ( $wpdb->insert( $this->tbl_locations, $data, $format ) ) {
						$this->calculate_distance( $track_id );
						$this->http_terminate( 200, "OK, track ID = $track_id, timestamp = $occurred" );
					} else {
						$this->http_terminate( 500, $wpdb->last_error );
					}
				}
			}

			$this->http_terminate( 400, 'Bad request' );
		}

		/**
		 * Handle a request from OwnTracks
		 *
		 * After validating the HTTP basic authentication, a track name is
		 * constructed from the strftime() format string in the settings and the
		 * track is created if it doesn't exist. The JSON payload from OwnTracks is
		 * parsed and the location data is stored. A response containing Locations
		 * and Cards for Friends is returned to the client.
		 *
		 * @since 4.0
		 */
		function handle_owntracks_request() {
			global $wpdb;

			$user_id = $this->validate_http_basicauth();
			$payload = file_get_contents( 'php://input' );
			// @codingStandardsIgnoreLine
			$json = @json_decode( $payload, true );

			//Array
			//(
			//    [_type] => location
			//    [tid] => te
			//    [acc] => 21
			//    [batt] => 24
			//    [conn] => w
			//    [lat] => 51.448285
			//    [lon] => 5.4727492
			//    [t] => u
			//    [tst] => 1505766127
			//)

			//Array
			//(
			//    [_type] => waypoint
			//    [desc] => Thuis
			//    [lat] => 51.4481325
			//    [lon] => 5.47281640625
			//    [tst] => 1505766855
			//)

			if ( isset( $json['_type'] ) && $json['_type'] == 'location' ) {

				// Largely copied from SendLocation handling
				$ts       = $json['tst'];
				$offset   = $this->utc_to_local_offset( $ts );
				$ts      += $offset;
				$occurred = date( 'Y-m-d H:i:s', $ts );

				// Get track name from strftime format string
				$trackname = strftime( $this->options['owntracks_trackname_format'], $ts );

				if ( $trackname != '' ) {
					$track_id = $this->get_track_by_name( $user_id, $trackname );

					if ( $track_id == null ) {
						$data   = array(
							'user_id' => $user_id,
							'name'    => $trackname,
							'created' => $occurred,
							'source'  => 'OwnTracks',
						);
						$format = array( '%d', '%s', '%s', '%s' );

						if ( $wpdb->insert( $this->tbl_tracks, $data, $format ) ) {
							$track_id = $wpdb->insert_id;
						} else {
							$this->http_terminate( 501, 'Database error' );
						}
					}

					$latitude  = $json['lat'];
					$longitude = $json['lon'];
					$now       = $occurred;

					if ( $latitude != '' && $longitude != '' ) {
						$data   = array(
							'trip_id'   => $track_id,
							'latitude'  => $latitude,
							'longitude' => $longitude,
							'created'   => $now,
							'occurred'  => $occurred,
						);
						$format = array( '%d', '%s', '%s', '%s', '%s' );

						if ( $json['alt'] != '' ) {
							$data['altitude'] = $json['alt'];
							$format[]         = '%s';
						}

						$fenced = $this->is_geofenced( $user_id, $data );
						if ( $fenced == 'discard' ) {
							$this->http_terminate( 200, '[]' );  // This will not return
						}
						$data['hidden'] = ( $fenced == 'hide' ? 1 : 0 );
						$format[]       = '%d';

						if ( $wpdb->insert( $this->tbl_locations, $data, $format ) ) {
							$this->calculate_distance( $track_id );
						} else {
							$this->http_terminate( 501, 'Database error' );
						}
					}
				}
			}
			$response = $this->create_owntracks_response( $user_id );
			$this->http_terminate( 200, $response );
		}

		/**
		 * Return a 2 character string, to be used as the Tracker ID (TID) in OwnTracks.
		 *
		 * Depending on what data is available, we use the user's first name, last name
		 * or login name to create the TID.
		 *
		 * @since 4.1
		 */
		function get_owntracks_tid( $user ) {
			if ( $user->first_name && $user->last_name ) {
				$tid = $user->first_name[0] . $user->last_name[0];
			} elseif ( $user->first_name ) {
				$tid = substr( $user->first_name, 0, 2 );
			} elseif ( $user->last_name ) {
				$tid = substr( $user->last_name, 0, 2 );
			} else {
				$tid = substr( $user->user_login, 0, 2 );
			}
			return $tid;
		}

		/**
		 * Return a list of user IDs that share their OwnTracks location with us.
		 *
		 * The function does a query directly on the 'usermeta' table, which is ugly.
		 * We want to match a given username against a comma-separated list of usernames.
		 * Unfortunately, using WP_User_Query is not feasable, because the 'LIKE' comparator
		 * doesn't allow us to match the beginning or the end of the meta data. If one
		 * username is a substring of another, we're in trouble: if the given username is
		 * 'john', and the list ends with ',johnpetersen', there is no way to avoid a match.
		 * Also, using the 'REGEXP' comparator would force us to construct a regexp
		 * containing a user name, which seems hard to properly quote.
		 *
		 * Instead, we do it like it's done in update_meta_cache()
		 * https://core.trac.wordpress.org/browser/tags/4.9.2/src/wp-includes/meta.php#L787
		 *
		 * The ID of the requesting user is always included.
		 *
		 * @since 4.1
		 */
		function get_owntracks_users_sharing( $user ) {
			global  $wpdb;

			$username = $user->user_login;
			$table    = _get_meta_table( 'user' );
			$sql      = $wpdb->prepare(
				// @codingStandardsIgnoreLine
				"SELECT user_id, meta_key, meta_value FROM $table WHERE meta_key='ts_owntracks_share' AND (" .
					'meta_value=%s OR meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s)',
				$username,
				'%,' . $wpdb->esc_like( $username ),
				'%,' . $wpdb->esc_like( $username ) . ',%',
				$wpdb->esc_like( $username ) . ',%'
			);
			// @codingStandardsIgnoreLine
			$res = $wpdb->get_results( $sql, ARRAY_A );
			$user_ids = array( $user->ID );
			foreach ( $res as $row ) {
				$user_ids[] = (int) $row['user_id'];
			}
			return array_unique( $user_ids );
		}

		/**
		 * Return an array of user IDs who are our OwnTracks Friends.
		 *
		 * The list of users sharing their location with us is filtered for the
		 * users we are following. An empty list means we follow all users. If
		 * there are positive usernames in the list, we intersect the list of
		 * sharing users with the list of users we follow.  If there are negative
		 * usernames in the list, they are removed from the final list.
		 *
		 * @since 4.1
		 */
		function get_owntracks_friends( $user ) {
			$friends   = $this->get_owntracks_users_sharing( $user );
			$following = get_user_meta( $user->ID, 'ts_owntracks_follow', true );
			if ( empty( $following ) ) {
				return $friends;
			}
			$following   = array_unique( array_map( 'trim', explode( ',', $following ) ) );  // convert to array and trim whitespace
			$do_follow   = array( $user->ID );
			$dont_follow = array();

			foreach ( $following as $f ) {
				$ff = ( $f[0] == '!' ? substr( $f, 1 ) : $f );
				$u  = get_user_by( 'login', $ff );
				if ( ! $u ) {   // username not found
					continue;
				}
				if ( $f[0] == '!' ) {
					$dont_follow[] = $u->ID;
				} else {
					$do_follow[] = $u->ID;
				}
			}

			// our own user ID is always in the list
			if ( count( $do_follow ) > 1 ) {
				$friends = array_intersect( $friends, $do_follow );
			}
			if ( ! empty( $dont_follow ) ) {
				$friends = array_diff( $friends, $dont_follow );
			}
			return array_values( $friends );   // strip explicit keys
		}

		function get_owntracks_avatar( $user_id ) {
			// TODO: cache the image in usermeta and serve it from there if available

			$avatar_data = get_avatar_data( $user_id, array( 'size' => 40 ) );
			$url         = $avatar_data['url'] . '&d=404';  // ask for a 404 if there is no image

			$options  = array(
				'httpversion' => '1.1',
				'user-agent'  => 'WordPress/Trackserver ' . TRACKSERVER_VERSION . '; https://github.com/tinuzz/wp-plugin-trackserver',
			);
			$response = wp_remote_get( $url, $options );
			if ( is_array( $response ) ) {
				$rc = (int) wp_remote_retrieve_response_code( $response );
				if ( $rc == 200 ) {
					return base64_encode( wp_remote_retrieve_body( $response ) );
				}
			}
			return false;
		}

		/**
		 * Create a response for the OwnTracks request.
		 *
		 * For all of our Friends (users that share with us, minus the users that
		 * we do not follow), construct a location object and a card object, put it
		 * all in a list and return the result as JSON.
		 *
		 * @since 4.1
		 */
		function create_owntracks_response( $author_id ) {
			global $wpdb;

			$user      = get_user_by( 'id', $author_id );
			$user_ids  = $this->get_owntracks_friends( $user );
			$track_ids = $this->get_live_tracks( $user_ids );
			$objects   = array();

			foreach ( $track_ids as $track_id ) {
				// @codingStandardsIgnoreStart
				$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, UNIX_TIMESTAMP(occurred) AS tst, t.user_id, t.name, t.distance, t.comment FROM ' .
					$this->tbl_locations . ' l INNER JOIN ' . $this->tbl_tracks .
					' t ON l.trip_id = t.id WHERE trip_id=%d AND l.hidden = 0 ORDER BY occurred DESC LIMIT 0,1', $track_id
				);
				$res = $wpdb->get_row( $sql, ARRAY_A );
				// @codingStandardsIgnoreEnd

				$ruser = get_user_by( 'id', $res['user_id'] );
				$tid   = $this->get_owntracks_tid( $ruser );

				$objects[] = array(
					'_type' => 'location',
					'lat'   => $res['latitude'],
					'lon'   => $res['longitude'],
					'tid'   => $tid,
					'tst'   => $res['tst'],
					'topic' => 'owntracks/' . $res['user_id'] . '/mobile',
				);

				$card = array(
					'_type' => 'card',
					'name'  => $ruser->display_name,
					'tid'   => $tid,
				);

				$face = $this->get_owntracks_avatar( $ruser->ID );
				if ( $face ) {
					$card['face'] = $face;
				}

				$objects[] = $card;
			}
			return json_encode( $objects );
		}

		/**
		 * Validate a timestamp supplied by a client.
		 *
		 * It checks if the timestamp is in the required format and if the
		 * timestamp is unchanged after parsing.
		 *
		 * @since 1.0
		 */
		function validate_timestamp( $ts ) {
			$d = DateTime::createFromFormat( 'Y-m-d H:i:s', $ts );
			return $d && ( $d->format( 'Y-m-d H:i:s' ) == $ts );
		}

		/**
		 * Validate WordPress credentials for basic HTTP authentication.
		 *
		 * If no crededtials are received, we send a 401 status code. If the
		 * username or password are incorrect, we terminate (default) or return
		 * false if so requested.
		 *
		 * On success, the authenticated user's ID is returned. By passing 'object'
		 * as the second argument, the WP_User object is returned instead.
		 *
		 * @since 1.0
		 */
		function validate_http_basicauth( $return = false, $what = 'id' ) {

			if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				header( 'WWW-Authenticate: Basic realm="Authentication Required"' );
				header( 'HTTP/1.0 401 Unauthorized' );
				die( "Authentication required\n" );
			}

			$user = get_user_by( 'login', $_SERVER['PHP_AUTH_USER'] );

			if ( $user ) {
				$hash    = $user->data->user_pass;
				$user_id = intval( $user->ID );

				if ( wp_check_password( $_SERVER['PHP_AUTH_PW'], $hash, $user_id ) ) {
					if ( user_can( $user_id, 'use_trackserver' ) ) {
						return ( $what == 'object' ? $user : $user_id );
					} else {
						if ( $return ) {
							return false;
						}
						$this->http_terminate( '403', 'Insufficient permissions' );
					}
				}
			}
			if ( $return ) {
				return false;
			}
			$this->http_terminate( '403', 'Username or password incorrect' );
		}

		/**
		 * Validate WordPress credentials.
		 *
		 * With valid credentials, this function returns the authenticated user's
		 * ID by default, or a WP_User object on request (3rd argument). Otherwise,
		 * it returns false.
		 *
		 * @since 4.4
		 */
		function validate_wp_user_pass( $username = '', $password = '', $what = 'id' ) {

			if ( $username == '' || $password == '' ) {
				return false;
			}

			$user = get_user_by( 'login', $username );

			if ( $user ) {
				$hash    = $user->data->user_pass;
				$user_id = intval( $user->ID );

				if ( wp_check_password( $password, $hash, $user_id ) ) {
					if ( user_can( $user_id, 'use_trackserver' ) ) {
						return ( $what == 'object' ? $user : $user_id );
					} else {
						return false;
					}
				}
			}
			return false;
		}

		/**
		 * Validate HTTP basic authentication, only if a username and password were sent in the request.
		 *
		 * If no username is found, return NULL.
		 *
		 * This function is meant to be used, where HTTP basic auth is optional,
		 * and authentication will be handled by another mechanism if it is not
		 * used. The actual validation is delegated to validate_http_basicauth().
		 *
		 * By default, invalid credentials will terminate the request.
		 *
		 * @since 4.3
		 */
		function try_http_basicauth( $return = false, $what = 'id' ) {
			if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
				return $this->validate_http_basicauth( $return, $what );
			}
			return null;
		}

		/**
		 * Handle the 'start_activity' request for the MapMyTracks protocol.
		 *
		 * If no title / trip name is received, nothing is done. Received points
		 * are validated. Trip is inserted with the first point's timstamp as start
		 * time, or the current time if no valid points are received. Valid points
		 * are inserted and and the new trip ID is returned in an XML message.
		 *
		 * @since 1.0
		 */
		function handle_mapmytracks_start_activity( $user_id ) {
			global $wpdb;

			$trip_name = $_POST['title'];
			if ( $trip_name != '' ) {
				$points = $this->mapmytracks_parse_points( $_POST['points'] );

				if ( $points ) {
					$ts = $points[0]['timestamp'];
					// Stolen from 'current_time' in wp-include/functions.php
					$occurred = date( 'Y-m-d H:i:s', $ts + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
				} else {
					$occurred = current_time( 'Y-m-d H:i:s' );
				}

				$source = $this->mapmytracks_get_source();
				$data   = array(
					'user_id' => $user_id,
					'name'    => $trip_name,
					'created' => $occurred,
					'source'  => $source,
				);
				$format = array( '%d', '%s', '%s', '%s' );

				if ( $wpdb->insert( $this->tbl_tracks, $data, $format ) ) {
					$trip_id = $wpdb->insert_id;
					if ( $this->mapmytracks_insert_points( $points, $trip_id, $user_id ) ) {
						$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
						$xml->addChild( 'type', 'activity_started' );
						$xml->addChild( 'activity_id', (string) $trip_id );
						echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
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

			$sql     = $wpdb->prepare( 'SELECT id, user_id FROM ' . $this->tbl_tracks . ' WHERE id=%d', $_POST['activity_id'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$trip_id = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $trip_id ) {
				// Get the user ID for the trip from the cached result of previous query
				$trip_owner = $wpdb->get_var( null, 1 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				// Check if the current login is actually the owner of the trip
				if ( $trip_owner == $user_id ) {
					$points = $this->mapmytracks_parse_points( $_POST['points'] );
					if ( $this->mapmytracks_insert_points( $points, $trip_id, $user_id ) ) {
						$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
						$xml->addChild( 'type', 'activity_updated' );
						//echo $xml->asXML();
						echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
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
			$xml->addChild( 'type', 'activity_stopped' );
			echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
		}

		/**
		 * Function to send an XML message to the client in case of failed authentication or authorization.
		 */
		function mapmytracks_invalid_auth() {
			$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
			$xml->addChild( 'type', 'error' );
			$xml->addChild( 'reason', 'unauthorised' );
			echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
		}

		/**
		 * Function to handle 'upload_activity' request for the MapMyTracks protocol. It validates
		 * and processes the input as GPX data, and returns an appropriate XML message.
		 */
		function handle_mapmytracks_upload_activity( $user_id ) {
			global $wpdb;

			$_POST = stripslashes_deep( $_POST );
			if ( isset( $_POST['gpx_file'] ) ) {
				$xml = $this->validate_gpx_string( $_POST['gpx_file'] );
				if ( $xml ) {
					$result = $this->process_gpx( $xml, $user_id );

					// If a description was given, put it in the comment field.
					if ( isset( $_POST['description'] ) ) {
						$track_ids = $result['track_ids'];
						if ( count( $track_ids ) > 0 ) {
							$in  = '(' . implode( ',', $track_ids ) . ')';
							$sql = $wpdb->prepare( 'UPDATE ' . $this->tbl_tracks . " SET comment=%s WHERE user_id=%d AND id IN $in", $_POST['description'], $user_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						}
					}

					// Output a success message
					$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
					$xml->addChild( 'type', 'success' );
					echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
				}
			}
		}

		function mapmytracks_parse_points( $points ) {

			// Check the points syntax. It should match groups of four items, each containing only
			// numbers, some also dots and dashes
			$pattern = '/^(-?[\d.]+ -?[\d.]+ -?[\d.]+ [\d]+ ?)*$/';
			$n       = preg_match( $pattern, $points );

			if ( $n == 1 ) {
				$parsed = array();
				$all    = explode( ' ', $points );
				for ( $i = 0; $i < count( $all ); $i += 4 ) {
					if ( $all[ $i ] ) {

						$parsed[] = array(
							'latitude'  => $all[ $i ],
							'longitude' => $all[ $i + 1 ],
							'altitude'  => $all[ $i + 2 ],
							'timestamp' => $all[ $i + 3 ],
						);
					}
				}
				return $parsed;
			} else {
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

			$localtz = get_option( 'timezone_string' );
			if ( ! $localtz ) {
				$localtz = ini_get( 'date.timezone' );
				if ( ! $localtz ) {
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

		function mapmytracks_insert_points( $points, $trip_id, $user_id ) {
			global $wpdb;

			if ( $points ) {
				$now     = current_time( 'Y-m-d H:i:s' );
				$offset  = $this->utc_to_local_offset( $points[0]['timestamp'] );
				$sqldata = array();

				foreach ( $points as $p ) {
					$ts       = $p['timestamp'] + $offset;
					$occurred = date( 'Y-m-d H:i:s', $ts );
					$fenced   = $this->is_geofenced( $user_id, $p );

					if ( $fenced == 'discard' ) {
						continue;
					}
					$hidden    = ( $fenced == 'hide' ? 1 : 0 );
					$sqldata[] = $wpdb->prepare( '(%d, %s, %s, %s, %s, %s, %d)', $trip_id, $p['latitude'], $p['longitude'], $p['altitude'], $now, $occurred, $hidden );
				}

				// Let's see how many rows we can put in a single MySQL INSERT query.
				// A row is roughly 86 bytes, so lets's use 100 to be on the safe side.
				$sql       = "SHOW VARIABLES LIKE 'max_allowed_packet'";  // returns 2 columns, we need the second
				$max_bytes = intval( $wpdb->get_var( $sql, 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( $max_bytes ) {
					$max_rows = (int) ( $max_bytes / 100 );
				} else {
					$max_rows = 10000;   // max_allowed_packet is 1MB by default
				}
				$sqldata = array_chunk( $sqldata, $max_rows );

				// Insert the data into the databae in chunks of $max_rows.
				// If insertion fails, return false immediately
				foreach ( $sqldata as $chunk ) {
					$sql  = 'INSERT INTO ' . $this->tbl_locations . ' (trip_id, latitude, longitude, altitude, created, occurred, hidden) VALUES ';
					$sql .= implode( ',', $chunk );
					if ( $wpdb->query( $sql ) === false ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						return false;
					}
				}

				// Update the track's distance in the database
				$this->calculate_distance( $trip_id );
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
		 * Function to check whether a given location is geofenced for the given user ID
		 *
		 * @since 3.1
		 */
		function is_geofenced( $user_id, $data ) {
			$geofences = get_user_meta( $user_id, 'ts_geofences', true );
			if ( ! is_array( $geofences ) ) {
				return false;
			}
			foreach ( $geofences as $i => $fence ) {
				if ( $fence['radius'] > 0 ) {
					$lat1     = (float) $fence['lat'];
					$lon1     = (float) $fence['lon'];
					$lat2     = (float) $data['latitude'];
					$lon2     = (float) $data['longitude'];
					$distance = $this->distance( $lat1, $lon1, $lat2, $lon2 );
					if ( $distance <= $fence['radius'] ) {
						return $fence['action'];
					}
				}
			}
			return false;
		}

		/**
		 * Function to convert a human-readable size like '20M' to bytes
		 *
		 * @since 1.9
		 */
		function size_to_bytes( $val ) {
			$last = strtolower( $val[ strlen( $val ) - 1 ] );
			switch ( $last ) {
				case 'g':
					$val *= 1024;
					// Fall through
				case 'm':
					$val *= 1024;
					// Fall through
				case 'k':
					$val *= 1024;
			}
			return $val;
		}

		/**
		 * Function to convert bytes to a human-readable size like '20MB'
		 *
		 * @since 1.9
		 */
		function bytes_to_human( $size ) {
			if ( ( $size >= 1 << 30 ) ) {
				return number_format( $size / ( 1 << 30 ), 1 ) . 'GB';
			}
			if ( ( $size >= 1 << 20 ) ) {
				return number_format( $size / ( 1 << 20 ), 1 ) . 'MB';
			}
			if ( ( $size >= 1 << 10 ) ) {
				return number_format( $size / ( 1 << 10 ), 1 ) . 'KB';
			}
			return number_format( $size ) . ' bytes';
		}

		/**
		 * Function to rearrange the $_FILES array. It handles multiple postvars
		 * and it works with both single and multiple files in a single postvar.
		 */
		function rearrange( $files ) {
			$j   = 0;
			$new = array();
			foreach ( $files as $postvar => $arr ) {
				foreach ( $arr as $key => $list ) {
					if ( is_array( $list ) ) {               // name="userfile[]"
						foreach ( $list as $i => $val ) {
								$new[ $j + $i ][ $key ] = $val;
						}
					} else {                                   // name="userfile"
						$new[ $j ][ $key ] = $list;
					}
				}
				$j += $i + 1;
			}
			return $new;
		}

		function validate_gpx_file( $filename ) {
			$xml = new DOMDocument();
			$xml->load( $filename );
			return $this->validate_gpx_data( $xml );
		}

		function validate_gpx_string( $data ) {
			$xml = new DOMDocument();
			$xml->loadXML( $data );
			return $this->validate_gpx_data( $xml );
		}

		function validate_gpx_data( $xml ) {
			$schema = plugin_dir_path( __FILE__ ) . '/gpx-1.1.xsd';
			if ( $xml->schemaValidate( $schema ) ) {
				return $xml;
			}
			$schema = plugin_dir_path( __FILE__ ) . '/gpx-1.0.xsd';
			if ( $xml->schemaValidate( $schema ) ) {
				return $xml;
			}
			return false;
		}

		function handle_uploaded_files( $user_id ) {

			$tmp = $this->get_temp_dir();

			$message = '';
			$files   = $this->rearrange( $_FILES );

			foreach ( $files as $f ) {
				$filename = $tmp . '/' . uniqid();

				// Check the filename extension case-insensitively
				if ( strcasecmp( substr( $f['name'], -4 ), '.gpx' ) == 0 ) {
					if ( $f['error'] == 0 && move_uploaded_file( $f['tmp_name'], $filename ) ) {
						$xml = $this->validate_gpx_file( $filename );
						if ( $xml ) {
							$result = $this->process_gpx( $xml, $user_id );

							// No need to HTML-escape the message here
							$format   = __( "File '%1\$s': imported %2\$s points from %3\$s track(s) in %4\$s seconds.", 'trackserver' );
							$message .= sprintf(
								$format,
								(string) $f['name'],
								(string) $result['num_trkpt'],
								(string) $result['num_trk'],
								(string) $result['exec_time']
							) . "\n";
						} else {
							// No need to HTML-escape the message here
							$message .= sprintf( __( "ERROR: File '%1\$s' could not be validated as GPX 1.1", 'trackserver' ), $f['name'] ) . "\n";
						}
					} else {
						// No need to HTML-escape the message here
						$message .= sprintf( __( "ERROR: Upload '%1\$s' failed", 'trackserver' ), $f['name'] ) . ' (rc=' . $f['error'] . ")\n";
					}
				} else {
					$message .= sprintf( __( "ERROR: Only .gpx files accepted; discarding '%1\$s'", 'trackserver' ), $f['name'] ) . "\n";
				}
				unlink( $filename );
			}
			if ( $message == '' ) {
				$max = $this->size_to_bytes( ini_get( 'post_max_size' ) );
				if ( isset( $_SERVER['CONTENT_LENGTH'] ) && $max > 0 && (int) $_SERVER['CONTENT_LENGTH'] > $max ) {
					$message = 'ERROR: File too large, maximum size is ' . $this->bytes_to_human( $max ) . "\n";
				} else {
					$message = "ERROR: No file found\n";
				}
			}
			return $message;
		}

		/**
		 * Function to handle file uploads from a (mobile) client to the 'upload' slug
		 */
		function handle_upload() {
			header( 'Content-Type: text/plain' );
			$user_id = $this->validate_http_basicauth();
			$msg     = $this->handle_uploaded_files( $user_id );
			echo $msg;
		}

		/**
		 * Function to handle file uploads from the WordPress admin
		 */
		function handle_admin_upload() {
			$user_id = get_current_user_id();
			if ( user_can( $user_id, 'use_trackserver' ) ) {
				return $this->handle_uploaded_files( $user_id );
			} else {
				return 'User has insufficient permissions.';
			}
		}

		/**
		 * Function to get a track ID by name. Used to find duplicates.
		 */
		function get_track_id_by_name( $name, $user_id ) {
			global $wpdb;
			$sql     = $wpdb->prepare( 'SELECT id FROM ' . $this->tbl_tracks . ' WHERE name=%s AND user_id=%d LIMIT 0,1', $name, $user_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$trip_id = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return ( $trip_id ? $trip_id : false );
		}

		/**
		 * Function to parse the XML from a GPX file and insert it into the database. It first converts the
		 * provided DOMDocument to SimpleXML for easier processing and uses the same intermediate format
		 * as the MapMyTracks import, so it can use the same function for inserting the locations
		 */
		function process_gpx( $dom, $user_id, $skip_existing = false ) {
			global $wpdb;

			$gpx        = simplexml_import_dom( $dom );
			$source     = $gpx['creator'];
			$trip_start = false;
			$fake_time  = false;
			$ntrk       = 0;
			$ntrkpt     = 0;
			$track_ids  = array();
			$exec_t0    = microtime( true );

			foreach ( $gpx->trk as $trk ) {
				$points    = array();
				$trip_name = (string) $trk->name;

				// @codingStandardsIgnoreLine
				if ( $skip_existing && ( $trk_id = $this->get_track_id_by_name( $trip_name, $user_id ) ) ) {
					$track_ids[] = $trk_id;
				} else {
					foreach ( $trk->trkseg as $trkseg ) {
						foreach ( $trkseg->trkpt as $trkpt ) {
							$trkpt_ts = $this->parse_iso_date( (string) $trkpt->time );
							if ( ! $trip_start ) {
								$trip_start = date( 'Y-m-d H:i:s', $trkpt_ts );
								$last_ts    = (int) $trkpt_ts - 1;
								if ( empty( $trkpt->time ) ) {
									$fake_time = true;
								}
							}
							$points[] = array(
								'latitude'  => (string) $trkpt['lat'],
								'longitude' => (string) $trkpt['lon'],
								'altitude'  => (string) $trkpt->ele,
								'timestamp' => ( $fake_time ? ( $last_ts + 1 ) : $this->parse_iso_date( (string) $trkpt->time ) ),
							);
							$ntrkpt++;
							$last_ts++;
						}
					}
					$data   = array(
						'user_id' => $user_id,
						'name'    => $trip_name,
						'created' => $trip_start,
						'source'  => $source,
					);
					$format = array( '%d', '%s', '%s', '%s' );
					if ( $wpdb->insert( $this->tbl_tracks, $data, $format ) ) {
						$trip_id = $wpdb->insert_id;
						$this->mapmytracks_insert_points( $points, $trip_id, $user_id );
						$track_ids[] = $trip_id;
						$ntrk++;
					}
				}
			}
			$exec_time = round( microtime( true ) - $exec_t0, 1 );
			return array(
				'num_trk'   => $ntrk,
				'num_trkpt' => $ntrkpt,
				'track_ids' => $track_ids,
				'exec_time' => $exec_time,
			);
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
			//$d = $d->add( $i );
			return $d->format( 'U' );
		}

		/**
		 * Function to return the author ID for a given post ID
		 */
		function get_author( $post_id ) {
			$post = get_post( $post_id );
			return ( is_object( $post ) ? $post->post_author : false );
		}

		function get_live_tracks( $user_ids, $maxage = 0 ) {
			global $wpdb;

			if ( empty( $user_ids ) ) {
				return array();
			}
			$user_ids = array_unique( $user_ids );

			$sql_in = "('" . implode( "','", $user_ids ) . "')";

			$sql = 'SELECT DISTINCT t.user_id, l.trip_id AS id FROM ' . $this->tbl_tracks . ' t INNER JOIN ' . $this->tbl_locations . ' l ' .
				'ON t.id = l.trip_id INNER JOIN (SELECT t2.user_id, MAX(l2.occurred) AS endts FROM ' . $this->tbl_locations . ' l2 ' .
				'INNER JOIN ' . $this->tbl_tracks . ' t2 ON l2.trip_id = t2.id GROUP BY t2.user_id) uu ON l.occurred = uu.endts ' .
				'AND t.user_id = uu.user_id WHERE t.user_id IN ' . $sql_in;

			if ( $maxage > 0 ) {
				$ts   = gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - $maxage ) );
				$sql .= " AND uu.endts > '$ts'";
			}

			$res       = $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$track_ids = array();
			foreach ( $user_ids as $uid ) {
				if ( array_key_exists( $uid, $res ) ) {
					$track_ids[] = $res[ $uid ]->id;
				}
			}
			return $track_ids;
		}

		/**
		 * Function to handle a 'gettrack' query
		 *
		 * @since 3.0
		 */
		function handle_gettrack_query() {
			global $wpdb;

			$query_string = stripslashes( $_REQUEST['query'] );
			$maxage       = ( isset( $_REQUEST['maxage'] ) ? (int) $_REQUEST['maxage'] : 0 );
			$post_id      = ( isset( $_REQUEST['p'] ) ? intval( $_REQUEST['p'] ) : 0 );
			$format       = $_REQUEST['format'];
			$is_admin     = array_key_exists( 'admin', $_REQUEST ) ? true : false;

			// Refuse to serve the track without a valid nonce. Admin screen uses a different nonce.
			if (
				( $is_admin &&
				! wp_verify_nonce( $_REQUEST['_wpnonce'], 'manage_track_' . $query_string ) ) ||
				( ( ! $is_admin ) &&
				! wp_verify_nonce( $_REQUEST['_wpnonce'], 'gettrack_' . $query_string . '_p' . $post_id ) )
			) {
				$this->http_terminate( '403', "Access denied (q=$query_string)" );
			}

			// 'is_admin' is verified at this point. We use the current user's id as author, to make
			// sure users can only download their own tracks, unless they can 'trackserver_publish'
			if ( $is_admin ) {
				$author_id = get_current_user_id();
			} else {
				$author_id = $this->get_author( $post_id );
			}

			$query               = base64_decode( $query_string );
			$query               = json_decode( $query );
			$track_ids           = $query->id;
			$user_ids            = $query->live;
			$validated_track_ids = $this->validate_track_ids( $track_ids, $author_id );
			$validated_user_ids  = $this->validate_user_ids( $user_ids, $author_id );
			$user_track_ids      = $this->get_live_tracks( $validated_user_ids, $maxage );
			$track_ids           = array_merge( $validated_track_ids, $user_track_ids );

			// @codingStandardsIgnoreStart
			$sql_in = "('" . implode( "','", $track_ids ) . "')";
			$sql = 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' . $this->tbl_locations .
				' l INNER JOIN ' . $this->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id IN ' . $sql_in . ' AND l.hidden = 0 ORDER BY trip_id, occurred';
			$res = $wpdb->get_results( $sql, ARRAY_A );
			// @codingStandardsIgnoreEnd

			if ( $format == 'gpx' ) {
				$this->send_as_gpx( $res );
			} else {
				$this->send_alltracks( $res ); // default to 'alltracks' internal format
			}

		}

		/**
		 * Handle a gettrack proxy request
		 *
		 * This function is called when a 'gettrack' request has a 'proxy'
		 * parameter and the proxy function is enabled. If the supplied nonce
		 * checks out, the requested URL is fetched with wp_remote_get() and sent
		 * to the client. If the nonce check fails, a 403 is returned; in case of
		 * an error, we send a 500 status. A good request is always sent as
		 * application/xml. This may have to change once we start supporting
		 * GeoJSON.
		 *
		 * @since 3.1
		 */
		function handle_gettrack_proxy() {
			$proxy_string = stripslashes( $_REQUEST['proxy'] );
			$post_id      = ( isset( $_REQUEST['p'] ) ? intval( $_REQUEST['p'] ) : 0 );

			if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'proxy_' . $proxy_string . '_p' . $post_id ) ) {
				$url      = base64_decode( $proxy_string );
				$options  = array(
					'httpversion' => '1.1',
					'user-agent'  => 'WordPress/Trackserver ' . TRACKSERVER_VERSION . '; https://github.com/tinuzz/wp-plugin-trackserver',
				);
				$response = wp_remote_get( $url, $options );
				if ( is_array( $response ) ) {
					$rc = (int) wp_remote_retrieve_response_code( $response );
					if ( $rc != 200 ) {
						header( 'HTTP/1.1 ' . $rc . ' ' . wp_remote_retrieve_response_message( $response ) );
						$ct = wp_remote_retrieve_header( $response, 'content-type' );
						if ( $ct != '' ) {
							header( 'Content-Type: ' . $ct );
						}
					} else {
						header( 'Content-Type: application/xml' );
					}
					print( wp_remote_retrieve_body( $response ) );
				} else {
					$this->http_terminate( '500', $response->get_error_message() );
				}
			} else {
				$this->http_terminate();
			}
			die();
		}

		/**
		 * Handle the 'gettrack' request
		 *
		 * This function handles the 'gettrack' request. If a 'query' parameter is found,
		 * the processing is delegated to handle_gettrack_query(). The rest of this
		 * function handles the request in case of a single 'id' request, which should
		 * only happen from the admin. Only numeric IDs are handled.
		 */
		function handle_gettrack() {

			global $wpdb;

			$post_id  = ( isset( $_REQUEST['p'] ) ? intval( $_REQUEST['p'] ) : null );
			$track_id = ( isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : null );
			$format   = ( isset( $_REQUEST['format'] ) ? $_REQUEST['format'] : null );

			if ( isset( $_REQUEST['query'] ) ) {
				return $this->handle_gettrack_query();
			}
			if ( isset( $_REQUEST['proxy'] ) ) {
				if ( $this->options['enable_proxy'] ) {
					return $this->handle_gettrack_proxy();
				} else {
					$this->http_terminate( '403', 'Proxy disabled' );
				}
			}

			// Refuse to serve the track without a valid nonce. Admin screen uses a different nonce.
			if (
				( array_key_exists( 'admin', $_REQUEST ) &&
				! wp_verify_nonce( $_REQUEST['_wpnonce'], 'manage_track_' . $track_id ) ) ||
				( ( ! array_key_exists( 'admin', $_REQUEST ) ) &&
				! wp_verify_nonce( $_REQUEST['_wpnonce'], 'gettrack_' . $track_id . '_p' . $post_id ) )
			) {
				$this->http_terminate( '403', "Access denied (t=$track_id)" );
			}

			if ( $track_id ) {

				// @codingStandardsIgnoreStart
				$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' .
					 $this->tbl_locations . ' l INNER JOIN ' . $this->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id=%d  ORDER BY occurred', $track_id );
				$res = $wpdb->get_results( $sql, ARRAY_A );
				// @codingStandardsIgnoreEnd

				if ( $format == 'gpx' ) {
					$this->send_as_gpx( $res );
				} elseif ( $format == 'geojson' ) {
					$this->send_as_geojson( $res );
				} else {
					$this->send_as_polyline( $res ); // default to 'polyline'
				}
			} else {
				echo "ENOID\n";
			}
		}

		/**
		 * Function to output a track with metadata as GeoJSON. Takes a $wpdb result set as input.
		 *
		 * @since 2.2
		 */
		function send_as_geojson( $res ) {
			$points = array();
			foreach ( $res as $row ) {
				$points[] = array( $row['longitude'], $row['latitude'] );
			}
			$encoded  = array(
				'type'        => 'LineString',
				'coordinates' => $points,
			);
			$metadata = $this->get_metadata( $row );
			$this->send_as_json( $encoded, $metadata );
		}

		/**
		 * Function to output a track as Polyline, with metadata, encoded in JSON.
		 * Takes a $wpdb result set as input.
		 *
		 * @since 2.2
		 */
		function send_as_polyline( $res ) {

			// Include polyline encoder
			require_once TRACKSERVER_PLUGIN_DIR . 'class-polyline.php';

			$points = array();
			foreach ( $res as $row ) {
				$points[] = array( $row['latitude'], $row['longitude'] );
			}
			$encoded  = Polyline::encode( $points );
			$metadata = $this->get_metadata( $row );
			$this->send_as_json( $encoded, $metadata );
		}

		function send_alltracks( $res ) {

			// Include polyline encoder
			require_once TRACKSERVER_PLUGIN_DIR . 'class-polyline.php';

			$tracks = array();
			foreach ( $res as $row ) {
				$id = $row['trip_id'];
				if ( ! array_key_exists( $id, $tracks ) ) {
					$tracks[ $id ] = array(
						'points' => array(),
					);
				}
				$tracks[ $id ]['points'][] = array( $row['latitude'], $row['longitude'] );
				$tracks[ $id ]['metadata'] = $this->get_metadata( $row );   // Overwrite the value on every row, so the last row remains
			}

			// Convert points to Polyline
			foreach ( $tracks as $id => $values ) {
				$tracks[ $id ]['track'] = Polyline::encode( $values['points'] );
				unset( $tracks[ $id ]['points'] );
			}
			header( 'Content-Type: application/json' );
			echo json_encode( $tracks );
		}

		/**
		 * Function to actually output data as JSON. Takes encoded location points and metadata
		 * (both arrays) as input.
		 *
		 * @since 2.2
		 */
		function send_as_json( $encoded, $metadata ) {
			$data = array(
				'track'    => $encoded,
				'metadata' => $metadata,
			);
			header( 'Content-Type: application/json' );
			echo json_encode( $data );
		}

		function send_as_gpx( $res ) {
			$dom = new DOMDocument( '1.0', 'utf-8' );
			// @codingStandardsIgnoreStart
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			// @codingStandardsIgnoreEnd
			$gpx = $dom->createElementNS( 'http://www.topografix.com/GPX/1/1', 'gpx' );
			$dom->appendChild( $gpx );
			$gpx->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance' );
			$gpx->setAttribute( 'creator', 'Trackserver ' . TRACKSERVER_VERSION );
			$gpx->setAttribute( 'version', '1.1' );
			$gpx->setAttributeNS( 'http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 'http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd' );
			$metadata = $dom->createElement( 'metadata' );
			$gpx->appendChild( $metadata );
			$author = $dom->createElement( 'author' );
			$metadata->appendChild( $author );
			$authorname = $dom->createElement( 'name' );
			$author->appendChild( $authorname );
			$home_url = get_home_url( null, '/', ( is_ssl() ? 'https' : 'http' ) );
			$link     = $dom->createElement( 'link' );
			$metadata->appendChild( $link );
			$link->setAttribute( 'href', $home_url );

			$first         = true;
			$last_track_id = false;
			foreach ( $res as $row ) {

				// Once, for the first record. Add stuff to the <gpx> element, using the database results
				if ( $first ) {
					$authorname->appendChild( $dom->createCDATASection( $this->get_user_id( (int) $row['user_id'], 'display_name' ) ) );
					$first_track_id = $row['trip_id'];
					$first          = false;
				}

				// For every track
				if ( $row['trip_id'] != $last_track_id ) {
					$trk = $dom->createElement( 'trk' );
					$gpx->appendChild( $trk );
					$name = $dom->createElement( 'name' );
					$trk->appendChild( $name );
					$name->appendChild( $dom->createCDATASection( $row['name'] ) );
					if ( $row['comment'] ) {
						$desc = $dom->createElement( 'desc' );
						$trk->appendChild( $desc );
						$desc->appendChild( $dom->createCDATASection( $row['comment'] ) );
					}
					$trkseg = $dom->createElement( 'trkseg' );
					$trk->appendChild( $trkseg );
					$last_track_id = $row['trip_id'];
				}

				$trkpt = $dom->createElement( 'trkpt' );
				$trkseg->appendChild( $trkpt );
				$trkpt->setAttribute( 'lat', $row['latitude'] );
				$trkpt->setAttribute( 'lon', $row['longitude'] );

				$offset_seconds  = (int) get_option( 'gmt_offset' ) * 3600;
				$timezone_offset = new DateInterval( 'PT' . abs( $offset_seconds ) . 'S' );
				$occurred        = new DateTime( $row['occurred'] );  // A DateTime object in local time

				if ( $offset_seconds < 0 ) {
					$occurred = $occurred->add( $timezone_offset );
				} else {
					$occurred = $occurred->sub( $timezone_offset );
				}
				$occ_iso = $occurred->format( 'c' );
				$trkpt->appendChild( $dom->createElement( 'time', $occ_iso ) );
			}

			header( 'Content-Type: application/gpx+xml' );
			header( 'Content-Disposition: filename="trackserver-' . $first_track_id . '.gpx"' );
			echo $dom->saveXML();
		}

		/**
		 * Function to construct a metadata array from a $wpdb row.
		 *
		 * @since 2.2
		 */
		function get_metadata( $row ) {
			$metadata = array(
				'last_trkpt_time'     => $row['occurred'],
				'last_trkpt_altitude' => $row['altitude'],
				'last_trkpt_speed_ms' => number_format( $row['speed'], 3 ),
				'distance'            => $row['distance'],
				'trackname'           => $row['name'],
			);
			if ( $row['user_id'] ) {
				$metadata['userid']      = $row['user_id'];
				$metadata['userlogin']   = $this->get_user_id( (int) $row['user_id'], 'user_login' );
				$metadata['displayname'] = $this->get_user_id( (int) $row['user_id'], 'display_name' );
			}
			return $metadata;
		}

		/**
		 * Function to check if a given user ID has any tracks in the database.
		 */
		function user_has_tracks( $user_id ) {
			global $wpdb;
			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT count(id) FROM ' . $this->tbl_tracks . ' WHERE user_id=%d', $user_id );
			$n = (int) $wpdb->get_var( $sql );
			// @codingStandardsIgnoreEnd
			return $n > 0;
		}

		function setup_tracks_list_table() {

			// Do this only once.
			if ( $this->tracks_list_table ) {
				return;
			}

			// Load prerequisites
			if ( ! class_exists( 'WP_List_Table' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
			}
			require_once( TRACKSERVER_PLUGIN_DIR . 'tracks-list-table.php' );

			$user_id = get_current_user_id();
			$view    = $user_id;
			if ( current_user_can( 'trackserver_admin' ) ) {
				$view = (int) get_user_meta( $user_id, 'ts_tracks_admin_view', true );
				if ( isset( $_REQUEST['author'] ) ) {
					$view = (int) $_REQUEST['author'];
				}
				if ( ! $this->user_has_tracks( $view ) ) {
					$view = 0;
				}
				// if ( $old_view != $view ) ?
				update_user_meta( $user_id, 'ts_tracks_admin_view', $view );
			}

			// Get / set the value for the number of tracks per page from the selectbox
			$per_page = (int) get_user_meta( $user_id, 'ts_tracks_admin_per_page', true );
			$per_page = ( $per_page == 0 ? 20 : $per_page );
			if ( isset( $_REQUEST['per_page'] ) ) {
				$per_page = (int) $_REQUEST['per_page'];
				update_user_meta( $user_id, 'ts_tracks_admin_per_page', $per_page );
			}

			$list_table_options = array(
				'tbl_tracks'    => $this->tbl_tracks,
				'tbl_locations' => $this->tbl_locations,
				'view'          => $view,
				'per_page'      => $per_page,
			);

			$this->tracks_list_table = new Tracks_List_Table( $list_table_options );
		}

		function manage_tracks_html() {

			if ( ! current_user_can( 'use_trackserver' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
			}

			add_thickbox();
			$this->setup_tracks_list_table();

			$search = ( isset( $_REQUEST['s'] ) ? wp_unslash( $_REQUEST['s'] ) : '' );
			$this->tracks_list_table->prepare_items( $search );

			$url = admin_url() . 'admin-post.php';

			?>
				<div id="ts-edit-modal" style="display:none;">
					<p>
						<form id="trackserver-edit-track" method="post" action="<?php echo $url; ?>">
							<table style="width: 100%">
								<?php wp_nonce_field( 'manage_track' ); ?>
								<input type="hidden" name="action" value="trackserver_save_track" />
								<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
								<input type="hidden" id="track_id" name="track_id" value="" />
								<tr>
									<th style="width: 150px;"><?php esc_html_e( 'Name', 'trackserver' ); ?></th>
									<td><input id="input-track-name" name="name" type="text" style="width: 100%" /></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Source', 'trackserver' ); ?></th>
									<td><input id="input-track-source" name="source" type="text" style="width: 100%" /></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Comment', 'trackserver' ); ?></th>
									<td><textarea id="input-track-comment" name="comment" rows="3" style="width: 100%; resize: none;"></textarea></td>
								</tr>
							</table>
							<br />
							<input class="button action" type="submit" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" name="save_track">
							<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
							<input type="hidden" id="trackserver-edit-action" name="trackserver_action" value="save">
							<a id="ts-delete-track" href="#" style="float: right; color: red" ><?php esc_html_e( 'Delete', 'trackserver' ); ?></a>
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
						<?php esc_html__( 'Merge all points of multiple tracks into one track. Please specify the name for the merged track.', 'trackserver' ); ?>
						<form method="post" action="<?php echo $url; ?>">
							<table style="width: 100%">
								<?php wp_nonce_field( 'manage_track' ); ?>
								<tr>
									<th style="width: 150px;"><?php esc_html_e( 'Merged track name', 'trackserver' ); ?></th>
									<td><input id="input-merged-name" name="name" type="text" style="width: 100%" /></td>
								</tr>
							</table>
							<br />
							<span class="aligncenter"><i><?php esc_html_e( 'Warning: this action cannot be undone!', 'trackserver' ); ?></i></span><br />
							<div class="alignright">
								<input class="button action" type="button" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" id="merge-submit-button">
								<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
							</div>
						</form>
					</p>
				</div>
				<div id="ts-upload-modal" style="display:none;">
					<div style="padding: 15px 0">
						<form id="ts-upload-form" method="post" action="<?php echo $url; ?>" enctype="multipart/form-data">
							<?php wp_nonce_field( 'upload_track' ); ?>
							<input type="hidden" name="action" value="trackserver_upload_track" />
							<input type="file" name="gpxfile[]" multiple="multiple" style="display: none" id="ts-file-input" />
							<input type="button" class="button button-hero" value="<?php esc_attr_e( 'Select files', 'trackserver' ); ?>" id="ts-select-files-button" />
							<!-- <input type="button" class="button button-hero" value="Upload" id="ts-upload-files-button" disabled="disabled" /> -->
							<button type="button" class="button button-hero" value="<?php esc_attr_e( 'Upload', 'trackserver' ); ?>" id="ts-upload-files-button" disabled="disabled"><?php esc_html_e( 'Upload', 'trackserver' ); ?></button>
						</form>
						<br />
						<br />
						<?php esc_html_e( 'Selected files', 'trackserver' ); ?>:<br />
						<div id="ts-upload-filelist" style="height: 200px; max-height: 200px; overflow-y: auto; border: 1px solid #dddddd; padding-left: 5px;"></div>
						<br />
						<div id="ts-upload-warning"></div>
					</div>
				</div>
				<form id="trackserver-tracks" method="post">
					<input type="hidden" name="page" value="trackserver-tracks" />
					<div class="wrap">
						<h2><?php esc_html_e( 'Manage tracks', 'trackserver' ); ?></h2>
						<?php $this->notice_bulk_action_result(); ?>
						<?php $this->tracks_list_table->views(); ?>
						<?php $this->tracks_list_table->search_box( esc_attr__( 'Search tracks', 'trackserver' ), 'search_tracks' ); ?>
						<?php $this->tracks_list_table->display(); ?>
					</div>
				</form>
			<?php
		}

		function yourprofile_html() {

			if ( ! current_user_can( 'use_trackserver' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
			}

			add_thickbox();

			$user = wp_get_current_user();

			// translators: placeholder is for a user's display name
			$title = __( 'Trackserver profile for %s', 'trackserver' );
			$title = sprintf( $title, $user->display_name );

			?>
			<div class="wrap">
				<h2><?php echo esc_html( $title ); ?></h2>
				<?php $this->notice_bulk_action_result(); ?>
				<form id="trackserver-profile" method="post">
					<?php wp_nonce_field( 'your-profile' ); ?>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label for="trackme_access_key">
										<?php esc_html_e( 'TrackMe password', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->trackme_passwd_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="mapmytracks_profile">
										<?php esc_html_e( 'MapMyTracks profile', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->mapmytracks_profile_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="osmand_access_key">
										<?php esc_html_e( 'OsmAnd access key', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->osmand_key_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="sendlocation_access_key">
										<?php esc_html_e( 'SendLocation access key', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->sendlocation_key_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label>
										<?php esc_html_e( 'Share via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->share_friends_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label>
										<?php esc_html_e( 'Follow users via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->follow_friends_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="infobar_template">
										<?php esc_html_e( 'Shortcode infobar template', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->infobar_template_html(); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="geofence_center">
										<?php esc_html_e( 'Geofencing', 'trackserver' ); ?>
									</label>
								</th>
								<td>
									<?php $this->geofences_html(); ?>
								</td>
							</tr>
						<tbody>
					</table>
					<p class="submit">
						<input type="submit" value="<?php esc_html_e( 'Update profile', 'trackserver' ); ?>"
							class="button button-primary" id="submit" name="submit">
					</p>
				</form>
				<div id="ts-view-modal" style="display:none;">
						<div id="tsadminmapcontainer">
							<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
						</div>
				</div>
			</div>
			<?php
			$this->howto_modals_html();

		}

		function mapmytracks_profile_html() {
			$val    = $this->printf_htmlspecialchars( $this->options['mapmytracks_tag'] );
			$url    = $this->printf_htmlspecialchars( site_url( null ) . $this->url_prefix );
			$format = "<strong>%1\$s:</strong> $url/$val<br /><br />";

			printf( $format, esc_html__( 'Full custom URL', 'trackserver' ) );

			$this->admin->settings->mapmytracks_settings_html();
		}

		function osmand_key_html() {
			$url          = $this->printf_htmlspecialchars( site_url( null ) . $this->url_prefix );
			$current_user = wp_get_current_user();
			$key          = $this->printf_htmlspecialchars( get_user_meta( $current_user->ID, 'ts_osmand_key', true ) );
			$slug         = $this->printf_htmlspecialchars( $this->options['osmand_slug'] );
			$username     = $current_user->user_login;
			$suffix       = $this->printf_htmlspecialchars( "/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}&username=$username&key=$key" );

			$format = <<<EOF
				%1\$s<br />
				<input type="text" size="25" name="ts_user_meta[ts_osmand_key]" id="trackserver_osmand_key" value="$key" autocomplete="off" /><br /><br />
				<strong>%2\$s:</strong> $url/$slug$suffix<br /><br />
EOF;

			// @codingStandardsIgnoreStart
			printf(
				$format,
				esc_html__( 'An access key for online tracking. We do not use WordPress password here for security reasons. ' .
				'The key should be added, together with your WordPress username, as a URL parameter to the online tracking ' .
				'URL set in OsmAnd, as displayed below. Change this regularly.', 'trackserver' ),
				esc_html__( 'Full URL', 'trackserver' )
			);
			// @codingStandardsIgnoreEnd

			$this->admin->settings->osmand_settings_html();
		}

		function sendlocation_key_html() {
			$url          = $this->printf_htmlspecialchars( site_url( null ) . $this->url_prefix );
			$current_user = wp_get_current_user();
			$key          = $this->printf_htmlspecialchars( get_user_meta( $current_user->ID, 'ts_sendlocation_key', true ) );
			$slug         = $this->printf_htmlspecialchars( $this->options['sendlocation_slug'] );
			$username     = $current_user->user_login;
			$suffix       = $this->printf_htmlspecialchars( "/$username/$key/" );

			$format = <<<EOF
				%1\$s<br />
				<input type="text" size="25" name="ts_user_meta[ts_sendlocation_key]" id="trackserver_sendlocation_key" value="$key" autocomplete="off" /><br /><br />
				<strong>%2\$s:</strong> $url/$slug$suffix<br />
EOF;

			// @codingStandardsIgnoreStart
			printf(
				$format,
				esc_html__( 'An access key for online tracking. We do not use WordPress password here for security reasons. ' .
				'The key should be added, together with your WordPress username, as a URL component in the tracking ' .
				'URL set in SendLocation, as displayed below. Change this regularly.', 'trackserver' ),
				esc_html__( 'Your personal server and script', 'trackserver' )
			);
			// @codingStandardsIgnoreEnd
		}

		function trackme_passwd_html() {
			$url          = $this->printf_htmlspecialchars( site_url( null ) . $this->url_prefix );
			$current_user = wp_get_current_user();
			$key          = $this->printf_htmlspecialchars( get_user_meta( $current_user->ID, 'ts_trackme_key', true ) );
			$slug         = $this->printf_htmlspecialchars( $this->options['trackme_slug'] );
			$extn         = $this->printf_htmlspecialchars( $this->options['trackme_extension'] );
			$username     = $current_user->user_login;

			$format = <<<EOF
				%1\$s<br />
				<input type="text" size="25" name="ts_user_meta[ts_trackme_key]" id="trackserver_trackme_key" value="$key" autocomplete="off" /><br /><br />
				<strong>%2\$s:</strong> $url/$slug/$username/$key<br />
				<strong>%3\$s:</strong> $extn<br /><br />
EOF;

			// @codingStandardsIgnoreStart
			printf(
				$format,
				esc_html__( 'A password for online tracking. We do not use WordPress password here for security reasons. Change this regularly.', 'trackserver' ),
				esc_html__( 'URL header', 'trackserver' ),
				esc_html__( 'Server extension', 'trackserver' )
			);
			// @codingStandardsIgnoreEnd

			$this->admin->settings->trackme_settings_html();
		}

		function share_friends_html() {
			$current_user = wp_get_current_user();
			$value        = htmlspecialchars( get_user_meta( $current_user->ID, 'ts_owntracks_share', true ) );
			$link_url     = 'http://owntracks.org/booklet/features/friends/';

			// @codingStandardsIgnoreStart
			echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to share your location with. ' .
				'Users who use OwnTracks or TrackMe\'s "Show Cloud People" feature will see your latest location on the map, ' .
				'if they follow you. This setting is only about sharing your latest (live) location with TrackMe and ' .
				'OwnTracks users. It does not grant access to your track data in any other way.', 'trackserver'
			) . '<br /><br />';
			// translators: placeholder is for a http link URL
			echo sprintf(
				__( 'See <a href="%1$s" target="_blank">the description of the Friends feature in the OwnTracks booklet</a> for more information.', 'trackserver' ), $link_url
			) . '<br /><br />';
			// @codingStandardsIgnoreEnd
			echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_share]" value="' . $value . '" autocomplete="off" /><br /><br />';
		}

		function follow_friends_html() {
			$current_user = wp_get_current_user();
			$value        = htmlspecialchars( get_user_meta( $current_user->ID, 'ts_owntracks_follow', true ) );
			// @codingStandardsIgnoreStart
			echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to follow with TrackMe\'s ' .
				'"Show Cloud People" feature or with OwnTracks. These users must share their location with you, by listing ' .
				'your username in the "Share via ..." setting above and publishing their location to Trackserver with one ' .
				'of the supported apps. Leave this setting empty to follow all users that share their location with you. ' .
				'You can exclude users by prefixing their username with a "!" (exclamation mark).', 'trackserver'
			) . '<br />';
			// @codingStandardsIgnoreEnd
			echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_follow]" value="' . $value . '" autocomplete="off" /><br /><br />';
		}

		function infobar_template_html() {
			$current_user = wp_get_current_user();
			$template     = $this->printf_htmlspecialchars( get_user_meta( $current_user->ID, 'ts_infobar_template', true ) );
			$format       = <<<EOF
				%1\$s<br />
				<input type="text" size="40" name="ts_user_meta[ts_infobar_template]" id="trackserver_infobar_template" value="$template" autocomplete="off" /><br /><br />
EOF;
			// @codingStandardsIgnoreStart
			printf(
				$format,
				esc_html__(
					'With live tracking, an information bar can be shown on the map, displaying some data from the track and the latest trackpoint. ' .
					'Here you can format the content of the infobar.', 'trackserver'
				)
			);
			// @codingStandardsIgnoreEnd
			echo esc_html__( 'Possible replacement tags are:', 'trackserver' ) . '<br>';
			echo '{lat}, {lon} - ' . esc_html__( 'the last known coordinates', 'trackserver' ) . '<br>';
			echo '{timestamp} - ' . esc_html__( 'the timestamp of the last update', 'trackserver' ) . '<br>';
			echo '{userid} - ' . esc_html__( 'the numeric user id of the track owner', 'trackserver' ) . '<br>';
			echo '{userlogin} - ' . esc_html__( 'the username of the track owner', 'trackserver' ) . '<br>';
			echo '{displayname} - ' . esc_html__( 'the display name of the track owner', 'trackserver' ) . '<br>';
			echo '{trackname} - ' . esc_html__( 'the name of the track', 'trackserver' ) . '<br>';
			echo '{altitudem} - ' . esc_html__( 'the altitude in meters', 'trackserver' ) . '<br>';
			echo '{altitudeft} - ' . esc_html__( 'the altitude in feet', 'trackserver' ) . '<br>';
			echo '{speedms}, {speedms1}, {speedms2} - ' . esc_html__( 'last known speed in m/s, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
			echo '{speedkmh}, {speedkmh1}, {speedkmh2} - ' . esc_html__( 'last known speed in km/h, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
			echo '{speedmph}, {speedmph1}, {speedmph2} - ' . esc_html__( 'last known speed in mi/h, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
			echo '{distancem} - ' . esc_html__( 'track total distance in meters', 'trackserver' ) . '<br>';
			echo '{distanceyd} - ' . esc_html__( 'track total distance in yards', 'trackserver' ) . '<br>';
			echo '{distancekm}, {distancekm1}, {distancekm2} - ' . esc_html__( 'track total distance in km, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
			echo '{distancemi}, {distancemi1}, {distancemi2} - ' . esc_html__( 'track total distance in miles, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
		}


		/**
		 * Output HTML for managing geofences
		 *
		 * This function outputs the HTML for managing geofences in the user
		 * profile. Geofences are stored in the user's metadata as a single
		 * associative array. If the stored array does not contain a geofence with
		 * all '0' values, we add one. This can be used to add new geofences.
		 *
		 * @since 3.1
		 */
		function geofences_html() {
			$current_user      = wp_get_current_user();
			$url               = admin_url() . 'admin.php?page=trackserver-yourprofile';
			$geofences         = get_user_meta( $current_user->ID, 'ts_geofences', true );
			$default_geofence  = array(
				'lat'    => 0,
				'lon'    => 0,
				'radius' => 0,
				'action' => 'hide',
			);
			$action_select_fmt = '<select name="ts_geofence_action[%1$d]" data-id="%1$d" class="ts-input-geofence">%2$s</select>';
			$actions           = array(
				'hide'    => esc_html__( 'Hide', 'trackserver' ),
				'discard' => esc_html__( 'Discard', 'trackserver' ),
			);

			if ( ! in_array( $default_geofence, $geofences ) ) {
				$geofences[] = $default_geofence;
			}

			// @codingStandardsIgnoreStart
			echo esc_html__( 'Track updates that fall within the specified radius (in meters) from the center point, ' .
				'will be marked as hidden or discarded, depending on the specified action. Modify the "0, 0, 0" entry to add a new fence. ' .
				'Set the values to "0, 0, 0, Hide" to delete an entry. ' .
				'The center point coordinates should be specified in decimal degrees. A radius of 0 will disable the fence. ' .
				'Use the link below to view existing geofences. You can also click the map to pick the center coordinates for a new fence. ' .
				'Remember to set a radius and update your profile afterwards.', 'trackserver' ) . '<br>';
			// @codingStandardsIgnoreEnd

			echo '<table>';
			for ( $i = 0; $i < count( $geofences ); $i++ ) {
				$fence                 = $geofences[ $i ];
				$lat                   = htmlspecialchars( $fence['lat'] );
				$lon                   = htmlspecialchars( $fence['lon'] );
				$radius                = htmlspecialchars( $fence['radius'] );
				$action                = $fence['action'];
				$action_select_options = '';
				foreach ( $actions as $k => $v ) {
					$option_selected        = ( $action == $k ? 'selected' : '' );
					$action_select_options .= '<option value="' . $k . '" ' . $option_selected . '>' . $v . '</option>';
				}

				// Mark all rows (and especially the '0,0,0' row) for easier finding in JavaScript
				$itemdata = 'data-id="' . $i . '"';
				if ( $lat == '0' && $lon == '0' && $radius == '0' && $action == 'hide' ) {
					$itemdata .= ' data-newentry';
				}

				echo '<tr ' . $itemdata . ' class="trackserver_geofence">' .
					'<td>' . esc_html__( 'Center latitude', 'trackserver' ) .
					' <input type="text" size="10" name="ts_geofence_lat[' . $i . ']" value="' . $lat . '" class="ts-input-geofence-lat ts-input-geofence" autocomplete="off" ' .
					$itemdata . ' /></td>' .
					'<td>' . esc_html__( 'Longitude', 'trackserver' ) .
					' <input type="text" size="10" name="ts_geofence_lon[' . $i . ']" value="' . $lon . '" class="ts-input-geofence-lon ts-input-geofence" autocomplete="off" ' .
					$itemdata . ' /></td>' .
					'<td>' . esc_html__( 'Radius', 'trackserver' ) .
					' <input type="text" size="10" name="ts_geofence_radius[' . $i . ']" value="' . $radius . '" class="ts-input-geofence-radius ts-input-geofence" autocomplete="off" ' .
					$itemdata . ' /></td>' .
					'<td>' . esc_html__( 'Action', 'trackserver' ) . ' ';

				printf( $action_select_fmt, $i, $action_select_options );

				echo '</td></tr>';
			}
			echo '<tr><td colspan="4" style="text-align: right">' .
				'<a href="#TB_inline?width=&inlineId=ts-view-modal" title="' . esc_attr__( 'Geofences', 'trackserver' ) .
				'" class="thickbox" data-id="0" data-action="fences">' . esc_html__( 'View geofences', 'trackserver' ) . '</a>';
				'</td></tr>';
			echo '</table>';
			echo '<div id="ts_geofences_changed" style="color: red; display: none">' .
				esc_html__( "It seems you have made changes to the geofences. Don't forget to update your profile!", 'trackserver' );
				'</div>';

			// Prepare the map data
			wp_localize_script( 'trackserver-admin', 'trackserver_admin_geofences', $geofences );
		}

		function profiles_html() {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
			}
			echo '<h2>Trackserver map profiles</h2>';
		}

		function admin_post_save_track() {
			global $wpdb;

			$track_id = (int) $_REQUEST['track_id'];
			check_admin_referer( 'manage_track_' . $track_id );

			if ( $this->current_user_can_manage( $track_id ) ) {

				// Save track. Use stripslashes() on the data, because WP magically escapes it.
				$name    = stripslashes( $_REQUEST['name'] );
				$source  = stripslashes( $_REQUEST['source'] );
				$comment = stripslashes( $_REQUEST['comment'] );

				if ( $_REQUEST['trackserver_action'] == 'delete' ) {
					$result  = $this->wpdb_delete_tracks( (int) $track_id );
					$message = 'Track "' . $name . '" (ID=' . $track_id . ', ' .
						$result['locations'] . ' locations) deleted';
				} elseif ( $_REQUEST['trackserver_action'] == 'split' ) {
					$vertex  = intval( $_REQUEST['vertex'] );  // not covered by nonce!
					$r       = $this->wpdb_split_track( $track_id, $vertex );
					$message = 'Track "' . $name . '" (ID=' . $track_id . ') has been split at point ' . $vertex . ' ' . $r;  // TODO: i18n
				} else {
					$data  = array(
						'name'    => $name,
						'source'  => $source,
						'comment' => $comment,
					);
					$where = array(
						'id' => $track_id,
					);
					$wpdb->update( $this->tbl_tracks, $data, $where, '%s', '%d' );

					$message = 'Track "' . $name . '" (ID=' . $track_id . ') saved';
				}
			} else {
				$message = __( 'It seems you have insufficient permissions to manage track ID ' ) . $track_id;
			}

			// Redirect back to the admin page. This should be safe.
			setcookie( 'ts_bulk_result', $message, time() + 300 );

			// Propagate search string to the redirect
			$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 's' ), $_REQUEST['_wp_http_referer'] );
			if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
				$referer = add_query_arg( 's', rawurlencode( wp_unslash( $_POST['s'] ) ), $referer );
			}
			wp_redirect( $referer );
			exit;
		}

		/**
		 * Function to return an array of location ids given an array of location
		 * indexes relative to the track, as passed from Leaflet Editable
		 */
		function get_location_ids_by_index( $track_id, $indexes ) {
			global $wpdb;

			$sql_in = "('" . implode( "','", $indexes ) . "')";
			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT c.* FROM (' .
				'SELECT @row := @row + 1 AS row, l.id FROM ' . $this->tbl_locations . ' l CROSS JOIN (select @row := -1) r WHERE l.trip_id=%d ORDER BY occurred' .
				') c WHERE c.row IN ' . $sql_in, $track_id );
			$res = $wpdb->get_results( $sql, OBJECT_K );
			// @codingStandardsIgnoreEnd
			return $res;
		}

		/**
		 * Function to handle AJAX request from track editor
		 */
		function admin_ajax_save_modified_track() {
			global $wpdb;

			$_POST = stripslashes_deep( $_POST );

			if ( ! isset( $_POST['t'], $_POST['modifications'] ) ) {
				$this->http_terminate( '403', 'Missing parameter(s)' );
			}

			check_ajax_referer( 'manage_track_' . $_POST['t'] );

			$modifications = json_decode( $_POST['modifications'], true );
			$i             = 0;

			if ( count( $modifications ) ) {
				$track_ids = $this->filter_current_user_tracks( array_keys( $modifications ) );

				foreach ( $track_ids as $track_id ) {
					$indexes    = array_keys( $modifications[ $track_id ] );
					$loc_ids    = $this->get_location_ids_by_index( $track_id, $indexes );
					$sql        = array();
					$delete_ids = array();
					foreach ( $modifications[ $track_id ] as $loc_index => $mod ) {
						if ( $mod['action'] == 'delete' ) {
							$delete_ids[] = $loc_ids[ $loc_index ]->id;
						} elseif ( $mod['action'] == 'move' ) {
							$qfmt  = 'UPDATE ' . $this->tbl_locations . ' SET latitude=%s, longitude=%s WHERE id=%d';
							$sql[] = $wpdb->prepare( $qfmt, $mod['lat'], $mod['lng'], $loc_ids[ $loc_index ]->id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						}
					}

					if ( count( $delete_ids ) ) {
						$sql_in = "('" . implode( "','", $delete_ids ) . "')";
						$sql[]  = 'DELETE FROM ' . $this->tbl_locations . ' WHERE id IN ' . $sql_in;
					}

					// If a query fails, give up immediately
					foreach ( $sql as $query ) {
						if ( $wpdb->query( $query ) === false ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							break;
						}
						$i++;
					}
					$this->calculate_distance( $track_id );
				}
			}
			echo "OK: $i queries executed";
			die();
		}

		/**
		 * Handler for the admin_post_trackserver_upload_track action
		 */
		function admin_post_upload_track() {
			check_admin_referer( 'upload_track' );
			$message = $this->handle_admin_upload();
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
			$this->setup_tracks_list_table();
			$action = $this->tracks_list_table->get_current_action();
			if ( $action ) {
				$this->process_bulk_action( $action );
			}
			// Set up bulk action result notice
			$this->setup_bulk_action_result_msg();
		}

		/**
		 * Function to set up a bulk action result message to be displayed later.
		 */
		function setup_bulk_action_result_msg() {
			if ( isset( $_COOKIE['ts_bulk_result'] ) ) {
				$this->bulk_action_result_msg = stripslashes( $_COOKIE['ts_bulk_result'] );
				setcookie( 'ts_bulk_result', '', time() - 3600 );
			}
		}

		/**
		 * Handler for the load-$hook for the 'Trackserver profile' page.
		 * It handles a POST (profile update) and sets up a result message.
		 *
		 * @since 1.9
		 */
		function load_your_profile() {
			// Handle POST from 'Trackserver profile' page
			// $_POST['ts_user_meta'] holds all the values
			if ( isset( $_POST['ts_user_meta'] ) ) {
				check_admin_referer( 'your-profile' );
				$this->process_profile_update();
			}

			// Set up bulk action result notice
			$this->setup_bulk_action_result_msg();
		}

		/**
		 * Function to handle a profile update for the current user
		 *
		 * @since 1.9
		 */
		function process_profile_update() {
			$user_id         = get_current_user_id();
			$data            = $_POST['ts_user_meta'];
			$geofence_lat    = $_POST['ts_geofence_lat'];
			$geofence_lon    = $_POST['ts_geofence_lon'];
			$geofence_radius = $_POST['ts_geofence_radius'];
			$geofence_action = $_POST['ts_geofence_action'];
			$valid_fields    = array(
				'ts_osmand_key',
				'ts_trackme_key',
				'ts_sendlocation_key',
				'ts_owntracks_share',
				'ts_owntracks_follow',
				'ts_infobar_template',
			);
			$valid_actions   = array( 'hide', 'discard' );

			// If the data is not an array, do nothing
			if ( is_array( $data ) ) {
				foreach ( $data as $meta_key => $meta_value ) {
					if ( in_array( $meta_key, $valid_fields ) ) {
						update_user_meta( $user_id, $meta_key, $meta_value );
					}
				}

				if ( is_array( $geofence_lat ) && is_array( $geofence_lon ) && is_array( $geofence_radius ) && is_array( $geofence_action ) ) {
					$geofences = array();
					$keys      = array_keys( $geofence_lat );  // The keys should be the same for all relevant arrays, normally a 0-based index.
					foreach ( $keys as $k ) {
						$newfence = array(
							'lat'    => (float) $geofence_lat[ $k ],
							'lon'    => (float) $geofence_lon[ $k ],
							'radius' => abs( (int) $geofence_radius[ $k ] ),
							'action' => ( in_array( $geofence_action[ $k ], $valid_actions ) ? $geofence_action[ $k ] : 'hide' ),
						);
						if ( ! in_array( $newfence, $geofences ) ) {
							$geofences[] = $newfence;
						}
					}
					update_user_meta( $user_id, 'ts_geofences', $geofences );
				}

				$message = __( 'Profile updated', 'trackserver' );
			} else {
				$message = __( 'ERROR: could not update user profile', 'trackserver' );
			}

			setcookie( 'ts_bulk_result', $message, time() + 300 );
			wp_redirect( $_REQUEST['_wp_http_referer'] );
			exit;
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

				$in  = '(' . implode( ',', $track_ids ) . ')';
				$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->tbl_tracks . " WHERE user_id=%d AND id IN $in", $user_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				if ( current_user_can( 'trackserver_admin' ) ) {
					$sql = 'SELECT id FROM ' . $this->tbl_tracks . " WHERE id IN $in";
				}
				return $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			return array();
		}

		function current_user_can_manage( $track_id ) {
			$valid = $this->filter_current_user_tracks( array( $track_id ) );
			return count( $valid ) == 1;
		}

		/**
		 * Function to delete tracks from the database
		 *
		 * @since 1.7
		 *
		 * @global object $wpdb The WordPress database interface
		 *
		 * @param array $track_ids Track IDs to delete.
		 */
		function wpdb_delete_tracks( $track_ids ) {
			global $wpdb;
			if ( ! is_array( $track_ids ) ) {
				$track_ids = array( $track_ids );
			}
			$in  = '(' . implode( ',', $track_ids ) . ')';
			$sql = 'DELETE FROM ' . $this->tbl_locations . " WHERE trip_id IN $in";
			$nl  = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = 'DELETE FROM ' . $this->tbl_tracks . " WHERE id IN $in";
			$nt  = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return array(
				'locations' => $nl,
				'tracks'    => $nt,
			);
		}

		function wpdb_split_track( $track_id, $point ) {
			global $wpdb;

			$split_id_arr = $this->get_location_ids_by_index( $track_id, array( $point ) );
			if ( count( $split_id_arr ) > 0 ) {  // should be exactly 1
				$split_id = $split_id_arr[ $point ]->id;

				// @codingStandardsIgnoreStart
				$sql = $wpdb->prepare( 'SELECT occurred FROM ' . $this->tbl_locations . ' WHERE id=%s', $split_id );
				$occurred = $wpdb->get_var( $sql );

				// Duplicate track record
				$sql = $wpdb->prepare( 'INSERT INTO ' . $this->tbl_tracks .
					" (user_id, name, created, source, comment) SELECT user_id, CONCAT(name, ' #2'), created," .
					" source, comment FROM " . $this->tbl_tracks . " WHERE id=%s", $track_id );
				$wpdb->query( $sql );
				$new_id = $wpdb->insert_id;

				// Update locations with the new track ID
				$sql = $wpdb->prepare( 'UPDATE ' . $this->tbl_locations . ' SET trip_id=%s WHERE trip_id=%s AND occurred > %s', $new_id, $track_id, $occurred );
				$wpdb->query( $sql );

				// Duplicate the split-point to the new track
				$sql = $wpdb->prepare(
					'INSERT INTO ' . $this->tbl_locations .
					" (trip_id, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment) " .
					" SELECT %s, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment FROM " .
					$this->tbl_locations . ' WHERE id=%s', $new_id, $split_id
				);
				$wpdb->query( $sql );
				// @codingStandardsIgnoreEnd

				$this->calculate_distance( $new_id );
				return print_r( $new_id, true );
			}
		}

		/**
		 * Function to process any bulk action from the tracks_list_table
		 */
		function process_bulk_action( $action ) {
			global $wpdb;

			// The action name is 'bulk-' + plural form of items in WP_List_Table
			check_admin_referer( 'bulk-tracks' );
			$track_ids = $this->filter_current_user_tracks( $_REQUEST['track'] );

			// Propagate search string to the redirect
			$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 's' ), $_REQUEST['_wp_http_referer'] );
			if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
				$referer = add_query_arg( 's', rawurlencode( wp_unslash( $_POST['s'] ) ), $referer );
			}

			if ( $action === 'delete' ) {
				if ( count( $track_ids ) > 0 ) {
					$result = $this->wpdb_delete_tracks( $track_ids );
					$nl     = $result['locations'];
					$nt     = $result['tracks'];
					// translators: placeholders are for number of locations and number of tracks
					$format  = __( 'Deleted %1$d location(s) in %2$d track(s).', 'trackserver' );
					$message = sprintf( $format, intval( $nl ), intval( $nt ) );
				} else {
					$message = __( 'No tracks deleted', 'trackserver' );
				}
				setcookie( 'ts_bulk_result', $message, time() + 300 );
				wp_redirect( $referer );
				exit;
			}

			if ( $action === 'merge' ) {
				// Need at least 2 tracks
				$n = count( $track_ids );
				if ( $n > 1 ) {
					$id   = min( $track_ids );
					$rest = array_diff( $track_ids, array( $id ) );
					// How useful is it to escape integers?
					array_walk( $rest, array( $wpdb, 'escape_by_ref' ) );
					$in   = '(' . implode( ',', $rest ) . ')';
					$sql  = $wpdb->prepare( 'UPDATE ' . $this->tbl_locations . " SET trip_id=%d WHERE trip_id IN $in", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$nl   = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$sql  = 'DELETE FROM ' . $this->tbl_tracks . " WHERE id IN $in";
					$nt   = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$name = stripslashes( $_REQUEST['merged_name'] );
					$sql  = $wpdb->prepare( 'UPDATE ' . $this->tbl_tracks . ' SET name=%s WHERE id=%d', $name, $id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					// TODO: consider checking for duplicate points that we created ourselves when splitting a track,
					// (see wpdb_split_track()) and remove them. We'd need 2 or 3 queries and some ugly code for that.
					// Is it worth the effort?

					$this->calculate_distance( $id );
					$format  = __( "Merged %1\$d location(s) from %2\$d track(s) into '%3\$s'.", 'trackserver' );
					$message = sprintf( $format, intval( $nl ), intval( $nt ), $name );
				} else {
					$format  = __( 'Need >= 2 tracks to merge, got only %1\$d', 'trackserver' );
					$message = sprintf( $format, $n );
				}
				setcookie( 'ts_bulk_result', $message, time() + 300 );
				wp_redirect( $referer );
				exit;
			}

			if ( $action === 'dlgpx' ) {

				$track_format = 'gpx';
				// @codingStandardsIgnoreLine
				$query         = json_encode( array( 'id' => $track_ids, 'live' => array() ) );
				$query         = base64_encode( $query );
				$query_nonce   = wp_create_nonce( 'manage_track_' . $query );
				$alltracks_url = get_home_url( null, $this->url_prefix . '/' . $this->options['gettrack_slug'] . '/?query=' . rawurlencode( $query ) . "&format=$track_format&admin=1&_wpnonce=$query_nonce" );
				wp_redirect( $alltracks_url );
			}

			if ( $action === 'recalc' ) {
				if ( count( $track_ids ) > 0 ) {
					$exec_t0 = microtime( true );
					foreach ( $track_ids as $id ) {
						$this->calculate_distance( $id );
					}
					$exec_time = round( microtime( true ) - $exec_t0, 1 );
					// translators: placeholders are for number of tracks and numer of seconds elapsed
					$format  = __( 'Recalculated track stats for %1$d track(s) in %2$d seconds', 'trackserver' );
					$message = sprintf( $format, count( $track_ids ), $exec_time );
				} else {
					$message = __( 'No tracks found to recalculate', 'trackserver' );
				}
				setcookie( 'ts_bulk_result', $message, time() + 300 );
				wp_redirect( $referer );
				exit;
			}
		}

		/**
		 * Function to display the bulk_action_result_msg
		 */
		function notice_bulk_action_result() {
			if ( $this->bulk_action_result_msg ) {
				?>
					<div class="updated">
						<p><?php echo nl2br( htmlspecialchars( $this->bulk_action_result_msg ) ); ?></p>
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

				$user_id  = $this->get_author( $id );
				$filename = get_attached_file( $id );
				$xml      = $this->validate_gpx_file( $filename );

				if ( $xml ) {

					// Call 'process_gpx' with 'skip_existing' == true, to prevent
					// uploaded files being processed more than once
					$result = $this->process_gpx( $xml, $user_id, true );

					if ( count( $result['track_ids'] ) > 0 ) {
						$html = '';
						foreach ( $result['track_ids'] as $trk ) {
							$html .= "[tsmap track=$trk]\n";
						}
					} else {
						$html = 'Error: no tracks found in GPX.';
					}
				} else {
					$html = 'Error: file could not be parsed as valid GPX 1.1.';
				}
			}
			return $html;
		}

		function distance( $lat1, $lon1, $lat2, $lon2 ) {
			$radius = 6371000; // meter

			list( $lat1, $lon1, $lat2, $lon2 ) = array_map( 'deg2rad', array( $lat1, $lon1, $lat2, $lon2 ) );

			$dlat = $lat2 - $lat1;
			$dlon = $lon2 - $lon1;
			$a    = pow( sin( $dlat / 2 ), 2 ) + cos( $lat1 ) * cos( $lat2 ) * pow( sin( $dlon / 2 ), 2 );
			$c    = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
			$d    = $radius * $c;
			return (int) $d;
		}

		// https://stackoverflow.com/questions/10053358/measuring-the-distance-between-two-coordinates-in-php
		// Results are the same as with the function above
		function distance2( $lat1, $lon1, $lat2, $lon2 ) {
			$radius = 6371000; // meter

			list( $lat1, $lon1, $lat2, $lon2 ) = array_map( 'deg2rad', array( $lat1, $lon1, $lat2, $lon2 ) );

			$dlat = $lat2 - $lat1;
			$dlon = $lon2 - $lon1;
			$a    = pow( cos( $lat2 ) * sin( $dlon ), 2 ) + pow( cos( $lat1 ) * sin( $lat2 ) - sin( $lat1 ) * cos( $lat2 ) * cos( $dlon ), 2 );
			$b    = sin( $lat1 ) * sin( $lat2 ) + cos( $lat1 ) * cos( $lat2 ) * cos( $dlon );
			$c    = atan2( sqrt( $a ), $b );
			$d    = $radius * $c;
			return (int) $d;
		}

		function calculate_distance( $track_id ) {
			$this->calculate_distance_speed( $track_id );
		}

		function calculate_distance_speed( $track_id ) {
			global $wpdb;

			$sql = $wpdb->prepare( 'SELECT id, latitude, longitude, speed, occurred FROM ' . $this->tbl_locations . ' WHERE trip_id=%d ORDER BY occurred', $track_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$res = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$oldlat   = false;
			$distance = 0;
			foreach ( $res as $row ) {
				if ( $oldlat ) {
					$delta_distance = $this->distance( $oldlat, $oldlon, $row['latitude'], $row['longitude'] );
					$distance      += $delta_distance;

					if ( $row['speed'] == '0' ) {
						$oldtime    = new DateTime( $oldocc );
						$newtime    = new DateTime( $row['occurred'] );
						$delta_time = $newtime->getTimestamp() - $oldtime->getTimestamp();

						// On duplicate timestamps, we assume the delta was 1 second
						if ( $delta_time < 1 ) {
							$delta_time = 1;
						}
						$speed = $delta_distance / $delta_time; // in m/s

						// Update the speed column in the database for this location
						// @codingStandardsIgnoreLine
						$wpdb->update( $this->tbl_locations, array( 'speed' => $speed ), array( 'id' => $row['id'] ), '%f', '%d' );
					}
				}
				$oldlat = $row['latitude'];
				$oldlon = $row['longitude'];
				$oldocc = $row['occurred'];
			}

			if ( $distance > 0 ) {
				// @codingStandardsIgnoreLine
				$wpdb->update( $this->tbl_tracks, array( 'distance' => $distance ), array( 'id' => $track_id ), '%d', '%d' );
			}
		}

		function printf_htmlspecialchars( $input ) {
			return str_replace( '%', '%%', htmlspecialchars( $input ) );
		}

		function register_tsmap_post_type() {

			$slug = $this->options['embedded_slug'];

			register_post_type(
				'tsmap',
				array(
					'label'               => esc_html__( 'Embedded maps', 'trackserver' ),
					'labels'              => array(
						'singular_name' => esc_html__( 'embedded map', 'trackserver' ),
						'add_new_item'  => esc_html__( 'Add new embedded map', 'trackserver' ),  // Translate!!
						'edit_item'     => esc_html__( 'Edit embedded map', 'trackserver' ),
						'search_items'  => esc_html__( 'Search embedded maps', 'trackserver' ),
						'not_found'     => esc_html__( 'No embedded maps found', 'trackserver' ),
					),
					'public'              => true,
					'exclude_from_search' => true,
					'show_ui'             => true,
					'capability_type'     => 'post',
					'hierarchical'        => false,
					'query_var'           => false,
					'supports'            => array( 'title', 'editor', 'author' ),
					'show_in_menu'        => 'trackserver-options',
					'rewrite'             => array( 'slug' => $slug ),
				)
			);
		}

		function get_tsmap_single_template( $template ) {
			global $post;
			if ( $post->post_type == 'tsmap' ) {
				$template = dirname( __FILE__ ) . '/embedded-template.php';
			}
			return $template;
		}

		function get_tsmap_404_template( $template ) {
			global $wp;
			$slug = $this->options['embedded_slug'];
			if (
				( substr( $wp->request, 0, strlen( $slug ) + 1 ) == "${slug}/" ) || // match trailing slash to not match it as a prefix
				( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] == $slug )
			) {
				$template = dirname( __FILE__ ) . '/embedded-404.php';
			}
			return $template;
		}

	} // class
} // if !class_exists

// Main
$trackserver = new Trackserver();
