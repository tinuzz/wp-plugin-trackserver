<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Mapmytracks {

	private $trackserver;  // Reference to the calling object
	private $user_id;      // User ID of WP_User doing the request

	/**
	 * Constructor.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
	}

	/**
	 * Handle a MapMyTracks request.
	 *
	 * @since 1.0
	 * @since 4.4 Moved to Trackserver_Mapmytracks class
	 */
	public function handle_request() {

		// Validate with '$return = true' so we can handle the auth failure.
		$this->user_id = $this->trackserver->validate_http_basicauth( true );

		if ( $this->user_id === false ) {
			return $this->error_response( 'unauthorized' );
		}

		switch ( $_POST['request'] ) {
			case 'start_activity':
				$this->trackserver->handle_mapmytracks_start_activity( $this->user_id );
				break;
			case 'update_activity':
				$this->trackserver->handle_mapmytracks_update_activity( $this->user_id );
				break;
			case 'stop_activity':
				$this->trackserver->handle_mapmytracks_stop_activity( $this->user_id );
				break;
			case 'upload_activity':
				$this->trackserver->handle_mapmytracks_upload_activity( $this->user_id );
				break;
			default:
				$this->trackserver->http_terminate( 501, 'Unsupported MapMyTracks request.' );
		}
	}

	/**
	 * Send a MapMyTracks error message. Reason is optional.
	 *
	 * @since 4.4
	 */
	private function error_response( $reason = null ) {
		$data = array();
		if ( ! is_null( $reason ) ) {
			$data = array( 'reason' => $reason );
		}
		return $this->send_response( 'error', $data );
	}

	/**
	 * Format a MapMyTracks XML response.
	 *
	 * A message must have a type, and it can have additional data.
	 *
	 * @since 4.4
	 */
	function send_response( $type, $data = array() ) {
		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
		$xml->addChild( 'type', $type );
		foreach ( $data as $key => $value ) {
			$xml->addChild( $key, $value );
		}
		echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
	}

}  // class
