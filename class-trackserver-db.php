<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Db {

	/**
	 * Database version that this code base needs.
	 *
	 * @since 1.0
	 */
	private $db_version = 29;

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	private $tbl_tracks;
	private $tbl_locations;

	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
		$this->tbl_tracks    = $this->trackserver->tbl_tracks;
		$this->tbl_locations = $this->trackserver->tbl_locations;
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
	 * Create database tables
	 */
	public function create_tables() {
		global $wpdb;

		if ( ! $this->trackserver->options['db_version'] ) {

			$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->tbl_locations . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`trip_id` int(11) NOT NULL,
				`latitude` double NOT NULL,
				`longitude` double NOT NULL,
				`altitude` double NOT NULL,
				`speed` double NOT NULL,
				`heading` double NOT NULL,
				`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`created` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
				`occurred` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
				`comment` varchar(255) NOT NULL,
				`hidden` tinyint(1) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`),
				KEY `occurred` (`occurred`),
				KEY `trip_id` (`trip_id`),
				KEY `trip_id_occurred` (`trip_id`,`occurred`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->tbl_tracks . " (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`user_id` int(11) NOT NULL,
				`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
				`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`created` timestamp NOT NULL DEFAULT '1971-01-01 00:00:00',
				`source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
				`comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
				`distance` int(11) NOT NULL,
				PRIMARY KEY (`id`),
				KEY `user_id` (`user_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			$this->trackserver->update_option( 'db_version', $this->db_version );
		}
	}

	/**
	 * Check if the database schema is the correct version and upgrade if necessary.
	 *
	 * @since 1.0
	 *
	 * @global object $wpdb The WordPress database interface
	 */
	public function check_update_db() {
		global $wpdb;

		$installed_version = (int) $this->trackserver->options['db_version'];
		if ( $installed_version > 0 && $installed_version !== $this->db_version ) {

			// Get a list of column names for the tracks table
			$sql = 'SELECT * FROM ' . $this->tbl_tracks . ' LIMIT 0,1';
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$colnames = $wpdb->get_col_info( 'name' );

			// Get a list of column names for the locations table
			$sql = 'SELECT * FROM ' . $this->tbl_tracks . ' LIMIT 0,1';
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$colnames_locations = $wpdb->get_col_info( 'name' );

			// Upgrade table if necessary. Add upgrade SQL statements here, and
			// update $db_version at the top of the file
			$upgrade_sql     = array();
			$upgrade_sql[5]  = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `created` `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';
			$upgrade_sql[6]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `created` TIMESTAMP NOT NULL AFTER `updated`';
			$upgrade_sql[7]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `source` VARCHAR( 255 ) NOT NULL AFTER `created`';
			$upgrade_sql[8]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `distance` INT( 11 ) NOT NULL AFTER `comment`';
			$upgrade_sql[9]  = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD INDEX ( `user_id` )';
			$upgrade_sql[10] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX ( `trip_id` )';
			$upgrade_sql[11] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX ( `occurred` )';

			// Fix the 'update'/'updated' mess in the tracks table
			if ( in_array( 'update', $colnames, true ) ) {
				$upgrade_sql[13] = 'ALTER TABLE ' . $this->tbl_tracks . ' DROP `update`';
			}
			if ( ! in_array( 'updated', $colnames, true ) ) {
				$upgrade_sql[14] = 'ALTER TABLE ' . $this->tbl_tracks . ' ADD `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() AFTER `name`';
			}
			$upgrade_sql[15] = 'UPDATE ' . $this->tbl_tracks . ' t SET t.updated=(SELECT max(occurred) FROM `' . $this->tbl_locations . '` WHERE trip_id = t.id)';
			$upgrade_sql[16] = 'ALTER TABLE ' . $this->tbl_locations . " ADD `hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `comment`";

			// Fix the missing 'hidden' column in fresh installs of v4.0
			if ( ! in_array( 'hidden', $colnames_locations, true ) ) {
				$upgrade_sql[17] = 'ALTER TABLE ' . $this->tbl_locations . " ADD `hidden` TINYINT(1) NOT NULL DEFAULT '0' AFTER `comment`";
			}

			// Change the default value for timestamps to be compatible with MySQL 5.7+
			$upgrade_sql[18] = 'ALTER TABLE ' . $this->tbl_locations . " ALTER occurred SET DEFAULT '1971-01-01 00:00:00', ALTER created SET DEFAULT '1971-01-01 00:00:00'";
			$upgrade_sql[19] = 'ALTER TABLE ' . $this->tbl_tracks . " ALTER created SET DEFAULT '1971-01-01 00:00:00'";

			// Change the charset of the text columns in the tracks table, to be able to hold unicode content.
			$upgrade_sql[25] = 'ALTER TABLE ' . $this->tbl_tracks . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
			$upgrade_sql[26] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `name` `name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';
			$upgrade_sql[27] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `source` `source` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';
			$upgrade_sql[28] = 'ALTER TABLE ' . $this->tbl_tracks . ' CHANGE `comment` `comment` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL';

			// Add multi-column index on the locations table if missing. This index can be missing on new installs between 4.3 and 4.3.2.
			$sql     = 'SHOW INDEX FROM ' . $this->tbl_locations . " WHERE Key_name='trip_id_occurred'";
			$indexes = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( count( $indexes ) === 0 ) {
				$upgrade_sql[29] = 'ALTER TABLE ' . $this->tbl_locations . ' ADD INDEX `trip_id_occurred` (`trip_id`, `occurred`)';
			}

			for ( $i = $installed_version + 1; $i <= $this->db_version; $i++ ) {
				if ( array_key_exists( $i, $upgrade_sql ) ) {
					$wpdb->query( $upgrade_sql[ $i ] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
			$this->trackserver->update_option( 'db_version', $this->db_version );
		}
	}

} // class
