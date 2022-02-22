<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Getrequest {

	private $trackserver;  // Reference to the calling object
	private $user_id;      // WP user ID doing the request
	private $permissions;  // The used password's associated permissions

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver, $username, $password ) {
		$this->trackserver = $trackserver;

		if ( is_null( $username ) && ! empty( $_REQUEST['username'] ) ) {
			$username = $_REQUEST['username'];
		}
		if ( is_null( $password ) && ! empty( $_REQUEST['key'] ) ) {
			$password = $_REQUEST['key'];
		}
		if ( is_null( $password ) && ! empty( $_REQUEST['password'] ) ) {
			$password = $_REQUEST['password'];
		}
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Handle a generic GET/POST request, as sent by OsmAnd, SendLocation and others.
	 *
	 * @since 5.0
	 */
	public function handle_request() {

		// Try HTTP Basic auth first. This can return a user ID, or NULL.
		$user_id = $this->trackserver->try_http_basicauth();

		if ( is_null( $user_id ) ) {
			// If this function returns, we're OK
			$user_id = $this->validate_credentials();
		}

		$source = $this->get_source();
		$ts     = 0;

		// OsmAnd sends a timestamp in milliseconds, and in UTC. Use substr() to truncate the timestamp,
		// because dividing by 1000 causes an integer overflow on 32-bit systems.
		if ( array_key_exists( 'timestamp', $_REQUEST ) ) {
			$timestamp = rawurldecode( $_REQUEST['timestamp'] );
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
		$occurred = date( 'Y-m-d H:i:s', $ts ); // phpcs:ignore

		// Get track name from strftime format string. Use the 'osmand' format. This format should be renamed.
		// The 'sendlocation' format is now deprecated.
		$trackname = strftime( str_replace( '{source}', $source, $this->trackserver->options['osmand_trackname_format'] ), $ts );

		if ( ! empty( $trackname ) ) {
			$track = new Trackserver_Track( $this->trackserver, $trackname, $user_id, 'name' );

			if ( is_null( $track->id ) ) {
				$track->set( 'name', $trackname );
				$track->set( 'source', $source );

				$track->save();
				if ( empty( $track->id ) ) {
					$this->trackserver->http_terminate( 501, 'Database error (trk)' );
				}
			}

			if ( ! ( empty( $_REQUEST['lat'] ) || empty( $_REQUEST['lon'] ) ) ) {
				$loc = new Trackserver_Location( $this->trackserver, $track->id, $user_id );

				// SendLocation sometimes uses commas as decimal separators (issue #12)
				$loc->set( 'latitude', str_replace( ',', '.', rawurldecode( $_REQUEST['lat'] ) ) );
				$loc->set( 'longitude', str_replace( ',', '.', rawurldecode( $_REQUEST['lon'] ) ) );
				$loc->set( 'occurred', $occurred );

				if ( ! empty( $_REQUEST['altitude'] ) ) {
					$loc->set( 'altitude', str_replace( ',', '.', rawurldecode( $_REQUEST['altitude'] ) ) );
				}
				if ( ! empty( $_REQUEST['speed'] ) ) {
					$loc->set( 'speed', str_replace( ',', '.', rawurldecode( $_REQUEST['speed'] ) ) );
				}
				if ( ! empty( $_REQUEST['bearing'] ) ) {
					$loc->set( 'heading', str_replace( ',', '.', rawurldecode( $_REQUEST['bearing'] ) ) );
				}
				if ( ! empty( $_REQUEST['heading'] ) ) {
					$loc->set( 'heading', str_replace( ',', '.', rawurldecode( $_REQUEST['heading'] ) ) );
				}
				if ( $loc->save() ) {
					$this->trackserver->calculate_distance( $track->id );
					$this->trackserver->http_terminate( 200, "OK, track ID = $track->id, timestamp = $occurred" );
				} else {
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
	 * @since 5.0
	 */
	private function validate_credentials() {

		if ( empty( $this->username ) || empty( $this->password ) ) {
			$this->trackserver->http_terminate();
		}

		$this->user_id = $this->trackserver->validate_credentials( $this->username, $this->password );

		if ( $this->user_id === false ) {
			$this->trackserver->http_terminate();
		}

		$this->permissions = $this->trackserver->permissions;
		return $this->user_id;
	}

	private function get_source() {
		if ( ! empty( $_REQUEST['source'] ) ) {
			$source = rawurldecode( $_REQUEST['source'] );

		} elseif ( array_key_exists( 'timestamp', $_REQUEST ) && strlen( $timestamp ) > 10 ) {
			$source = 'OsmAnd';

		} elseif ( array_key_exists( 'deviceid', $_REQUEST ) ) {
			$source = 'SendLocation';

		} elseif ( array_key_exists( 'id', $_REQUEST ) && array_key_exists( 'batt', $_REQUEST ) ) {
			$source = 'Traccar';

		} elseif ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$source = trim( strtok( $_SERVER['HTTP_USER_AGENT'], ';(' ) );

		} else {
			$source = __( 'Unknown', 'trackserver' );
		}
		return $source;

	}

}  // class
