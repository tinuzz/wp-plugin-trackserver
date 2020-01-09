<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-shortcode.php';

if ( ! class_exists( 'Trackserver' ) ) {

	/**
	 * The main plugin class.
	 */
	class Trackserver {

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
				Trackserver_Admin::get_instance( $this )->add_actions();
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

			// Front-end JavaScript and CSS
			add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts' ) );
			add_action( 'wp_footer', array( &$this, 'wp_footer' ) );

			// Shortcodes
			Trackserver_Shortcode::get_instance( $this );

			// Media upload
			add_filter( 'upload_mimes', array( &$this, 'upload_mimes' ) );
			add_filter( 'media_send_to_editor', array( &$this, 'media_send_to_editor' ), 10, 3 );

			// Custom post type for embedded maps
			add_action( 'init', array( &$this, 'wp_init' ) );
			add_filter( 'single_template', array( &$this, 'get_tsmap_single_template' ) );
			add_filter( '404_template', array( &$this, 'get_tsmap_404_template' ) );

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

		function sanitize_option_values( $options ) {
			$options['enable_proxy']  = (bool) $options['enable_proxy'];
			$options['fetchmode_all'] = (bool) $options['fetchmode_all'];
			return $options;
		}

		function admin_menu() {

			require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-settings.php';

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
		 * Handle the request.
		 *
		 * This function compares the incoming request against a list of known
		 * protcols. Protocols can be distinguished by URL (regexp-based),
		 * mandatory GET/POST parameters, request method and Content-Type. If a
		 * match is found, the appropriate request handler is called. If username
		 * and access key are present in the URL, they are used. If no protocol is
		 * matched, the request is passed on to WP.
		 *
		 * @since 1.0
		 * @since 4.4 Use intelligent protocol matchers against a universal Trackserver slug
		 */
		function parse_request( $wp ) {

			// Handle requests to all the Trackserver URL paths. Requests will in principle match both GET
			// and POST requests, unless a 'method' key is present in the protocol's properties.

			$req_uri = $this->get_request_uri();
			$slug    = $this->options['trackserver_slug'];

			$pattern1 = '/^(?<slug>' . preg_quote( $slug, '/' ) . ')\/(?<username>[^\/]+)\/(?<password>[^\/]+)\/?$/';
			$pattern2 = '/^(?<slug>' . preg_quote( $slug, '/' ) . ')\/?$/';

			$protocols = array(
				'gettrack'     => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['gettrack_slug'], '/' ) . ')\/?$/',
				),
				'trackmeold1'  => array(
					'pattern' => '/^(?<slug>' . preg_quote( $slug, '/' ) . ')\/(?<method>requests|export|cloud)\.(?<ext>.*)/',
				),
				'trackme1'     => array(
					'pattern' => '/^(?<slug>' . preg_quote( $slug, '/' ) . ')\/(?<username>[^\/]+)\/(?<password>[^\/]+)\/(?<method>requests|export|cloud)\.(?<ext>.*)/',
				),
				'ulogger'      => array(
					'pattern' => '/^(?<slug>' . preg_quote( $slug, '/' ) . ')\/client\/index\.php/',
				),
				'mapmytracks1' => array(
					'pattern' => $pattern2,
					'params'  => array( 'request' ),
				),
				'upload1'      => array(
					'pattern' => $pattern2,
					'method'  => 'POST',
					'enctype' => 'multipart/form-data',
				),
				'owntracks1'   => array(
					'pattern' => $pattern2,
					'method'  => 'POST',
					'enctype' => 'application/json',
				),
				'get1'         => array(
					'pattern' => $pattern1,
				),
				'get2'         => array(
					'pattern' => $pattern2,
				),

				// The matches below all use dedicated slugs and are DEPRECATED as of v5.0

				'trackmeold2'  => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['trackme_slug'], '/' ) . ')\/(?<method>requests|export|cloud)\.(?<ext>.*)/',
				),
				'trackme2'     => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['trackme_slug'], '/' ) .
						')\/(?<username>[^\/]+)\/(?<password>[^\/]+)\/(?<method>requests|export|cloud)\.(?<ext>.*)/',
				),
				'mapmytracks2' => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['mapmytracks_tag'], '/' ) . ')\/?$/',
				),
				'osmand'       => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['osmand_slug'], '/' ) . ')\/?$/',
				),
				'sendlocation' => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['sendlocation_slug'], '/' ) . ')\/(?<username>[^\/]+)\/(?<password>[^\/]+)\/?$/',
				),
				'upload2'      => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['upload_tag'], '/' ) . ')\/?$/',
					'method'  => 'POST',
					'enctype' => 'multipart/form-data',
				),
				'owntracks2'   => array(
					'pattern' => '/^(?<slug>' . preg_quote( $this->options['owntracks_slug'], '/' ) . ')\/?$/',
					'method'  => 'POST',
					'enctype' => 'application/json',
				),
			);

			foreach ( $protocols as $proto => $props ) {

				// Match the URL
				$n = preg_match( $props['pattern'], $req_uri, $matches );
				if ( $n === 1 ) {

					// Get credentials from the URL if present
					if ( ! empty( $matches['username'] ) ) {
						$username = rawurldecode( stripslashes( $matches['username'] ) );
						$password = rawurldecode( stripslashes( $matches['password'] ) );
					} else {
						$username = null;
						$password = null;
					}

					// Check mandatory parameters
					if ( ! empty( $props['params'] ) ) {
						// Count the number of params that are present in $props['params'],
						// but not in $_REQUEST. There should be none to match this protocol.
						if ( count( array_diff_key( array_flip( $props['params'] ), $_REQUEST ) ) > 0 ) {
							continue;
						}
					}

					// Check mandatory HTTP request method
					if ( ! empty( $props['method'] ) ) {
						if ( $_SERVER['REQUEST_METHOD'] !== $props['method'] ) {
							continue;
						}
					}

					// Check mandatory Content-Type
					if ( ! empty( $props['enctype'] ) ) {
						$req_enctype = strtok( $_SERVER['CONTENT_TYPE'], ';' );    // Strip charset/boundary off header
						if ( $req_enctype !== $props['enctype'] ) {
							continue;
						}
					}

					if ( $proto === 'gettrack' ) {
						Trackserver_Shortcode::get_instance( $this )->handle_gettrack();

					} elseif ( $proto === 'trackmeold1' || $proto === 'trackmeold2' || $proto === 'trackme1' || $proto === 'trackme2' ) {
						require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-trackme.php';
						Trackserver_Trackme::get_instance( $this )->handle_protocol( $matches['method'], $username, $password );

					} elseif ( $proto === 'mapmytracks1' || $proto === 'mapmytracks2' ) {
						require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-mapmytracks.php';
						$client = new Trackserver_Mapmytracks( $this );
						$client->handle_request();

					} elseif ( $proto === 'ulogger' ) {
						require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-ulogger.php';
						$client = new Trackserver_Ulogger( $this );
						$client->handle_request();

					} elseif ( $proto === 'get1' || $proto === 'get2' || $proto === 'osmand' || $proto === 'sendlocation' ) {
						require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-getrequest.php';
						$client = new Trackserver_Getrequest( $this, $username, $password );
						$client->handle_request();

					} elseif ( $proto === 'upload1' || $proto === 'upload2' ) {
						$this->handle_upload();

					} elseif ( $proto === 'owntracks1' || $proto === 'owntracks2' ) {
						$this->handle_owntracks_request();

					} else {
						$this->http_terminate( 500, 'BUG: Unhandled protocol. Please file a bug report.' );
					}
					die();
				}
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
		 * If no credentials are received, a 401 status code is sent. Validation is
		 * delegated to validate_wp_user_pass(). If that function returns a trueish
		 * value, we return it as is. Otherwise, we terminate the request (default)
		 * or return false, if so requested.
		 *
		 * On success, the return value is the validated user's ID. By passing
		 * 'object' as the second argument, the WP_User object is requested and
		 * returned instead.
		 *
		 * @since 1.0
		 */
		function validate_http_basicauth( $return = false, $what = 'id' ) {

			if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				header( 'WWW-Authenticate: Basic realm="Authentication Required"' );
				header( 'HTTP/1.0 401 Unauthorized' );
				die( "Authentication required\n" );
			}

			$valid = $this->validate_wp_user_pass( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $what );
			if ( $return || $valid !== false ) {
				return $valid;
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

			Trackserver_Settings::get_instance( $this )->mapmytracks_settings_html();
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

			Trackserver_Settings::get_instance( $this )->osmand_settings_html();
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

			Trackserver_Settings::get_instance( $this )->trackme_settings_html();
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
