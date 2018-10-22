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
		add_settings_section( 'trackserver-trackme', esc_html__( 'TrackMe settings', 'trackserver' ), array( &$this, 'trackme_settings_html' ), 'trackserver' );

		// Settings for section 'trackserver-trackme'
		add_settings_field(
			'trackserver_trackme_slug',
			esc_html__( 'TrackMe URL slug', 'trackserver' ),
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
	}

	// Force some options to be booleans
	public function sanitize_option_values( $options ) {
		$options['enable_proxy']	= (bool) $options['enable_proxy'];
		$options['fetchmode_all'] = (bool) $options['fetchmode_all'];
		return $options;
	}

	/**
	 * Output HTML for the Trackme settings section.
	 *
	 * @since 1.0
	 */
	public function trackme_settings_html() {
		$howto		= esc_html__( 'How to use TrackMe', 'trackserver' );
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
			$val     = $this->trackserver->printf_htmlspecialchars( $this->trackserver->options['trackme_slug'] );
			$url     = $this->trackserver->printf_htmlspecialchars( site_url( null ) . $this->trackserver->url_prefix );
			$linkurl = esc_attr__( 'http://en.wikipedia.org/wiki/Server_Name_Indication', 'trackserver' );
			$link    = "<a href=\"$linkurl\">SNI</a>";

			$format = <<<EOF
				%1\$s ($url/<b>&lt;slug&gt;</b>/) <br />
				<input type="text" size="25" name="trackserver_options[trackme_slug]" id="trackserver_trackme_slug" value="$val" autocomplete="off" /><br /><br />
				<strong>%2\$s:</strong> $url/$val<br /><br />
				%3\$s<br /><br />
EOF;

			printf(
				$format,
				esc_html__( "The URL slug for TrackMe, used in 'URL Header' setting in TrackMe", 'trackserver' ),
				esc_html__( 'Full URL header', 'trackserver' ),
				sprintf(
					// translators: placeholders are for product name and version, and link to Wikipedia page
					esc_html__(
						// @codingStandardsIgnoreStart
						'Note about HTTPS: %1$s as of v%2$s does not support %3$s for HTTPS connections. ' .
						'If your WordPress install is hosted on a HTTPS URL that depends on SNI, please use HTTP. This is a ' .
						'problem with %1$s that Trackserver cannot fix.', 'trackserver'
						// @codingStandardsIgnoreEnd
					),
					'TrackMe',
					'2.00.1',
					$link
				)
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


}
