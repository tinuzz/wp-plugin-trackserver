<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Settings {

	private $trackserver; // Reference to the main object

	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
	}

	public function register() {
		// All options in one array
		$args = array( 'sanitize_callback' => array( &$this, 'sanitize_option_values' ) );  // WP >= 4.7
		register_setting( 'trackserver-options', 'trackserver_options', $args );

		// Add a custom action to add the settings sections.
		// This was meant to be called from options_page_html(), but that seems too late; the sections aren't rendered.
		add_action( 'trackserver_add_settings_sections', array( &$this, 'add_sections' ) );
		do_action( 'trackserver_add_settings_sections' );
	}

	public function add_sections() {

		// Settings for section 'trackserver-general'
		add_settings_section(
			'trackserver-general',
			esc_html__( 'General settings', 'trackserver' ),
			array( &$this, 'general_settings_html' ),
			'trackserver'
		);
		add_settings_field(
			'trackserver_universal_slug',
			esc_html__( 'Trackserver URL slug', 'trackserver' ),
			array( &$this, 'universal_slug_html' ),
			'trackserver',
			'trackserver-general'
		);

		// Settings for section 'trackserver-trackme'
		add_settings_section(
			'trackserver-trackme',
			esc_html__( 'TrackMe settings', 'trackserver' ),
			array( &$this, 'trackme_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_trackme_slug',
			esc_html__( 'TrackMe URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'trackme_slug_html' ),
			'trackserver',
			'trackserver-trackme'
		);
		add_settings_field(
			'trackserver_trackme_extension',
			esc_html__( 'TrackMe server extension', 'trackserver' ),
			array( &$this, 'trackme_extension_html' ),
			'trackserver',
			'trackserver-trackme'
		);
		add_settings_field(
			'trackserver_trackme_password',
			esc_html__( 'TrackMe password', 'trackserver' ),
			array( &$this, 'trackme_password_html' ),
			'trackserver',
			'trackserver-trackme'
		);

		// Settings for section 'trackserver-mapmytracks'
		add_settings_section(
			'trackserver-mapmytracks',
			esc_html__( 'OruxMaps MapMyTracks settings', 'trackserver' ),
			array( &$this, 'mapmytracks_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_mapmytracks_tag',
			esc_html__( 'MapMyTracks URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'mapmytracks_tag_html' ),
			'trackserver',
			'trackserver-mapmytracks'
		);

		// Settings for section 'trackserver-osmand'
		add_settings_section(
			'trackserver-osmand',
			esc_html__( 'OsmAnd online tracking settings', 'trackserver' ),
			array( &$this, 'osmand_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_osmand_slug',
			esc_html__( 'OsmAnd URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'osmand_slug_html' ),
			'trackserver',
			'trackserver-osmand'
		);
		add_settings_field(
			'trackserver_osmand_key',
			esc_html__( 'OsmAnd access key', 'trackserver' ),
			array( &$this, 'osmand_key_deprecation_html' ),
			'trackserver',
			'trackserver-osmand'
		);
		add_settings_field(
			'trackserver_osmand_trackname_format',
			esc_html__( 'OsmAnd trackname format', 'trackserver' ),
			array( &$this, 'osmand_trackname_format_html' ),
			'trackserver',
			'trackserver-osmand'
		);

		// Settings for section 'trackserver-sendlocation'
		add_settings_section(
			'trackserver-sendlocation',
			esc_html__( 'SendLocation settings', 'trackserver' ),
			array( &$this, 'sendlocation_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_sendlocation_slug',
			esc_html__( 'SendLocation URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'sendlocation_slug_html' ),
			'trackserver',
			'trackserver-sendlocation'
		);
		add_settings_field(
			'trackserver_sendlocation_trackname_format',
			esc_html__( 'SendLocation trackname format', 'trackserver' ),
			array( &$this, 'sendlocation_trackname_format_html' ),
			'trackserver',
			'trackserver-sendlocation'
		);

		// Settings for section 'trackserver-owntracks'
		add_settings_section(
			'trackserver-owntracks',
			esc_html__( 'OwnTracks settings', 'trackserver' ),
			array( &$this, 'owntracks_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_owntracks_slug',
			esc_html__( 'OwnTracks URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'owntracks_slug_html' ),
			'trackserver',
			'trackserver-owntracks'
		);
		add_settings_field(
			'trackserver_owntracks_trackname_format',
			esc_html__( 'OwnTracks trackname format', 'trackserver' ),
			array( &$this, 'owntracks_trackname_format_html' ),
			'trackserver',
			'trackserver-owntracks'
		);

		// Settings for section 'trackserver-httppost'
		add_settings_section(
			'trackserver-httppost',
			esc_html__( 'HTTP upload settings', 'trackserver' ),
			array( &$this, 'httppost_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_upload_tag',
			esc_html__( 'HTTP POST URL slug', 'trackserver' ) . '<br>' . esc_html__( '(deprecated)', 'trackserver' ),
			array( &$this, 'upload_tag_html' ),
			'trackserver',
			'trackserver-httppost'
		);

		// Settings for section 'trackserver-shortcode'
		add_settings_section(
			'trackserver-shortcode',
			esc_html__( 'Shortcode / map settings', 'trackserver' ),
			array( &$this, 'shortcode_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_tile_url',
			esc_html__( 'OSM/Google tile server URL', 'trackserver' ),
			array( &$this, 'tile_url_html' ),
			'trackserver',
			'trackserver-shortcode'
		);
		add_settings_field(
			'trackserver_attribution',
			esc_html__( 'Tile attribution', 'trackserver' ),
			array( &$this, 'attribution_html' ),
			'trackserver',
			'trackserver-shortcode'
		);

		// Settings for section 'trackserver-embedded'
		add_settings_section(
			'trackserver-embedded',
			esc_html__( 'Embedded maps settings', 'trackserver' ),
			array( &$this, 'embedded_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_embedded_slug',
			esc_html__( 'Embedded map URL slug', 'trackserver' ),
			array( &$this, 'embedded_slug_html' ),
			'trackserver',
			'trackserver-embedded'
		);

		// Settings for section 'trackserver-advanced'
		add_settings_section(
			'trackserver-advanced',
			esc_html__( 'Advanced settings', 'trackserver' ),
			array( &$this, 'advanced_settings_html' ),
			'trackserver'
		);

		add_settings_field(
			'trackserver_gettrack_slug',
			esc_html__( 'Gettrack URL slug', 'trackserver' ),
			array( &$this, 'gettrack_slug_html' ),
			'trackserver',
			'trackserver-advanced'
		);
		add_settings_field(
			'trackserver_enable_proxy',
			esc_html__( 'Enable proxy', 'trackserver' ),
			array( &$this, 'enable_proxy_html' ),
			'trackserver',
			'trackserver-advanced'
		);
		add_settings_field(
			'trackserver_enable_fetchmode_all',
			esc_html__( 'Single request mode', 'trackserver' ),
			array( &$this, 'fetchmode_all_html' ),
			'trackserver',
			'trackserver-advanced'
		);

	}

	// Force some options to be booleans
	public function sanitize_option_values( $options ) {
		$options['enable_proxy']  = (bool) $options['enable_proxy'];
		$options['fetchmode_all'] = (bool) $options['fetchmode_all'];
		return $options;
	}

	public function general_settings_html() {
		esc_html_e(
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'As of version 5.0, Trackserver uses a single URL slug for all ' .
			'the protocols it supports. The old, seperate slugs for TrackMe, ' .
			'MapMyTracks, OsmAnd, SendLocation and OwnTracks are now deprecated and ' .
			'will likely be removed in a future version.',
			'trackserver'
		);
		echo '<br><br>';
		esc_html_e(
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'Some protocols that use HTTP GET need to have a username and ' .
			'password in the URL, while protocols that use HTTP POST and/or Basic ' .
			'Authentication send the password via another method. So there are two ' .
			'versions of the URL, that each can be used with specific protocols, as ' .
			'listed below.',
			'trackserver'
		);
	}

	public function universal_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackserver_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[trackserver_slug]" id="trackserver_slug" value="$val" autocomplete="off" /><br /><br />
			<strong>%2\$s:<br></strong> $url/$val<br><br>
			<strong>%3\$s:<br></strong> $url/$val/&lt;<strong>%4\$s</strong>&gt;/&lt;<strong>%5\$s</strong>&gt;<br /><br>
EOF;

		printf(
			$format,
			esc_html__( 'The Trackserver universal URL slug for all protocols', 'trackserver' ),
			esc_html__( 'Full URL for OruxMaps / MapMyTracks, OwnTracks, uLogger', 'trackserver' ),
			esc_html__( 'Full URL for TrackMe, OsmAnd, SendLocation', 'trackserver' ),
			esc_html__( 'username', 'trackserver' ),
			esc_html__( 'password', 'trackserver' )
		);
	}

	/**
	 * Output HTML for the Trackme settings section.
	 *
	 * @since 1.0
	 */
	public function trackme_settings_html() {
		$howto    = esc_html__( 'How to use TrackMe', 'trackserver' );
		$download = esc_html__( 'Download TrackMe', 'trackserver' );
		$settings = esc_attr__( 'TrackMe settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=ts-trackmehowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=LEM.TrackMe" target="tsexternal">$download</a>
			<br />
EOF;
	}

	function trackme_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackme_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[trackme_slug]" id="trackserver_trackme_slug" value="$val" autocomplete="off" /><br /><br />
			<strong>%2\$s:</strong> $url/$val/&lt;<strong>%3\$s</strong>&gt;/&lt;<strong>%4\$s</strong>&gt;<br /><br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for TrackMe, used in 'URL Header' setting in TrackMe", 'trackserver' ),
			esc_html__( 'Full URL header', 'trackserver' ),
			esc_html__( 'username' ),
			esc_html__( 'password' )
		);
	}

	function trackme_extension_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackme_extension'] );

		$format = <<<EOF
			%1\$s<br />
			<input type="text" size="25" name="trackserver_options[trackme_extension]" id="trackserver_trackme_extension" value="$val" autocomplete="off" /><br />
			<br />
			<b>%2\$s</b>: %3\$s<br /><br />
EOF;
		printf(
			$format,
			esc_html__( "The Server extension in TrackMe's settings", 'trackserver' ),
			esc_html__( 'WARNING', 'trackserver' ),
			esc_html__(
				// @codingStandardsIgnoreStart
				"the default value in TrackMe is 'php', but this will most likely NOT work, so better change " .
				"it to something else. Anything will do, as long as the request is handled by WordPress' index.php, " .
				"so it's better to not use any known file type extension, like 'html' or 'jpg'. A single " .
				"character like 'z' (the default) should work just fine. Change the 'Server extension' setting " .
				'in TrackMe to match the value you put here.', 'trackserver'
				// @codingStandardsIgnoreEnd
			)
		);
	}

	function trackme_password_html() {
		$format      = <<<EOF
			%1\$s<br /><br />
			<b>%2\$s</b>: %3\$s
EOF;
		$link        = '<a href="admin.php?page=trackserver-yourprofile">' .
			esc_html__( 'your Trackserver profile', 'trackserver' ) . '</a>';
		$user_id     = get_current_user_id();
		$trackme_key = '<code>' . htmlspecialchars( get_user_meta( $user_id, 'ts_trackme_key', true ) ) . '</code>';

		printf(
			$format,
			sprintf(
				// translators: placeholders are for link to user profile and trackme access key
				esc_html__(
					// @codingStandardsIgnoreStart
					'Since version 1.9, Trackserver needs a separate password for online tracking with TrackMe. We do not use the WordPress ' .
					'password here anymore for security reasons. The access key is unique to your ' .
					'user account and it can be configured in %1$s. Your current TrackMe password is: %2$s. This is what you enter in the Password field ' .
					'in TrackMe\'s settings!!', 'trackserver'
				// @codingStandardsIgnoreEnd
				),
				$link,
				$trackme_key
			),
			esc_html__( 'WARNING', 'trackserver' ),
			esc_html__(
					// @codingStandardsIgnoreStart
				'if you just upgraded to version 1.9 or higher and you have been using Trackserver with TrackMe, ' .
				'you should update the password in TrackMe to match the password in your profile. Trackserver does not check your ' .
				'WordPress password anymore, because the way TrackMe uses your password is not sufficiently secure.', 'trackserver'
				// @codingStandardsIgnoreEnd
			)
		);
	}

	/**
	 * Output HTML for the Mapmytracks settings section.
	 *
	 * @since 1.0
	 */
	function mapmytracks_settings_html() {
		$howto    = esc_html__( 'How to use OruxMaps MapMyTracks', 'trackserver' );
		$download = esc_html__( 'Download OruxMaps', 'trackserver' );
		$settings = esc_attr__( 'OruxMaps MapMyTracks settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=ts-oruxmapshowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://www.oruxmaps.com/cs/en/" target="tsexternal">$download</a>
			<br />
EOF;
	}

	function mapmytracks_tag_html() {
		$val     = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['mapmytracks_tag'] );
		$url     = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );
		$linkurl = esc_attr__( 'http://en.wikipedia.org/wiki/Server_Name_Indication', 'trackserver' );
		$link    = "<a href=\"$linkurl\">SNI</a>";

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[mapmytracks_tag]" id="trackserver_mapmytracks_tag" value="$val" autocomplete="off" /><br /><br />
			<strong>%2\$s:</strong> $url/$val<br /><br />
			%3\$s<br /><br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for MapMyTracks, used in 'Custom Url' setting in OruxMaps", 'trackserver' ),
			esc_html__( 'Full custom URL', 'trackserver' ),
			sprintf(
				// translators: placeholders are for product name and version, and link to SNI Wikipedia page
				esc_html__(
					// @codingStandardsIgnoreStart
					'Note about HTTPS: older versions of %1$s and/or Android may or may not support %3$s for HTTPS connections. ' .
					'As of v%2$s, SNI is verified to work. If your WordPress depends on SNI for HTTPS connections and you cannot ' .
					'use the latest version of %1$s, please use HTTP. This is a ' .
					'problem with %1$s that Trackserver cannot fix.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				'OruxMaps',
				'7.1.2',
				$link
			)
		);
	}

	function osmand_settings_html() {
		$howto    = esc_html__( 'How to use OsmAnd', 'trackserver' );
		$download = esc_html__( 'Download OsmAnd', 'trackserver' );
		$settings = esc_attr__( 'OsmAnd settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=ts-osmandhowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=net.osmand" target="tsexternal">$download</a>
			<br />
EOF;
	}

	function osmand_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['osmand_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/?...) <br />
			<input type="text" size="25" name="trackserver_options[osmand_slug]" id="trackserver_osmand_slug" value="$val" autocomplete="off" /><br /><br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for OsmAnd, used in 'Online tracking' settings in OsmAnd", 'trackserver' )
		);
	}

	function osmand_key_deprecation_html() {
		$user_id    = get_current_user_id();
		$osmand_key = '<code>' . htmlspecialchars( get_user_meta( $user_id, 'ts_osmand_key', true ) ) . '</code>';

		$format = <<<EOF
			%1\$s<br /><br />
			<b>%2\$s</b>: %3\$s
EOF;
		$link   = '<a href="admin.php?page=trackserver-yourprofile">' .
			esc_html__( 'your Trackserver profile', 'trackserver' ) . '</a>';
		printf(
			$format,
			sprintf(
				// translators: placeholder is for link to user profile
				esc_html__(
					// @codingStandardsIgnoreStart
					'Trackserver needs an access key for online tracking with OsmAnd. We do not use WordPress ' .
					'password here for security reasons. Since version 1.9 of Trackserver, the access key is unique to your ' .
					'user account and it can be configured in %1$s.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$link
			),
			esc_html__( 'WARNING', 'trackserver' ),
			sprintf(
				// translators: placeholder is for access key
				esc_html__(
					// @codingStandardsIgnoreStart
					'if you just upgraded to version 1.9 or higher, the OsmAnd access key has been ' .
					'reset to a new random value. Your old key is no longer valid. If you use Trackserver with OsmAnd, please ' .
					'make sure the key matches your settings in OsmAnd. Your current access key is: %1$s. Change it regularly. ' .
					'You can find the full tracking URL in your Trackserver profile.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$osmand_key
			)
		);
	}

	function osmand_trackname_format_html() {
		$val  = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['osmand_trackname_format'] );
		$link = '<a href="' . esc_attr__( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>';

		$format = <<<EOF
			%1\$s<br /><br />
			<input type="text" size="25" name="trackserver_options[osmand_trackname_format]" id="trackserver_osmand_trackname_format" value="$val" autocomplete="off" /><br />
			%%Y = %2\$s, %%m = %3\$s, %%d = %4\$s, %%H = %5\$s, %%F = %%Y-%%m-%%d
			<br />
EOF;

		printf(
			$format,
			sprintf(
				// translators: placeholder is for link to strftime() manual
				esc_html__(
					// @codingStandardsIgnoreStart
					'Generated track name in %1$s format. OsmAnd online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$link
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' )
		);
	}

	function sendlocation_settings_html() {
	}

	function owntracks_settings_html() {
		$download = esc_html__( 'Download OwnTracks', 'trackserver' );

		echo <<<EOF
			<a href="https://play.google.com/store/apps/details?id=org.owntracks.android" target="tsexternal">$download</a>
			<br />
EOF;
	}

	/**
	 * Output HTML for the HTTP POST settings section.
	 *
	 * @since 1.0
	 */
	function httppost_settings_html() {
		$howto    = esc_html__( 'How to use AutoShare', 'trackserver' );
		$download = esc_html__( 'Download AutoShare', 'trackserver' );
		$settings = esc_attr__( 'AutoShare settings', 'trackserver' );

		echo <<<EOF
			<a class="thickbox" href="#TB_inline?width=&inlineId=ts-autosharehowto-modal"
				data-action="howto" title="$settings">$howto</a> &nbsp; &nbsp;
			<a href="https://play.google.com/store/apps/details?id=com.dngames.autoshare" target="tsexternal">$download</a>
			<br />
EOF;
	}

	function shortcode_settings_html() {
	}

	function embedded_settings_html() {
	}

	function advanced_settings_html() {
	}

	function sendlocation_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['sendlocation_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;/&lt;username&gt;/&lt;access key&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[sendlocation_slug]" id="trackserver_sendlocation_slug" value="$val" autocomplete="off" /><br /><br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for SendLocation, used in SendLocation's settings", 'trackserver' )
		);
	}

	function sendlocation_trackname_format_html() {
		$val  = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['sendlocation_trackname_format'] );
		$link = '<a href="' . esc_attr__( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>';

		$format = <<<EOF
			%1\$s<br /><br />
			<input type="text" size="25" name="trackserver_options[sendlocation_trackname_format]" id="trackserver_sendlocation_trackname_format" value="$val" autocomplete="off" /><br />
			%%Y = %2\$s, %%m = %3\$s, %%d = %4\$s, %%H = %5\$s, %%F = %%Y-%%m-%%d
			<br />
EOF;

		printf(
			$format,
			sprintf(
				// translators: placeholder is for link to strftime() manual
				esc_html__(
					// @codingStandardsIgnoreStart
					'Generated track name in %1$s format. SendLocation online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$link
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' )
		);
	}

	function owntracks_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['owntracks_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[owntracks_slug]" id="trackserver_owntracks_slug" value="$val" autocomplete="off" /><br /><br />
			<strong>%2\$s:</strong> $url/$val/<br /><br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for OwnTracks, used in OwnTracks' settings", 'trackserver' ),
			esc_html__( 'Preferences -> Connection -> Host', 'trackserver' )
		);
	}

	function owntracks_trackname_format_html() {
		$val  = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['owntracks_trackname_format'] );
		$link = '<a href="' . esc_attr__( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>';

		$format = <<<EOF
			%1\$s<br /><br />
			<input type="text" size="25" name="trackserver_options[owntracks_trackname_format]" id="trackserver_owntracks_trackname_format" value="$val" autocomplete="off" /><br />
			%%Y = %2\$s, %%m = %3\$s, %%d = %4\$s, %%H = %5\$s, %%F = %%Y-%%m-%%d
			<br />
EOF;

		printf(
			$format,
			sprintf(
				// translators: placeholder is for link to strftime() manual
				esc_html__(
					// @codingStandardsIgnoreStart
					'Generated track name in %1$s format. OwnTracks online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$link
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' )
		);
	}

	function upload_tag_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['upload_tag'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[upload_tag]" id="trackserver_upload_tag" value="$val" autocomplete="off" /><br />
			<br />
			<strong>%2\$s:</strong> $url/$val<br />
EOF;
		printf(
			$format,
			esc_html__( 'The URL slug for upload via HTTP POST', 'trackserver' ),
			esc_html__( 'Full URL', 'trackserver' )
		);
	}

	function tile_url_html() {
		$val = htmlspecialchars( $this->trackserver->options['tile_url'] );
		echo <<<EOF
			<input type="text" size="50" name="trackserver_options[tile_url]" id="trackserver_tile_url" value="$val" autocomplete="off" /><br /><br />
EOF;
	}

	function attribution_html() {
		$val    = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['attribution'] );
		$format = <<<EOF
			<input type="text" size="50" name="trackserver_options[attribution]" id="trackserver_attribution" value="$val" autocomplete="off" /><br />
			%1\$s<br />
EOF;

		printf( $format, esc_html__( 'Please check with your map tile provider what attribution is required.', 'trackserver' ) );
	}

	function embedded_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['embedded_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			<input type="text" size="25" name="trackserver_options[embedded_slug]" id="trackserver_embedded_slug" value="$val" autocomplete="off" /><br />
			<br />
EOF;

		printf(
			$format,
			esc_html__( 'The URL slug for embedded maps', 'trackserver' )
		);

		echo esc_html__( 'Warning: if you change this, links to existing embedded maps will be invalidated.', 'trackserver' );
	}

	function gettrack_slug_html() {
		$val = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['gettrack_slug'] );
		$url = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );

		$format = <<<EOF
			%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
			%2\$s<br />
			<input type="text" size="25" name="trackserver_options[gettrack_slug]" id="trackserver_gettrack_slug" value="$val" autocomplete="off" /><br />
			<br />
EOF;

		printf(
			$format,
			esc_html__( "The URL slug for the 'gettrack' API, used by Trackserver's shortcode [tsmap]", 'trackserver' ),
			esc_html__( 'There is generally no need to change this.', 'trackserver' )
		);
	}

	function enable_proxy_html() {
		$checked  = ( $this->trackserver->options['enable_proxy'] ? 'checked' : '' );
		$linkurl  = esc_attr__( 'https://wordpress.org/plugins/trackserver/faq/', 'trackserver' );
		$linktext = esc_html__( 'FAQ about security', 'trackserver' );
		$link     = "<a href=\"$linkurl\">$linktext</a>";

		$format = <<<EOF
			<input type="checkbox" name="trackserver_options[enable_proxy]" id="trackserver_enable_proxy" $checked> %1\$s<br />
			%2\$s<br />
EOF;

		printf(
			$format,
			esc_html__( "Check this to enable the proxy for external tracks, which can be used by prefixing their URL with 'proxy:'", 'trackserver' ),
			sprintf(
				// translators: placeholder is for link to Trackserver FAQ
				esc_html__(
					// @codingStandardsIgnoreStart
					'This will enable your authors to invoke HTTP requests originating from your server. ' .
					'Only enable this when you need it and if you trust your authors not to use harmful URLs. ' .
					'Please see the %1$s for more information.', 'trackserver'
					// @codingStandardsIgnoreEnd
				),
				$link
			)
		);
	}

	function fetchmode_all_html() {
		$checked = ( $this->trackserver->options['fetchmode_all'] ? 'checked' : '' );
		$format  = '<input type="checkbox" name="trackserver_options[fetchmode_all]" id="trackserver_fetchmode_all" ' . $checked . '> %1$s<br /><br />';
		printf(
			$format,
			esc_html__( 'Check this to enable the mode where Trackserver gets all track data in a single HTTP request when displaying a map.' )
		);
		echo esc_html__(
			// @codingStandardsIgnoreStart
			'Disabling this mode will make Trackserver use a separate HTTP request ' .
			'for each single track. This may have a positive or a negative ' .
			'effect on the loading speed of Trackserver maps and tracks. If ' .
			'some of your maps have multiple tracks with many, many locations, ' .
			'unchecking this and disabling the single request mode may improve ' .
			'the performance. On the other hand, if your users are on slow or ' .
			'high-latency networks, enabling single request mode may give ' .
			'better results. You can safely switch between modes to see what works ' .
			'best for you.'
			// @codingStandardsIgnoreEnd
		);
	}

}
