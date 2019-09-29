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

	// Trackserver object
	private $trackserver;

	/**
	 * Initialize the instance.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver, $track_id = null ) {
		$this->trackserver = $trackserver;
		$this->track_id    = $track_id;
	}

	/**
	 * Save the track to the database.
	 *
	 * If the instance doesn't have an ID, insert a new track, otherwise update
	 * the existing track. Returns the track ID on success, or false on failure.
	 *
	 * @since 4.4
	 */
	public function save() {
		global $wpdb;

		$data	= array(
			'trip_id'   => $this->track_id,
			'latitude'  => $this->latitude,
			'longitude' => $this->longitude,
			'altitude'  => $this->altitude,
			'speed'     => $this->speed,
			'heading'   => $this->heading,
			'created'   => $this->created,
			'occurred'  => $this->occurred,
			'comment'   => $this->comment,
			'hidden'    => $this->hidden,
		);
		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( is_null( $this->id ) ) {

			$this->created   = current_time( 'Y-m-d H:i:s' );
			$data['created'] = $this->created;

			if ( $wpdb->insert( $this->trackserver->tbl_locations, $data, $format ) ) {
				$this->id = $wpdb->insert_id;
				return $this->id;
			}
			var_dump($wpdb);

		} else {

			$where     = array( 'id' => $this->id );
			$where_fmt = array( '%d' );
			if ( $wpdb->update( $this->trackserver->tbl_locations, $data, $where, $format, $where_fmt ) ) {
				return $this->id;
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

