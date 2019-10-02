<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Location {

	// Database fields
	private $id        = null;
	private $track_id  = null;
	private $latitude  = null;
	private $longitude = null;
	private $altitude  = 0;
	private $speed     = 0;
	private $heading   = 0;
	private $created   = null;
	private $occurred  = null;
	private $comment   = '';
	private $hidden    = 0;

	private $trackserver;
	private $user_id;

	/**
	 * Initialize the instance.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver, $track_id = null, $user_id = null ) {
		$this->trackserver = $trackserver;
		$this->track_id    = $track_id;
		$this->user_id     = $user_id;   // Needed for geofencing
	}

	/**
	 * Function to check whether this location is geofenced.
	 *
	 * @since 3.1
	 * @since 4.4 Get all the data from instance properties rather than function args
	 */
	private function is_geofenced() {
		if ( is_null( $this->latitude ) || is_null( $this->longitude ) || is_null( $this->user_id ) ) {
			return false;
		}
		$geofences = get_user_meta( $this->user_id, 'ts_geofences', true );
		if ( ! is_array( $geofences ) ) {
			return false;
		}
		foreach ( $geofences as $i => $fence ) {
			if ( $fence['radius'] > 0 ) {
				$lat1     = (float) $fence['lat'];
				$lon1     = (float) $fence['lon'];
				$lat2     = (float) $this->latitude;
				$lon2     = (float) $this->longitude;
				$distance = $this->trackserver->distance( $lat1, $lon1, $lat2, $lon2 );
				if ( $distance <= $fence['radius'] ) {
					return $fence['action'];
				}
			}
		}
		return false;
	}

	/**
	 * Save the location to the database.
	 *
	 * If the instance doesn't have an ID, insert a new location, otherwise update
	 * the existing one. Returns true on success, or false on failure.
	 *
	 * @since 4.4
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

			if ( $wpdb->insert( $this->trackserver->tbl_locations, $data, $format ) ) {
				$this->id = $wpdb->insert_id;
				return true;
			}
		} else {

			$where     = array( 'id' => $this->id );
			$where_fmt = array( '%d' );
			if ( $wpdb->update( $this->trackserver->tbl_locations, $data, $where, $format, $where_fmt ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Set a property on this object to a given value.
	 *
	 * @since 4.4.
	 */
	public function set( $what, $value ) {
		$this->$what = $value;
	}

}  // class

