<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	  die( 'No, sorry.' );
}

class Trackserver_Track {

	// Database fields
	private $id       = null;
	private $user_id  = null;
	private $name     = '';
	private $created  = null;
	private $source   = '';
	private $comment  = '';
	private $distance = 0;

	// Trackserver object
	private $trackserver;

	/**
	 * Initialize the instance.
	 *
	 * @since 4.4
	 */
	public function __construct( $trackserver, $id = null, $user_id = null ) {
		$this->trackserver = $trackserver;
		$this->user_id     = $user_id;

		if ( ! ( is_null( $id ) || is_null( $user_id ) ) ) {
			$this->get( (int) $id, (int) $user_id );
		}
	}

	/**
	 * Get a track from the database.
	 *
	 * Given a track ID and a user ID, get the track's properties and store them
	 * on this object.  Returns true on success or false on failure.
	 *
	 * @since 4.4
	 */
	private function get( $id, $user_id ) {
		global $wpdb;

		if ( user_can( $user_id, 'trackserver_publish' ) ) {
			$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->trackserver->tbl_tracks . ' WHERE id=%d', $id );
		} else {
			$sql = $wpdb->prepare( 'SELECT id FROM ' . $this->trackserver->tbl_tracks . ' WHERE id=%d AND user_id=%d;', $id, $user_id );
		}
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_null( $result ) ) {
			$this->id       = $row['id'];
			$this->user_id  = $row['user_id'];
			$this->name     = $row['name'];
			$this->created  = $row['created'];
			$this->source   = $row['source'];
			$this->comment  = $row['comment'];
			$this->distance = $row['distance'];
			return true;
		}
		return false;
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
			'user_id'  => $this->user_id,
			'name'		 => $this->name,
			'created'  => $this->created,
			'source'	 => $this->source,
			'comment'	 => $this->comment,
			'distance' => $this->distance,
		);
		$format = array( '%d', '%s', '%s', '%s', '%s', '%d' );

		if ( is_null( $this->id ) ) {

			$this->created   = current_time( 'Y-m-d H:i:s' );
			$data['created'] = $this->created;

			if ( $wpdb->insert( $this->trackserver->tbl_tracks, $data, $format ) ) {
				$this->id = $wpdb->insert_id;
				return $this->id;
			}

		} else {

			$where     = array( 'id' => $this->id );
			$where_fmt = array( '%d' );
			if ( $wpdb->update( $this->trackserver->tbl_tracks, $data, $where, $format, $where_fmt ) ) {
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

