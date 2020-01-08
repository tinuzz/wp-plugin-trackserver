<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Admin {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	public  $settings;    // Reference to the settings object
	private $tbl_tracks;
	private $tbl_locations;

	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
		$this->set_table_refs();
	}

	/**
	 * Create a singleton if it doesn't exist and return it.
	 *
	 * @since 4.4
	 */
	public static function get_instance( $trackserver ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $trackserver );
		}
		return self::$instance;
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
		add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 10, 4 );

		// Still on the main plugin object
		add_action( 'admin_menu', array( $this->trackserver, 'admin_menu' ), 9 );
		add_action( 'admin_post_trackserver_save_track', array( $this->trackserver, 'admin_post_save_track' ) );
		add_action( 'admin_post_trackserver_upload_track', array( $this->trackserver, 'admin_post_upload_track' ) );
		add_action( 'admin_enqueue_scripts', array( $this->trackserver, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_trackserver_save_track', array( $this->trackserver, 'admin_ajax_save_modified_track' ) );
		add_filter( 'plugin_action_links_trackserver/trackserver.php', array( $this->trackserver, 'add_settings_link' ) );

		// WordPress MU
		add_action( 'wpmu_new_blog', array( &$this, 'wpmu_new_blog' ) );
		add_filter( 'wpmu_drop_tables', array( &$this, 'wpmu_drop_tables' ) );
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

	/**
	 * Installer function.
	 *
	 * This runs on every admin request. It installs/update the database
	 * tables and sets capabilities for user roles.
	 *
	 * @since 1.0
	 *
	 * @global object $wpdb The WordPress database interface
	 */
	private function trackserver_install() {
		$this->trackserver->create_tables();
		$this->trackserver->check_update_db();
		$this->trackserver->set_capabilities();
	}

	/**
	 * Handler for 'wpmu_new_blog'. Only accepts one argument.
	 *
	 * This action is called when a new blog is created in a WordPress
	 * network. This function switches to the new blog, stores options with
	 * default values and calls the installer function to create the database
	 * tables and set user capabilities.
	 *
	 * @since 3.0
	 */
	function wpmu_new_blog( $blog_id ) {
		if ( is_plugin_active_for_network( 'trackserver/trackserver.php' ) ) {
			$this->switch_to_blog( $blog_id );
			$this->trackserver->init_options();
			$this->trackserver_install();
			$this->restore_current_blog();
		}
	}

	/**
	 * Handler for 'wpmu_drop_tables'
	 *
	 * This filter adds Trackserver's database tables to the list of tables
	 * to be dropped when a blog is deleted from a WordPress network.
	 *
	 * @since 3.0
	 */
	function wpmu_drop_tables( $tables ) {
		$this->set_table_refs();
		$tables[] = $this->tbl_tracks;
		$tables[] = $this->tbl_locations;
		return $tables;
	}

	/**
	 * Update the DB table properties on $this. Admin actions that can be called
	 * from the context of a different blog (network admin actions) need to call
	 * this before using the 'tbl_*' properties
	 */
	function set_table_refs() {
		global $wpdb;
		$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
		$this->tbl_locations = $wpdb->prefix . 'ts_locations';
	}

	/**
	/* Wrapper for switch_to_blog() that sets properties on $this
	 */
	function switch_to_blog( $blog_id ) {
		switch_to_blog( $blog_id );
		$this->set_table_refs();
	}

	/**
	 * Wrapper for restore_current_blog() that sets properties on $this
	 */
	function restore_current_blog() {
		restore_current_blog();
		$this->set_table_refs();
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
		Trackserver_Settings::get_instance( $this->trackserver )->register();
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

	/**
	 * Add some relevant links to the plugin meta data on the WordPress plugins page.
	 *
	 * @since 5.0
	 */
	public function plugin_row_meta( $links_array, $plugin_file_name, $plugin_data, $status ) {
		if ( $plugin_file_name === 'trackserver/trackserver.php' ) {
			$links_array[] = '<a href="https://www.grendelman.net/wp/trackserver-wordpress-plugin/" target="_blank">Homepage</a>';
			$links_array[] = '<a href="https://github.com/tinuzz/wp-plugin-trackserver" target="_blank">Github</a>';
		}
		return $links_array;
	}

}
