<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Ulogger {

	private $trackserver;  // Reference to the calling object
	private $user;         // WP_User doing the request

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
	}

	/**
	 * Handle a request from uLogger.
	 *
	 * Based on the value of $_POST['action'], delegate the actual work to an
	 * appropriate function.
	 *
	 * @since 5.0
	 */
	public function handle_request() {
		switch ( $_POST['action'] ) {
			case 'auth':
				$this->handle_auth();
				break;
			case 'addtrack':
				$this->handle_addtrack();
				break;
			case 'addpos':
				$this->handle_addpos();
				break;
			default:
				$this->send_response( array( 'message' => 'Unknown command' ) );
		}
	}

	/**
	 * Terminate the request with a 401 status.
	 *
	 * @since 5.0
	 */
	private function require_auth() {
		// The reference server does this, I'm not sure to what end.
		//header( 'WWW-Authenticate: OAuth realm="users@ulogger"' );
		header( 'HTTP/1.0 401 Unauthorized' );
		die( "Authentication required\n" );
	}

	/**
	 * Generate a JSON response for uLogger.
	 *
	 * It takes an associative array, to be sent as JSON. If the array contains a key
	 * named 'message', it will be regarded as an error condition and the 'error' key
	 * in the response will be set to 'true'.
	 *
	 * @since 5.0
	 */
	private function send_response( $data = array() ) {
		$response          = array();
		$response['error'] = ( array_key_exists( 'message', $data ) ? true : false );
		header( 'Content-Type: application/json' );
		echo json_encode( array_merge( $response, $data ) );
	}

	/**
	 * Handle 'auth' action.
	 *
	 * Verify the username and password sent in the POST request.  When valid,
	 * start a session, store the user ID in it and send a succesful response.
	 * The WordPress password is also considered in this case.
	 *
	 * @since 5.0
	 */
	private function handle_auth() {
		if ( array_key_exists( 'user', $_POST ) && array_key_exists( 'pass', $_POST ) ) {
			$user_id = $this->trackserver->validate_credentials( $_POST['user'], $_POST['pass'], 'id', true );
			if ( $user_id !== false ) {
				session_start();
				$_SESSION                        = array();
				$_SESSION['trackserver_user_id'] = $user_id;
				return $this->send_response();
				//return $this->send_response( array( "user_id" => $user_id ) );  // for debugging
			}
		}
		$this->require_auth();
	}

	/**
	 * Get user ID from session.
	 *
	 * If a user_id from Trackserver is present in the session. return it.
	 * Otherwise, terminate the request.
	 *
	 * @since 5.0
	 */
	private function session_user() {
		session_start();
		if ( isset( $_SESSION['trackserver_user_id'] ) ) {
			return $_SESSION['trackserver_user_id'];
		}
		$this->require_auth();
	}

	/**
	 * Handle 'addtrack' action.
	 *
	 * @since 5.0
	 */
	private function handle_addtrack() {

		// If this function returns, we're OK.
		$user_id = $this->session_user();

		if ( empty( $_POST['track'] ) ) {
			return $this->send_response( array( 'message' => 'Missing required parameter' ) );
		}

		$track = new Trackserver_Track( $this->trackserver, null, $user_id );
		$track->set( 'name', $_POST['track'] );

		if ( array_key_exists( 'HTTP_USER_AGENT', $_SERVER ) ) {
			$track->set( 'source', strtok( $_SERVER['HTTP_USER_AGENT'], ';' ) );
		} else {
			$track->set( 'source', 'uLogger' );
		}

		$track_id = $track->save();
		if ( $track_id ) {
			return $this->send_response( array( 'trackid' => $track_id ) );
		} else {
			return $this->send_response( array( 'message' => 'Server error' ) );
		}
	}

	/**
	 * Handle 'addpos' action.
	 *
	 * @since 5.0
	 */
	private function handle_addpos() {
		$user_id = $this->session_user();
		$track   = new Trackserver_Track( $this->trackserver, $_POST['trackid'], $user_id );  // $restrict = true

		if ( $track->id ) {

			if ( ! ( empty( $_POST['lat'] ) || empty( $_POST['lon'] ) || empty( $_POST['time'] ) ) ) {

				$loc = new Trackserver_Location( $this->trackserver, $track->id, $user_id );
				$ts  = (int) $_POST['time'];
				$ts += $this->trackserver->utc_to_local_offset( $ts );

				$loc->set( 'latitude', $_POST['lat'] );
				$loc->set( 'longitude', $_POST['lon'] );
				$loc->set( 'occurred', date( 'Y-m-d H:i:s', $ts ) ); // phpcs:ignore

				if ( ! empty( $_POST['altitude'] ) ) {
					$loc->set( 'altitude', $_POST['altitude'] );
				}
				if ( ! empty( $_POST['speed'] ) ) {
					$loc->set( 'speed', $_POST['speed'] );
				}
				if ( ! empty( $_POST['bearing'] ) ) {
					$loc->set( 'heading', $_POST['bearing'] );
				}

				if ( $loc->save() ) {
					//$track->calculate_distance();  // not implemented yet
					$this->trackserver->calculate_distance( $track_id );
					return $this->send_response();
				} else {
					return $this->send_response( array( 'message' => 'Server error' ) );
				}
			} else {
				return $this->send_response( array( 'message' => 'Missing required parameter' ) );
			}
		} else {
			return $this->send_response( array( 'message' => 'Unknown track ID' ) );
		}
	}

}  // class
