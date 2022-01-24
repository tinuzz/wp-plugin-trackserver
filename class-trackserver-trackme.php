<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Trackme {

	protected static $instance;   // Singleton
	private $trackserver;         // Reference to the calling object
	private $tbl_tracks;
	private $tbl_locations;
	private $permissions;         // The permissions of the used app password

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
	 * Handle a request from TrackMe.
	 *
	 * Based on the request, delegate the actual work to an appropriate
	 * function.
	 *
	 * @since 5.0
	 */
	public function handle_protocol( $method, $username, $password ) {

		if ( $method === 'cloud' ) {

			// TrackMe doesn't send credentials as URL params for 'cloud' requests.
			// If we don't have creds from the URL, short-circuit this case to be
			// able to give the user a useful error message.

			if ( empty( $username ) || empty( $password ) ) {       // No credentials from the URL; values are null
				if ( empty( $_GET['u'] ) || empty( $_GET['p'] ) ) {   // No credentials from GET parameters either
					$this->handle_cloud_error();   // This will not return
				}
			}
		}

		// Validate credentials. If this function returns, we're OK
		$this->validate_login( $username, $password );

		if ( $method === 'requests' ) {
			$this->handle_request();
		}

		if ( $method === 'export' ) {
			$this->handle_export();
		}

		if ( $method === 'cloud' ) {
			$this->handle_cloud_request();
		}
		die();
	}

	/**
	 * Handle TrackMe GET requests. It validates the user and password and
	 * delegates the requested action to a dedicated function
	 */
	private function handle_request() {
		// Delegate the action to another function
		switch ( $_GET['a'] ) {
			case 'upload':
				$this->handle_upload();
				break;
			case 'gettriplist':
				$this->handle_gettriplist();
				break;
			case 'gettripfull':
			case 'gettriphighlights':
				$this->handle_gettripfull();
				break;
			case 'deletetrip':
				$this->handle_deletetrip();
				break;
		}
	}

	/**
	 * Validate the credentials in a Trackme request aginast the user's key
	 *
	 * @since 1.0
	 */
	private function validate_login( $username, $password ) {

		if ( empty( $username ) ) {
			$username = urldecode( $_GET['u'] );
			$password = urldecode( $_GET['p'] );
		}

		if ( empty( $username ) || empty( $password ) ) {  // '0' is hereby disqualified as username and password
			$this->trackme_result( 3 );
		}

		$this->user_id = $this->trackserver->validate_credentials( $username, $password );

		if ( $this->user_id === false ) {
			$this->trackme_result( 1 );  // Password incorrect or insufficient permissions
		}

		$this->permissions = $this->trackserver->permissions;
		return true;
	}

	/**
	 * Check whether a given set of permissions is present for the current request.
	 *
	 * Terminate with an error if any of the specified permissions is not present.
	 *
	 * @since 5.0
	 */
	private function ensure_permissions( $perms ) {
		if ( is_array( $this->permissions ) ) {
			if ( is_string( $perms ) ) {
				$perms = array( $perms );
			}

			// Assert that the intersection of $perms and $this->permissions is equal in size to $perms.
			if ( count( array_intersect( $this->permissions, $perms ) ) === count( $perms ) ) {
				return true;
			}
		}
		// If not, finish with error.
		$this->trackme_result( 1 );  // Password incorrect or insufficient permissions
	}

	/**
	 * Print a result for the TrackMe client. It prints a result code and optionally a message.
	 *
	 * @since 1.0
	 */
	private function trackme_result( $rc, $message = false ) {
		echo "Result:$rc";
		if ( $message ) {
			echo "|$message";
		}
		die();
	}

	/**
	 * Handle TrackMe export requests. Not currently implemented.
	 *
	 * @since 1.0
	 */
	private function handle_export() {
		$this->http_terminate( 501, 'Export is not supported by the server.' );
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
	private function handle_cloud_error() {

		switch ( $_GET['a'] ) {
			case 'update':
				$this->trackserver->http_terminate( 501, 'For cloud sharing you need to update your server URL, see Trackserver FAQ.' );
				break;
			case 'show':
				$this->trackserver->http_terminate( 501, 'For "Show Cloud People" you need to update your server URL, see Trackserver FAQ.' );
				break;
		}
		$this->trackserver->http_terminate( 501, 'The TrackMe Cloud action you requested is not supported by the server.' );
	}

	/**
	 * Handle TrackMe cloud requests with new URL style, containing username and secret.
	 *
	 * @since 4.3
	 */
	private function handle_cloud_request() {

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

				$this->handle_upload( $trip_name );
				break;
			case 'show':
				$this->handle_cloud_show();
				break;
		}
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
	private function handle_cloud_show() {
		global $wpdb;

		$this->ensure_permissions( 'read' );  // If this returns, we're fine.

		$user      = get_user_by( 'id', $this->user_id );
		$user_ids  = $this->trackserver->get_followed_users( $user );
		$track_ids = $this->trackserver->get_live_tracks( $user_ids, 3660 );
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
	private function handle_upload( $trip_name = '' ) {

		$this->ensure_permissions( 'write' );  // If this returns, we're fine.

		if ( $trip_name === '' ) {
			$trip_name = urldecode( $_GET['tn'] );
		}
		$occurred = urldecode( $_GET['do'] );

		if ( ! empty( $trip_name ) ) {
			$track = new Trackserver_Track( $this->trackserver, $trip_name, $this->user_id, 'name' );

			if ( is_null( $track->id ) ) {

				$track->set( 'name', $trip_name );
				$track->set( 'source', 'TrackMe' );
				$track->save();

				if ( empty( $track->id ) ) {
					$this->trackme_result( 6 ); // Unable to create trip
				}
			}

			if ( intval( $track->id ) > 0 ) {

				if ( ! ( empty( $_GET['lat'] ) || empty( $_GET['long'] ) ) && $this->validate_timestamp( $occurred ) ) {
					$loc = new Trackserver_Location( $this->trackserver, $track->id, $this->user_id );
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
						$this->trackserver->calculate_distance( $track->id );
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
	private function handle_gettriplist() {
		global $wpdb;

		$this->ensure_permissions( 'read' );  // If this returns, we're fine.

		// @codingStandardsIgnoreStart
		$sql   = $wpdb->prepare( 'SELECT name,created FROM ' . $this->tbl_tracks . ' WHERE user_id=%d ORDER BY created DESC LIMIT 0,25', $this->user_id );
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
	private function handle_gettripfull() {

		$this->ensure_permissions( 'read' );  // If this returns, we're fine.

		$trip_name = urldecode( $_GET['tn'] );
		if ( ! empty( $trip_name ) ) {

			$track   = new Trackserver_Track( $this->trackserver, $trip_name, $this->user_id, 'name' );
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
	 * @since 5.0 Use Trackserver_Track class
	 */
	private function handle_deletetrip() {

		$this->ensure_permissions( 'delete' );  // If this returns, we're fine.

		$trip_name = urldecode( $_GET['tn'] );
		if ( ! empty( $trip_name ) ) {
			$track   = new Trackserver_Track( $this->trackserver, $trip_name, $this->user_id, 'name' );
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
	 * Validate a timestamp supplied by a client.
	 *
	 * It checks if the timestamp is in the required format and if the
	 * timestamp is unchanged after parsing.
	 *
	 * @since 1.0
	 */
	private function validate_timestamp( $ts ) {
		$d = DateTime::createFromFormat( 'Y-m-d H:i:s', $ts );
		return $d && ( $d->format( 'Y-m-d H:i:s' ) === $ts );
	}

} // class
