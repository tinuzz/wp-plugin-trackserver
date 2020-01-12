<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-db.php';
require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-settings.php';

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
		add_action( 'admin_menu', array( &$this, 'admin_menu' ), 9 );

		// Still on the main plugin object
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
		}
		return $links_array;
	}

	public function admin_menu() {

		require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-settings.php';

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
			array( &$this, 'yourprofile_html' )
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

		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == 'true' ) {
			echo '<div class="updated"><p>' . esc_html__( 'Settings updated', 'trackserver' ) . '</p></div>';

			// Flush rewrite rules, for when embedded maps slug has been changed
			flush_rewrite_rules();
		}

		?>
			<hr />
			<form id="trackserver-options" name="trackserver-options" action="options.php" method="post">
		<?php

		settings_fields( 'trackserver-options' );
		do_settings_sections( 'trackserver' );
		submit_button( esc_attr__( 'Update options', 'trackserver' ), 'primary', 'submit' );

		?>
			</form>
			<hr />
		</div>
		<?php
		$this->trackserver->howto_modals_html();
	}

	function manage_tracks_html() {

		if ( ! current_user_can( 'use_trackserver' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		add_thickbox();
		$this->trackserver->setup_tracks_list_table();

		$search = ( isset( $_REQUEST['s'] ) ? wp_unslash( $_REQUEST['s'] ) : '' );
		$this->trackserver->tracks_list_table->prepare_items( $search );

		$url = admin_url() . 'admin-post.php';

		?>
			<div id="ts-edit-modal" style="display:none;">
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
						<input class="button action" type="submit" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" name="save_track">
						<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
						<input type="hidden" id="trackserver-edit-action" name="trackserver_action" value="save">
						<a id="ts-delete-track" href="#" style="float: right; color: red" ><?php esc_html_e( 'Delete', 'trackserver' ); ?></a>
					</form>
				</p>
			</div>
			<div id="ts-view-modal" style="display:none;">
					<div id="tsadminmapcontainer">
						<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
					</div>
			</div>
			<div id="ts-merge-modal" style="display:none;">
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
			<div id="ts-upload-modal" style="display:none;">
				<div style="padding: 15px 0">
					<form id="ts-upload-form" method="post" action="<?php echo $url; ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'upload_track' ); ?>
						<input type="hidden" name="action" value="trackserver_upload_track" />
						<input type="file" name="gpxfile[]" multiple="multiple" style="display: none" id="ts-file-input" />
						<input type="button" class="button button-hero" value="<?php esc_attr_e( 'Select files', 'trackserver' ); ?>" id="ts-select-files-button" />
						<!-- <input type="button" class="button button-hero" value="Upload" id="ts-upload-files-button" disabled="disabled" /> -->
						<button type="button" class="button button-hero" value="<?php esc_attr_e( 'Upload', 'trackserver' ); ?>" id="ts-upload-files-button" disabled="disabled"><?php esc_html_e( 'Upload', 'trackserver' ); ?></button>
					</form>
					<br />
					<br />
					<?php esc_html_e( 'Selected files', 'trackserver' ); ?>:<br />
					<div id="ts-upload-filelist" style="height: 200px; max-height: 200px; overflow-y: auto; border: 1px solid #dddddd; padding-left: 5px;"></div>
					<br />
					<div id="ts-upload-warning"></div>
				</div>
			</div>
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

	public function yourprofile_html() {

		if ( ! current_user_can( 'use_trackserver' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		add_thickbox();

		$user = wp_get_current_user();

		// translators: placeholder is for a user's display name
		$title = __( 'Trackserver profile for %s', 'trackserver' );
		$title = sprintf( $title, $user->display_name );

		?>
		<div class="wrap">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php $this->trackserver->notice_bulk_action_result(); ?>
			<form id="trackserver-profile" method="post">
				<?php wp_nonce_field( 'your-profile' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="trackme_access_key">
									<?php esc_html_e( 'TrackMe password', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->trackme_passwd_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="mapmytracks_profile">
									<?php esc_html_e( 'MapMyTracks profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->mapmytracks_profile_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="osmand_access_key">
									<?php esc_html_e( 'OsmAnd access key', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->osmand_key_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="sendlocation_access_key">
									<?php esc_html_e( 'SendLocation access key', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->sendlocation_key_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Share via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->share_friends_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Follow users via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->follow_friends_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="infobar_template">
									<?php esc_html_e( 'Shortcode infobar template', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->infobar_template_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="geofence_center">
									<?php esc_html_e( 'Geofencing', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver->geofences_html(); ?>
							</td>
						</tr>
					<tbody>
				</table>
				<p class="submit">
					<input type="submit" value="<?php esc_html_e( 'Update profile', 'trackserver' ); ?>"
						class="button button-primary" id="submit" name="submit">
				</p>
			</form>
			<div id="ts-view-modal" style="display:none;">
					<div id="tsadminmapcontainer">
						<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
					</div>
			</div>
		</div>
		<?php
		$this->trackserver->howto_modals_html();
	}

	/**
	 * Handler for the load-$hook for the 'Manage tracks' page
	 * It sets up the list table and processes any bulk actions
	 */
	public function load_manage_tracks() {
		$this->trackserver->setup_tracks_list_table();
		$action = $this->trackserver->tracks_list_table->get_current_action();
		if ( $action ) {
			$this->trackserver->process_bulk_action( $action );
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
		// $_POST['ts_user_meta'] holds all the values
		if ( isset( $_POST['ts_user_meta'] ) ) {
			check_admin_referer( 'your-profile' );
			$this->trackserver->process_profile_update();
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

} // class
