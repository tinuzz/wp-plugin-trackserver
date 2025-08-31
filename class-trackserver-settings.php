<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Settings {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object

	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
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
		$options['enable_proxy']  = ( isset( $options['enable_proxy'] ) ? (bool) $options['enable_proxy'] : false );
		$options['fetchmode_all'] = ( isset( $options['fetchmode_all'] ) ? (bool) $options['fetchmode_all'] : false );
		return $options;
	}

	/**
	 * Callback for sanitizing map profile data.
	 *
	 * @since 6.0
	 */
	public function sanitize_map_profiles( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				if ( empty( $v['tile_url'] ) && empty( $v['attribution'] ) ) {
					unset( $data[ $k ] );
					continue;
				}
				$data[ $k ]['vector']      = ( isset( $v['vector'] ) && $v['vector'] === 'on' ? true : false );
				$data[ $k ]['label']       = ( empty( $v['label'] ) ? 'profile' . $k : $v['label'] );
				$data[ $k ]['min_zoom']    = ( (int) $v['min_zoom'] < 0 ? '0' : (int) $v['min_zoom'] );
				$data[ $k ]['max_zoom']    = ( (int) $v['max_zoom'] <= 0 ? '18' : (int) $v['max_zoom'] );
				$data[ $k ]['default_lat'] = (float) $v['default_lat'];
				$data[ $k ]['default_lon'] = (float) $v['default_lon'];
			}
		} else {
			$data = $this->trackserver->map_profiles;
		}
		return $data;
	}

	public function general_settings_html() {
		esc_html_e(
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			'As of version 5.0, Trackserver uses a single URL slug for all ' .
			'the protocols it supports. The old, seperate slugs for TrackMe, ' .
			'MapMyTracks, OsmAnd, SendLocation and OwnTracks are now deprecated and ' .
			'will be removed in a future version.',
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
		$val = $this->htmlspecialchars( $this->trackserver->options['trackserver_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%6$s/<b>&lt;slug&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[trackserver_slug]" id="trackserver_slug" value="%7$s" autocomplete="off" /><br /><br />' .
			'<strong>%2$s:</strong><br> %6$s/%8$s<br><br>' .
			'<strong>%3$s:</strong><br> %6$s/%8$s/&lt;<strong>%4$s</strong>&gt;/&lt;<strong>%5$s</strong>&gt;<br /><br>',
			esc_html__( 'The Trackserver universal URL slug for all protocols', 'trackserver' ),
			esc_html__( 'Full URL for OruxMaps / MapMyTracks, OwnTracks, uLogger', 'trackserver' ),
			esc_html__( 'Full URL for TrackMe, OsmAnd, SendLocation', 'trackserver' ),
			esc_html__( 'username', 'trackserver' ),
			esc_html__( 'password', 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
			esc_html( $val ),
		);
	}

	/**
	 * Output HTML for the Trackme settings section.
	 *
	 * @since 1.0
	 */
	public function trackme_settings_html() {
	}

	public function trackme_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['trackme_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[trackme_slug]" id="trackserver_trackme_slug" value="%3$s" autocomplete="off" /><br /><br />',
			esc_html__( "The URL slug for TrackMe, used in 'URL Header' setting in TrackMe", 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
		);
	}

	public function trackme_extension_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['trackme_extension'] );

		printf(
			'%1$s<br />' .
			'<input type="text" size="25" name="trackserver_options[trackme_extension]" id="trackserver_trackme_extension" value="%4$s" autocomplete="off" /><br />' .
			'<br />' .
			'<b>%2$s</b>: %3$s<br /><br />',
			esc_html__( "The Server extension in TrackMe's settings", 'trackserver' ),
			esc_html__( 'WARNING', 'trackserver' ),
			esc_html__(
				// phpcs:disable
				"the default value in TrackMe is 'php', but this will most likely NOT work, so better change " .
				"it to something else. Anything will do, as long as the request is handled by WordPress' index.php, " .
				"so it's better to not use any known file type extension, like 'html' or 'jpg'. A single " .
				"character like 'z' (the default) should work just fine. Change the 'Server extension' setting " .
				'in TrackMe to match the value you put here.', 'trackserver'
				// phpcs:enable
			),
			esc_attr( $val ),
		);
	}

	/**
	 * Output HTML for the Mapmytracks settings section.
	 *
	 * @since 1.0
	 */
	public function mapmytracks_settings_html() {
	}

	public function mapmytracks_tag_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['mapmytracks_tag'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[mapmytracks_tag]" id="trackserver_mapmytracks_tag" value="%3$s" autocomplete="off" /><br /><br />',
			esc_html__( "The URL slug for MapMyTracks, used in 'Custom Url' setting in OruxMaps", 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
		);
	}

	public function osmand_settings_html() {
	}

	public function osmand_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['osmand_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;</b>/?...) <br />' .
			'<input type="text" size="25" name="trackserver_options[osmand_slug]" id="trackserver_osmand_slug" value="%3$s" autocomplete="off" /><br /><br />',
			esc_html__( "The URL slug for OsmAnd, used in 'Online tracking' settings in OsmAnd", 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
		);
	}

	public function osmand_trackname_format_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['osmand_trackname_format'] );

		printf(
			'%1$s<br /><br />' .
			'<input type="text" size="25" name="trackserver_options[osmand_trackname_format]" id="trackserver_osmand_trackname_format" value="%6$s" autocomplete="off" /><br />' .
			'%%Y = %2$s, %%m = %3$s, %%d = %4$s, %%H = %5$s, %%F = %%Y-%%m-%%d' .
			'<br />',
			wp_kses_post(
				sprintf(
					// translators: placeholder is for link to strftime() manual
					// phpcs:disable
					__('Generated track name in %1$s format. OsmAnd online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver' ),
					// phpcs:enable
					'<a href="' . __( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>'
				)
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' ),
			esc_attr( $val ),
		);
	}

	public function sendlocation_settings_html() {
	}

	public function owntracks_settings_html() {
	}

	/**
	 * Output HTML for the HTTP POST settings section.
	 *
	 * @since 1.0
	 */
	public function httppost_settings_html() {
	}

	public function embedded_settings_html() {
	}

	public function advanced_settings_html() {
	}

	public function sendlocation_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['sendlocation_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;/&lt;username&gt;/&lt;access key&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[sendlocation_slug]" id="trackserver_sendlocation_slug" value="%3$s" autocomplete="off" /><br /><br />',
			esc_html__( "The URL slug for SendLocation, used in SendLocation's settings", 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
		);
	}

	public function sendlocation_trackname_format_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['sendlocation_trackname_format'] );

		printf(
			'%1$s<br /><br />' .
			'<input type="text" size="25" name="trackserver_options[sendlocation_trackname_format]" id="trackserver_sendlocation_trackname_format" value="%6$s" autocomplete="off" /><br />' .
			'%%Y = %2$s, %%m = %3$s, %%d = %4$s, %%H = %5$s, %%F = %%Y-%%m-%%d' .
			'<br />',
			wp_kses_post(
				sprintf(
					// translators: placeholder is for link to strftime() manual
					// phpcs:disable
					__('Generated track name in %1$s format. Sendlocation online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver' ),
					// phpcs:enable
					'<a href="' . __( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>'
				)
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' ),
			esc_attr( $val ),
		);
	}

	public function owntracks_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['owntracks_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%3$s/<b>&lt;slug&gt;/&lt;username&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[owntracks_slug]" id="trackserver_owntracks_slug" value="%4$s" autocomplete="off" /><br /><br />' .
			'<strong>%2$s:</strong> %s$s/%5$s/<br /><br />',
			esc_html__( "The URL slug for OwnTracks, used in OwnTracks' settings", 'trackserver' ),
			esc_html__( 'Preferences -> Connection -> Host', 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
			esc_html( $val ),
		);
	}

	public function owntracks_trackname_format_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['owntracks_trackname_format'] );

		printf(
			'%1$s<br /><br />' .
			'<input type="text" size="25" name="trackserver_options[owntracks_trackname_format]" id="trackserver_owntracks_trackname_format" value="%6$s" autocomplete="off" /><br />' .
			'%%Y = %2$s, %%m = %3$s, %%d = %4$s, %%H = %5$s, %%F = %%Y-%%m-%%d' .
			'<br />',
			wp_kses_post(
				sprintf(
					// translators: placeholder is for link to strftime() manual
					// phpcs:disable
					__('Generated track name in %1$s format. OwnTracks online tracking does not support the concept of ' .
					"'tracks', there are only locations. Trackserver needs to group these in tracks and automatically generates " .
					"new tracks based on the location's timestamp. The format to use (and thus, how often to start a new track) " .
					'can be specified here. If you specify a constant string, without any strftime() format placeholders, one ' .
					'and the same track will be used forever and all locations.', 'trackserver' ),
					// phpcs:enable
					'<a href="' . __( 'http://php.net/manual/en/function.strftime.php', 'trackserver' ) . '" target="_blank">strftime()</a>'
				)
			),
			esc_html__( 'year', 'trackserver' ),
			esc_html__( 'month', 'trackserver' ),
			esc_html__( 'day', 'trackserver' ),
			esc_html__( 'hour', 'trackserver' ),
			esc_attr( $val ),
		);
	}

	public function upload_tag_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['upload_tag'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%3$s/<b>&lt;slug&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[upload_tag]" id="trackserver_upload_tag" value="%4$s" autocomplete="off" /><br />' .
			'<br />' .
			'<strong>%2$s:</strong> %3$s/%5$s<br />',
			esc_html__( 'The URL slug for upload via HTTP POST', 'trackserver' ),
			esc_html__( 'Full URL', 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val ),
			esc_html( $val ),
		);
	}

	public function embedded_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['embedded_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;</b>/) <br />' .
			'<input type="text" size="25" name="trackserver_options[embedded_slug]" id="trackserver_embedded_slug" value="%3$s" autocomplete="off" /><br />' .
			'<br />',
			esc_html__( 'The URL slug for embedded maps', 'trackserver' ),
			esc_url( $url ),
			esc_attr( $val )
		);

		echo esc_html__( 'Warning: if you change this, links to existing embedded maps will be invalidated.', 'trackserver' );
	}

	public function gettrack_slug_html() {
		$val = $this->htmlspecialchars( $this->trackserver->options['gettrack_slug'] );
		$url = site_url( null ) . $this->trackserver->url_prefix;

		printf(
			'%1$s (%2$s/<b>&lt;slug&gt;</b>/) <br />' .
			'%3$s<br />' .
			'<input type="text" size="25" name="trackserver_options[gettrack_slug]" id="trackserver_gettrack_slug" value="%4$s" autocomplete="off" /><br />' .
			'<br />',
			esc_html__( "The URL slug for the 'gettrack' API, used by Trackserver's shortcode [tsmap]", 'trackserver' ),
			esc_url( $url ),
			esc_html__( 'There is generally no need to change this.', 'trackserver' ),
			esc_attr( $val )
		);
	}

	public function enable_proxy_html() {
		$checked  = ( $this->trackserver->options['enable_proxy'] ? 'checked' : '' );
		$linkurl  = 'https://wordpress.org/plugins/trackserver/faq/';
		$linktext = esc_html__( 'FAQ about security', 'trackserver' );
		$link     = '<a href="' . esc_url( $linkurl ) . '">' . $linktext . '</a>';

		printf(
			'<input type="checkbox" name="trackserver_options[enable_proxy]" id="trackserver_enable_proxy" %1$s> %2$s<br /><br />' .
			'%3$s<br />',
			esc_attr( $checked ),   // %1
			esc_html__( "Check this to enable the proxy for external tracks, which can be used by prefixing their URL with 'proxy:'", 'trackserver' ), // %2
			sprintf(
				// translators: placeholder is for link to Trackserver FAQ
				esc_html__(
					// phpcs:disable
					'This will enable your authors to invoke HTTP requests originating from your server. ' .
					'Only enable this when you need it and if you trust your authors not to use harmful URLs. ' .
					'Please see the %1$s for more information.', 'trackserver'
					// phpcs:enable
				),
				wp_kses_post( $link )
			)
		);
	}

	public function fetchmode_all_html() {
		$checked = ( $this->trackserver->options['fetchmode_all'] ? 'checked' : '' );

		printf(
			'<input type="checkbox" name="trackserver_options[fetchmode_all]" id="trackserver_fetchmode_all" %1$s> %2$s<br /><br />',
			esc_attr( $checked ),
			esc_html__( 'Check this to enable the mode where Trackserver gets all track data in a single HTTP request when displaying a map.', 'trackserver' )
		);
		echo esc_html__(
			// phpcs:disable
			'Disabling this mode will make Trackserver use a separate HTTP request ' .
			'for each single track. This may have a positive or a negative ' .
			'effect on the loading speed of Trackserver maps and tracks. If ' .
			'some of your maps have multiple tracks with many, many locations, ' .
			'unchecking this and disabling the single request mode may improve ' .
			'the performance. On the other hand, if your users are on slow or ' .
			'high-latency networks, enabling single request mode may give ' .
			'better results. You can safely switch between modes to see what works ' .
			'best for you.', 'trackserver'
			// phpcs:enable
		);
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
