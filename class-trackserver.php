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
		 * @since 5.0
		 * @access private
		 * @var str $leaflet_version
		 */
		var $leaflet_version = '1.9.3';

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

		var $shortcode           = 'tsmap';
		var $shortcode2          = 'tsscripts';
		var $shortcode3          = 'tslink';
		var $track_format        = 'polyline';  // 'polyline' or 'geojson'
		var $trackserver_scripts = array();
		var $trackserver_styles  = array();

		public $permissions;

		/**
		 * Class constructor.
		 *
		 * @since 1.0
		 */
		public function __construct() {
			global $wpdb;

			$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
			$this->tbl_locations = $wpdb->prefix . 'ts_locations';
			$this->init_options();

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
		public function init_options() {
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
		public function debug( $log ) {
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
		public function init_user_meta( $user_id = null ) {

			if ( is_null( $user_id ) ) {
				$user_id = get_current_user_id();
			}
			if ( ! user_can( $user_id, 'use_trackserver' ) ) {
				return;
			}

			// Set default profile values for the given user
			foreach ( $this->user_meta_defaults as $key => $value ) {
				if ( get_user_meta( $user_id, $key, true ) === '' ) {
					update_user_meta( $user_id, $key, $value );
				}
			}

			// Handle 'ts_app_passwords' specially, because it needs existing values
			if ( get_user_meta( $user_id, 'ts_app_passwords', true ) === '' ) {

				$passwords      = array();
				$password_perms = array(
					'ts_trackme_key'      => array( 'read', 'write', 'delete' ),
					'ts_osmand_key'       => array( 'write' ),
					'ts_sendlocation_key' => array( 'write' ),
				);

				// Copy each of the three existing keys to an app password with appropriate permissions, if non-empty
				foreach ( $password_perms as $key => $value ) {
					$srcval = get_user_meta( $user_id, $key, true );
					if ( $srcval !== '' ) {
						$passwords[] = array(
							'password'    => $srcval,
							'permissions' => $value,
						);
					}
				}

				// If none of the keys exist, create one default app password with write permissions only.
				if ( empty( $passwords ) ) {
					$passwords[] = array(
						'password'    => substr( md5( uniqid() ), -8 ),
						'permissions' => array( 'write' ),
					);
				}

				// If the keys have been copied successfully, we can delete the originals.
				if ( update_user_meta( $user_id, 'ts_app_passwords', $passwords ) !== false ) {
					delete_user_meta( $user_id, 'ts_trackme_key' );
					delete_user_meta( $user_id, 'ts_osmand_key' );
					delete_user_meta( $user_id, 'ts_sendlocation_key' );
				}
			}
		}

		/**
		 * Add common actions and filters.
		 *
		 * @since 1.0
		 */
		private function add_actions() {

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
		public function wp_init() {
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
		public function wp_loaded() {
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
		public function update_option( $option, $value ) {
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
		public function delete_option( $option ) {
			if ( array_key_exists( $option, $this->options ) ) {
				unset( $this->options[ $option ] );
				update_option( 'trackserver_options', $this->options );
			}
		}

		/**
		 * Wrapper for wp_enqueue_script() that keeps a list of this plugin's scripts
		 *
		 * @since 5.0
		 */
		private function wp_enqueue_script( ...$args ) {
			$this->trackserver_scripts[] = $args[0];
			wp_enqueue_script( ...$args );
		}

		/**
		 * Wrapper for wp_enqueue_style() that keeps a list of this plugin's styles
		 *
		 * @since 5.0
		 */
		private function wp_enqueue_style( ...$args ) {
			$this->trackserver_styles[] = $args[0];
			wp_enqueue_style( ...$args );
		}

		/**
		 * Function to load scripts for both front-end and admin. It is called from the
		 * 'wp_enqueue_scripts' and 'admin_enqueue_scripts' handlers.
		 *
		 * @since 1.0
		 */
		public function load_common_scripts() {

			$this->wp_enqueue_style( 'leaflet-js', TRACKSERVER_JSLIB . 'leaflet-' . $this->leaflet_version . '/leaflet.css' );
			$this->wp_enqueue_script( 'leaflet-js', TRACKSERVER_JSLIB . 'leaflet-' . $this->leaflet_version . '/leaflet.js', array(), false, true );
			$this->wp_enqueue_style( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-1.0.2/leaflet.fullscreen.css' );
			$this->wp_enqueue_script( 'leaflet-fullscreen', TRACKSERVER_JSLIB . 'leaflet-fullscreen-1.0.2/Leaflet.fullscreen.min.js', array(), false, true );
			$this->wp_enqueue_script( 'leaflet-omnivore', TRACKSERVER_PLUGIN_URL . 'trackserver-omnivore.js', array(), TRACKSERVER_VERSION, true );
			$this->wp_enqueue_style( 'trackserver', TRACKSERVER_PLUGIN_URL . 'trackserver.css', array(), TRACKSERVER_VERSION );
			$this->wp_enqueue_script( 'promise-polyfill', TRACKSERVER_JSLIB . 'promise-polyfill-6.0.2/promise.min.js', array(), false, true );

			// To be localized in wp_footer() with data from the shortcode(s). Enqueued last, in wp_enqueue_scripts.
			// Also localized and enqueued in admin_enqueue_scripts
			wp_register_script( 'trackserver', TRACKSERVER_PLUGIN_URL . 'trackserver.js', array(), TRACKSERVER_VERSION, true );

			$settings = array(
				'tile_url'        => $this->options['tile_url'],
				'attribution'     => $this->options['attribution'],
				'plugin_url'      => TRACKSERVER_PLUGIN_URL,
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
		public function wp_enqueue_scripts( $force = false ) {
			global $post;

			if ( $force || $this->detect_shortcode() ) {

				/* For embedded maps, we want to print only the necessary scripts, and
				 * skip scripts added by other plugins. We use an extra action in the
				 * 'wp_head' hook, that runs after 'wp_enqueue_scripts' (prio 1) and
				 * before 'wp_print_styles' (prio 8) and 'wp_print_head_scripts' (prio
				 * 9). Another approach would be to just clear the queue here and start
				 * empty, but we have no way of knowing what will be added afterwards,
				 * so that wouldn't be effective.
				 */
				if ( $post->post_type === 'tsmap' ) {
					add_action( 'wp_head', array( &$this, 'tsmap_dequeue_scripts' ), 5 );
				}

				$this->load_common_scripts();

				// Live-update only on the front-end, not in admin
				$this->wp_enqueue_style( 'leaflet-messagebox', TRACKSERVER_JSLIB . 'leaflet-messagebox-1.0/leaflet-messagebox.css' );
				$this->wp_enqueue_script( 'leaflet-messagebox', TRACKSERVER_JSLIB . 'leaflet-messagebox-1.0/leaflet-messagebox.js', array(), false, true );
				$this->wp_enqueue_style( 'leaflet-liveupdate', TRACKSERVER_JSLIB . 'leaflet-liveupdate-1.1/leaflet-liveupdate.css' );
				$this->wp_enqueue_script( 'leaflet-liveupdate', TRACKSERVER_JSLIB . 'leaflet-liveupdate-1.1/leaflet-liveupdate.js', array(), false, true );

				// Enqueue the main script last
				$this->wp_enqueue_script( 'trackserver' );

				// Instruct wp_footer() that we already have the scripts.
				$this->have_scripts = true;
			}
		}

		/**
		 * Function added to the wp_head() hook to dequeue unneeded styles and scripts for embedded maps.
		 *
		 * This function takes the currently queued scripts and styles, and
		 * everything that is not added by Trackserver is removed from the queue.
		 * See wp_enqueue_scripts() above for more information.
		 *
		 * @since 5.0
		 */
		public function tsmap_dequeue_scripts() {

			foreach ( wp_scripts()->queue as $script ) {
				if ( ! in_array( $script, $this->trackserver_scripts, true ) ) {
					wp_dequeue_script( $script );
				}
			}

			foreach ( wp_styles()->queue as $css ) {
				if ( ! in_array( $css, $this->trackserver_styles, true ) ) {
					wp_dequeue_style( $css );
				}
			}
		}

		/**
		 * Function to load the gettext domain for this plugin. Called from the 'init' hook.
		 *
		 * @since 4.3
		 */
		private function load_textdomain() {
			// Load the MO file for the current language.
			load_plugin_textdomain( 'trackserver', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
		}

		/**
		 * Detect shortcode
		 *
		 * This function tries to detect Trackserver's shortcodes [tsmap] or [tsscripts]
		 * in the current query's output and returns true if it is found.
		 *
		 * @since 1.0
		 */

		private function detect_shortcode() {
			global $wp_query;

			$posts = $wp_query->posts;
			if ( is_array( $posts ) ) {

				foreach ( $posts as $post ) {
					if ( $this->has_shortcode( $post ) ) {
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * Function to find the Trackserver shortcodes in the content of a post or page
		 *
		 * @since 2.0
		 */
		private function has_shortcode( $post ) {
			$pattern = get_shortcode_regex();
			if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches )
				&& array_key_exists( 2, $matches )
				&& ( in_array( $this->shortcode, $matches[2], true ) || in_array( $this->shortcode2, $matches[2], true ) ) ) {
					return true;
			}
			return false;
		}

		/**
		 * Provision the JavaScript that initializes the map(s) with settings and data
		 *
		 * @since 2.0
		 */
		public function wp_footer() {
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
		 * @since 5.0 Use intelligent protocol matchers against a universal Trackserver slug
		 */
		public function parse_request( $wp ) {

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
						require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-owntracks.php';
						Trackserver_OwnTracks::get_instance( $this )->handle_request();

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
		 * @since 5.0
		 */
		private function get_request_uri() {
			global $wp_rewrite;
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
				if ( $request_uri === $wp_rewrite->index ) {
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
		public function http_terminate( $http = '403', $message = 'Access denied' ) {
			http_response_code( $http );
			header( 'Content-Type: text/plain' );
			echo $message . "\n";
			die();
		}

		/**
		 * Return a list of user IDs that share their location with us.
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
		private function get_users_sharing( $user ) {
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
		 * Return an array of user IDs who we follow, that are sharing with us.
		 *
		 * The list of users sharing their location with us is filtered for the
		 * users we are following. An empty list means we follow all users. If
		 * there are positive usernames in the list, we intersect the list of
		 * sharing users with the list of users we follow.  If there are negative
		 * usernames in the list, they are removed from the final list.
		 *
		 * @since 4.1
		 */
		public function get_followed_users( $user ) {
			$friends   = $this->get_users_sharing( $user );
			$following = get_user_meta( $user->ID, 'ts_owntracks_follow', true );
			if ( empty( $following ) ) {
				return $friends;
			}
			$following   = array_unique( array_map( 'trim', explode( ',', $following ) ) );  // convert to array and trim whitespace
			$do_follow   = array( $user->ID );
			$dont_follow = array();

			foreach ( $following as $f ) {
				$ff = ( $f[0] === '!' ? substr( $f, 1 ) : $f );
				$u  = get_user_by( 'login', $ff );
				if ( ! $u ) {   // username not found
					continue;
				}
				if ( $f[0] === '!' ) {
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

		/**
		 * Validate credentials for basic HTTP authentication.
		 *
		 * If no credentials are received, a 401 status code is sent. Validation is
		 * delegated to validate_credentials(). If that function returns a trueish
		 * value, we return it as is. Otherwise, we terminate the request (default)
		 * or return false, if so requested.
		 *
		 * On success, the return value is the validated user's ID. By passing
		 * 'object' as the second argument, the WP_User object is requested and
		 * returned instead.
		 *
		 * @since 1.0
		 */
		public function validate_http_basicauth( $return = false, $what = 'id' ) {

			if ( ! isset( $_SERVER['PHP_AUTH_USER'] ) ) {
				header( 'WWW-Authenticate: Basic realm="Authentication Required"' );
				header( 'HTTP/1.0 401 Unauthorized' );
				die( "Authentication required\n" );
			}

			$valid = $this->validate_credentials( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $what, true );
			if ( $return || $valid !== false ) {
				return $valid;
			}

			$this->http_terminate( '403', 'Username or password incorrect' );
		}

		/**
		 * Validate given credentials against user metadata and optionally the WP
		 * password.
		 *
		 * With valid credentials, this function returns the authenticated user's
		 * ID by default, or a WP_User object on request (3rd argument). Otherwise,
		 * it returns false.
		 *
		 * @since 5.0
		 */
		public function validate_credentials( $username, $password, $what = 'id', $wppass = false ) {
			if ( empty( $username ) || empty( $password ) ) {
				return false;
			}
			$user = get_user_by( 'login', $username );
			if ( $user && user_can( $user, 'use_trackserver' ) ) {

				$this->init_user_meta( $user->ID );

				if ( $wppass ) {
					$hash = $user->data->user_pass;
					if ( wp_check_password( $password, $hash, $user->ID ) ) {
						$this->permissions = array( 'read', 'write', 'delete' );
						return ( $what === 'object' ? $user : $user->ID );
					}
				}

				$passwords = get_user_meta( $user->ID, 'ts_app_passwords', true );
				foreach ( $passwords as $entry ) {
					if ( $password === $entry['password'] ) {
						$this->permissions = $entry['permissions'];
						return ( $what === 'object' ? $user : $user->ID );
					}
				}
			}
			return false;
		}

		/**
		 * Validate HTTP basic authentication, only if a username and password were sent in the request.
		 *
		 * If no credentials are found, return NULL.
		 *
		 * This function is meant to be used, where HTTP basic auth is optional,
		 * and authentication will be handled by another mechanism if it is not
		 * used. The actual validation is delegated to validate_http_basicauth().
		 *
		 * By default, invalid credentials will terminate the request.
		 *
		 * @since 4.3
		 */
		public function try_http_basicauth( $return = false, $what = 'id' ) {
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
		public function utc_to_local_offset( $ts ) {

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

		public function mapmytracks_insert_points( $points, $trip_id, $user_id ) {
			global $wpdb;

			if ( $points ) {
				$now     = current_time( 'Y-m-d H:i:s' );
				$offset  = $this->utc_to_local_offset( $points[0]['timestamp'] );
				$sqldata = array();

				foreach ( $points as $p ) {
					$ts       = $p['timestamp'] + $offset;
					$occurred = date( 'Y-m-d H:i:s', $ts ); // phpcs:ignore
					$fenced   = $this->is_geofenced( $user_id, $p );

					if ( $fenced === 'discard' ) {
						continue;
					}
					$hidden    = ( $fenced === 'hide' ? 1 : 0 );
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
				if ( strcasecmp( substr( $f['name'], -4 ), '.gpx' ) === 0 ) {
					if ( $f['error'] === 0 && move_uploaded_file( $f['tmp_name'], $filename ) ) {
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
			if ( $message === '' ) {
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
				$trip_name = substr( (string) $trk->name, 0, 255 );
				$comment   = substr( (string) $trk->desc, 0, 255 );

				// @codingStandardsIgnoreLine
				if ( $skip_existing && ( $trk_id = $this->get_track_id_by_name( $trip_name, $user_id ) ) ) {
					$track_ids[] = $trk_id;
				} else {
					foreach ( $trk->trkseg as $trkseg ) {
						foreach ( $trkseg->trkpt as $trkpt ) {
							$trkpt_ts = $this->parse_iso_date( (string) $trkpt->time );
							if ( ! $trip_start ) {
								$trip_start = date( 'Y-m-d H:i:s', $trkpt_ts ); // phpcs:ignore
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
						'comment' => $comment,
					);
					$format = array( '%d', '%s', '%s', '%s', '%s' );
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

			// This is a nice query that gives the exact result we need, but at times it seems to be really slow.
			/*
			$sql = 'SELECT DISTINCT t.user_id, l.trip_id AS id FROM ' . $this->tbl_tracks . ' t INNER JOIN ' . $this->tbl_locations . ' l ' .
				'ON t.id = l.trip_id INNER JOIN (SELECT t2.user_id, MAX(l2.occurred) AS endts FROM ' . $this->tbl_locations . ' l2 ' .
				'INNER JOIN ' . $this->tbl_tracks . ' t2 ON l2.trip_id = t2.id GROUP BY t2.user_id) uu ON l.occurred = uu.endts ' .
				'AND t.user_id = uu.user_id WHERE t.user_id IN ' . $sql_in;
			*/

			$track_ids = array();
			foreach ( $user_ids as $uid ) {
				// @codingStandardsIgnoreStart
				$sql = $wpdb->prepare(
					'SELECT l.trip_id AS id FROM ' . $this->tbl_tracks . ' t INNER JOIN ' . $this->tbl_locations . ' l ' .
					'ON t.id = l.trip_id WHERE t.user_id=%d ',
					$uid
				);
				// @codingStandardsIgnoreEnd

				if ( $maxage > 0 ) {
					$ts   = gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) - $maxage ) );
					$sql .= " AND l.occurred > '$ts' ";
				}

				$sql .= 'ORDER BY l.occurred DESC LIMIT 0,1';
				$res  = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( ! is_null( $res ) ) {
					$track_ids[] = (int) $res['id'];
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
			$per_page = ( $per_page === 0 ? 20 : $per_page );
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

		function profiles_html() {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
			}
			echo '<h2>Trackserver map profiles</h2>';
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
						if ( $mod['action'] === 'delete' ) {
							$delete_ids[] = $loc_ids[ $loc_index ]->id;
						} elseif ( $mod['action'] === 'move' ) {
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
		 * Function to handle a profile update for the current user
		 *
		 * If this is called, check_admin_referer() has already succeeded.
		 *
		 * @since 1.9
		 */
		function process_profile_update() {
			$user_id       = get_current_user_id();
			$data          = $_POST['ts_user_meta'];
			$valid_fields  = array(
				'ts_owntracks_share',
				'ts_owntracks_follow',
				'ts_infobar_template',
			);
			$valid_actions = array( 'hide', 'discard' );

			// Hijack the request if a 'Delete password' button was pressed.
			if ( $_POST['apppass_action'] === 'delete' ) {

				$passwords = get_user_meta( $user_id, 'ts_app_passwords', true );
				unset( $passwords[ (int) $_POST['apppass_id'] ] );
				$passwords = array_values( $passwords );  // renumber from 0
				update_user_meta( $user_id, 'ts_app_passwords', $passwords );
				$message = 'App password deleted.';

			} elseif ( $_POST['apppass_action'] === 'add' ) {
				if ( ! ( empty( $_POST['password'] ) || empty( $_POST['permission'] ) ) ) {

					$passwords   = get_user_meta( $user_id, 'ts_app_passwords', true );
					$passwords[] = array(
						'password'    => $_POST['password'],
						'permissions' => $_POST['permission'],
						'created'     => wp_date( 'Y-m-d H:i:s' ),
					);
					update_user_meta( $user_id, 'ts_app_passwords', $passwords );
					$message = 'App password added.';

				}
			} elseif ( is_array( $data ) ) {

				foreach ( $data as $meta_key => $meta_value ) {
					if ( in_array( $meta_key, $valid_fields, true ) ) {
						update_user_meta( $user_id, $meta_key, $meta_value );
					}
				}

				$geofence_lat    = $_POST['ts_geofence_lat'];
				$geofence_lon    = $_POST['ts_geofence_lon'];
				$geofence_radius = $_POST['ts_geofence_radius'];
				$geofence_action = $_POST['ts_geofence_action'];

				if ( is_array( $geofence_lat ) && is_array( $geofence_lon ) && is_array( $geofence_radius ) && is_array( $geofence_action ) ) {
					$geofences = array();
					$keys      = array_keys( $geofence_lat );  // The keys should be the same for all relevant arrays, normally a 0-based index.
					foreach ( $keys as $k ) {
						$newfence = array(
							'lat'    => (float) $geofence_lat[ $k ],
							'lon'    => (float) $geofence_lon[ $k ],
							'radius' => abs( (int) $geofence_radius[ $k ] ),
							'action' => ( in_array( $geofence_action[ $k ], $valid_actions, true ) ? $geofence_action[ $k ] : 'hide' ),
						);
						if ( ! in_array( $newfence, $geofences, true ) ) {
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
			return count( $valid ) === 1;
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
			if ( $type === 'application/gpx+xml' ) {

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

					if ( $row['speed'] === '0' ) {
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
			if ( $post->post_type === 'tsmap' ) {
				$template = dirname( __FILE__ ) . '/embedded-template.php';
			}
			return $template;
		}

		function get_tsmap_404_template( $template ) {
			global $wp;
			$slug = $this->options['embedded_slug'];
			if (
				( substr( $wp->request, 0, strlen( $slug ) + 1 ) === "${slug}/" ) || // match trailing slash to not match it as a prefix
				( isset( $_REQUEST['post_type'] ) && $_REQUEST['post_type'] === $slug )
			) {
				$template = dirname( __FILE__ ) . '/embedded-404.php';
			}
			return $template;
		}

	} // class
} // if !class_exists

// Main
$trackserver = new Trackserver();
