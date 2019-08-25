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
		add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
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

	/**
	 * Handler for 'add_meta_boxes'. Adds the meta box for Embedded Maps.
	 *
	 * @since 3.4
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'ts_embedded_meta_box',
			esc_html__( 'Embed HTML', 'trackserver' ),      // Title
			array( &$this, 'ts_embedded_meta_box_html' ),   // Callback
			'tsmap'                                         // Post type
		);
	}

	/**
	 * Callback function to print the Enbedded Map meta box HTML.
	 *
	 * @since 3.4
	 */
	public function ts_embedded_meta_box_html( $post ) {
		// The is_ssl() check should not be necessary, but somehow, get_permalink() doesn't correctly return a https URL by itself
		$url    = set_url_scheme( get_permalink( $post ), ( is_ssl() ? 'https' : 'http' ) );
		$code   = '<iframe src="' . $url . '" width="600" height="450" frameborder="0" style="border:0" allowfullscreen></iframe>';
		$status = get_post_status( $post->ID );
		$msg    = '<i>X-Frame-Options</i>';

		esc_html_e( 'To embed this map in a web page outside WordPress, include the following HTML in the page: ', 'trackserver' );
		echo '<br><br><div style="font-family: monospace; background-color: #dddddd">';
	 	esc_html_e( $code );
		echo '</div><br>';
		esc_html_e( 'Please note:', 'trackserver' );
		echo '<br><ul style="list-style: square; margin-left: 20px;">';
		if ( in_array( $status, array( 'draft', 'auto-draft', 'future' ) ) ) {
			echo '<li>';
			esc_html_e( 'This map has not been published. Publishing it may cause the permalink URL to change.', 'trackserver' );
			echo '</li>';
		}
		echo '<li>';
		// translators: the placeholder is for the literal header name in <i> tags.
		printf( esc_html__( 'Make sure your WordPress doesn\'t forbid framing the map with a too-strict %1$s header.', 'trackserver' ), $msg );
		echo '</li></ul>';
		esc_html_e( 'This is what the last saved version of the embedded map looks like:', 'trackserver' );
		echo '<br><br>';
		echo '<iframe src="' . $url . '" width="600" height="450" frameborder="0" style="border:0" allowfullscreen></iframe>';
	}

}
