<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Location {

	// Database fields
	public $id        = null;
	public $track_id  = null;
	public $latitude  = null;
	public $longitude = null;
	public $altitude  = 0;
	public $speed     = 0;
	public $heading   = 0;
	public $created   = null;
	public $occurred  = null;
	public $comment   = '';
	public $hidden    = 0;

	private $trackserver;
	private $user_id;
	private $tbl_tracks;
	private $tbl_locations;

	/**
	 * Initialize the instance.
	 *
	 * @since 5.0
	 */
	public function __construct( $trackserver, $track_id = null, $user_id = null ) {
		$this->trackserver   = $trackserver;
		$this->track_id      = $track_id;
		$this->user_id       = $user_id;   // Needed for geofencing
		$this->tbl_tracks    = $this->trackserver->tbl_tracks;
		$this->tbl_locations = $this->trackserver->tbl_locations;
	}

	/**
	 * Function to check whether this location is geofenced.
	 *
	 * @since 3.1
	 * @since 5.0 Delegate to the function in Trackserver main class
	 */
	private function is_geofenced() {
		if ( is_null( $this->latitude ) || is_null( $this->longitude ) || is_null( $this->user_id ) ) {
			return false;
		}
		$data = array(
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
		);
		return $this->trackserver->is_geofenced( $this->user_id, $data );
	}

	/**
	 * Save the location to the database.
	 *
	 * If the instance doesn't have an ID, insert a new location, otherwise update
	 * the existing one. Returns true on success, or false on failure.
	 *
	 * @since 5.0
	 */
	public function save() {
		global $wpdb;

		$fenced = $this->is_geofenced();
		if ( $fenced === 'discard' ) {
			return true;
		}

		$data   = array(
			'trip_id'   => $this->track_id,
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
			'altitude'  => $this->altitude,
			'speed'     => $this->speed,
			'heading'   => $this->heading,
			'created'   => $this->created,
			'occurred'  => $this->occurred,
			'comment'   => $this->comment,
			'hidden'    => ( $fenced === 'hide' ? 1 : 0 ),
		);
		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( is_null( $this->id ) ) {

			$this->created   = current_time( 'Y-m-d H:i:s' );
			$data['created'] = $this->created;

			if ( $wpdb->insert( $this->tbl_locations, $data, $format ) ) {
				$this->id = $wpdb->insert_id;
				return true;
			}
		} else {

			$where     = array( 'id' => $this->id );
			$where_fmt = array( '%d' );
			if ( $wpdb->update( $this->tbl_locations, $data, $where, $format, $where_fmt ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Set a property on this object to a given value.
	 *
	 * @since 5.0.
	 */
	public function set( $what, $value ) {
		$this->$what = $value;
	}

} // class
