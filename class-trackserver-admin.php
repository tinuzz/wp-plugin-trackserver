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
		add_action( 'admin_head', array( &$this, 'admin_head' ) );
		add_action( 'add_meta_boxes', array( &$this, 'add_meta_boxes' ) );
		add_filter( 'default_content', array( &$this, 'embedded_map_default_content' ), 10, 2 );
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

	/**
	 * Print some CSS in the header of the admin panel.
	 *
	 * @since 1.0
	 */
	function admin_head() {
		echo <<<EOF
			<style type="text/css">
				.wp-list-table .column-id { width: 50px; }
				.wp-list-table .column-user_id { width: 100px; }
				.wp-list-table .column-tstart { width: 150px; }
				.wp-list-table .column-tend { width: 150px; }
				.wp-list-table .column-numpoints { width: 50px; }
				.wp-list-table .column-distance { width: 60px; }
				.wp-list-table .column-edit { width: 50px; }
				.wp-list-table .column-view { width: 50px; }
				#addtrack { margin: 1px 8px 0 0; }
			</style>\n
EOF;
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
		echo esc_html( $code );
		echo '</div><br>';
		esc_html_e( 'Please note:', 'trackserver' );
		echo '<br><ul style="list-style: square; margin-left: 20px;">';
		if ( in_array( $status, array( 'draft', 'auto-draft', 'future' ), true ) ) {
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

	/**
	 * Handler for 'default_content' filter. Sets the default content of a new
	 * embedded map to an empty [tsmap] shortcode.
	 */
	function embedded_map_default_content( $content, $post ) {
		switch ( $post->post_type ) {
			case 'tsmap':
				$content = '[tsmap]';
				break;
		}
		return $content;
	}

}
