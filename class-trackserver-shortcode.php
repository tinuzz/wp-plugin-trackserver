<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Shortcode {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	private $shortcode1 = 'tsmap';
	private $shortcode2 = 'tsscripts';
	private $shortcode3 = 'tslink';

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
		$this->add_actions();
	}

	/**
	 * Create a singleton if it doesn't exist and return it.
	 *
	 * @since 5.0
	 */
	public static function get_instance( $trackserver ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $trackserver );
		}
		return self::$instance;
	}

	/**
	 * Add shortcode handlers.
	 *
	 * @since 1.0
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 */
	private function add_actions() {
		add_shortcode( $this->shortcode1, array( $this, 'handle_shortcode1' ) );
		add_shortcode( $this->shortcode2, array( $this, 'handle_shortcode2' ) );
		add_shortcode( $this->shortcode3, array( $this, 'handle_shortcode3' ) );
	}

	/**
	 * Handle the main [tsmap] shortcode
	 *
	 * @since 1.0
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 */
	public function handle_shortcode1( $atts ) {
		global $wpdb, $post;

		$save_atts = $atts;

		$default_height = '480px';
		if ( $post->post_type === 'tsmap' ) {
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

		$atts      = shortcode_atts( $defaults, $atts, $this->shortcode1 );
		$author_id = get_the_author_meta( 'ID' );
		$post_id   = get_the_ID();
		$is_live   = false;

		static $num_maps = 0;
		$div_id          = 'tsmap_' . ++$num_maps;

		$classes = array();
		if ( $atts['class'] ) {
			$classes[] = $atts['class'];
		}
		if ( in_array( $atts['align'], array( 'left', 'center', 'right', 'none' ), true ) ) {
			$classes[] = 'align' . $atts['align'];
		}

		$class_str = '';
		if ( count( $classes ) ) {
			$class_str = 'class="' . implode( ' ', $classes ) . '"';
		}

		if ( ! $atts['track'] ) {
			$atts['track'] = $atts['id'];
		}

		$style      = $this->get_style( $atts, false );      // result is not used
		$points     = $this->get_points( $atts, false );     // result is not used
		$markers    = $this->get_markers( $atts, false );    // result is not used
		$markersize = $this->get_markersize( $atts, false ); // result is not used
		$maxage     = $this->get_maxage( $atts['maxage'] );

		list( $validated_track_ids, $validated_user_ids ) = $this->validate_ids( $atts );

		if ( count( $validated_user_ids ) > 0 ) {
			$is_live = true;
		}

		$tracks              = array();
		$alltracks_url       = false;
		$default_lat         = '51.443168';
		$default_lon         = '5.447200';
		$gettrack_url_prefix = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/' );

		if ( count( $validated_track_ids ) > 0 || count( $validated_user_ids ) > 0 ) {

			$live_tracks   = $this->trackserver->get_live_tracks( $validated_user_ids, $maxage );
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
				$this->trackserver->track_format . "&maxage=$maxage&_wpnonce=$query_nonce";
			$following     = false;

			//foreach ( $validated_track_ids as $validated_id ) {
			foreach ( $all_track_ids as $validated_id ) {

				// For the first live track, set 'follow' to true
				if ( in_array( $validated_id, $live_tracks, true ) && ! $following ) {
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
				if ( ! $this->trackserver->options['fetchmode_all'] ) {

					// Use wp_create_nonce() instead of wp_nonce_url() due to escaping issues
					// https://core.trac.wordpress.org/ticket/4221
					$nonce = wp_create_nonce( 'gettrack_' . $validated_id . '_p' . $post_id );

					switch ( $this->trackserver->track_format ) {
						case 'geojson':
							$trk['track_type'] = 'geojson';
							break;
						default:
							$trk['track_type'] = 'polylinexhr';
					}
					$trk['track_url'] = $gettrack_url_prefix . '?id=' . $validated_id . "&p=$post_id&format=" .
						$this->trackserver->track_format . "&maxage=$maxage&_wpnonce=$nonce";
				}

				$tracks[] = $trk;
			}

			if ( count( $all_track_ids ) ) {
				$sql_in = "('" . implode( "','", $all_track_ids ) . "')";
				$sql    = 'SELECT AVG(latitude) FROM ' . $this->trackserver->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
				$result = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				if ( $result ) {
					$default_lat = $result;
				}
				$sql    = 'SELECT AVG(longitude) FROM ' . $this->trackserver->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
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

		if ( $this->trackserver->options['fetchmode_all'] ) {
			$mapdata['alltracks'] = $alltracks_url;
		}

		if ( $infobar ) {
			if ( in_array( $atts['infobar'], array( 't', 'true', 'y', 'yes' ), true ) ) {
				$mapdata['infobar_tpl'] = htmlspecialchars( $infobar_tpl );
			} else {
				$mapdata['infobar_tpl'] = $atts['infobar'];   // This value is already HTML escaped, because the post as a whole is stored in the database that way
			}
		}

		$this->trackserver->mapdata[]    = $mapdata;
		$out                             = '<div id="' . $div_id . '" ' . $class_str . ' style="width: ' . $atts['width'] . '; height: ' . $atts['height'] . '; max-width: 100%"></div>';
		$this->trackserver->need_scripts = true;

		return $out;
	}

	/**
	 * Stub function for the [tsscripts] shortcode. It doesn't do anything.
	 *
	 * The [tsscripts] shortcode doesn't do anything by itself, but you can use
	 * it to hint Trackserver to include the needed scripts, in case the
	 * shortcode detection falls short otherwise. This is never strictly
	 * necessary, but in complex setups, Trackserver may load its CSS at the
	 * bottom of the page, which could cause awkward rendering of your page. In
	 * those cases, use this shortcode in the outermost loop of your post to fix
	 * this. If this doesn't make any sense to you, please forget about it.
	 *
	 * @since 2.0
	 */
	public function handle_shortcode2( $atts ) {
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
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 */
	public function handle_shortcode3( $atts, $content = '' ) {

		$defaults = array(
			'text'      => '',
			'class'     => '',
			'id'        => false,
			'track'     => false,
			'user'      => false,
			'format'    => 'gpx',
			'maxage'    => false,
			'href_only' => false,
		);

		$atts = shortcode_atts( $defaults, $atts, $this->shortcode3 );

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
			if ( $atts['format'] && in_array( $atts['format'], array( 'gpx' ), true ) ) {
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
			$alltracks_url = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/?query=' . rawurlencode( $query ) . "&p=$post_id&format=$track_format&maxage=$maxage&_wpnonce=$query_nonce" );

			$text = $atts['text'] . $content;
			if ( $text === '' ) {
				$text = 'download ' . $track_format;
			}

			if ( $atts['href_only'] === false ) {
				$out = '<a href="' . $alltracks_url . '" ' . $class_str . '>' . htmlspecialchars( $text ) . '</a>';
			} else {
				$out = $alltracks_url;
			}
		}

		return $out;
	}

	/**
	 * Return a proxy URL for a given URL.
	 *
	 * This function wraps a URL in a different URL, so that it points to this
	 * WordPress and it can be proxied by Trackserver to work around CORS
	 * restrictions. Please read the FAQ for important security information.
	 */
	private function proxy_url( $url, $post_id ) {
		if ( substr( $url, 0, 6 ) === 'proxy:' ) {
			$track_base_url = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/?', ( is_ssl() ? 'https' : 'http' ) );
			$proxy          = base64_encode( substr( $url, 6 ) );
			$proxy_nonce    = wp_create_nonce( 'proxy_' . $proxy . '_p' . $post_id );
			$url            = $track_base_url . 'proxy=' . rawurlencode( $proxy ) . "&p=$post_id&_wpnonce=$proxy_nonce";
		}
		return $url;
	}

	/**
	 * Return style object for a track based on shortcode attributes.
	 *
	 * A function to return a style object from 'color', 'weight' and 'opacity' parameters.
	 * If the $atts array is passed as an argument, the data is initialized. Then the style
	 * array is created. The default is to shift an element off the beginning of the array.
	 * When an array is empty (meaning there are more tracks than values in the parameter),
	 * the last value is restored to be used for subsequent tracks.
	 *
	 * @since 3.0
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 */
	private function get_style( $atts = false, $shift = true ) {

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
	 * Return the value of 'points' for a track based on shortcode attribute.
	 *
	 * @since 3.0
	 */
	private function get_points( $atts = false, $shift = true ) {

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
	 * Return the value of 'markers' for a track based on shortcode attribute.
	 *
	 * @since 3.0
	 */
	private function get_markers( $atts = false, $shift = true ) {

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
	 * Return the value of 'markersize' for a track based on shortcode attribute.
	 *
	 * @since 4.3
	 */
	private function get_markersize( $atts = false, $shift = true ) {

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
	private function get_maxage( $str ) {
		if ( $str === false ) {
			return 0;
		}
		preg_match_all( '/^(\d+)\s*(\w)?$/', $str, $matches );
		$n = (int) $matches[1][0];
		$u = strtolower( $matches[2][0] );
		if ( $u === '' ) {
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
	 * Turn shortcode attributes into lists of validated track IDs and user IDs
	 *
	 * @since 3.0
	 **/
	private function validate_ids( $atts ) {
		$validated_track_ids = array();     // validated array of tracks to display
		$validated_user_ids  = array();      // validated array of user id's whois live track to display
		$author_id           = get_the_author_meta( 'ID' );

		if ( $atts['track'] ) {
			$track_ids = explode( ',', $atts['track'] );
			// Backward compatibility
			if ( in_array( 'live', $track_ids, true ) ) {
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
	 * Handle the 'gettrack' request
	 *
	 * This function handles the 'gettrack' request. If a 'query' parameter is found,
	 * the processing is delegated to handle_gettrack_query(). The rest of this
	 * function handles the request in case of a single 'id' request, which should
	 * only happen from the admin. Only numeric IDs are handled.
	 */
	public function handle_gettrack() {

		global $wpdb;

		$post_id  = ( isset( $_REQUEST['p'] ) ? intval( $_REQUEST['p'] ) : null );
		$track_id = ( isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : null );
		$format   = ( isset( $_REQUEST['format'] ) ? $_REQUEST['format'] : null );

		if ( isset( $_REQUEST['query'] ) ) {
			return $this->handle_gettrack_query();
		}
		if ( isset( $_REQUEST['proxy'] ) ) {
			if ( $this->trackserver->options['enable_proxy'] ) {
				return $this->handle_gettrack_proxy();
			} else {
				$this->trackserver->http_terminate( '403', 'Proxy disabled' );
			}
		}

		// Refuse to serve the track without a valid nonce. Admin screen uses a different nonce.
		if (
			( array_key_exists( 'admin', $_REQUEST ) &&
			! wp_verify_nonce( $_REQUEST['_wpnonce'], 'manage_track_' . $track_id ) ) ||
			( ( ! array_key_exists( 'admin', $_REQUEST ) ) &&
			! wp_verify_nonce( $_REQUEST['_wpnonce'], 'gettrack_' . $track_id . '_p' . $post_id ) )
		) {
			$this->trackserver->http_terminate( '403', "Access denied (t=$track_id)" );
		}

		if ( $track_id ) {

			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' .
				 $this->trackserver->tbl_locations . ' l INNER JOIN ' . $this->trackserver->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id=%d  ORDER BY occurred', $track_id );
			$res = $wpdb->get_results( $sql, ARRAY_A );
			// @codingStandardsIgnoreEnd

			if ( $format === 'gpx' ) {
				$this->send_as_gpx( $res );
			} elseif ( $format === 'geojson' ) {
				$this->send_as_geojson( $res );
			} else {
				$this->send_as_polyline( $res ); // default to 'polyline'
			}
		} else {
			echo "ENOID\n";
		}
	}

	/**
	 * Function to handle a 'gettrack' query
	 *
	 * @since 3.0
	 */
	private function handle_gettrack_query() {
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
			$this->trackserver->http_terminate( '403', "Access denied (q=$query_string)" );
		}

		// 'is_admin' is verified at this point. We use the current user's id as author, to make
		// sure users can only download their own tracks, unless they can 'trackserver_publish'
		if ( $is_admin ) {
			$author_id = get_current_user_id();
		} else {
			$author_id = $this->trackserver->get_author( $post_id );
		}

		$query               = base64_decode( $query_string );
		$query               = json_decode( $query );
		$track_ids           = $query->id;
		$user_ids            = $query->live;
		$validated_track_ids = $this->validate_track_ids( $track_ids, $author_id );
		$validated_user_ids  = $this->validate_user_ids( $user_ids, $author_id );
		$user_track_ids      = $this->trackserver->get_live_tracks( $validated_user_ids, $maxage );
		$track_ids           = array_merge( $validated_track_ids, $user_track_ids );

		// @codingStandardsIgnoreStart
		$sql_in = "('" . implode( "','", $track_ids ) . "')";
		$sql = 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' . $this->trackserver->tbl_locations .
			' l INNER JOIN ' . $this->trackserver->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id IN ' . $sql_in . ' AND l.hidden = 0 ORDER BY trip_id, occurred';
		$res = $wpdb->get_results( $sql, ARRAY_A );
		// @codingStandardsIgnoreEnd

		if ( $format === 'gpx' ) {
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
	private function handle_gettrack_proxy() {
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
				if ( $rc !== 200 ) {
					header( 'HTTP/1.1 ' . $rc . ' ' . wp_remote_retrieve_response_message( $response ) );
					$ct = wp_remote_retrieve_header( $response, 'content-type' );
					if ( $ct !== '' ) {
						header( 'Content-Type: ' . $ct );
					}
				} else {
					header( 'Content-Type: application/xml' );
				}
				print( wp_remote_retrieve_body( $response ) );
			} else {
				$this->trackserver->http_terminate( '500', $response->get_error_message() );
			}
		} else {
			$this->trackserver->http_terminate();
		}
		die();
	}

	/**
	 * Function to output a track with metadata as GeoJSON. Takes a $wpdb result set as input.
	 *
	 * @since 2.2
	 */
	private function send_as_geojson( $res ) {
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
	private function send_as_polyline( $res ) {
		list( $encoded, $metadata ) = $this->polyline_encode( $res );
		$this->send_as_json( $encoded, $metadata );
	}

	/**
	 * Function to actually output data as JSON. Takes encoded location points and metadata
	 * (both arrays) as input.
	 *
	 * @since 2.2
	 */
	private function send_as_json( $encoded, $metadata ) {
		$data = array(
			'track'    => $encoded,
			'metadata' => $metadata,
		);
		header( 'Content-Type: application/json' );
		echo json_encode( $data );
	}

	/**
	 * Send output as GPX 1.1. Takes a $wpdb result set as input.
	 */
	private function send_as_gpx( $res ) {
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
			if ( $row['trip_id'] !== $last_track_id ) {
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

			if ( $row['altitude'] !== '0' ) {
				$trkpt->appendChild( $dom->createElement( 'ele', $row['altitude'] ) );
			}

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
	 * Encode the results of a gettrack query as polyline and send it, with
	 * metadata, as JSON.  Takes a $wpdb result set as input.
	 */
	private function send_alltracks( $res ) {

		$tracks = array();
		foreach ( $res as $row ) {
			$id = $row['trip_id'];
			if ( ! array_key_exists( $id, $tracks ) ) {
				$tracks[ $id ] = array(
					'track' => '',
				);
				// Reset the temporary state. This depends on the points of one track being grouped together!
				$this->previous = array( 0, 0 );
				$index          = 0;
			}
			$tracks[ $id ]['track'] .= $this->polyline_get_chunk( $row['latitude'], $index );
			$index++;
			$tracks[ $id ]['track'] .= $this->polyline_get_chunk( $row['longitude'], $index );
			$index++;
			$tracks[ $id ]['metadata'] = $this->get_metadata( $row );   // Overwrite the value on every row, so the last row remains
		}

		header( 'Content-Type: application/json' );
		echo json_encode( $tracks );
	}

	/**
	 * Function to construct a metadata array from a $wpdb row.
	 *
	 * @since 2.2
	 */
	private function get_metadata( $row ) {
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
	 * Apply Google Polyline algorithm to list of points. Takes a $wpdb result
	 * set as input.
	 *
	 * Because it works on a DB result set directly, it saves two time and CPU
	 * consuming steps: first the assembly of an array of points thats could be
	 * passed to the encoder, and second the mandatory flattening of that array.
	 *
	 * This function was largely copied from E. McConville's Polyline encoder
	 * and some parts are copyright 2009-2015 E. McConville. These parts are
	 * released under the terms of the GNU Lesser General Public License v3.
	 * See https://github.com/emcconville/google-map-polyline-encoding-tool
	 * for more information.
	 *
	 * @since 5.0
	 */
	private function polyline_encode( $res ) {
		$encoded_string = '';
		$index          = 0;
		$this->previous = array( 0, 0 );

		foreach ( $res as $row ) {
			$encoded_string .= $this->polyline_get_chunk( $row['latitude'], $index );
			$index++;
			$encoded_string .= $this->polyline_get_chunk( $row['longitude'], $index );
			$index++;
		}

		// Metadata stuff doesn't really belong here, but this is the only place
		// where we have the last row of the result set available.
		$metadata = $this->get_metadata( $row );
		return array( $encoded_string, $metadata );
	}

	/**
	 * Return a polyline encoded chunk for a single number (either a latitude or a longitude).
	 *
	 * @since 5.0
	 */
	private function polyline_get_chunk( $number, $index ) {
		$precision                    = 5;               // Precision level
		$number                       = (float) $number;
		$number                       = (int) round( $number * pow( 10, $precision ) );
		$diff                         = $number - $this->previous[ $index % 2 ];
		$this->previous[ $index % 2 ] = $number;
		$number                       = $diff;
		$number                       = ( $number < 0 ) ? ~( $number << 1 ) : ( $number << 1 );
		$chunk                        = '';
		while ( $number >= 0x20 ) {
			$chunk   .= chr( ( 0x20 | ( $number & 0x1f ) ) + 63 );
			$number >>= 5;
		}
		$chunk .= chr( $number + 63 );
		return $chunk;
	}

	/**
	 * Function to validate a list of track IDs against user ID and post ID.
	 * It tries to leave the given order of IDs unchanged.
	 *
	 * @since 3.0
	 */
	private function validate_track_ids( $track_ids, $author_id ) {
		global $wpdb;

		if ( count( $track_ids ) === 0 ) {
			return array();
		}

		// Remove all non-numeric values from the tracks array and prepare query
		$track_ids = array_map( 'intval', array_filter( $track_ids, 'is_numeric' ) );
		$sql_in    = "('" . implode( "','", $track_ids ) . "')";

		// If the author has the power, don't check the track's owner
		if ( user_can( $author_id, 'trackserver_publish' ) ) {
			$sql = 'SELECT id FROM ' . $this->trackserver->tbl_tracks . ' WHERE id IN ' . $sql_in;
		} else {
			// Otherwise, filter the list of posts against the author ID
			$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->trackserver->tbl_tracks . ' WHERE id IN ' . $sql_in . ' AND user_id=%d;', $author_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$validated_track_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Restore track order as given in the shortcode
		$trk0 = array();
		foreach ( $track_ids as $tid ) {
			if ( in_array( (string) $tid, $validated_track_ids, true ) ) {
				$trk0[] = $tid;
			}
		}
		return $trk0;
	}


	/**
	 * Validate users against the DB and the author's permission to publish.
	 *
	 * It turns user names into numeric IDs.
	 *
	 * @since 3.0
	 */
	private function validate_user_ids( $user_ids, $author_id ) {
		global $wpdb;

		if ( count( $user_ids ) === 0 ) {
			return array();
		}

		$user_ids = array_map( array( $this, 'get_user_id' ), $user_ids );  // Get numeric IDs
		$user_ids = array_filter( $user_ids );   // Get rid of the falses.

		if ( ! user_can( $author_id, 'trackserver_publish' ) ) {
			$user_ids = array_intersect( $user_ids, array( $author_id ) );   // array containing 0 or 1 elements
		}

		if ( count( $user_ids ) > 0 ) {
			$sql_in             = "('" . implode( "','", $user_ids ) . "')";
			$sql                = 'SELECT DISTINCT(user_id) FROM ' . $this->trackserver->tbl_tracks . ' WHERE user_id IN ' . $sql_in;
			$validated_user_ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			// Restore track order as given in the shortcode
			$usr0 = array();
			foreach ( $user_ids as $uid ) {
				if ( in_array( (string) $uid, $validated_user_ids, true ) ) {
					$usr0[] = $uid;
				}
			}
			return $usr0;
		} else {
			return array();
		}
	}

	/**
	 * Function to return a user ID, given a user name or ID. Unknown users return false.
	 *
	 * @since 3.0
	 */
	private function get_user_id( $user, $property = 'ID' ) {
		if ( $user === '@' ) {
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
			return ( $property === 'ID' ? (int) $user->$property : $user->$property );
		} else {
			return false;
		}
	}

} // Class
