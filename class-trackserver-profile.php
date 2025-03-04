<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Profile {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	private $p_index = 3; // A counter used for numbering HTML elements
	private $current_user;
	private $app_passwords;
	private $username;
	private $url;
	private $url2;

	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
		$this->current_user  = wp_get_current_user();
		$this->app_passwords = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );
		$this->username      = $this->current_user->user_login;
		$base_url            = site_url( null ) . $this->trackserver->url_prefix;
		$slug                = $this->trackserver->options['trackserver_slug'];
		$this->url           = $base_url . '/' . $slug;
		$this->url2          = $this->url . '/' . $this->username . '/**********';
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
	 * This function returns an array of localized messages for the trackserver-admin.js,
	 * specific to the Trackserver_Profile class. It is called from Trackserver_Admin.
	 */
	public function get_messages() {

		return array(
			'view' => __( 'View', 'trackserver' ),
			'hide' => __( 'Hide', 'trackserver' ),
		);
	}

	public function process_profile_update() {
	}

	public function yourprofile_html() {

		if ( ! current_user_can( 'use_trackserver' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		add_thickbox();

		$user = wp_get_current_user();

		// translators: placeholder is for a user's display name
		$title = __( 'Trackserver profile for %s', 'trackserver' );
		$title = sprintf( $title, $user->display_name );
		$url   = menu_page_url( 'trackserver-yourprofile', false );

		?>
		<div class="wrap">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php $this->trackserver->notice_bulk_action_result(); ?>
			<form method="post">
				<?php wp_nonce_field( 'your-profile' ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Trackserver URL', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackserver_url_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'App passwords', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->app_passwords_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( '&micro;Logger profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->ulogger_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/ulogger-logo.png'; ?>" alt="&micro;logger" width="150">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="mapmytracks_profile">
									<?php esc_html_e( 'OruxMaps profile', 'trackserver' ); ?>
								</label>
							</th>
							<td style="vertical-align: top">
								<div class="profile-content-grid">
									<div>
										<?php $this->mapmytracks_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/oruxmaps-logo.png'; ?>" alt="OruxMaps" width="150">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'TrackMe profile', 'trackserver' ); ?>
								</label>
							</th>
							<td style="vertical-align: top">
								<div class="profile-content-grid">
									<div>
										<?php $this->trackme_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/trackme-logo.png'; ?>" alt="TrackMe" width="150">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'GPSLogger profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->gpslogger_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/gpslogger-logo.png'; ?>" alt="GPSLogger" width="130">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'OwnTracks profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->owntracks_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/owntracks-logo.png'; ?>" alt="OwnTracks" width="150">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'PhoneTrack profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->phonetrack_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/phonetrack-logo.png'; ?>" alt="PhoneTrack" width="130">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'OsmAnd profile', 'trackserver' ); ?>
								</label>
							</th>
							<td style="vertical-align: top">
								<div class="profile-content-grid">
									<div>
										<?php $this->osmand_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/osmand-logo.png'; ?>" alt="OsmAnd" width="150">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Traccar Client profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->traccar_profile_html(); ?>
									</div>
									<div>
										<img src="<?php echo esc_url( TRACKSERVER_PLUGIN_URL ) . 'img/traccar-logo.png'; ?>" alt="Traccar Client" width="130">
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'SendLocation profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<div class="profile-content-grid">
									<div>
										<?php $this->sendlocation_profile_html(); ?>
									</div>
									<div>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Share via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->share_friends_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'Follow users via TrackMe Cloud Sharing / OwnTracks Friends', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->follow_friends_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="infobar_template">
									<?php esc_html_e( 'Shortcode infobar template', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->infobar_template_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="geofence_center">
									<?php esc_html_e( 'Geofencing', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->geofences_html(); ?>
							</td>
						</tr>
					<tbody>
				</table>
				<p class="submit">
					<input type="submit" value="<?php esc_html_e( 'Update profile', 'trackserver' ); ?>"
						class="button button-primary" id="submit" name="submit">
				</p>
			</form>
			<div id="trackserver-geofences-modal" style="display:none;">
				<div id="trackserver-adminmap-container">
					<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
				</div>
			</div>
			<div id="trackserver-addpass-modal" style="display:none;">
				<p>
					<form id="trackserver-add-pass" method="post" action="<?php echo esc_url( $url ); ?>">
						<table style="width: 100%">
							<?php wp_nonce_field( 'your-profile' ); ?>
							<input type="hidden" name="apppass_action" value="add">
							<tr>
								<th style="width: 150px;"><?php esc_html_e( 'Password', 'trackserver' ); ?></th>
								<td>
									<input id="ts-apppass-input" name="password" type="password" size="30" />
									<input type="button" class="button" id="ts-gen-pass-button" value="<?php esc_attr_e( 'Generate', 'trackserver' ); ?>">
									<input type="button" class="button" id="ts-view-pass-button" value="<?php esc_attr_e( 'View', 'trackserver' ); ?>">
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Permissions', 'trackserver' ); ?></th>
								<td>
									<input type="checkbox" name="permission[]" value="read"> Read
									<input type="checkbox" name="permission[]" value="write" checked> Write
									<input type="checkbox" name="permission[]" value="delete"> Delete
								</td>
							</tr>
						</table>
						<br>
						<input class="button action button-primary" type="submit" value="<?php esc_attr_e( 'Save', 'trackserver' ); ?>" name="save_track">
						<input class="button action" type="button" value="<?php esc_attr_e( 'Cancel', 'trackserver' ); ?>" onClick="tb_remove(); return false;">
					</form>
				</p>
			</div>
		</div>
		<?php
		$this->howto_modals_html();
	}

	private function howto_modals_html() {
		printf(
			'<div id="trackserver-trackmehowto-modal" style="display:none;">
				<p>
						<img src="%1$s" alt="%2$s" />
				</p>
			</div>
			<div id="trackserver-osmandhowto-modal" style="display:none;">
				<p>
						<img src="%3$s" alt="%4$s" />
				</p>
			</div>
			<div id="trackserver-oruxmapshowto-modal" style="display:none;">
				<p>
						<img src="%5$s" alt="%6$s" />
				</p>
			</div>
			<div id="trackserver-uloggerhowto-modal" style="display:none;">
				<p>
						<img src="%7$s" alt="%8$s" />
				</p>
			</div>
			<div id="trackserver-gpsloggerhowto-modal" style="display:none;">
				<p>
						<img src="%9$s" alt="%10$s" />
				</p>
			</div>
			<div id="trackserver-owntrackshowto-modal" style="display:none;">
				<p>
						<img src="%11$s" alt="%12$s" />
				</p>
			</div>
			<div id="trackserver-phonetrackhowto-modal" style="display:none;">
				<p>
						<img src="%13$s" alt="%14$s" />
				</p>
			</div>
			<div id="trackserver-traccarhowto-modal" style="display:none;">
				<p>
						<img src="%15$s" alt="%16$s" />
				</p>
			</div>',
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/trackme-howto.png' ),
			esc_attr__( 'TrackMe settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/osmand-howto.png' ),
			esc_attr__( 'OsmAnd settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/oruxmaps-mapmytracks.png' ),
			esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/ulogger-howto.png' ),
			esc_attr__( '&micro;logger settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/gpslogger-howto.png' ),
			esc_attr__( 'GPSLogger settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/owntracks-howto.png' ),
			esc_attr__( 'OwnTracks settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/phonetrack-howto.png' ),
			esc_attr__( 'PhoneTrack settings', 'trackserver' ),
			esc_url( TRACKSERVER_PLUGIN_URL . 'img/traccar-howto.png' ),
			esc_attr__( 'Traccar Client settings', 'trackserver' ),
		);
	}

	private function trackserver_url_html() {

		esc_html_e(
			'As of version 5.0, Trackserver uses a single URL slug for all the protocols it supports.
			The old, seperate slugs for TrackMe, MapMyTracks, OsmAnd, SendLocation and OwnTracks are now deprecated.
			With this single slug, two different URLs can be made for Trackserver: one with credentials in it, and
			one without. Some apps need credentials in the URL, because they do not support other mechanisms for
			authentication.',
			'trackserver'
		);

		printf(
			'<br><br>
			<strong>%1$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url1">%6$s</div>
			<input id="trackserver-copy-url-button1" type="button" class="button trackserver-copy-url" value="%3$s" style="margin-top: 5px">
			<br><br>
			<strong>%2$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url2">%7$s</div>
			<input id="trackserver-copy-url-button2" type="button" class="button trackserver-copy-url" value="%4$s" style="margin-top: 5px">
			<br><br>
			%5$s
			<br><br>',
			esc_html__( 'Full URL without credentials', 'trackserver' ),
			esc_html__( 'Full URL with credentials', 'trackserver' ),
			esc_html__( 'Copy', 'trackserver' ),
			esc_html__( 'Copy with password', 'trackserver' ),
			esc_html__( 'See below for app-specific URLs with URL parameters.', 'trackserver' ),
			esc_html( $this->url ),
			esc_html( $this->url2 ),
		);
	}

	private function app_passwords_html() {

		esc_html_e(
			'As of Trackserver v5.0, app-specific access keys have been replaced with app passwords.
			An app password is usable in all of the supported apps, including the ones that previously only worked with
			your WordPress password, like OruxMaps.',
			'trackserver'
		);
		echo '<br><br>';
		esc_html_e(
			'App passwords have configurable permissions. "Write" permission means that the password can
			be used for creating tracks. "Read" means that tracks and metadata can be queried and downloaded. "Delete"
			means the password can be used to delete tracks. For most apps, only write permission is needed, but for
			example TrackMe has functionality that requires read and/or delete permissions.',
			'trackserver'
		);
		echo '<br><br>';

		$passwords = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );

		printf(
			'<input type="hidden" name="apppass_action">
			<input type="hidden" name="apppass_id">
			<table class="form-table fixed">
			  <tr>
				   <th>%1$s</th>
				   <th style="width: 90px">&nbsp;</th>
				   <th style="width: 40px">%2$s</th>
				   <th style="width: 40px">%3$s</th>
				   <th style="width: 40px">%4$s</th>
				   <th>%5$s</th>
				   <th>%6$s</th>
			  </tr>',
			esc_html__( 'Password', 'trackserver' ),
			esc_html__( 'Read', 'trackserver' ),
			esc_html__( 'Write', 'trackserver' ),
			esc_html__( 'Delete', 'trackserver' ),
			esc_html__( 'Created', 'trackserver' ),
			esc_html__( 'Operations', 'trackserver' ),
		);

		$num_passwords = count( $passwords );
		for ( $i = 0; $i < $num_passwords; $i++ ) {
			$pass = $passwords[ $i ]['password'];
			if ( is_array( $passwords[ $i ]['permissions'] ) ) {
				$perm = $passwords[ $i ]['permissions'];
			} else {
				$perm = array();
			}
			$created    = ( array_key_exists( 'created', $passwords[ $i ] ) ? htmlspecialchars( $passwords[ $i ]['created'] ) : '&lt;' . esc_html__( 'unknown', 'trackserver' ) . '&gt;' );
			$readperm   = ( in_array( 'read', $perm, true ) ? __( 'Yes', 'trackserver' ) : '-' );
			$writeperm  = ( in_array( 'write', $perm, true ) ? __( 'Yes', 'trackserver' ) : '-' );
			$deleteperm = ( in_array( 'delete', $perm, true ) ? __( 'Yes', 'trackserver' ) : '-' );

			printf(
				'<tr data-id="%1$s" class="trackserver-apppass">
				  <td id="pass%1$s" data-id="%1$s" data-password="%2$s">
				    <tt id="passtext%1$s">**********</tt>
				  </td><td>
				    <input type="button" class="button ts-view-pass" data-action="view" id="viewbutton%1$s" data-id="%1$s" value="%7$s">
				  </td>
				  <td>%3$s</td>
				  <td>%4$s</td>
				  <td>%5$s</td>
				  <td>%6$s</td>
				  <td>
				    <input type="submit" class="button ts-delete-pass" data-action="delete" id="ts-deletepass-button%1$s" data-id="%1$s" value="%8$s">
				  </td>
				</tr>',
				esc_attr( $i ),
				esc_attr( $this->htmlspecialchars( $pass ) ),
				esc_html( $readperm ),
				esc_html( $writeperm ),
				esc_html( $deleteperm ),
				esc_html( $created ),
				esc_attr__( 'View', 'trackserver' ),
				esc_attr__( 'Delete', 'trackserver' ),
			);
		}

		printf(
			'</table>' .
			'<a href="#TB_inline?width=&inlineId=trackserver-addpass-modal&height=200" title="%1$s" ' .
			'	class="button thickbox" data-id="0" data-action="addpass">%2$s</a>',
			esc_attr__( 'Add app password', 'trackserver' ),
			esc_html__( 'Add app password', 'trackserver' ),
		);
	}

	private function profile_html( $description, $with_creds, $suffix = null ) {
		$url  = ( $with_creds ? $this->url2 : $this->url );
		$copy = ( $with_creds ? __( 'Copy with password', 'trackserver' ) : __( 'Copy', 'trackserver' ) );

		if ( ! empty( $suffix ) ) {
				$url .= $suffix;
		}

		printf(
			'<strong>%1$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url%2$s">%3$s</div>
			<input id="trackserver-copy-url-button%2$s" type="button" class="button trackserver-copy-url" value="%4$s" style="margin-top: 5px">
			<br><br>',
			esc_html__( $description, 'trackserver' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			esc_attr( $this->p_index ),
			esc_html( $url ),
			esc_attr( $copy ),
		);

		$this->p_index += 1;

		if ( ! $with_creds ) {
			printf(
				'<strong>%1$s:</strong> %2$s<br>
				<strong>%3$s:</strong> %4$s<br><br>',
				esc_html__( 'Username', 'trackserver' ),
				esc_html( $this->username ),
				esc_html__( 'Password', 'trackserver' ),
				esc_html__( 'an app password', 'trackserver' ),
			);
		}
	}

	private function trackme_profile_html() {
		$this->profile_html( 'URL header', true );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-trackmehowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=LEM.TrackMe" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'TrackMe settings', 'trackserver' ),
			esc_html__( 'How to use TrackMe', 'trackserver' ),
			esc_html__( 'Download TrackMe', 'trackserver' ),
		);
	}

	private function mapmytracks_profile_html() {
		$this->profile_html( 'Full custom URL', false );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-oruxmapshowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://www.oruxmaps.com/cs/en/" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' ),
			esc_html__( 'How to use OruxMaps MapMyTracks', 'trackserver' ),
			esc_html__( 'Download OruxMaps', 'trackserver' ),
		);
	}

	private function osmand_profile_html() {
		$suffix = '/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}';
		$this->profile_html( 'Online tracking web address', true, $suffix );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-osmandhowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=net.osmand" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'OsmAnd settings', 'trackserver' ),
			esc_html__( 'How to use OsmAnd', 'trackserver' ),
			esc_html__( 'Download OsmAnd', 'trackserver' ),
		);
	}

	private function ulogger_profile_html() {
		$this->profile_html( 'Server URL', false );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-uloggerhowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://f-droid.org/en/packages/net.fabiszewski.ulogger/" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( '&micro;logger settings', 'trackserver' ),
			esc_html__( 'How to use &micro;logger', 'trackserver' ),
			esc_html__( 'Download &micro;logger', 'trackserver' )
		);
	}

	private function gpslogger_profile_html() {
		$suffix = '/?lat=%LAT&lon=%LON&timestamp=%TIMESTAMP&altitude=%ALT&speed=%SPD&bearing=%DIR';
		$this->profile_html( 'Custom URL', false, $suffix );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-gpsloggerhowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://gpslogger.app/" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'GPSLogger settings', 'trackserver' ),
			esc_html__( 'How to use GPSLogger', 'trackserver' ),
			esc_html__( 'Download GPSLogger', 'trackserver' )
		);
	}

	private function sendlocation_profile_html() {
		$this->profile_html( 'Your personal server and script', true );
	}

	private function owntracks_profile_html() {
		$this->profile_html( 'Connection Host', false );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-owntrackshowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=org.owntracks.android" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'OwnTracks settings', 'trackserver' ),
			esc_html__( 'How to use OwnTracks', 'trackserver' ),
			esc_html__( 'Download OwnTracks', 'trackserver' )
		);
	}

	private function phonetrack_profile_html() {
		$suffix = '/?lat=%LAT&lon=%LON&timestamp=%TIMESTAMP&altitude=%ALT&speed=%SPD&bearing=%DIR';
		$this->profile_html( 'Target address', false, $suffix );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-phonetrackhowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://f-droid.org/en/packages/net.eneiluj.nextcloud.phonetrack/" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'PhoneTrack settings', 'trackserver' ),
			esc_html__( 'How to use PhoneTrack', 'trackserver' ),
			esc_html__( 'Download PhoneTrack', 'trackserver' )
		);
	}

	private function traccar_profile_html() {
		$this->profile_html( 'Server URL', true );

		printf(
			'<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-traccarhowto-modal"
				data-action="howto" title="%1$s">%2$s</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=org.traccar.client" target="tsexternal">%3$s</a>
			<br />',
			esc_attr__( 'Traccar Client settings', 'trackserver' ),
			esc_html__( 'How to use Traccar Client', 'trackserver' ),
			esc_html__( 'Download Traccar Client', 'trackserver' )
		);
	}

	private function share_friends_html() {
		$value     = $this->htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_owntracks_share', true ) );
		$link_url  = 'http://owntracks.org/booklet/features/friends/';
		$link_desc = __( 'the description of the Friends feature in the OwnTracks booklet', 'trackserver' );

		esc_html_e(
			'A comma-separated list of WordPress usernames, whom you want to share your location with.
			Users who use OwnTracks or TrackMe\'s "Show Cloud People" feature will see your latest location on the map,
			if they follow you. This setting is only about sharing your latest (live) location with TrackMe and
			OwnTracks users. It does not grant access to your track data in any other way.',
			'trackserver'
		);
		echo '<br><br>';

		printf(
			// translators: placeholder is for a http link URL and description
			esc_html__( 'See %1$s for more information.', 'trackserver' ),
			'<a href="' . esc_url( $link_url ) . '" target="_blank">' . esc_html( $link_desc ) . '</a>',
		);

		printf(
			'<br><br><input type="text" size="40" name="ts_user_meta[ts_owntracks_share]" value="%1$s" autocomplete="off" /><br><br>',
			esc_attr( $value )
		);
	}

	private function follow_friends_html() {
		$value = $this->htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_owntracks_follow', true ) );

		esc_html_e(
			'A comma-separated list of WordPress usernames, whom you want to follow with TrackMe\'s
			"Show Cloud People" feature or with OwnTracks. These users must share their location with you, by listing
			your username in the "Share via ..." setting above and publishing their location to Trackserver with one
			of the supported apps. Leave this setting empty to follow all users that share their location with you.
			You can exclude users by prefixing their username with a "!" (exclamation mark).',
			'trackserver'
		);

		printf( '<br><br><input type="text" size="40" name="ts_user_meta[ts_owntracks_follow]" value="%1$s" autocomplete="off" /><br><br>', esc_attr( $value ) );
	}

	private function infobar_template_html() {
		$template = $this->htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_infobar_template', true ) );

		printf(
			'%1s<br><br>
			<input type="text" size="40" name="ts_user_meta[ts_infobar_template]" id="trackserver_infobar_template" value="%2$s" autocomplete="off" /><br><br>',
			esc_html__(
				'With live tracking, an information bar can be shown on the map, displaying some data from the track and the latest trackpoint.
				Here you can format the content of the infobar.',
				'trackserver'
			),
			esc_attr( $template ),
		);

		printf(
			'%16$s:<br><br>
			 <table class="tsadmin-table">
			   <tr><td>{lat}, {lon}</td><td>%1$s</td></tr>
			   <tr><td>{timestamp}</td><td>%2$s</td></tr>
			   <tr><td>{userid}</td><td>%3$s</td></tr>
			   <tr><td>{userlogin}</td><td>%4$s</td></tr>
			   <tr><td>{displayname}</td><td>%5$s</td></tr>
			   <tr><td>{trackname}</td><td>%6$s</td></tr>
			   <tr><td>{altitudem}</td><td>%7$s</td></tr>
			   <tr><td>{altitudeft}</td><td>%8$s</td></tr>
			   <tr><td>{speedms}, {speedms1}, {speedms2}</td><td>%9$s</td></tr>
			   <tr><td>{speedkmh}, {speedkmh1}, {speedkmh2}</td><td>%10$s</td></tr>
			   <tr><td>{speedmph}, {speedmph1}, {speedmph2}</td><td>%11$s</td></tr>
			   <tr><td>{distancem}</td><td>%12$s</td></tr>
			   <tr><td>{distanceyd}</td><td>%13$s</td></tr>
			   <tr><td>{distancekm}, {distancekm1}, {distancekm2}</td><td>%14$s</td></tr>
			   <tr><td>{distancemi}, {distancemi1}, {distancemi2}</td><td>%15$s</td></tr>
			</table>',
			esc_html__( 'the last known coordinates', 'trackserver' ),
			esc_html__( 'the timestamp of the last update', 'trackserver' ),
			esc_html__( 'the numeric user id of the track owner', 'trackserver' ),
			esc_html__( 'the username of the track owner', 'trackserver' ),
			esc_html__( 'the display name of the track owner', 'trackserver' ),
			esc_html__( 'the name of the track', 'trackserver' ),
			esc_html__( 'the altitude in meters', 'trackserver' ),
			esc_html__( 'the altitude in feet', 'trackserver' ),
			esc_html__( 'last known speed in m/s, with 0, 1 or 2 decimals', 'trackserver' ),
			esc_html__( 'last known speed in km/h, with 0, 1 or 2 decimals', 'trackserver' ),
			esc_html__( 'last known speed in mi/h, with 0, 1 or 2 decimals', 'trackserver' ),
			esc_html__( 'track total distance in meters', 'trackserver' ),
			esc_html__( 'track total distance in yards', 'trackserver' ),
			esc_html__( 'track total distance in km, with 0, 1 or 2 decimals', 'trackserver' ),
			esc_html__( 'track total distance in miles, with 0, 1 or 2 decimals', 'trackserver' ),
			esc_html__( 'Possible replacement tags are', 'trackserver' ),
		);
	}

	/**
	 * Output HTML for managing geofences
	 *
	 * This function outputs the HTML for managing geofences in the user
	 * profile. Geofences are stored in the user's metadata as a single
	 * associative array. If the stored array does not contain a geofence with
	 * all '0' values, we add one. This can be used to add new geofences.
	 *
	 * @since 3.1
	 */
	private function geofences_html() {
		$url               = admin_url() . 'admin.php?page=trackserver-yourprofile';
		$geofences         = get_user_meta( $this->current_user->ID, 'ts_geofences', true );
		$default_geofence  = array(
			'lat'    => (float) 0,
			'lon'    => (float) 0,
			'radius' => (int) 0,
			'action' => 'hide',
		);
		$action_select_fmt = '<select name="ts_geofence_action[%1$d]" data-id="%1$d" class="ts-input-geofence">%2$s</select>';
		$actions           = array(
			'hide'    => esc_html__( 'Hide', 'trackserver' ),
			'discard' => esc_html__( 'Discard', 'trackserver' ),
		);

		if ( ! in_array( $default_geofence, $geofences, true ) ) {
			$geofences[] = $default_geofence;
		}

		esc_html_e(
			'Track updates that fall within the specified radius (in meters) from the center point,
			will be marked as hidden or discarded, depending on the specified action. Modify the "0, 0, 0" entry to add a new fence.
			Set the values to "0, 0, 0, Hide" to delete an entry.
			The center point coordinates should be specified in decimal degrees. A radius of 0 will disable the fence.
			Use the link below to view existing geofences. You can also click the map to pick the center coordinates for a new fence.
			Remember to set a radius and update your profile afterwards.',
			'trackserver'
		);

		echo '<br><table class="tsadmin-table">';
		$num_geofences = count( $geofences );
		for ( $i = 0; $i < $num_geofences; $i++ ) {
			$fence                 = $geofences[ $i ];
			$lat                   = $fence['lat'];
			$lon                   = $fence['lon'];
			$radius                = $fence['radius'];
			$action                = $fence['action'];
			$action_select_options = '';

			foreach ( $actions as $k => $v ) {
				$option_selected        = ( $action === $k ? 'selected' : '' );
				$action_select_options .= '<option value="' . $k . '" ' . $option_selected . '>' . $v . '</option>';
			}

			$newentry = '';
			if ( $lat === '0' && $lon === '0' && $radius === '0' && $action === 'hide' ) {
				$newentry = 'data-newentry';
			}

			printf(
				'<tr data-id="%1$s" %2$s>
				   <td>%3$s</td>
				   <td><input type="text" size="10" name="ts_geofence_lat[%1$s]" value="%4$s" class="ts-input-geofence-lat ts-input-geofence" autocomplete="off" data-id="%1$s"></td>
				   <td>%5$s</td>
				   <td><input type="text" size="10" name="ts_geofence_lon[%1$s]" value="%6$s" class="ts-input-geofence-lon ts-input-geofence" autocomplete="off" data-id="%1$s"></td>
				   <td>%7$s</td>
				   <td><input type="text" size="10" name="ts_geofence_radius[%1$s]" value="%8$s" class="ts-input-geofence-lon ts-input-radius" autocomplete="off" data-id="%1$s"></td>
				   <td>%9$s</td>
				   <td><select name="ts_geofence_action[%1$s]" data-id="%1$s" class="ts-input-geofence">%10$s</select></td>
				 </tr>',
				esc_attr( $i ),
				esc_html( $newentry ),
				esc_html__( 'Center latitude', 'trackserver' ),
				esc_attr( $lat ),
				esc_html__( 'Longitude', 'trackserver' ),
				esc_attr( $lon ),
				esc_html__( 'Radius', 'trackserver' ),
				esc_attr( $radius ),
				esc_html__( 'Action', 'trackserver' ),
				wp_kses(
					$action_select_options,
					array(
						'option' => array(
							'value'    => array(),
							'selected' => array(),
						),
					),
				),
			);
		}

		printf(
			'</table><br>
			<a href="#TB_inline?width=&inlineId=trackserver-geofences-modal" title="%1$s"
			  class="button thickbox" data-id="0" data-action="fences">%2$s</a><br><br>
			<div id="ts_geofences_changed" style="color: red; display: none">%3$s</div>',
			esc_attr__( 'Geofences', 'trackserver' ),
			esc_html__( 'View geofences', 'trackserver' ),
			esc_html__( "It seems you have made changes to the geofences. Don't forget to update your profile!", 'trackserver' )
		);

		// Prepare the map data
		wp_localize_script( 'trackserver-admin', 'trackserver_admin_geofences', $geofences );
	}

	/**
	 * A function to escape HTML special characters for printing, needed for form fields.
	 *
	 * @since @6.0
	 */
	private function htmlspecialchars( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
} // class
