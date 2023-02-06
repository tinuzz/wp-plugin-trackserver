<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-db.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-settings.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-profile.php';

class Trackserver_Admin {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	public  $settings;    // Reference to the settings object
	private $tbl_tracks;
	private $tbl_locations;
	private $trashcan_icon = '<svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="16" height="16" x="0px" y="0px" viewBox="0 0 172.541 172.541" style="enable-background:new 0 0 172.541 172.541;" xml:space="preserve"><g><path d="M166.797,25.078h-13.672h-29.971V0H49.388v25.078H19.417H5.744v15h14.806l10,132.463h111.443l10-132.463h14.805V25.078z M64.388,15h43.766v10.078H64.388V15z M128.083,157.541H44.46L35.592,40.078h13.796h73.766h13.796L128.083,157.541z"/><rect x="80.271" y="65.693" width="12" height="66.232"/><rect x="57.271" y="65.693" width="12" height="66.232"/><rect x="103.271" y="65.693" width="12" height="66.232"/></g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> <g> </g> </svg>';

	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
		$this->set_table_refs();
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
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		add_filter( 'plugin_action_links_trackserver/trackserver.php', array( &$this, 'add_settings_link' ) );
		add_action( 'admin_post_trackserver_save_track', array( &$this, 'admin_post_save_track' ) );

		// Still on the main plugin object
		add_action( 'admin_post_trackserver_upload_track', array( $this->trackserver, 'admin_post_upload_track' ) );
		add_action( 'wp_ajax_trackserver_save_track', array( $this->trackserver, 'admin_ajax_save_modified_track' ) );

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
	 * Installer.
	 *
	 * This runs on every admin request. It installs/update the database
	 * tables and sets capabilities for user roles.
	 *
	 * @since 1.0
	 *
	 * @global object $wpdb The WordPress database interface
	 */
	private function trackserver_install() {
		$db = Trackserver_Db::get_instance( $this->trackserver );
		$db->create_tables();
		$db->check_update_db();
		$this->set_capabilities();
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
	public function wpmu_new_blog( $blog_id ) {
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
	public function wpmu_drop_tables( $tables ) {
		$this->set_table_refs();
		$tables[] = $this->tbl_tracks;
		$tables[] = $this->tbl_locations;
		return $tables;
	}

	/**
	/* Wrapper for switch_to_blog() that sets properties on $this
	 */
	private function switch_to_blog( $blog_id ) {
		switch_to_blog( $blog_id );
		$this->set_table_refs();
	}

	/**
	 * Wrapper for restore_current_blog() that sets properties on $this
	 */
	private function restore_current_blog() {
		restore_current_blog();
		$this->set_table_refs();
	}

	/**
	 * Update the DB table properties on $this. Admin actions that can be called
	 * from the context of a different blog (network admin actions) need to call
	 * this before using the 'tbl_*' properties
	 */
	private function set_table_refs() {
		global $wpdb;
		$this->tbl_tracks    = $wpdb->prefix . 'ts_tracks';
		$this->tbl_locations = $wpdb->prefix . 'ts_locations';
	}

	/**
	 * Add capabilities for using Trackserver to WordPress roles.
	 *
	 * @since 1.3
	 */
	private function set_capabilities() {
		$roles = array(
			'administrator' => array( 'use_trackserver', 'trackserver_publish', 'trackserver_admin' ),
			'editor'        => array( 'use_trackserver', 'trackserver_publish' ),
			'author'        => array( 'use_trackserver' ),
		);

		foreach ( $roles as $rolename => $capnames ) {
			$role = get_role( $rolename );
			foreach ( $capnames as $cap ) {
				if ( ! ( $role->has_cap( $cap ) ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Handler for 'admin_enqueue_scripts'. Load javascript and stylesheets
	 * for the admin panel.
	 *
	 * @since 1.0
	 *
	 * @param string $hook The hook suffix for the current admin page.
	 */
	public function admin_enqueue_scripts( $hook ) {

		$settings = array();

		switch ( $hook ) {
			case 'trackserver_page_trackserver-tracks':
			case 'trackserver_page_trackserver-yourprofile':
				$this->trackserver->load_common_scripts();

				wp_enqueue_style( 'trackserver-admin', TRACKSERVER_PLUGIN_URL . 'trackserver-admin.css', array(), TRACKSERVER_VERSION );

				// The is_ssl() check should not be necessary, but somehow, get_home_url() doesn't correctly return a https URL by itself
				$track_base_url = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/?', ( is_ssl() ? 'https' : 'http' ) );
				wp_localize_script( 'trackserver', 'track_base_url', array( 'track_base_url' => $track_base_url ) );
				$settings['profile_msg'] = Trackserver_Profile::get_instance( $this->trackserver )->get_messages();

				// Enqueue the main script last
				wp_enqueue_script( 'trackserver' );

				// No break! The following goes for both hooks.
				// The options page only has 'trackserver-admin.js'.

			case 'toplevel_page_trackserver-options':
				$settings['msg']   = array(
					'areyousure'     => __( 'Are you sure?', 'trackserver' ),
					'delete'         => __( 'deletion', 'trackserver' ),
					'deletecap'      => __( 'Deleting', 'trackserver' ),
					'merge'          => __( 'merging', 'trackserver' ),
					'duplicatecap'   => __( 'Duplicating', 'trackserver' ),
					'recalc'         => __( 'recalculation', 'trackserver' ),
					'dlgpx'          => __( 'downloading', 'trackserver' ),
					'dlkml'          => __( 'downloading', 'trackserver' ),
					'track'          => __( 'track', 'trackserver' ),
					'tracks'         => __( 'tracks', 'trackserver' ),
					'edittrack'      => __( 'Edit track', 'trackserver' ),
					'deletepoint'    => __( 'Delete point', 'trackserver' ),
					'splittrack'     => __( 'Split track here', 'trackserver' ),
					'savechanges'    => __( 'Save changes', 'trackserver' ),
					'unsavedchanges' => __( 'There are unsaved changes. Save?', 'trackserver' ),
					'save'           => __( 'Save', 'trackserver' ),
					'discard'        => __( 'Discard', 'trackserver' ),
					'cancel'         => __( 'Cancel', 'trackserver' ),
					'delete1'        => __( 'Delete', 'trackserver' ),
					/* translators: %1$s = action, %2$s = number and %3$s is 'track' or 'tracks' */
					'selectminimum'  => __( 'For %1$s, select %2$s %3$s at minimum', 'trackserver' ),
				);
				$settings['urls']  = array(
					'adminpost'    => admin_url() . 'admin-post.php',
					'managetracks' => admin_url() . 'admin.php?page=trackserver-tracks',
				);
				$settings['icons'] = array(
					'trashcan' => $this->trashcan_icon,
				);

				// Enqueue leaflet-editable
				wp_enqueue_script( 'leaflet-editable', TRACKSERVER_JSLIB . 'leaflet-editable-1.1.0/Leaflet.Editable.min.js', array(), false, true );

				// Enqueue the admin js (Thickbox overrides) in the footer
				wp_register_script( 'trackserver-admin', TRACKSERVER_PLUGIN_URL . 'trackserver-admin.js', array( 'thickbox' ), TRACKSERVER_VERSION, true );
				wp_localize_script( 'trackserver-admin', 'trackserver_admin_settings', $settings );
				wp_enqueue_script( 'trackserver-admin' );
				break;
		}
	}

	/**
	 * Print some CSS in the header of the admin panel.
	 *
	 * @since 1.0
	 */
	public function admin_head() {
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
	public function embedded_map_default_content( $content, $post ) {
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
			$links_array[] = '<a href="https://www.grendelman.net/wp/trackserver-v5-0-released/" target="_blank">v5.0 Release notes</a> (<b>Please read!</b>)';
		}
		return $links_array;
	}

	public function admin_menu() {
		$page                   = add_options_page( 'Trackserver Options', 'Trackserver', 'manage_options', 'trackserver-admin-menu', array( &$this, 'options_page_html' ) );
		$page                   = str_replace( 'admin_page_', '', $page );
		$this->options_page     = str_replace( 'settings_page_', '', $page );
		$this->options_page_url = menu_page_url( $this->options_page, false );

		// A dedicated menu in the main tree
		add_menu_page(
			esc_html__( 'Trackserver Options', 'trackserver' ),
			esc_html__( 'Trackserver', 'trackserver' ),
			'manage_options',
			'trackserver-options',
			array( &$this, 'options_page_html' ),
			TRACKSERVER_PLUGIN_URL . 'img/trackserver.png'
		);

		add_submenu_page(
			'trackserver-options',
			esc_html__( 'Trackserver Options', 'trackserver' ),
			esc_html__( 'Options', 'trackserver' ),
			'manage_options',
			'trackserver-options',
			array( &$this, 'options_page_html' )
		);

		$page2 = add_submenu_page(
			'trackserver-options',
			esc_html__( 'Manage tracks', 'trackserver' ),
			esc_html__( 'Manage tracks', 'trackserver' ),
			'use_trackserver',
			'trackserver-tracks',
			array( &$this, 'manage_tracks_html' )
		);

		$page3 = add_submenu_page(
			'trackserver-options',
			esc_html__( 'Your profile', 'trackserver' ),
			esc_html__( 'Your profile', 'trackserver' ),
			'use_trackserver',
			'trackserver-yourprofile',
			array( Trackserver_Profile::get_instance( $this->trackserver ), 'yourprofile_html' )
		);

		// Early action to set up the 'Manage tracks' page and handle bulk actions.
		add_action( 'load-' . $page2, array( &$this, 'load_manage_tracks' ) );

		// Early action to set up the 'Your profile' page and handle POST
		add_action( 'load-' . $page3, array( &$this, 'load_your_profile' ) );
	}

	/**
	 * Output HTML for the Trackserver options page.
	 *
	 * @since 1.0
	 */
	public function options_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		add_thickbox();

		echo '<div class="wrap"><h2>';
		esc_html_e( 'Trackserver Options', 'trackserver' );
		echo '</h2>';

		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			echo '<div class="updated"><p>' . esc_html__( 'Settings updated', 'trackserver' ) . '</p></div>';

			// Flush rewrite rules, for when embedded maps slug has been changed
			flush_rewrite_rules();
		}

		?>
			<hr />
			<form name="trackserver-options" action="options.php" method="post">
		<?php

		settings_fields( 'trackserver-options' );
		do_settings_sections( 'trackserver' );
		submit_button( esc_attr__( 'Update options', 'trackserver' ), 'primary', 'submit' );

		?>
			</form>
			<hr />
		</div>
		<?php
	}

	public function manage_tracks_html() {

		if ( ! current_user_can( 'use_trackserver' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		add_thickbox();
		$this->trackserver->setup_tracks_list_table();

		$search = ( isset( $_REQUEST['s'] ) ? wp_unslash( $_REQUEST['s'] ) : '' );
		$this->trackserver->tracks_list_table->prepare_items( $search );

		$url = admin_url() . 'admin-post.php';

		?>
			<!-- Edit track properties -->
			<div id="trackserver-edit-modal" style="display:none;">
				<p>
					<form id="trackserver-edit-track" method="post" action="<?php echo $url; ?>">
						<table style="width: 100%">
							<?php wp_nonce_field( 'manage_track' ); ?>
							<input type="hidden" name="action" value="trackserver_save_track" />
							<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />
							<input type="hidden" id="track_id" name="track_id" value="" />
							<tr>
								<th style="width: 150px;"><?php esc_html_e( 'Name', 'trackserver' ); ?></th>
								<td><input id="input-track-name" name="name" type="text" style="width: 100%" /></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Source', 'trackserver' ); ?></th>
								<td><input id="input-track-source" name="source" type="text" style="width: 100%" /></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Comment', 'trackserver' ); ?></th>
								<td><textarea id="input-track-comment" name="comment" rows="3" style="width: 100%; resize: none;"></textarea></td>
							</tr>
						</table>
						<br />
						<input class="button action button-primary" type="submit" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" name="save_track">
						<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
						<input type="hidden" id="trackserver-edit-action" name="trackserver_action" value="save">
						<button id="trackserver-delete-track" class="button action" type="button" title="<?php esc_html_e( 'Delete', 'trackserver' ); ?>" style="float: right;" onClick="tb_remove(); return false;">
							<div style="position: relative; top: 3px; display: inline-block;">
								<?php echo $this->trashcan_icon; ?>
							</div>
							<?php esc_html_e( 'Delete', 'trackserver' ); ?>
						</button>
					</form>
				</p>
			</div>

			<!-- View track -->
			<div id="trackserver-view-modal" style="display:none;">
				<div id="trackserver-adminmap-container">
					<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
				</div>
			</div>

			<!-- Merge tracks -->
			<div id="trackserver-merge-modal" style="display:none;">
				<p>
					<?php esc_html__( 'Merge all points of multiple tracks into one track. Please specify the name for the merged track.', 'trackserver' ); ?>
					<form method="post" action="<?php echo $url; ?>">
						<table style="width: 100%">
							<?php wp_nonce_field( 'manage_track' ); ?>
							<tr>
								<th style="width: 150px;"><?php esc_html_e( 'Merged track name', 'trackserver' ); ?></th>
								<td><input id="input-merged-name" name="name" type="text" style="width: 100%" /></td>
							</tr>
						</table>
						<br />
						<span class="aligncenter"><i><?php esc_html_e( 'Warning: this action cannot be undone!', 'trackserver' ); ?></i></span><br />
						<div class="alignright">
							<input class="button action" type="button" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" id="merge-submit-button">
							<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
						</div>
					</form>
				</p>
			</div>

			<!-- Upload files -->
			<div id="trackserver-upload-modal" style="display:none;">
				<div style="padding: 15px 0">
					<form id="trackserver-upload-form" method="post" action="<?php echo $url; ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'upload_track' ); ?>
						<input type="hidden" name="action" value="trackserver_upload_track" />
						<input type="file" name="gpxfile[]" multiple="multiple" style="display: none" id="trackserver-file-input" />
						<input type="button" class="button button-hero" value="<?php esc_attr_e( 'Select files', 'trackserver' ); ?>" id="ts-select-files-button" />
						<button type="button" class="button button-hero" value="<?php esc_attr_e( 'Upload', 'trackserver' ); ?>" id="trackserver-upload-files" disabled="disabled"><?php esc_html_e( 'Upload', 'trackserver' ); ?></button>
					</form>
					<br />
					<br />
					<?php esc_html_e( 'Selected files', 'trackserver' ); ?>:<br />
					<div id="trackserver-upload-filelist" style="height: 200px; max-height: 200px; overflow-y: auto; border: 1px solid #dddddd; padding-left: 5px;"></div>
					<br />
					<div id="trackserver-upload-warning"></div>
				</div>
			</div>

			<!-- Main list table -->
			<form id="trackserver-tracks" method="post">
				<input type="hidden" name="page" value="trackserver-tracks" />
				<div class="wrap">
					<h2><?php esc_html_e( 'Manage tracks', 'trackserver' ); ?></h2>
					<?php $this->trackserver->notice_bulk_action_result(); ?>
					<?php $this->trackserver->tracks_list_table->views(); ?>
					<?php $this->trackserver->tracks_list_table->search_box( esc_attr__( 'Search tracks', 'trackserver' ), 'search_tracks' ); ?>
					<?php $this->trackserver->tracks_list_table->display(); ?>
				</div>
			</form>
		<?php
	}

	/**
	 * Handler for the load-$hook for the 'Manage tracks' page
	 * It sets up the list table and processes any bulk actions
	 */
	public function load_manage_tracks() {
		$this->trackserver->setup_tracks_list_table();
		$action = $this->trackserver->tracks_list_table->get_current_action();
		if ( $action ) {
			$this->process_bulk_action( $action );
		}
		// Set up bulk action result notice
		$this->setup_bulk_action_result_msg();
	}

	/**
	 * Handler for the load-$hook for the 'Trackserver profile' page.
	 * It handles a POST (profile update) and sets up a result message.
	 *
	 * @since 1.9
	 */
	public function load_your_profile() {
		// Handle POST from 'Trackserver profile' page
		// $_POST['ts_user_meta'] holds all the values, or we handle 'apppass_action'
		if ( isset( $_POST['ts_user_meta'] ) || isset( $_POST['apppass_action'] ) ) {
			check_admin_referer( 'your-profile' );
			Trackserver_Profile::get_instance( $this->trackserver )->process_profile_update();
			$this->trackserver->process_profile_update();  // this will not return
		}

		// Set up bulk action result notice
		$this->setup_bulk_action_result_msg();
	}

	/**
	 * Function to set up a bulk action result message to be displayed later.
	 */
	private function setup_bulk_action_result_msg() {
		if ( isset( $_COOKIE['ts_bulk_result'] ) ) {
			$this->trackserver->bulk_action_result_msg = stripslashes( $_COOKIE['ts_bulk_result'] );
			setcookie( 'ts_bulk_result', '', time() - 3600 );
		}
	}

	/**
	 * Filter callback to add a link to the plugin's settings.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=trackserver-options">' . esc_html__( 'Settings', 'trackserver' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	public function admin_post_save_track() {
		global $wpdb;

		$track_id = (int) $_REQUEST['track_id'];
		check_admin_referer( 'manage_track_' . $track_id );

		if ( $this->trackserver->current_user_can_manage( $track_id ) ) {

			// Save track. Use stripslashes() on the data, because WP magically escapes it.
			$name    = stripslashes( $_REQUEST['name'] );
			$source  = stripslashes( $_REQUEST['source'] );
			$comment = stripslashes( $_REQUEST['comment'] );

			if ( $_REQUEST['trackserver_action'] === 'delete' ) {
				$result  = $this->trackserver->wpdb_delete_tracks( (int) $track_id );
				$message = 'Track "' . $name . '" (ID=' . $track_id . ', ' .
					$result['locations'] . ' locations) deleted';
			} elseif ( $_REQUEST['trackserver_action'] === 'split' ) {
				$vertex  = intval( $_REQUEST['vertex'] );  // not covered by nonce!
				$r       = $this->wpdb_split_track( $track_id, $vertex );
				$message = 'Track "' . $name . '" (ID=' . $track_id . ') has been split at point ' . $vertex . ' ' . $r;  // TODO: i18n
			} else {
				$data  = array(
					'name'    => $name,
					'source'  => $source,
					'comment' => $comment,
				);
				$where = array(
					'id' => $track_id,
				);
				$wpdb->update( $this->tbl_tracks, $data, $where, '%s', '%d' );

				$message = 'Track "' . $name . '" (ID=' . $track_id . ') saved';
			}
		} else {
			$message = __( 'It seems you have insufficient permissions to manage track ID ' ) . $track_id;
		}

		// Redirect back to the admin page. This should be safe.
		setcookie( 'ts_bulk_result', $message, time() + 300 );

		// Propagate search string to the redirect
		$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 's' ), $_REQUEST['_wp_http_referer'] );
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			$referer = add_query_arg( 's', rawurlencode( wp_unslash( $_POST['s'] ) ), $referer );
		}
		wp_redirect( $referer );
		exit;
	}

	/**
	 * Function to process any bulk action from the tracks_list_table
	 */
	private function process_bulk_action( $action ) {
		global $wpdb;

		// The action name is 'bulk-' + plural form of items in WP_List_Table
		check_admin_referer( 'bulk-tracks' );
		$track_ids = $this->trackserver->filter_current_user_tracks( $_REQUEST['track'] );

		// Propagate search string to the redirect
		$referer = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 's' ), $_REQUEST['_wp_http_referer'] );
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			$referer = add_query_arg( 's', rawurlencode( wp_unslash( $_POST['s'] ) ), $referer );
		}

		if ( $action === 'delete' ) {
			if ( count( $track_ids ) > 0 ) {
				$result = $this->trackserver->wpdb_delete_tracks( $track_ids );
				$nl     = $result['locations'];
				$nt     = $result['tracks'];
				// translators: placeholders are for number of locations and number of tracks
				$format  = __( 'Deleted %1$d location(s) in %2$d track(s).', 'trackserver' );
				$message = sprintf( $format, intval( $nl ), intval( $nt ) );
			} else {
				$message = __( 'No tracks deleted', 'trackserver' );
			}
			setcookie( 'ts_bulk_result', $message, time() + 300 );
			wp_redirect( $referer );
			exit;
		}

		if ( $action === 'merge' ) {
			// Need at least 2 tracks
			$n = count( $track_ids );
			if ( $n > 1 ) {
				$id   = min( $track_ids );
				$rest = array_diff( $track_ids, array( $id ) );
				// How useful is it to escape integers?
				array_walk( $rest, array( $wpdb, 'escape_by_ref' ) );
				$in   = '(' . implode( ',', $rest ) . ')';
				$sql  = $wpdb->prepare( 'UPDATE ' . $this->tbl_locations . " SET trip_id=%d WHERE trip_id IN $in", $id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$nl   = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql  = 'DELETE FROM ' . $this->tbl_tracks . " WHERE id IN $in";
				$nt   = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$name = stripslashes( $_REQUEST['merged_name'] );
				$sql  = $wpdb->prepare( 'UPDATE ' . $this->tbl_tracks . ' SET name=%s WHERE id=%d', $name, $id ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				// TODO: consider checking for duplicate points that we created ourselves when splitting a track,
				// (see wpdb_split_track()) and remove them. We'd need 2 or 3 queries and some ugly code for that.
				// Is it worth the effort?

				$this->trackserver->calculate_distance( $id );
				$format  = __( "Merged %1\$d location(s) from %2\$d track(s) into '%3\$s'.", 'trackserver' );
				$message = sprintf( $format, intval( $nl ), intval( $nt ), $name );
			} else {
				$format  = __( 'Need >= 2 tracks to merge, got only %1\$d', 'trackserver' );
				$message = sprintf( $format, $n );
			}
			setcookie( 'ts_bulk_result', $message, time() + 300 );
			wp_redirect( $referer );
			exit;
		}

		if ( $action === 'duplicate' ) {
			$n = count( $track_ids );
			if ( $n > 0 ) {

				$nl = 0;
				foreach ( $track_ids as $tid ) {

					// Duplicate track record
					$sql = $wpdb->prepare(
						'INSERT INTO ' . $this->tbl_tracks . // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						' (user_id, name, created, source, comment) SELECT user_id, name, created,' .
						' source, comment FROM ' . $this->tbl_tracks . ' WHERE id=%s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$tid
					);
					$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$new_id = $wpdb->insert_id;

					// Duplicate locations
					$sql = $wpdb->prepare(
						'INSERT INTO ' . $this->tbl_locations . // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						' (trip_id, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment, hidden) ' .
						'SELECT %d, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment, hidden ' .
						'FROM ' . $this->tbl_locations . ' WHERE trip_id=%d ORDER BY occurred', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$new_id,
						$tid
					);
					$nl += $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

					$this->trackserver->calculate_distance( $new_id );
				}

				// translators: placeholders are for total number of locations and number of tracks
				$format  = __( 'Duplicated %1$d location(s) in %2$d track(s).', 'trackserver' );
				$message = sprintf( $format, intval( $nl ), intval( $n ) );

			} else {
				$message = __( 'No tracks duplicated', 'trackserver' );
			}
			setcookie( 'ts_bulk_result', $message, time() + 300 );
			wp_redirect( $referer );
			exit;
		}

		if ( $action === 'dlgpx' ) {

			$track_format = 'gpx';
			// @codingStandardsIgnoreLine
			$query         = json_encode( array( 'id' => $track_ids, 'live' => array() ) );
			$query         = base64_encode( $query );
			$query_nonce   = wp_create_nonce( 'manage_track_' . $query );
			$alltracks_url = get_home_url( null, $this->trackserver->url_prefix . '/' . $this->trackserver->options['gettrack_slug'] . '/?query=' . rawurlencode( $query ) . "&format=$track_format&admin=1&_wpnonce=$query_nonce" );
			wp_redirect( $alltracks_url );
		}

		if ( $action === 'recalc' ) {
			if ( count( $track_ids ) > 0 ) {
				$exec_t0 = microtime( true );
				foreach ( $track_ids as $id ) {
					$this->trackserver->calculate_distance( $id );
				}
				$exec_time = round( microtime( true ) - $exec_t0, 1 );
				// translators: placeholders are for number of tracks and number of seconds elapsed
				$format  = __( 'Recalculated track stats for %1$d track(s) in %2$d seconds', 'trackserver' );
				$message = sprintf( $format, count( $track_ids ), $exec_time );
			} else {
				$message = __( 'No tracks found to recalculate', 'trackserver' );
			}
			setcookie( 'ts_bulk_result', $message, time() + 300 );
			wp_redirect( $referer );
			exit;
		}
	}

	private function wpdb_split_track( $track_id, $point ) {
		global $wpdb;

		$split_id_arr = $this->trackserver->get_location_ids_by_index( $track_id, array( $point ) );
		if ( count( $split_id_arr ) > 0 ) {  // should be exactly 1
			$split_id = $split_id_arr[ $point ]->id;

			// @codingStandardsIgnoreStart
			$sql = $wpdb->prepare( 'SELECT occurred FROM ' . $this->tbl_locations . ' WHERE id=%s', $split_id );
			$occurred = $wpdb->get_var( $sql );

			// Duplicate track record
			$sql = $wpdb->prepare( 'INSERT INTO ' . $this->tbl_tracks .
				" (user_id, name, created, source, comment) SELECT user_id, CONCAT(name, ' #2'), created," .
				" source, comment FROM " . $this->tbl_tracks . " WHERE id=%s", $track_id );
			$wpdb->query( $sql );
			$new_id = $wpdb->insert_id;

			// Update locations with the new track ID
			$sql = $wpdb->prepare( 'UPDATE ' . $this->tbl_locations . ' SET trip_id=%s WHERE trip_id=%s AND occurred > %s', $new_id, $track_id, $occurred );
			$wpdb->query( $sql );

			// Duplicate the split-point to the new track
			$sql = $wpdb->prepare(
				'INSERT INTO ' . $this->tbl_locations .
				" (trip_id, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment) " .
				" SELECT %s, latitude, longitude, altitude, speed, heading, updated, created, occurred, comment FROM " .
				$this->tbl_locations . ' WHERE id=%s', $new_id, $split_id
			);
			$wpdb->query( $sql );
			// @codingStandardsIgnoreEnd

			$this->trackserver->calculate_distance( $track_id );
			$this->trackserver->calculate_distance( $new_id );
			return print_r( $new_id, true );
		}
	}

} // class
