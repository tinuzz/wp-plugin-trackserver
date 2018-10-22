<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Admin {

	private $trackserver; // Reference to the main object
	public  $settings;    // Reference to the settings object

	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
	}

	/**
	 * Add actions for the admin pages.
	 *
	 * @since 1.0
	 */

	public function add_actions() {
		add_action( 'admin_init', array( &$this, 'admin_init' ) );
	}

	/**
	 * Handler for 'admin_init'. Calls trackserver_install() and registers settings.
	 *
	 * @since 3.0
	 */
	public function admin_init() {
		$this->trackserver_install();
		$this->register_settings();
	}

	private function trackserver_install() {
	}

	private function register_settings() {
		require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-settings.php';
		$this->settings = new Trackserver_Settings( $this->trackserver );
		$this->settings->register();
	}

}
