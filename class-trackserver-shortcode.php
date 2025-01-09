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
	private $attr_data  = array();
	private $track_type = null;
	private $shortcode_data;

	private $map_attr_defaults = array(
		'width'       => '100%',
		'height'      => '480px',
		'align'       => '',
		'class'       => '',
		'continuous'  => true,
		'infobar'     => false,
		'zoom'        => false,
		'live'        => null,
		'maxage'      => false,
	);

	private $link_attr_defaults = array(
		'format'    => 'gpx',
		'class'     => '',
		'text'      => '',
		'href_only' => false,
	);

	private $item_attr_defaults = array(
		'id'         => false,
		'track'      => false,
		'user'       => false,
		'gpx'        => false,
		'kml'        => false,
		'json'       => false,
		'markers'    => true,
		'markersize' => 5,
		'color'      => false,
		'weight'     => false,
		'opacity'    => false,
		'dash'       => false,
		'points'     => false,
		'arrows'     => false,
		'delay'      => false,
	);

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
		add_filter( 'no_texturize_shortcodes', array( $this, 'no_texturize_shortcodes' ) );
	}

	/**
	 * Add filter to declare our main shortcode not to be texturized.
	 *
	 * @since 5.1
	 */
	public function no_texturize_shortcodes( $shortcodes ) {
		$shortcodes[] = $this->shortcode1;
		return $shortcodes;
	}

	/**
	 * Return a default track format as derived from settings.
	 *
	 * @since 5.1
	 */
	private function get_default_track_type() {

		if ( $this->trackserver->options['fetchmode_all'] ) {
			return 'polyline';
		}

		if ( is_null( $this->track_type ) ) {
			switch ( $this->trackserver->track_format ) {
				case 'geojson':
					$this->track_type = 'geojson';
					break;
				default:
					$this->track_type = 'polylinexhr';
			}
		}

		return $this->track_type;
	}

	/**
	 * Parse shortcode attributes and store values in a class property.
	 *
	 * @since 5.1
	 */
	private function parse_shortcode_atts( $atts, $mode = 'map' ) {
		global $post;

		$instance_defaults = ( $mode === 'link' ? $this->link_attr_defaults : $this->map_attr_defaults );
		$defaults          = array_merge( $instance_defaults, $this->item_attr_defaults );

		if ( $post->post_type === 'tsmap' ) {
			$defaults['height'] = '100%';
		}

		$track_ids = array();
		$user_ids  = array();
		$atts      = shortcode_atts( $defaults, $atts, $this->shortcode1 );
		$this->init_multivalue_atts( $atts );

		if ( $atts['track'] === false ) {
			$atts['track'] = $atts['id'];
		}
		if ( $atts['track'] ) {
			$track_ids = explode( ',', $atts['track'] );
		}
		if ( $atts['user'] ) {
			$user_ids = explode( ',', $atts['user'] );
		}

		foreach ( $instance_defaults as $k => $v ) {
			if ( $atts[ $k ] ) {
				$this->shortcode_data['config'][ $k ] = $atts[ $k ];
			} else {
				$this->shortcode_data['config'][ $k ] = $v;
			}
		}

		$this->shortcode_data['config']['continuous'] = $this->get_content_boolean( $atts['continuous'], true );
		$this->shortcode_data['config']['live']       = $this->get_content_boolean( $atts['live'], false );
		$this->shortcode_data['config']['zoom']       = ( $atts['zoom'] !== false ? intval( $atts['zoom'] ) : false );
		$this->shortcode_data['config']['fit']        = ( $atts['zoom'] !== false ? false : true );  // zoom is always set, so we need a signal for altering fitBounds() options

		if ( $atts['infobar'] !== false ) {
			$this->shortcode_data['config']['infobar'] = ( in_array( $atts['infobar'], array( 'true', 't', 'yes', 'y' ), true ) ? true : $atts['infobar'] ); // selectively convert to boolean
		}

		if ( in_array( $atts['infobar'], array( 'true', 't', 'yes', 'y', 'false', 'f', 'no', 'n' ), true ) ) {
			$this->shortcode_data['config']['infobar']     = $this->get_content_boolean( $atts['infobar'] );
			$this->shortcode_data['config']['infobar_tpl'] = null;
		} elseif ( $atts['infobar'] !== false ) {    // set to other value
			$this->shortcode_data['config']['infobar']     = true;
			$this->shortcode_data['config']['infobar_tpl'] = $atts['infobar'];    // Already HTML escaped.
		}

		$this->shortcode_data['config']['maxage']                              = $this->get_age_seconds( $atts['maxage'] );
		list( $validated_track_ids, $validated_user_ids, $validated_live_ids ) = $this->validate_ids( $track_ids, $user_ids );
		$this->shortcode_data['user_ids']                                      = array_unique( array_merge( $this->shortcode_data['user_ids'], $validated_user_ids ) );
		$this->shortcode_data['track_ids']                                     = $validated_track_ids;

		// Add static tracks
		foreach ( $validated_track_ids as $id ) {
			// phpcs:ignore
			$trk = array(
				'track_id'   => $id,
				'track_type' => $this->get_default_track_type(),
				'style'      => $this->get_style(),
				'points'     => $this->get_boolean_att( 'points' ),
				'arrows'     => $this->get_boolean_att( 'arrows' ),
				'markers'    => $this->get_markers(),
				'markersize' => $this->get_markersize(),
				'is_live'    => false,
				'follow'     => false,
			);
			$this->shortcode_data['tracks'][ $id ] = $trk;
		}

		// Add live tracks
		$following = false;
		foreach ( $validated_live_ids as $user_id => $track_id ) {

			// For the first live track, set 'follow' to true
			if ( $following === false ) {
				$following = true;
				$follow    = true;
			} else {
				$follow = false;
			}

			$trk = array(
				'track_id'   => $track_id,
				'track_type' => $this->get_default_track_type(),
				'style'      => $this->get_style(),
				'points'     => $this->get_boolean_att( 'points' ),
				'arrows'     => $this->get_boolean_att( 'arrows' ),
				'markers'    => $this->get_markers(),
				'markersize' => $this->get_markersize(),
				'is_live'    => true,
				'follow'     => $follow,
			);
			$this->shortcode_data['tracks'][ $track_id ] = $trk;
		}

		$this->shortcode_data['all_track_ids'] = array_unique( array_merge( $this->shortcode_data['all_track_ids'], array_keys( $this->shortcode_data['tracks'] ) ) );

		// Add GPX and KML tracks
		foreach ( array( 'gpx', 'kml', 'json' ) as $type ) {
			if ( $atts[ $type ] ) {
				$urls = explode( ' ', $atts[ $type ] );
				$j    = 0;
				foreach ( $urls as $u ) {
					if ( ! empty( $u ) ) {
						$u        = $this->proxy_url( $u );
						$track_id = $type . $j;
						$this->shortcode_data['tracks'][ $track_id ] = array(
							'track_id'   => $track_id,
							'track_type' => $type,
							'track_url'  => $u,
							'style'      => $this->get_style(),
							'points'     => $this->get_boolean_att( 'points' ),
							'arrows'     => $this->get_boolean_att( 'arrows' ),
							'markers'    => $this->get_markers(),
							'markersize' => $this->get_markersize(),
							'is_live'    => false,
							'follow'     => false,
						);
						++$j;
					}
				}
			}
		}
	}

	/**
	 * Process the content of the main shortcode.
	 *
	 * This function parses the content of the shortcode and extracts track data
	 * and other things from it. The code is partially borrowed from WordPress' own
	 * shortcode parsing.
	 *
	 * @since 5.1
	 */
	private function parse_shortcode_content( $content ) {

		if ( ! str_contains( $content, '{' ) ) {
			return array();
		}
		$content = preg_replace( "/[\x{00a0}\x{200b}\x09\x0a\x0d]+/u", ' ', $content );
		$pat     = get_shortcode_atts_regex();
		$atts    = array(
			'track' => array(),
			'user'  => array(),
			'gpx'   => array(),
			'kml'   => array(),
			'json'  => array(),
		);
		$types   = apply_filters( 'trackserver_content_types', array_keys( $atts ) );
		$types   = implode( '|', $types );

		preg_match_all( '@\{\s*(' . $types . ') ([^\{\}\x00-\x1f]+)\}@', $content, $matches );
		for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
			$type = $matches[1][ $i ];
			if ( ! array_key_exists( $type, $atts ) ) {
				$atts[ $type ] = array();
			}

			$record = array();
			$args   = $matches[2][ $i ];
			preg_match_all( $pat, $args, $m_arg, PREG_SET_ORDER );

			foreach ( $m_arg as $m ) {
				if ( ! empty( $m[1] ) ) {
					$record[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
				} elseif ( ! empty( $m[3] ) ) {
					$record[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
				} elseif ( ! empty( $m[5] ) ) {
					$record[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
				} elseif ( isset( $m[7] ) && strlen( $m[7] ) ) {
					$record[ strtolower( $m[7] ) ] = 'y';
				} elseif ( isset( $m[8] ) && strlen( $m[8] ) ) {
					$record[ strtolower( $m[8] ) ] = 'y';
				} elseif ( isset( $m[9] ) ) {
					$record[ strtolower( $m[9] ) ] = 'y';
				}
			}

			// Fill in default values for missing attributes, much like WP's shortcode_atts() does.
			foreach ( $this->item_attr_defaults as $name => $val ) {
				if ( ! array_key_exists( $name, $record ) ) {
					$record[ $name ] = $val;
				}
			}

			# For tracks and users, 'id' is mandatory, and it is used as an array key.
			if ( $type === 'track' || $type === 'user' ) {
				if ( isset( $record['id'] ) ) {
					$atts[ $type ][ $record['id'] ] = $record;
				}
			} else {
					$atts[ $type ][] = $record;
			}
		}

		$track_ids = array_keys( $atts['track'] );
		$user_ids  = array_keys( $atts['user'] );

		list( $validated_track_ids, $validated_user_ids, $validated_live_ids ) = $this->validate_ids( $track_ids, $user_ids );
		$this->shortcode_data['user_ids']                                      = array_unique( array_merge( $this->shortcode_data['user_ids'], $validated_user_ids ) );
		$this->shortcode_data['track_ids']                                     = $validated_track_ids;

		// Add static tracks
		foreach ( $validated_track_ids as $id ) {
			// phpcs:ignore
			$trk = array(
				'track_id'   => $id,
				'track_type' => $this->get_default_track_type(),
				'style'      => $this->get_content_style( $atts['track'][ $id ] ),
				'points'     => $this->get_content_boolean( $atts['track'][ $id ]['points'] ),
				'arrows'     => $this->get_content_boolean( $atts['track'][ $id ]['arrows'] ),
				'markers'    => $this->get_content_markers( $atts['track'][ $id ]['markers'] ),
				'markersize' => $this->get_content_markersize( $atts['track'][ $id ]['markersize'] ),
				'is_live'    => false,
				'follow'     => false,
			);
			$this->shortcode_data['tracks'][ $id ] = $trk;
		}

		// Add live tracks
		$following = false;
		foreach ( $validated_live_ids as $user_id => $track_id ) {

			// For the first live track, set 'follow' to true
			if ( $following === false ) {
				$following = true;
				$follow    = true;
			} else {
				$follow = false;
			}

			$trk = array(
				'track_id'   => $track_id,
				'track_type' => $this->get_default_track_type(),
				'style'      => $this->get_content_style( $atts['user'][ $user_id ] ),
				'points'     => $this->get_content_boolean( $atts['user'][ $user_id ]['points'] ),
				'arrows'     => $this->get_content_boolean( $atts['user'][ $user_id ]['arrows'] ),
				'markers'    => $this->get_content_markers( $atts['user'][ $user_id ]['markers'] ),
				'markersize' => $this->get_content_markersize( $atts['user'][ $user_id ]['markersize'] ),
				'is_live'    => true,
				'follow'     => $follow,
			);
			$this->shortcode_data['tracks'][ $track_id ] = $trk;

		}

		$this->shortcode_data['all_track_ids'] = array_unique( array_merge( $this->shortcode_data['all_track_ids'], array_keys( $this->shortcode_data['tracks'] ) ) );

		// Add GPX and KML tracks. If 'url' is not set, no track is added.
		foreach ( array( 'gpx', 'kml', 'json' ) as $type ) {
			$j = 0;
			foreach ( $atts[ $type ] as $trk ) {
				if ( ! empty( $trk['url'] ) ) {
					$u        = $this->proxy_url( $trk['url'] );
					$track_id = $type . $j;
					$this->shortcode_data['tracks'][ $track_id ] = array(
						'track_id'   => $track_id,
						'track_type' => $type,
						'track_url'  => $u,
						'style'      => $this->get_content_style( $trk ),
						'points'     => $this->get_content_boolean( $trk['points'] ),
						'arrows'     => $this->get_content_boolean( $trk['arrows'] ),
						'markers'    => $this->get_content_markers( $trk['markers'] ),
						'markersize' => $this->get_content_markersize( $trk['markersize'] ),
						'is_live'    => false,
						'follow'     => false,
					);
					++$j;
				}
			}
		}
	}

	/**
	 * Return the initial lat/lng for the center of the map, based on the tracks it contains
	 *
	 * @since 5.1
	 */
	private function get_default_latlng() {
		global $wpdb;

		$default_lat = '51.443168';
		$default_lng = '5.447200';

		if ( count( $this->shortcode_data['all_track_ids'] ) ) {
			$sql_in = "('" . implode( "','", $this->shortcode_data['all_track_ids'] ) . "')";
			$sql    = 'SELECT AVG(latitude) FROM ' . $this->trackserver->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
			$result = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $result ) {
				$default_lat = $result;
			}
			$sql    = 'SELECT AVG(longitude) FROM ' . $this->trackserver->tbl_locations . ' WHERE trip_id IN ' . $sql_in;
			$result = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $result ) {
				$default_lng = $result;
			}
		}
		return array( $default_lat, $default_lng );
	}

	/**
	 * Initialize the structure for the shortcode data. This has to be done for each instance of any of our shortcodes.
	 *
	 * @since 5.1
	 */
	private function init_shortcode_data() {
		$this->shortcode_data = array(
			'tracks'        => array(),
			'points'        => array(),
			'user_ids'      => array(),
			'track_ids'     => array(),
			'all_track_ids' => array(),
			'config'        => array(),
		);
	}

	/**
	 * Handle the main [tsmap] shortcode
	 *
	 * This function processes the shortcode attributes and content to generate maps.
	 * Its output is two things:
	 * - It adds an element to $this->trackserver->mapdata, representing all data for this map.
	 * - It returns a 'div' element, for rendering the map.
	 *
	 * @since 1.0
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 * @since 5.1 Prevent expanding the shortcode outside The Loop.
	 * @since 5.2 Refactoring
	 */
	public function handle_shortcode1( $atts, $content = null ) {
		global $wpdb, $post;

		if ( ! in_the_loop() ) {
			return '';
		}

		static $num_maps = 0;
		$div_id          = 'tsmap_' . ++$num_maps;

		// Set $this->shortcode_data
		$this->init_shortcode_data();
		$this->parse_shortcode_atts( $atts );
		$this->parse_shortcode_content( $content );

		$classes = array();
		if ( $this->shortcode_data['config']['class'] ) {
			$classes[] = $this->shortcode_data['config']['class'];
		}
		if ( in_array( $this->shortcode_data['config']['align'], array( 'left', 'center', 'right', 'none' ), true ) ) {
			$classes[] = 'align' . $this->shortcode_data['config']['align'];
		}

		$class_str = '';
		if ( count( $classes ) ) {
			$class_str = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}

		$alltracks_url       = false;
		$gettrack_url_prefix = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/' );
		$post_id             = get_the_ID();

		if ( $this->trackserver->options['fetchmode_all'] ) {

			if ( count( $this->shortcode_data['tracks'] ) ) {
				$query         = json_encode(
					array(
						'id'    => $this->shortcode_data['track_ids'],
						'live'  => $this->shortcode_data['user_ids'],
						'delay' => $delay,
					)
				);
				$query         = base64_encode( $query );
				$query_nonce   = wp_create_nonce( 'gettrack_' . $query . '_p' . $post_id );
				$alltracks_url = $gettrack_url_prefix . '?query=' . rawurlencode( $query ) . "&p=$post_id&format=" .
					$this->trackserver->track_format . '&maxage=' . $this->shortcode_data['config']['maxage'] . "&_wpnonce=$query_nonce";
			}
		} else {

			array_walk(
				$this->shortcode_data['tracks'],
				function ( &$trk, $id ) use ( $gettrack_url_prefix, $post_id ) {
					if ( $trk['track_type'] === 'polylinexhr' || $trk['track_type'] === 'geojson' ) {

						// Use wp_create_nonce() instead of wp_nonce_url() due to escaping issues
						// https://core.trac.wordpress.org/ticket/4221
						$nonce = wp_create_nonce( 'gettrack_' . $id . '_p' . $post_id );

						$trk['track_url'] = $gettrack_url_prefix . '?id=' . $id . "&p=$post_id&format=" .
							$this->trackserver->track_format . '&maxage=' . $this->shortcode_data['config']['maxage'] . "&_wpnonce=$nonce";
					}
				}
			);
		}

		list( $default_lat, $default_lng ) = $this->get_default_latlng();

		if ( is_null( $this->shortcode_data['config']['live'] ) ) {
			$is_live = (bool) count( $this->shortcode_data['user_ids'] ) > 0;
		} else {
			$is_live = $this->shortcode_data['config']['live'];
		}

		if ( $this->shortcode_data['config']['zoom'] === false ) {   // not set
			$zoom = ( $is_live ? '16' : '6' );
		} else {
			$zoom = intval( $this->shortcode_data['config']['zoom'] );
		}

		if ( is_null( $this->shortcode_data['config']['infobar_tpl'] ) ) {
			$author_id   = get_the_author_meta( 'ID' );
			$infobar_tpl = htmlspecialchars( get_user_meta( $author_id, 'ts_infobar_template', true ) );
		} else {
			$infobar_tpl = $this->shortcode_data['config']['infobar_tpl'];     // This value is already HTML escaped
		}

		$mapdata = array(
			'div_id'       => $div_id,
			'tracks'       => array_values( $this->shortcode_data['tracks'] ),
			'default_lat'  => $default_lat,
			'default_lon'  => $default_lng,
			'default_zoom' => $zoom,
			'fit'          => $this->shortcode_data['config']['fit'],
			'fullscreen'   => true,
			'is_live'      => $is_live,
			'continuous'   => $this->shortcode_data['config']['continuous'],
			'infobar'      => $this->shortcode_data['config']['infobar'],
			'infobar_tpl'  => $infobar_tpl,
			'alltracks'    => $alltracks_url,
		);

		$this->trackserver->mapdata[]    = $mapdata;
		$out                             = '<div id="' . $div_id . '" ' . $class_str . ' style="width: ' . $this->shortcode_data['config']['width'] . '; height: ' . $this->shortcode_data['config']['height'] . '; max-width: 100%"></div>';
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
	 * @since 5.1 Refactoring .
	 */
	public function handle_shortcode3( $atts, $content = '' ) {

		if ( ! in_the_loop() ) {
			return '';
		}

		// Set $this->shortcode_data
		$this->init_shortcode_data();
		$this->parse_shortcode_atts( $atts, 'link' );

		$class_str = '';
		if ( $this->shortcode_data['config']['class'] ) {
			$class_str = 'class="' . esc_attr( $this->shortcode_data['config']['class'] ) . '"';
		}

		$out = 'ERROR';

		if ( count( $this->shortcode_data['tracks'] ) ) {

			$gettrack_url_prefix = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/' );
			$post_id             = get_the_ID();

			$track_format = 'gpx';
			if ( in_array( $this->shortcode_data['config']['format'], array( 'gpx' ), true ) ) {
				$track_format = $this->shortcode_data['config']['format'];
			}

			$query = json_encode(
				array(
					'id'   => $this->shortcode_data['track_ids'],
					'live' => $this->shortcode_data['user_ids'],
				)
			);

			$query         = base64_encode( $query );
			$query_nonce   = wp_create_nonce( 'gettrack_' . $query . '_p' . $post_id );
			$alltracks_url = $gettrack_url_prefix . '?query=' . rawurlencode( $query ) . "&p=$post_id&format=$track_format&maxage=" . $this->shortcode_data['config']['maxage'] . "&_wpnonce=$query_nonce";

			$text = $this->shortcode_data['config']['text'] . $content;
			if ( $text === '' ) {
				$text = 'download ' . $track_format;
			}

			if ( $this->shortcode_data['config']['href_only'] === false ) {
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
	private function proxy_url( $url ) {
		if ( substr( $url, 0, 6 ) === 'proxy:' ) {
			$track_base_url = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/?', ( is_ssl() ? 'https' : 'http' ) );
			$proxy          = base64_encode( substr( $url, 6 ) );
			$post_id        = get_the_ID();
			$proxy_nonce    = wp_create_nonce( 'proxy_' . $proxy . '_p' . $post_id );
			$url            = $track_base_url . 'proxy=' . rawurlencode( $proxy ) . "&p=$post_id&_wpnonce=$proxy_nonce";
		}
		return $url;
	}

	/**
	 * Initialize a data stucture for shortcode attributes.
	 *
	 * @since 5.1
	 */
	private function init_multivalue_atts( $atts ) {
		$allowed_attrs   = array( 'color', 'weight', 'opacity', 'dash', 'points', 'arrows', 'markers', 'markersize' );
		$this->attr_data = array();   // Start with an empty array for each tsmap.

		foreach ( $allowed_attrs as $a ) {
			$this->attr_data[ $a ] = ( $atts[ $a ] ? explode( ',', $atts[ $a ] ) : false );
		}
	}

	/**
	 * Return style object for a track based on shortcode attributes.
	 *
	 * A function to return a style object from 'color', 'weight', 'opacity' and
	 * 'dash' attributes. First, it shifts an element off the beginning
	 * of the array.  When an array is empty (meaning there are more tracks than
	 * values in the parameter), the last value is restored to be used for
	 * subsequent tracks.
	 *
	 * @since 3.0
	 * @since 5.0 Moved to and adapted for the Trackserver_Shortcode class.
	 * @since 5.1 Changed to use $this->attr_data and leave initialization to init_multivalue_atts().
	 */
	private function get_style() {

		$style = array();
		if ( is_array( $this->attr_data['color'] ) ) {
			$style['color'] = array_shift( $this->attr_data['color'] );
			if ( empty( $this->attr_data['color'] ) ) {
				$this->attr_data['color'][] = $style['color'];
			}
		}
		if ( is_array( $this->attr_data['weight'] ) ) {
			$style['weight'] = array_shift( $this->attr_data['weight'] );
			if ( empty( $this->attr_data['weight'] ) ) {
				$this->attr_data['weight'][] = $style['weight'];
			}
		}
		if ( is_array( $this->attr_data['opacity'] ) ) {
			$style['opacity'] = array_shift( $this->attr_data['opacity'] );
			if ( empty( $this->attr_data['opacity'] ) ) {
				$this->attr_data['opacity'][] = $style['opacity'];
			}
		}
		if ( is_array( $this->attr_data['dash'] ) ) {
			$style['dashArray'] = array_shift( $this->attr_data['dash'] );
			if ( empty( $this->attr_data['dash'] ) ) {
				$this->attr_data['dash'][] = $style['dashArray'];
			}
		}
		return $style;
	}

	private function get_content_style( $atts ) {
		$style = array();
		if ( $atts['color'] !== false ) {
			$style['color'] = $atts['color'];
		}
		if ( $atts['weight'] !== false ) {
			$style['weight'] = $atts['weight'];
		}
		if ( $atts['opacity'] !== false ) {
			$style['opacity'] = $atts['opacity'];
		}
		if ( $atts['dash'] !== false ) {
			$style['dashArray'] = $atts['dash'];
		}
		return $style;
	}

	/**
	 * Return the value of a boolean shortcode attribute from $this->attr_data.
	 *
	 * @since 5.1
	 */
	private function get_boolean_att( $att_name, $default_value = false ) {
		$p = false;
		if ( is_array( $this->attr_data[ $att_name ] ) ) {
			$p = array_shift( $this->attr_data[ $att_name ] );
			if ( empty( $this->attr_data[ $att_name ] ) ) {
				$this->attr_data[ $att_name ][] = $p;
			}
		}
		if ( $default_value === false ) {
			return ( in_array( $p, array( 'true', 't', 'yes', 'y' ), true ) ? true : false ); // default false
		} else {
			return ( in_array( $p, array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
		}
	}

	/**
	 * Convert a string value to a boolean, using a default result for unknown strings.
	 *
	 * @since 5.1
	 */
	private function get_content_boolean( $raw, $default_value = false ) {
		if ( is_null( $raw ) ) {
			return null;
		}
		if ( $default_value === false ) {
			return ( in_array( $raw, array( 'true', 't', 'yes', 'y' ), true ) ? true : false ); // default false
		} else {
			return ( in_array( $raw, array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
		}
	}

	/**
	 * Return the value of 'markers' for a track based on shortcode attribute.
	 *
	 * @since 3.0
	 * @since 5.1 Changed to use $this->attr_data and leave initialization to init_multivalue_atts().
	 */
	private function get_markers() {

		$p = false;
		if ( is_array( $this->attr_data['markers'] ) ) {
			$p = array_shift( $this->attr_data['markers'] );
			if ( empty( $this->attr_data['markers'] ) ) {
				$this->attr_data['markers'][] = $p;
			}
		}

		$markers = ( in_array( $p, array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
		$markers = ( in_array( $p, array( 'start', 's' ), true ) ? 'start' : $markers );
		$markers = ( in_array( $p, array( 'end', 'e' ), true ) ? 'end' : $markers );
		return $markers;
	}

	private function get_content_markers( $raw ) {
		$markers = ( in_array( $raw, array( 'false', 'f', 'no', 'n' ), true ) ? false : true ); // default true
		$markers = ( in_array( $raw, array( 'start', 's' ), true ) ? 'start' : $markers );
		$markers = ( in_array( $raw, array( 'end', 'e' ), true ) ? 'end' : $markers );
		return $markers;
	}


	/**
	 * Return the value of 'markersize' for a track based on shortcode attribute.
	 *
	 * @since 4.3
	 * @since 5.1 Changed to use $this->attr_data and leave initialization to init_multivalue_atts().
	 */
	private function get_markersize() {

		$p = false;
		if ( is_array( $this->attr_data['markersize'] ) ) {
			$p = array_shift( $this->attr_data['markersize'] );
			if ( empty( $this->attr_data['markersize'] ) ) {
				$this->attr_data['markersize'][] = $p;
			}
		}

		$default = $item_attr_defaults['markersize'];
		return ( (int) $p > 0 ? $p : $default );
	}

	private function get_content_markersize( $raw ) {
		$default = $item_attr_defaults['markersize'];
		return ( (int) $raw > 0 ? $raw : $default );
	}

	/**
	 * Return age in seconds for a time expression
	 *
	 * Takes an expression like 120s, 5m, 3h, 7d and turns it into seconds. No unit equals seconds.
	 *
	 * @since 3.1
	 */
	private function get_age_seconds( $str ) {
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
	 * Validate lists of track_ids and user_ids against the permissions of the author. Keep 'maxage' into account.
	 *
	 * @since 5.1
	 */
	private function validate_ids( $track_ids, $user_ids ) {
		$validated_user_ids = array();
		$author_id          = get_the_author_meta( 'ID' );
		$maxage             = $this->shortcode_data['config']['maxage'];

		// Backward compatibility
		if ( in_array( 'live', $track_ids, true ) ) {
			$validated_user_ids[] = $author_id;
		}

		$validated_track_ids = $this->validate_track_ids( $track_ids, $author_id );
		$validated_user_ids  = array_merge( $validated_user_ids, $this->validate_user_ids( $user_ids, $author_id ) );
		$live_tracks         = $this->trackserver->get_live_tracks( $validated_user_ids, $maxage, 'map' );   // { UID => TID }

		return array( $validated_track_ids, $validated_user_ids, $live_tracks );
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

			// phpcs:disable
			$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' .
				 $this->trackserver->tbl_locations . ' l INNER JOIN ' . $this->trackserver->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id=%d  ORDER BY occurred', $track_id );
			$res = $wpdb->get_results( $sql, ARRAY_A );
			// phpcs:enable

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

		// phpcs:disable
		$sql_in = "('" . implode( "','", $track_ids ) . "')";
		$sql = 'SELECT trip_id, latitude, longitude, altitude, speed, occurred, t.user_id, t.name, t.distance, t.comment FROM ' . $this->trackserver->tbl_locations .
			' l INNER JOIN ' . $this->trackserver->tbl_tracks . ' t ON l.trip_id = t.id WHERE trip_id IN ' . $sql_in . ' AND l.hidden = 0 ORDER BY trip_id, occurred';
		$res = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable

		if ( $format === 'gpx' ) {
			$this->send_as_gpx( $res );
		} else {
			$this->send_alltracks( $track_ids, $res ); // default to 'alltracks' internal format
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
		// phpcs:disable
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		// phpcs:enable
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
	private function send_alltracks( $track_ids, $res ) {

		// Initialize the results with the known set of IDs. What to do with 'metadata'?
		$tracks = array();
		foreach ( $track_ids as $id ) {
			$tracks[ $id ] = array(
				'track' => '',
			);
		}

		$last_id = -1;
		foreach ( $res as $row ) {
			$id = $row['trip_id'];
			if ( $id !== $last_id ) {
				// Reset the temporary state. This depends on the points of one track being grouped together!
				$this->previous = array( 0, 0 );
				$index          = 0;
			}
			$tracks[ $id ]['track'] .= $this->polyline_get_chunk( $row['latitude'], $index );
			++$index;
			$tracks[ $id ]['track'] .= $this->polyline_get_chunk( $row['longitude'], $index );
			++$index;
			$tracks[ $id ]['metadata'] = $this->get_metadata( $row );   // Overwrite the value on every row, so the last row remains

			$last_id = $id;
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
			++$index;
			$encoded_string .= $this->polyline_get_chunk( $row['longitude'], $index );
			++$index;
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
