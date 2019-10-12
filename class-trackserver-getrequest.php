<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Getrequest {

	private $trackserver;  // Reference to the calling object
	private $user;         // WP_User doing the request

	/**
	 * Constructor.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver, $username, $password ) {
		$this->trackserver = $trackserver;

		if ( is_null( $username ) && ! empty( $_GET['username'] ) ) {
			$username = $_GET['username'];
		}
		if ( is_null( $password ) && ! empty( $_GET['key'] ) ) {
			$password = $_GET['key'];
		}
		if ( is_null( $password ) && ! empty( $_GET['password'] ) ) {
			$password = $_GET['password'];
		}
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Handle a generic GET request, as sent by OsmAnd and SendLocation.
	 *
	 * @since 4.4
	 */
	public function handle_request() {

		// Try HTTP Basic auth first. This can return a user ID, or NULL.
		$user_id = $this->trackserver->try_http_basicauth();

		if ( is_null( $user_id ) ) {
			// If this function returns, we're OK
			$user_id = $this->validate_user_meta_key();
		}

		$ts = 0;

		// OsmAnd sends a timestamp in milliseconds, and in UTC. Use substr() to truncate the timestamp,
		// because dividing by 1000 causes an integer overflow on 32-bit systems.
    if ( array_key_exists( 'timestamp', $_GET ) ) {
			$timestamp = rawurldecode( $_GET['timestamp'] );
			if ( strlen( $timestamp ) > 10 ) {
				$timestamp = substr( $timestamp, 0, -3 );
			}
			$ts = intval( $timestamp );
		}

		// If no timestamp is given (SendLocation), we generate one.
		if ( $ts <= 0 ) {
			$ts = time();
		}

		$ts      += $this->trackserver->utc_to_local_offset( $ts );
		$occurred = date( 'Y-m-d H:i:s', $ts );

    if ( ! empty ( $_SERVER['HTTP_USER_AGENT'] ) ) {
      $source = strtok( $_SERVER['HTTP_USER_AGENT'], ';' );

    } elseif ( array_key_exists( 'timestamp', $_GET ) ) {
			$source = 'OsmAnd';

		} elseif ( array_key_exists( 'deviceid', $_GET ) ) {
			$source = 'SendLocation';

		} else {
			$source = __('Unknown', 'trackserver' );
		}

		$source = ( isset( $_GET['source'] ) ? rawurldecode( $_GET['source'] ) : $source );

		// Get track name from strftime format string. Use the 'osmand' format. This format should be renamed.
		// The 'sendlocation' format is now deprecated.
		$trackname = strftime( $this->trackserver->options['osmand_trackname_format'], $ts );

		if ( ! empty( $trackname ) ) {
			$track = new Trackserver_Track( $this, $trackname, $user_id, 'name' );

			if ( is_null( $track->id ) ) {
				$track->set( 'name', $trackname );
				$track->set( 'source', $source );

				$track->save();
				if ( empty( $track->id ) ) {
					$this->trackserver->http_terminate( 501, 'Database error (trk)' );
				}
			}

			if ( ! ( empty( $_GET['lat'] ) || empty( $_GET['lon'] ) ) ) {
				$loc = new Trackserver_Location( $this, $track->id, $user_id );

				// SendLocation sometimes uses commas as decimal separators (issue #12)
				$loc->set( 'latitude', str_replace( ',', '.', rawurldecode( $_GET['lat'] ) ) );
				$loc->set( 'longitude', str_replace( ',', '.', rawurldecode( $_GET['lon'] ) ) );
				$loc->set( 'occurred', $occurred );

				if ( ! empty( $_GET['altitude'] ) ) {
					$loc->set( 'altitude', str_replace( ',', '.', rawurldecode( $_GET['altitude'] ) ) );
				}
				if ( ! empty( $_GET['speed'] ) ) {
					$loc->set( 'speed', str_replace( ',', '.', rawurldecode( $_GET['speed'] ) ) );
				}
				if ( ! empty( $_GET['bearing'] ) ) {
					$loc->set( 'heading', str_replace( ',', '.', rawurldecode( $_GET['bearing'] ) ) );
				}
				if ( ! empty( $_GET['heading'] ) ) {
					$loc->set( 'heading', str_replace( ',', '.', rawurldecode( $_GET['heading'] ) ) );
				}
				if ( $loc->save() ) {
					$this->trackserver->calculate_distance( $track->id );
					$this->trackserver->http_terminate( 200, "OK, track ID = $track->id, timestamp = $occurred" );
				} else {
					var_dump($loc);
					$this->trackserver->http_terminate( 501, 'Database error (loc)' );
				}
			}
		}
		$this->trackserver->http_terminate( 400, 'Bad request' );
	}

	/**
	 * Validate credentials against keys from user metadata.
	 *
	 * It validates the username and password stored on this object against the
	 * WordPress username and several access keys from the user profile.
	 *
	 * @since 4.4
	 */
	private function validate_user_meta_key() {

		if ( empty( $this->username ) ) {
			$this->trackserver->http_terminate();
		}

		$this->user = get_user_by( 'login', $this->username );

		if ( $this->user ) {
			$user_keys = array(
				get_user_meta( $this->user->ID, 'ts_osmand_key', true ),
				get_user_meta( $this->user->ID, 'ts_sendlocation_key', true ),
				get_user_meta( $this->user->ID, 'ts_trackme_key', true ),
			);

			foreach ( $user_keys as $key ) {
				if ( $this->password === $key ) {
					if ( user_can( $this->user->ID, 'use_trackserver' ) ) {
						return $this->user->ID;
					}
				}
			}
		}
		$this->trackserver->http_terminate();
	}

}  // class
