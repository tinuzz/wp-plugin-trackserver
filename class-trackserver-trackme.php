<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Trackme {

	protected static $instance;   // Singleton
	private $trackserver;         // Reference to the calling object

	/**
	 * Constructor.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
	}

	/**
	 * Create a singleton if it doesn't exist and return it.
	 *
	 * @since 4.4
	 */
	public static function getInstance( $trackserver ) {
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
	 * @since 4.4
	 */
	public function handle_protocol( $method, $username, $password ) {
		if ( $method === 'requests' ) {
			$this->handle_request( $username, $password );
		}

		if ( $method === 'export' ) {
			$this->handle_export( $username, $password );
		}

		if ( $method === 'cloud' ) {

			// handle_trackme_cloud() will validate credentials, but if the old-style URL is in use,
			// no credentials will be available. We short-circuit this case to be able to give the
			// user a useful error message.

			if ( empty( $username ) || empty( $password ) ) {       // No credentials from the URL; values are null
				if ( empty( $_GET['u'] ) || empty( $_GET['p'] ) ) {   // No credentials from GET parameters either
					$this->trackserver->handle_trackme_cloud_error();   // This will not return
				}
			}
			$this->trackserver->handle_trackme_cloud( $username, $password );
		}
		die();
	}

	/**
	 * Handle TrackMe GET requests. It validates the user and password and
	 * delegates the requested action to a dedicated function
	 */
	private function handle_request( $username = '', $password = '' ) {
		require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
		require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

		// If this function returns, we're OK
		$user_id = $this->trackserver->validate_trackme_login( $username, $password );

		// Delegate the action to another function
		switch ( $_GET['a'] ) {
			case 'upload':
				$this->trackserver->handle_trackme_upload( $user_id );
				break;
			case 'gettriplist':
				$this->trackserver->handle_trackme_gettriplist( $user_id );
				break;
			case 'gettripfull':
			case 'gettriphighlights':
				$this->trackserver->handle_trackme_gettripfull( $user_id );
				break;
			case 'deletetrip':
				$this->trackserver->handle_trackme_deletetrip( $user_id );
				break;
		}
	}

	/**
	 * Handle TrackMe export requests. Not currently implemented.
	 *
	 * @since 1.0
	 */
	private function handle_export( $username = '', $password = '' ) {
		$this->http_terminate( 501, 'Export is not supported by the server.' );
	}
}
