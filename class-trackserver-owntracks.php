<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Owntracks {

	protected static $instance;   // Singleton
	private $trackserver;  // Reference to the calling object
	private $user;         // WP_User doing the request
	private $tbl_tracks;
	private $tbl_locations;

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver ) {
		global $wpdb;
		$this->trackserver   = $trackserver;
		$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
		$this->tbl_locations = $wpdb->prefix . 'ts_locations';
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
	public function handle_request() {

		// If this returns, we're OK
		$this->user = $this->trackserver->validate_http_basicauth( false, 'object' );
		$payload    = file_get_contents( 'php://input' );
		// @codingStandardsIgnoreLine
		$json       = @json_decode( $payload, true );

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

		if ( isset( $json['_type'] ) && $json['_type'] === 'location' ) {

			// Largely copied from SendLocation handling
			$ts       = $json['tst'];
			$offset   = $this->trackserver->utc_to_local_offset( $ts );
			$ts      += $offset;
			$occurred = date( 'Y-m-d H:i:s', $ts ); // phpcs:ignore

			// Get track name from strftime format string
			$trackname = strftime( $this->trackserver->options['owntracks_trackname_format'], $ts );

			if ( ! empty( $trackname ) ) {
				$track = new Trackserver_Track( $this->trackserver, $trackname, $this->user->ID, 'name' );

				if ( is_null( $track->id ) ) {
					$track->set( 'name', $trackname );
					$track->set( 'source', 'OwnTracks' );
					$track->save();

					if ( empty( $track->id ) ) {
						$this->trackserver->http_terminate( 501, 'Database error (track)' );
					}
				}

				$latitude  = $json['lat'];
				$longitude = $json['lon'];
				$now       = $occurred;

				if ( intval( $track->id ) > 0 ) {
					if ( is_numeric( $latitude ) && is_numeric( $longitude ) ) {

						$loc = new Trackserver_Location( $this->trackserver, $track->id, $this->user->ID );
						$loc->set( 'latitude', $latitude );
						$loc->set( 'longitude', $longitude );
						$loc->set( 'occurred', $occurred );

						if ( is_numeric( $json['alt'] ) ) {
							$loc->set( 'altitude', $json['alt'] );
						}

						if ( $loc->save() ) {
							$this->trackserver->calculate_distance( $track->id );
						} else {
							$this->trackserver->http_terminate( 501, 'Database error' );
						}
					}
				}
			}
		}
		$response = $this->create_response();
		$this->trackserver->http_terminate( 200, $response );
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
	private function create_response() {
		global $wpdb;

		$friends_ids       = $this->get_friends();
		$friends_track_ids = $this->trackserver->get_live_tracks( $friends_ids );
		$objects           = array();

		foreach ( $friends_track_ids as $track_id ) {
			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT trip_id, latitude, longitude, altitude, speed, UNIX_TIMESTAMP(occurred) AS tst, t.user_id, t.name, t.distance, t.comment FROM ' .
				$this->tbl_locations . ' l INNER JOIN ' . $this->tbl_tracks .
				' t ON l.trip_id = t.id WHERE trip_id=%d AND l.hidden = 0 ORDER BY occurred DESC LIMIT 0,1', $track_id
			);
			$res = $wpdb->get_row( $sql, ARRAY_A );
			// @codingStandardsIgnoreEnd

			$friend = get_user_by( 'id', $res['user_id'] );
			$tid    = $this->get_tid( $friend );

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
				'name'  => $friend->display_name,
				'tid'   => $tid,
			);

			$face = $this->get_avatar( $friend );
			if ( $face ) {
				$card['face'] = $face;
			}

			$objects[] = $card;
		}
		return json_encode( $objects );
	}

	/**
	 * Get image data for WP user avatar
	 *
	 * @since 4.1
	 */
	private function get_avatar( $user ) {
		// TODO: cache the image in usermeta and serve it from there if available

		$avatar_data = get_avatar_data( $user->ID, array( 'size' => 40 ) );
		$url         = $avatar_data['url'] . '&d=404';  // ask for a 404 if there is no image

		$options  = array(
			'httpversion' => '1.1',
			'user-agent'  => 'WordPress/Trackserver ' . TRACKSERVER_VERSION . '; https://github.com/tinuzz/wp-plugin-trackserver',
		);
		$response = wp_remote_get( $url, $options );
		if ( is_array( $response ) ) {
			$rc = (int) wp_remote_retrieve_response_code( $response );
			if ( $rc === 200 ) {
				return base64_encode( wp_remote_retrieve_body( $response ) );
			}
		}
		return false;
	}

	/**
	 * Return a 2 character string, to be used as the Tracker ID (TID) in OwnTracks.
	 *
	 * Depending on what data is available, we use the user's first name, last name
	 * or login name to create the TID.
	 *
	 * @since 4.1
	 */
	private function get_tid( $user ) {
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
	 * Return a list of friends' user-IDs
	 *
	 * @since 4.1
	 */
	private function get_friends() {
		return $this->trackserver->get_followed_users( $this->user );
	}

}
