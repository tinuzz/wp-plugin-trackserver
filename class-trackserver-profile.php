<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Profile {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object

	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
		$this->current_user  = wp_get_current_user();
		$this->app_passwords = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );
		$this->username      = $this->trackserver->printf_htmlspecialchars( $this->current_user->user_login );
		$base_url            = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );
		$slug                = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackserver_slug'] );
		$this->url           = $base_url . "/" . $slug;

		if ( empty( $this->app_passwords ) ) {
			$this->password = esc_html__( '<password>' );
		}
		else {
			$this->password = $this->trackserver->printf_htmlspecialchars( $this->app_passwords[0]['password'] );
		}
		$this->first_app_password = $this->password;
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
			'view'     => __( 'View', 'trackserver' ),
			'hide'     => __( 'Hide', 'trackserver' )
		);
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
									<?php esc_html_e( 'TrackMe profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->trackme_passwd_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="mapmytracks_profile">
									<?php esc_html_e( 'MapMyTracks profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->mapmytracks_profile_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'OsmAnd profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->osmand_key_html(); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label>
									<?php esc_html_e( 'SendLocation profile', 'trackserver' ); ?>
								</label>
							</th>
							<td>
								<?php $this->sendlocation_key_html(); ?>
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
			<div id="ts-view-modal" style="display:none;">
					<div id="tsadminmapcontainer">
						<div id="tsadminmap" style="width: 100%; height: 100%; margin: 10px 0;"></div>
					</div>
			</div>
		</div>
		<?php
		$this->trackserver->howto_modals_html();
	}


	private function trackserver_url_html() {
		$format = <<<EOF
			<strong>%1\$s:<br></strong> {$this->url}<br><br>
			<strong>%2\$s:<br></strong> {$this->url}/&lt;<strong>%3\$s</strong>&gt;/&lt;<strong>%4\$s</strong>&gt;<br /><br>
EOF;

		printf(
			$format,
			esc_html__( 'Full URL for OruxMaps / MapMyTracks, OwnTracks, uLogger', 'trackserver' ),
			esc_html__( 'Full URL for TrackMe, OsmAnd, SendLocation', 'trackserver' ),
			esc_html__( 'username', 'trackserver' ),
			esc_html__( 'password', 'trackserver' )
		);
	}

	private function app_passwords_html() {

		// @codingStandardsIgnoreStart
		echo esc_html__( 'As of Trackserver v5.0, app-specific access keys have been replaced with app passwords. ' .
			'An app password is usable in all of the supported apps, including the ones that previously only worked with ' .
			'your WordPress password, like OruxMaps / MapMyTracks.', 'trackserver' ) . '<br /><br />';
		echo esc_html__( 'App passwords have configurable permissions. "Write" permission means that the password can ' .
			'be used for creating tracks. "Read" means that tracks and metadata can be queried and downloaded. "Delete" ' .
			'means the password can be used to delete tracks. For most apps, only write permission is needed, but for ' .
			'example TrackMe has functionality that requires read and/or delete permissions.', 'trackserver' ) . '<br /><br />';
		// @codingStandardsIgnoreEnd

		$passwords    = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );

		//echo "<pre>"; var_dump( $passwords ); echo "</pre>";

		echo '<input type="hidden" name="apppass_action">';
		echo '<input type="hidden" name="apppass_id">';
		echo '<table>';
		echo '<tr><th>Password</th><th style="width: 90px">&nbsp;</th><th style="width: 40px">Read</th><th style="width: 40px">Write</th><th style="width: 40px">Delete</th><th>Created</th><th>Operations</th><tr>';
		for ( $i = 0; $i < count( $passwords ); $i++ ) {
			$pass = $passwords[ $i ]['password'];
			$perm = $passwords[ $i ]['permissions'];
			$created = ( array_key_exists( 'created', $passwords[ $i ] ) ? htmlspecialchars( $passwords[ $i ]['created'] ) : '&lt;' . esc_html__( 'unknown' ) . '&gt;' );
			$action_select_options = '';

			// Mark all rows for easier finding in JavaScript
			$itemdata = 'data-id="' . $i . '"';
			$passdata = 'data-password="' . htmlspecialchars( $pass ) . '"';

			echo '<tr ' . $itemdata . ' class="trackserver_apppass">' .
				//'<td ' . $itemdata . ' ' . $passdata . '><tt>' . htmlspecialchars( $pass ) . '</tt></td>' .
				'<td id="pass' . $i . '" ' . $itemdata . ' ' . $passdata . '><tt id="passtext' . $i . '">**********</tt></td>' .
				'<td><button class="ts-view-pass" data-action="view" id="viewbutton' . $i . '" ' . $itemdata . '>' .
				esc_html__( 'View' ) . '</button></td> ' .
				'<td>' . ( in_array( 'read', $perm ) ? 'Yes' : '-' ) . '</td>' .
				'<td>' . ( in_array( 'write', $perm ) ? 'Yes' : '-' ) . '</td>' .
				'<td>' . ( in_array( 'delete', $perm ) ? 'Yes' : '-' ) . '</td>' .
				'<td>' . $created . '</td>' .
				'<td>' .
				'<button class="ts-delete-pass" id="deletebutton' . $i . '" ' . $itemdata . '>Delete</button>' .
				'</td>' .
				'</tr>' ;
		}
		echo '</table>';
	}

	private function trackme_passwd_html() {
		$extn   = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackme_extension'] );
		$format = <<<EOF
			<strong>%1\$s:</strong> {$this->url}/{$this->username}/{$this->password}<br />
			<strong>%2\$s:</strong> $extn<br /><br />
EOF;

		// @codingStandardsIgnoreStart
		printf(
			$format,
			esc_html__( 'URL header', 'trackserver' ),
			esc_html__( 'Server extension', 'trackserver' )
		);
		// @codingStandardsIgnoreEnd

		Trackserver_Settings::get_instance( $this->trackserver )->trackme_settings_html();
	}

	private function mapmytracks_profile_html() {
		$format   = <<<EOF
			<strong>%1\$s:</strong> {$this->url}<br />
			<strong>%2\$s:</strong> {$this->username}<br />
			<strong>%3\$s:</strong> %4\$s<br /><br>
EOF;

		printf(
			$format,
			esc_html__( 'Full custom URL', 'trackserver' ),
			esc_html__( 'Username', 'trackserver' ),
			esc_html__( 'Password', 'trackserver' ),
			esc_html__( 'an app password or your WordPress password', 'trackserver' )
		);

		Trackserver_Settings::get_instance( $this->trackserver )->mapmytracks_settings_html();
	}

	private function osmand_key_html() {
		$suffix = $this->trackserver->printf_htmlspecialchars( '/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}&username=' ) . $this->username . '&amp;key=' . $this->password;

		$format = <<<EOF
			<strong>%1\$s:</strong> {$this->url}$suffix<br /><br />
EOF;

		// @codingStandardsIgnoreStart
		printf(
			$format,
			esc_html__( 'Online tracking web address', 'trackserver' )
		);
		// @codingStandardsIgnoreEnd

		Trackserver_Settings::get_instance( $this->trackserver )->osmand_settings_html();
	}

	private function sendlocation_key_html() {
		$format = <<<EOF
			<strong>%1\$s:</strong> {$this->url}/{$this->username}/{$this->password}/<br />
EOF;

		// @codingStandardsIgnoreStart
		printf(
			$format,
			esc_html__( 'Your personal server and script', 'trackserver' )
		);
		// @codingStandardsIgnoreEnd
	}

	private function share_friends_html() {
		$current_user = wp_get_current_user();
		$value        = htmlspecialchars( get_user_meta( $current_user->ID, 'ts_owntracks_share', true ) );
		$link_url     = 'http://owntracks.org/booklet/features/friends/';

		// @codingStandardsIgnoreStart
		echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to share your location with. ' .
			'Users who use OwnTracks or TrackMe\'s "Show Cloud People" feature will see your latest location on the map, ' .
			'if they follow you. This setting is only about sharing your latest (live) location with TrackMe and ' .
			'OwnTracks users. It does not grant access to your track data in any other way.', 'trackserver'
		) . '<br /><br />';
		// translators: placeholder is for a http link URL
		echo sprintf(
			__( 'See <a href="%1$s" target="_blank">the description of the Friends feature in the OwnTracks booklet</a> for more information.', 'trackserver' ), $link_url
		) . '<br /><br />';
		// @codingStandardsIgnoreEnd
		echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_share]" value="' . $value . '" autocomplete="off" /><br /><br />';
	}

	private function follow_friends_html() {
		$current_user = wp_get_current_user();
		$value        = htmlspecialchars( get_user_meta( $current_user->ID, 'ts_owntracks_follow', true ) );
		// @codingStandardsIgnoreStart
		echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to follow with TrackMe\'s ' .
			'"Show Cloud People" feature or with OwnTracks. These users must share their location with you, by listing ' .
			'your username in the "Share via ..." setting above and publishing their location to Trackserver with one ' .
			'of the supported apps. Leave this setting empty to follow all users that share their location with you. ' .
			'You can exclude users by prefixing their username with a "!" (exclamation mark).', 'trackserver'
		) . '<br />';
		// @codingStandardsIgnoreEnd
		echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_follow]" value="' . $value . '" autocomplete="off" /><br /><br />';
	}

	private function infobar_template_html() {
		$current_user = wp_get_current_user();
		$template     = $this->trackserver->printf_htmlspecialchars( get_user_meta( $current_user->ID, 'ts_infobar_template', true ) );
		$format       = <<<EOF
			%1\$s<br />
			<input type="text" size="40" name="ts_user_meta[ts_infobar_template]" id="trackserver_infobar_template" value="$template" autocomplete="off" /><br /><br />
EOF;
		// @codingStandardsIgnoreStart
		printf(
			$format,
			esc_html__(
				'With live tracking, an information bar can be shown on the map, displaying some data from the track and the latest trackpoint. ' .
				'Here you can format the content of the infobar.', 'trackserver'
			)
		);
		// @codingStandardsIgnoreEnd
		echo esc_html__( 'Possible replacement tags are:', 'trackserver' ) . '<br>';
		echo '{lat}, {lon} - ' . esc_html__( 'the last known coordinates', 'trackserver' ) . '<br>';
		echo '{timestamp} - ' . esc_html__( 'the timestamp of the last update', 'trackserver' ) . '<br>';
		echo '{userid} - ' . esc_html__( 'the numeric user id of the track owner', 'trackserver' ) . '<br>';
		echo '{userlogin} - ' . esc_html__( 'the username of the track owner', 'trackserver' ) . '<br>';
		echo '{displayname} - ' . esc_html__( 'the display name of the track owner', 'trackserver' ) . '<br>';
		echo '{trackname} - ' . esc_html__( 'the name of the track', 'trackserver' ) . '<br>';
		echo '{altitudem} - ' . esc_html__( 'the altitude in meters', 'trackserver' ) . '<br>';
		echo '{altitudeft} - ' . esc_html__( 'the altitude in feet', 'trackserver' ) . '<br>';
		echo '{speedms}, {speedms1}, {speedms2} - ' . esc_html__( 'last known speed in m/s, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
		echo '{speedkmh}, {speedkmh1}, {speedkmh2} - ' . esc_html__( 'last known speed in km/h, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
		echo '{speedmph}, {speedmph1}, {speedmph2} - ' . esc_html__( 'last known speed in mi/h, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
		echo '{distancem} - ' . esc_html__( 'track total distance in meters', 'trackserver' ) . '<br>';
		echo '{distanceyd} - ' . esc_html__( 'track total distance in yards', 'trackserver' ) . '<br>';
		echo '{distancekm}, {distancekm1}, {distancekm2} - ' . esc_html__( 'track total distance in km, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
		echo '{distancemi}, {distancemi1}, {distancemi2} - ' . esc_html__( 'track total distance in miles, with 0, 1 or 2 decimals', 'trackserver' ) . '<br>';
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
		$current_user      = wp_get_current_user();
		$url               = admin_url() . 'admin.php?page=trackserver-yourprofile';
		$geofences         = get_user_meta( $current_user->ID, 'ts_geofences', true );
		$default_geofence  = array(
			'lat'    => 0,
			'lon'    => 0,
			'radius' => 0,
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

		// @codingStandardsIgnoreStart
		echo esc_html__( 'Track updates that fall within the specified radius (in meters) from the center point, ' .
			'will be marked as hidden or discarded, depending on the specified action. Modify the "0, 0, 0" entry to add a new fence. ' .
			'Set the values to "0, 0, 0, Hide" to delete an entry. ' .
			'The center point coordinates should be specified in decimal degrees. A radius of 0 will disable the fence. ' .
			'Use the link below to view existing geofences. You can also click the map to pick the center coordinates for a new fence. ' .
			'Remember to set a radius and update your profile afterwards.', 'trackserver' ) . '<br>';
		// @codingStandardsIgnoreEnd

		echo '<table>';
		for ( $i = 0; $i < count( $geofences ); $i++ ) {
			$fence                 = $geofences[ $i ];
			$lat                   = htmlspecialchars( $fence['lat'] );
			$lon                   = htmlspecialchars( $fence['lon'] );
			$radius                = htmlspecialchars( $fence['radius'] );
			$action                = $fence['action'];
			$action_select_options = '';
			foreach ( $actions as $k => $v ) {
				$option_selected        = ( $action === $k ? 'selected' : '' );
				$action_select_options .= '<option value="' . $k . '" ' . $option_selected . '>' . $v . '</option>';
			}

			// Mark all rows (and especially the '0,0,0' row) for easier finding in JavaScript
			$itemdata = 'data-id="' . $i . '"';
			if ( $lat === '0' && $lon === '0' && $radius === '0' && $action === 'hide' ) {
				$itemdata .= ' data-newentry';
			}

			echo '<tr ' . $itemdata . ' class="trackserver_geofence">' .
				'<td>' . esc_html__( 'Center latitude', 'trackserver' ) .
				' <input type="text" size="10" name="ts_geofence_lat[' . $i . ']" value="' . $lat . '" class="ts-input-geofence-lat ts-input-geofence" autocomplete="off" ' .
				$itemdata . ' /></td>' .
				'<td>' . esc_html__( 'Longitude', 'trackserver' ) .
				' <input type="text" size="10" name="ts_geofence_lon[' . $i . ']" value="' . $lon . '" class="ts-input-geofence-lon ts-input-geofence" autocomplete="off" ' .
				$itemdata . ' /></td>' .
				'<td>' . esc_html__( 'Radius', 'trackserver' ) .
				' <input type="text" size="10" name="ts_geofence_radius[' . $i . ']" value="' . $radius . '" class="ts-input-geofence-radius ts-input-geofence" autocomplete="off" ' .
				$itemdata . ' /></td>' .
				'<td>' . esc_html__( 'Action', 'trackserver' ) . ' ';

			printf( $action_select_fmt, $i, $action_select_options );

			echo '</td></tr>';
		}
		echo '<tr><td colspan="4" style="text-align: right">' .
			'<a href="#TB_inline?width=&inlineId=ts-view-modal" title="' . esc_attr__( 'Geofences', 'trackserver' ) .
			'" class="thickbox" data-id="0" data-action="fences">' . esc_html__( 'View geofences', 'trackserver' ) . '</a>';
			'</td></tr>';
		echo '</table>';
		echo '<div id="ts_geofences_changed" style="color: red; display: none">' .
			esc_html__( "It seems you have made changes to the geofences. Don't forget to update your profile!", 'trackserver' );
			'</div>';

		// Prepare the map data
		wp_localize_script( 'trackserver-admin', 'trackserver_admin_geofences', $geofences );
	}

} // class
