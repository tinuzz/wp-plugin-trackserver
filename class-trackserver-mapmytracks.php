<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-track.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-location.php';

class Trackserver_Mapmytracks {

	private $trackserver;  // Reference to the calling object
	private $user_id;      // User ID of WP_User doing the request
	private $tbl_tracks;
	private $tbl_locations;

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
		$this->tbl_tracks    = $this->trackserver->tbl_tracks;
		$this->tbl_locations = $this->trackserver->tbl_locations;
	}

	/**
	 * Handle a MapMyTracks request.
	 *
	 * @since 1.0
	 * @since 5.0 Moved to Trackserver_Mapmytracks class
	 */
	public function handle_request() {

		// Validate with '$return = true' so we can handle the auth failure.
		$this->user_id = $this->trackserver->validate_http_basicauth( true );

		if ( $this->user_id === false ) {
			return $this->error_response( 'unauthorized' );
		}

		switch ( $_POST['request'] ) {
			case 'start_activity':
				$this->handle_start_activity();
				break;
			case 'update_activity':
				$this->handle_update_activity();
				break;
			case 'stop_activity':
				$this->handle_stop_activity();
				break;
			case 'upload_activity':
				$this->handle_upload_activity();
				break;
			default:
				$this->trackserver->http_terminate( 501, 'Unsupported MapMyTracks request.' );
		}
	}

	/**
	 * Send a MapMyTracks error message. Reason is optional.
	 *
	 * @since 5.0
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
	 * @since 5.0
	 */
	private function send_response( $type, $data = array() ) {
		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><message />' );
		$xml->addChild( 'type', $type );
		foreach ( $data as $key => $value ) {
			$xml->addChild( $key, $value );
		}
		echo str_replace( array( "\r", "\n" ), '', $xml->asXML() );
	}

	/**
	 * Get the source app and version from a MapMyTracks request.
	 */
	private function get_source() {
		$source = '';
		if ( array_key_exists( 'source', $_POST ) ) {
			$source .= $_POST['source'];
		}
		if ( array_key_exists( 'version', $_POST ) ) {
			$source .= ' v' . $_POST['version'];
		}
		if ( $source !== '' ) {
			$source .= ' / ';
		}
		$source .= 'MapMyTracks';
		return $source;
	}

	/**
	 * Handle the 'start_activity' request for the MapMyTracks protocol.
	 *
	 * If no title / track name is received, an error is sent, otherwise track
	 * is saved, received points are validated, valid points are inserted and
	 * and the new trip ID is sent in an XML reponse.
	 *
	 * @since 1.0
	 * @since 5.0 Delegate DB access to Trackserver_Track instance
	 */
	private function handle_start_activity() {
		if ( ! empty( $_POST['title'] ) ) {

			$track = new Trackserver_Track( $this->trackserver, null, $this->user_id );
			$track->set( 'name', $_POST['title'] );
			$track->set( 'source', $this->get_source() );

			if ( $track->save() ) {
				list( $result, $reason ) = $this->process_points( $track->id );
				if ( $result ) {
					return $this->send_response( 'activity_started', array( 'activity_id' => (string) $track->id ) );
				}
				return $this->error_response( $reason );
			}
		}
		return $this->error_response( 'input error' );
	}

	/**
	 * Handle 'update_activity' request for the MapMyTracks protocol.
	 *
	 * It tries to instantiate a track object from the given activity_id. If that succeeds,
	 * the location data is parsed and added to the track.
	 *
	 * @since 1.0
	 */
	private function handle_update_activity() {
		$track = new Trackserver_Track( $this->trackserver, $_POST['activity_id'], $this->user_id );   // $restrict = true
		if ( $track->id ) {
			list( $result, $reason ) = $this->process_points( $track->id );
			if ( $result ) {
				return $this->send_response( 'activity_updated' );
			}
			return $this->error_response( $reason );
		}
		return $this->error_response( 'track not found' );
	}

	/**
	 * Handle 'stop_activity' request for the MapMyTracks protocol.
	 *
	 * This doesn't do anything, except return an appropriate XML message.
	 *
	 * @since 1.0
	 */
	private function handle_stop_activity() {
		return $this->mapmytracks_response( 'activity_stopped' );
	}

	/**
	 * Handle 'upload_activity' request for the MapMyTracks protocol.
	 *
	 * It validates and processes the input as GPX data, and returns an
	 * appropriate XML message.
	 *
	 * @since 1.0
	 */
	private function handle_upload_activity() {
		global $wpdb;

		$_POST = stripslashes_deep( $_POST );
		if ( isset( $_POST['gpx_file'] ) ) {
			$xml = $this->trackserver->validate_gpx_string( $_POST['gpx_file'] );
			if ( $xml ) {
				$result = $this->trackserver->process_gpx( $xml, $this->user_id );

				// If a description was given, put it in the comment field.
				if ( isset( $_POST['description'] ) ) {
					$track_ids = $result['track_ids'];
					if ( count( $track_ids ) > 0 ) {
						$in  = '(' . implode( ',', $track_ids ) . ')';
						$sql = $wpdb->prepare( 'UPDATE ' . $this->tbl_tracks . " SET comment=%s WHERE user_id=%d AND id IN $in", $_POST['description'], $this->user_id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					}
				}
				return $this->send_response( 'success' );
			}
		}
		return $this->error_response( 'Invalid input' );
	}

	/**
	 * Process 'points' input from a MapMyTracks request.
	 *
	 * Parse the input from $_POST and save the points to the database. On
	 * errors, abort the operation and inform the caller.
	 *
	 * @since 5.0
	 */
	private function process_points( $track_id ) {
		if ( empty( $_POST['points'] ) ) {
			return array( true, '' );
		}

		$points = $this->parse_points( $_POST['points'] );

		if ( $points === false ) {
			return array( false, 'input error' );
		}

		if ( $this->trackserver->mapmytracks_insert_points( $points, $track_id, $this->user_id ) ) {
			return array( true, '' );
		}

		return array( false, 'server error' );
	}

	/**
	 * Parse a string of points from a MapMyTracks request.
	 *
	 * Usinga regular expression, the string of points is parsed into
	 * an associative array for easier storage in the database.
	 */
	private function parse_points( $points ) {

		// Check the points syntax. It should match groups of four items. The
		// first three may contain numbers, dots, dashes and the letter 'E' (it
		// appears that OruxMaps sometimes sends floats in the scientific
		// notation, like '7.72E-4'). The last one is the timestamp, which may
		// only contain numbers.
		$pattern = '/^([\dE.-]+ [\dE.-]+ [\dE.-]+ [\d]+ ?)*$/';
		$n       = preg_match( $pattern, $points );

		if ( $n === 1 ) {
			$parsed = array();
			$all    = explode( ' ', $points );
			for ( $i = 0; $i < count( $all ); $i += 4 ) {
				if ( $all[ $i ] ) {

					$parsed[] = array(
						'latitude'  => number_format( $all[ $i ], 6 ),
						'longitude' => number_format( $all[ $i + 1 ], 6 ),
						'altitude'  => number_format( $all[ $i + 2 ], 6 ),
						'timestamp' => $all[ $i + 3 ],
					);
				}
			}
			return $parsed;
		} else {
			return false; // Invalid input
		}
	}

} // class
