<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Profile {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	private $p_index = 3; // A counter used for numbering HTML elements

	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
		$this->current_user  = wp_get_current_user();
		$this->app_passwords = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );
		$this->username      = $this->trackserver->printf_htmlspecialchars( $this->current_user->user_login );
		$base_url            = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );
		$slug                = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackserver_slug'] );
		$this->url           = $base_url . '/' . $slug;
		$this->url2          = $this->url . '/' . $this->username . '/' . '**********';

		if ( empty( $this->app_passwords ) ) {
			$this->password = esc_html__( '<password>', 'trackserver' );
		} else {
			$this->password = $this->trackserver->printf_htmlspecialchars( $this->app_passwords[0]['password'] );
		}
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/ulogger-logo.png'; ?>" alt="&micro;logger" width="150">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/oruxmaps-logo.png'; ?>" alt="OruxMaps" width="150">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/trackme-logo.png'; ?>" alt="TrackMe" width="150">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/gpslogger-logo.png'; ?>" alt="GPSLogger" width="130">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/owntracks-logo.png'; ?>" alt="OwnTracks" width="150">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/phonetrack-logo.png'; ?>" alt="PhoneTrack" width="130">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/osmand-logo.png'; ?>" alt="OsmAnd" width="150">
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
										<img src="<?php echo TRACKSERVER_PLUGIN_URL . 'img/traccar-logo.png'; ?>" alt="Traccar Client" width="130">
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
					<form id="trackserver-add-pass" method="post" action="<?php echo $url; ?>">
						<table style="width: 100%">
							<?php wp_nonce_field( 'your-profile' ); ?>
							<input type="hidden" name="apppass_action" value="add">
							<tr>
								<th style="width: 150px;"><?php esc_html_e( 'Password', 'trackserver' ); ?></th>
								<td>
									<input id="ts-apppass-input" name="password" type="password" size="30" />
									<input type="button" class="button" id="ts-gen-pass-button" value="<?php esc_html_e( 'Generate', 'trackserver' ); ?>">
									<input type="button" class="button" id="ts-view-pass-button" value="<?php esc_html_e( 'View', 'trackserver' ); ?>">
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
		$trackme_settings_img     = TRACKSERVER_PLUGIN_URL . 'img/trackme-howto.png';
		$trackme_settings         = esc_attr__( 'TrackMe settings', 'trackserver' );
		$mapmytracks_settings_img = TRACKSERVER_PLUGIN_URL . 'img/oruxmaps-mapmytracks.png';
		$mapmytracks_settings     = esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' );
		$osmand_settings_img      = TRACKSERVER_PLUGIN_URL . 'img/osmand-howto.png';
		$osmand_settings          = esc_attr__( 'OsmAnd settings', 'trackserver' );
		$autoshare_settings_img   = TRACKSERVER_PLUGIN_URL . 'img/autoshare-settings.png';
		$autoshare_settings       = esc_attr__( 'AutoShare settings', 'trackserver' );
		$ulogger_settings_img     = TRACKSERVER_PLUGIN_URL . 'img/ulogger-howto.png';
		$ulogger_settings         = esc_attr__( '&micro;logger settings', 'trackserver' );
		$gpslogger_settings_img   = TRACKSERVER_PLUGIN_URL . 'img/gpslogger-howto.png';
		$gpslogger_settings       = esc_attr__( 'GPSLogger settings', 'trackserver' );
		$owntracks_settings_img   = TRACKSERVER_PLUGIN_URL . 'img/owntracks-howto.png';
		$owntracks_settings       = esc_attr__( 'OwnTracks settings', 'trackserver' );
		$phonetrack_settings_img  = TRACKSERVER_PLUGIN_URL . 'img/phonetrack-howto.png';
		$phonetrack_settings      = esc_attr__( 'PhoneTrack settings', 'trackserver' );
		$traccar_settings_img     = TRACKSERVER_PLUGIN_URL . 'img/traccar-howto.png';
		$traccar_settings         = esc_attr__( 'Traccar Client settings', 'trackserver' );

		echo <<<EOF
			<div id="trackserver-trackmehowto-modal" style="display:none;">
				<p>
						<img src="$trackme_settings_img" alt="$trackme_settings" />
				</p>
			</div>
			<div id="trackserver-osmandhowto-modal" style="display:none;">
				<p>
						<img src="$osmand_settings_img" alt="$osmand_settings" />
				</p>
			</div>
			<div id="trackserver-oruxmapshowto-modal" style="display:none;">
				<p>
						<img src="$mapmytracks_settings_img" alt="$mapmytracks_settings" />
				</p>
			</div>
			<div id="trackserver-uloggerhowto-modal" style="display:none;">
				<p>
						<img src="$ulogger_settings_img" alt="$ulogger_settings" />
				</p>
			</div>
			<div id="trackserver-gpsloggerhowto-modal" style="display:none;">
				<p>
						<img src="$gpslogger_settings_img" alt="$gpslogger_settings" />
				</p>
			</div>
			<div id="trackserver-owntrackshowto-modal" style="display:none;">
				<p>
						<img src="$owntracks_settings_img" alt="$owntracks_settings" />
				</p>
			</div>
			<div id="trackserver-phonetrackhowto-modal" style="display:none;">
				<p>
						<img src="$phonetrack_settings_img" alt="$phonetrack_settings" />
				</p>
			</div>
			<div id="trackserver-traccarhowto-modal" style="display:none;">
				<p>
						<img src="$traccar_settings_img" alt="$traccar_settings" />
				</p>
			</div>
EOF;
	}

	private function trackserver_url_html() {

		// @codingStandardsIgnoreStart
		echo esc_html__( 'As of version 5.0, Trackserver uses a single URL slug for all the protocols it supports. ' .
				'The old, seperate slugs for TrackMe, MapMyTracks, OsmAnd, SendLocation and OwnTracks are now deprecated. ' .
				'With this single slug, two different URLs can be made for Trackserver: one with credentials in it, and ' .
				'one without. Some apps need credentials in the URL, because they do not support other mechanisms for ' .
				'authentication.', 'trackserver' ) . '<br><br>';
		// @codingStandardsIgnoreEnd

		$suffix = $this->trackserver->printf_htmlspecialchars( '/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}' );
		$format = <<<EOF
			<strong>%1\$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url1">{$this->url}</div>
			<input id="trackserver-copy-url-button1" type="button" class="button trackserver-copy-url" value="%3\$s" style="margin-top: 5px">
			<br><br>
			<strong>%2\$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url2">{$this->url2}</div>
			<input id="trackserver-copy-url-button2" type="button" class="button trackserver-copy-url" value="%4\$s" style="margin-top: 5px">
			<br><br>
			%5\$s
			<br><br>
EOF;

		printf(
			$format,
			esc_html__( 'Full URL without credentials', 'trackserver' ),
			esc_html__( 'Full URL with credentials', 'trackserver' ),
			esc_html__( 'Copy', 'trackserver' ),
			esc_html__( 'Copy with password', 'trackserver' ),
			esc_html__( 'See below for app-specific URLs with URL parameters.', 'trackserver' ),
		);
	}

	private function app_passwords_html() {

		// @codingStandardsIgnoreStart
		echo esc_html__( 'As of Trackserver v5.0, app-specific access keys have been replaced with app passwords. ' .
			'An app password is usable in all of the supported apps, including the ones that previously only worked with ' .
			'your WordPress password, like OruxMaps.', 'trackserver' ) . '<br><br>';
		echo esc_html__( 'App passwords have configurable permissions. "Write" permission means that the password can ' .
			'be used for creating tracks. "Read" means that tracks and metadata can be queried and downloaded. "Delete" ' .
			'means the password can be used to delete tracks. For most apps, only write permission is needed, but for ' .
			'example TrackMe has functionality that requires read and/or delete permissions.', 'trackserver' ) . '<br><br>';
		// @codingStandardsIgnoreEnd

		$passwords = get_user_meta( $this->current_user->ID, 'ts_app_passwords', true );
		$viewstr   = esc_html__( 'View', 'trackserver' );
		$deletestr = esc_html__( 'Delete', 'trackserver' );
		$strings   = array(
			'password'   => esc_html__( 'Password', 'trackserver' ),
			'read'       => esc_html__( 'Read', 'trackserver' ),
			'write'      => esc_html__( 'Write', 'trackserver' ),
			'delete'     => esc_html__( 'Delete', 'trackserver' ),
			'created'    => esc_html__( 'Created', 'trackserver' ),
			'operations' => esc_html__( 'Operations', 'trackserver' ),
			'view'       => esc_html__( 'View', 'trackserver' ),
			'addapppass' => esc_html__( 'Add app password', 'trackserver' ),
		);

		echo <<<EOF
			<input type="hidden" name="apppass_action">
			<input type="hidden" name="apppass_id">
			<table class="form-table fixed">
			<tr>
				<th>{$strings['password']}</th>
				<th style="width: 90px">&nbsp;</th>
				<th style="width: 40px">{$strings['read']}</th>
				<th style="width: 40px">{$strings['write']}</th>
				<th style="width: 40px">{$strings['delete']}</th>
				<th>{$strings['created']}</th>
				<th>{$strings['operations']}</th>
			</tr>
EOF;

		for ( $i = 0; $i < count( $passwords ); $i++ ) {
			$pass = $passwords[ $i ]['password'];
			if ( is_array( $passwords[ $i ]['permissions'] ) ) {
				$perm = $passwords[ $i ]['permissions'];
			} else {
				$perm = array();
			}
			$created    = ( array_key_exists( 'created', $passwords[ $i ] ) ? htmlspecialchars( $passwords[ $i ]['created'] ) : '&lt;' . esc_html__( 'unknown', 'trackserver' ) . '&gt;' );
			$itemdata   = 'data-id="' . $i . '"';
			$passdata   = 'data-password="' . htmlspecialchars( $pass ) . '"';
			$readperm   = ( in_array( 'read', $perm, true ) ? esc_html__( 'Yes', 'trackserver' ) : '-' );
			$writeperm  = ( in_array( 'write', $perm, true ) ? esc_html__( 'Yes', 'trackserver' ) : '-' );
			$deleteperm = ( in_array( 'delete', $perm, true ) ? esc_html__( 'Yes', 'trackserver' ) : '-' );

			echo <<<EOF
				<tr $itemdata class="trackserver-apppass">
					<td id="pass$i" $itemdata $passdata><tt id="passtext$i">**********</tt></td>
					<td>
						<input type="button" class="button ts-view-pass" data-action="view" id="viewbutton$i" $itemdata value="{$strings['view']}">
					</td>
					<td>$readperm</td>
					<td>$writeperm</td>
					<td>$deleteperm</td>
					<td>$created</td>
					<td>
						<input type="submit" class="button ts-delete-pass" data-action="delete" id="ts-deletepass-button$i" $itemdata value="{$strings['delete']}">
					</td>
				</tr>
EOF;

		}

		echo <<<EOF
			</table>
			<a href="#TB_inline?width=&inlineId=trackserver-addpass-modal&height=200" title="{$strings['addapppass']}"
				class="button thickbox" data-id="0" data-action="addpass">{$strings['addapppass']}</a>
EOF;

	}

	private function profile_html( $description, $with_creds, $suffix = null ) {
		$url  = ( $with_creds ? $this->url2 : $this->url );
		$copy = ( $with_creds ? esc_html__( 'Copy with password', 'trackserver' ) : esc_html__( 'Copy', 'trackserver' ) );
		if ( ! empty( $suffix ) ) {
				$url .= $this->trackserver->printf_htmlspecialchars( $suffix );
		}
		$format = <<<EOF
			<strong>%1\$s:</strong><br>
			<div class="trackserver-info" id="trackserver-url{$this->p_index}">${url}</div>
			<input id="trackserver-copy-url-button{$this->p_index}" type="button" class="button trackserver-copy-url" value="%2\$s" style="margin-top: 5px">
			<br><br>
EOF;

		$args = array(
			esc_html__( $description, 'trackserver' ),  // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			$copy,
		);

		$this->p_index += 1;

		if ( ! $with_creds ) {
			$format .= <<<EOF
				<strong>%3\$s:</strong> {$this->username}<br>
				<strong>%4\$s:</strong> %5\$s<br><br>
EOF;

			$args[] = esc_html__( 'Username', 'trackserver' );
			$args[] = esc_html__( 'Password', 'trackserver' );
			$args[] = esc_html__( 'an app password', 'trackserver' );
		}
		// @codingStandardsIgnoreStart
		printf(
			$format,
			...$args
		);
		// @codingStandardsIgnoreEnd

	}

	private function trackme_profile_html() {
		$this->profile_html( 'URL header', true );

		$howto    = esc_html__( 'How to use TrackMe', 'trackserver' );
		$download = esc_html__( 'Download TrackMe', 'trackserver' );
		$settings = esc_attr__( 'TrackMe settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-trackmehowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=LEM.TrackMe" target="tsexternal">$download</a>
			<br />
EOF;

	}

	private function mapmytracks_profile_html() {
		$this->profile_html( 'Full custom URL', false );

		$howto    = esc_html__( 'How to use OruxMaps MapMyTracks', 'trackserver' );
		$download = esc_html__( 'Download OruxMaps', 'trackserver' );
		$settings = esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-oruxmapshowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://www.oruxmaps.com/cs/en/" target="tsexternal">$download</a>
			<br />
EOF;

	}

	private function osmand_profile_html() {
		$suffix = '/?lat={0}&lon={1}&timestamp={2}&altitude={4}&speed={5}&bearing={6}';
		$this->profile_html( 'Online tracking web address', true, $suffix );

		$howto    = esc_html__( 'How to use OsmAnd', 'trackserver' );
		$download = esc_html__( 'Download OsmAnd', 'trackserver' );
		$settings = esc_attr__( 'OsmAnd settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-osmandhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=net.osmand" target="tsexternal">$download</a>
			<br />
EOF;

	}

	private function ulogger_profile_html() {
		$this->profile_html( 'Server URL', false );

		$howto    = esc_html__( 'How to use &micro;logger', 'trackserver' );
		$download = esc_html__( 'Download &micro;logger', 'trackserver' );
		$settings = esc_attr__( '&micro;logger settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-uloggerhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://f-droid.org/en/packages/net.fabiszewski.ulogger/" target="tsexternal">$download</a>
			<br />
EOF;

	}

	private function gpslogger_profile_html() {
		$suffix = '/?lat=%LAT&lon=%LON&timestamp=%TIMESTAMP&altitude=%ALT&speed=%SPD&bearing=%DIR';
		$this->profile_html( 'Custom URL', false, $suffix );

		$howto    = esc_html__( 'How to use GPSLogger', 'trackserver' );
		$download = esc_html__( 'Download GPSLogger', 'trackserver' );
		$settings = esc_attr__( 'GPSLogger settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-gpsloggerhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://gpslogger.app/" target="tsexternal">$download</a>
			<br />
EOF;
	}

	private function sendlocation_profile_html() {
		$this->profile_html( 'Your personal server and script', true );
	}

	private function owntracks_profile_html() {
		$this->profile_html( 'Connection Host', false );
		$download = esc_html__( 'Download OwnTracks', 'trackserver' );
		$howto    = esc_html__( 'How to use OwnTracks', 'trackserver' );
		$settings = esc_attr__( 'OwnTracks settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-owntrackshowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=org.owntracks.android" target="tsexternal">$download</a>
			<br />
EOF;
	}

	private function phonetrack_profile_html() {
		$suffix = '/?lat=%LAT&lon=%LON&timestamp=%TIMESTAMP&altitude=%ALT&speed=%SPD&bearing=%DIR';
		$this->profile_html( 'Target address', false, $suffix );

		$download = esc_html__( 'Download PhoneTrack', 'trackserver' );
		$howto    = esc_html__( 'How to use PhoneTrack', 'trackserver' );
		$settings = esc_attr__( 'PhoneTrack settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-phonetrackhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://f-droid.org/en/packages/net.eneiluj.nextcloud.phonetrack/" target="tsexternal">$download</a>
			<br />
EOF;
	}

	private function traccar_profile_html() {
		$this->profile_html( 'Server URL', true );
		$download = esc_html__( 'Download Traccar Client', 'trackserver' );
		$howto    = esc_html__( 'How to use Traccar Client', 'trackserver' );
		$settings = esc_attr__( 'Traccar Client settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=trackserver-traccarhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=org.traccar.client" target="tsexternal">$download</a>
			<br />
EOF;
	}

	private function share_friends_html() {
		$value    = htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_owntracks_share', true ) );
		$link_url = 'http://owntracks.org/booklet/features/friends/';

		// @codingStandardsIgnoreStart
		echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to share your location with. ' .
			'Users who use OwnTracks or TrackMe\'s "Show Cloud People" feature will see your latest location on the map, ' .
			'if they follow you. This setting is only about sharing your latest (live) location with TrackMe and ' .
			'OwnTracks users. It does not grant access to your track data in any other way.', 'trackserver'
		) . '<br><br>';
		// translators: placeholder is for a http link URL
		echo sprintf(
			__( 'See <a href="%1$s" target="_blank">the description of the Friends feature in the OwnTracks booklet</a> for more information.', 'trackserver' ), $link_url
		) . '<br><br>';
		// @codingStandardsIgnoreEnd
		echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_share]" value="' . $value . '" autocomplete="off" /><br><br>';
	}

	private function follow_friends_html() {
		$value = htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_owntracks_follow', true ) );
		// @codingStandardsIgnoreStart
		echo esc_html__( 'A comma-separated list of WordPress usernames, whom you want to follow with TrackMe\'s ' .
			'"Show Cloud People" feature or with OwnTracks. These users must share their location with you, by listing ' .
			'your username in the "Share via ..." setting above and publishing their location to Trackserver with one ' .
			'of the supported apps. Leave this setting empty to follow all users that share their location with you. ' .
			'You can exclude users by prefixing their username with a "!" (exclamation mark).', 'trackserver'
		) . '<br>';
		// @codingStandardsIgnoreEnd
		echo '<input type="text" size="40" name="ts_user_meta[ts_owntracks_follow]" value="' . $value . '" autocomplete="off" /><br><br>';
	}

	private function infobar_template_html() {
		$template = $this->trackserver->printf_htmlspecialchars( get_user_meta( $this->current_user->ID, 'ts_infobar_template', true ) );
		$format   = <<<EOF
			%1\$s<br>
			<input type="text" size="40" name="ts_user_meta[ts_infobar_template]" id="trackserver_infobar_template" value="$template" autocomplete="off" /><br><br>
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
			'<a href="#TB_inline?width=&inlineId=trackserver-geofences-modal" title="' . esc_attr__( 'Geofences', 'trackserver' ) .
			'" class="thickbox" data-id="0" data-action="fences">' . esc_html__( 'View geofences', 'trackserver' ) . '</a>' .
			'</td></tr>';
		echo '</table>';
		echo '<div id="ts_geofences_changed" style="color: red; display: none">' .
			esc_html__( "It seems you have made changes to the geofences. Don't forget to update your profile!", 'trackserver' );
			'</div>';

		// Prepare the map data
		wp_localize_script( 'trackserver-admin', 'trackserver_admin_geofences', $geofences );
	}

} // class
