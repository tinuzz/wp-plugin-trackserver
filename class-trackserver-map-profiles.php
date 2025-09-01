<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}

class Trackserver_Map_Profiles {

	// Singleton
	protected static $instance;

	private $trackserver; // Reference to the main object
	private $page_name = 'trackserver-map-profiles';
	private $page;

	public function __construct( $trackserver ) {
		$this->trackserver   = $trackserver;
	}

	/**
	 * Create a singleton if it doesn't exist and return it.
	 *
	 * @since 6.0
	 */
	public static function get_instance( $trackserver ) {
		if ( ! self::$instance ) {
			self::$instance = new self( $trackserver );
		}
		return self::$instance;
	}

	/**
	 * Register the settings of this class, with their sanitize function.  Also
	 * add settings sections for this page. This is called from the main
	 * 'admin_init' handler in the Trackserver_Admin class.
	 *
	 * @since 6.0
	 */
	public function register() {
		$args = array( 'sanitize_callback' => array( &$this, 'sanitize_map_profiles' ) );
		register_setting( 'trackserver-map-profiles', 'trackserver_map_profiles', $args );

		add_settings_section(
			'trackserver-map-profiles',           // ID
			'',                                   // empty title
			array( &$this, 'map_profiles_html' ), // callback
			$this->page_name                      // page
		);
	}

	/**
	 * Add submenu page to the admin menu. Called from the 'admin_menu' handler
	 * in the Trackserver_Admin class.
	 *
	 * @since 6.0
	 */
	public function add_submenu_page() {

		$this->page = add_submenu_page(
			'trackserver-tracks',                        // parent slug
			esc_html__( 'Map profiles', 'trackserver' ), // page title
			esc_html__( 'Map profiles', 'trackserver' ), // menu title
			'manage_options',                            // capability
			$this->page_name          ,                  // menu slug
			array( &$this, 'map_profiles_page_html' ),   // callback
		);
	}

	/**
	 * Render function for the page HTML.
	 *
	 * @since 6.0
	 */
	public function map_profiles_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'trackserver' ) );
		}

		printf( '<div class="wrap"><h2>%s</h2>', esc_html__( 'Map profiles', 'trackserver' ) );

		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			printf( '<div class="updated"><p>%s</p></div>', esc_html__( 'Map profiles updated', 'trackserver' ) );
		}

		?>
			<form name="trackserver-map-profiles" action="options.php" method="post">
		<?php

		settings_fields( 'trackserver-map-profiles' );
		do_settings_sections( 'trackserver-map-profiles' );
		submit_button( esc_attr__( 'Update map profiles', 'trackserver' ), 'primary', 'submit' );

		?>
			</form>
		<?php

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
			$data = array_values( $data ); // reindex the array
		} else {
			$data = $this->trackserver->map_profiles;
		}
		return $data;
	}


	public function map_profiles_html() {

		echo wp_kses_post(
			__(
				'A map profile is a set of values that define what a map looks like and how it behaves. It consists of a tile server or style URL,
				an attribution string (often mandatory from the tile provider), a minimum and maximum zoom level for the map, and the default
				coordinates to display when for some reason nothing else is shown on the map. Map profiles have a label by which you can reference them
				in a shortcode (<tt>profile=&lt;label&gt;</tt>). If no profile is specified in the shortcode, the profile with the label
				"<em>default</em>" is used. If that label does not exist, the first profile is used.',
				'trackserver'
			),
		);

		echo '<br><br>';

		echo wp_kses_post(
			sprintf(
				// translators: placeholders are for documentation links
				__(
					'%1$s are supported. To use them, specify the URL to the <tt>%2$s</tt> document
					and check the "Vector tiles" checkbox. This will cause the necessary scripts for vector tile support to be loaded.',
					'trackserver'
				),
				'<a href="' . __( 'https://en.wikipedia.org/wiki/Vector_tiles', 'trackserver' ) . '" target="_blank">' . __( 'Vector tiles', 'trackserver' ) . '</a>',
				'<a href="https://maplibre.org/maplibre-style-spec/" target="_blank">style.json</a>',
			)
		);

		echo '<br><br>';

		$strings = array(
			'label'       => __( 'Label', 'trackserver' ),
			'url'         => __( 'Tile / style URL', 'trackserver' ),
			'vector'      => __( 'Vector tiles', 'trackserver' ),
			'attribution' => __( 'Tile attribution', 'trackserver' ),
			'maxzoom'     => __( 'Max. zoom', 'trackserver' ),
			'minzoom'     => __( 'Min. zoom', 'trackserver' ),
			'latitude'    => __( 'Default latitude', 'trackserver' ),
			'longitude'   => __( 'Default longitude', 'trackserver' ),
			'delete'      => __( 'Delete', 'trackserver' ),
			'addprofile'  => __( 'Add map profile', 'trackserver' ),
			'save'        => __( 'Save', 'trackserver' ),
			'cancel'      => __( 'Cancel', 'trackserver' ),
		);

		printf(
			'<table class="map-profiles striped" border="1" id="map-profile-table">
				<thead>
					<tr>
						<th style="width: 80px; padding-left: 10px">%1$s</th>
						<th style="padding-left: 10px">%2$s</th>
						<th style="width: 10px; padding-left: 10px">%3$s</th>
						<th style="padding-left: 10px">%4$s</th>
						<th style="width: 40px; padding-left: 10px">%5$s</th>
						<th style="width: 40px; padding-left: 10px">%6$s</th>
						<th style="width: 80px; padding-left: 10px">%7$s</th>
						<th style="width: 80px; padding-left: 10px">%8$s</th>
						<th style="width: 70px">&nbsp;</th>
					</tr></thead><tbody>',
			esc_html( $strings['label'] ),
			esc_html( $strings['url'] ),
			esc_html( $strings['vector'] ),
			esc_html( $strings['attribution'] ),
			esc_html( $strings['minzoom'] ),
			esc_html( $strings['maxzoom'] ),
			esc_html( $strings['latitude'] ),
			esc_html( $strings['longitude'] ),
		);

		$num_profiles = count( $this->trackserver->map_profiles );

		for ( $i = 0; $i < $num_profiles; $i++ ) {

			$label     = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['label'] );
			$url       = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['tile_url'] );
			$attrib    = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['attribution'] );
			$minzoom   = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['min_zoom'] );
			$maxzoom   = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['max_zoom'] );
			$latitude  = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['default_lat'] );
			$longitude = $this->htmlspecialchars( $this->trackserver->map_profiles[ $i ]['default_lon'] );
			$vector    = ( $this->trackserver->map_profiles[ $i ]['vector'] === true ? 'checked' : '' );

			$d = '&nbsp;';
			if ( $i > 0 ) {
				$d = '<a id="delete-profile-button' . $i . '" title="' . $strings['delete'] . '" class="button ts-delete-profile-button" ' .
					'data-id="' . $i . '" data-action="deleteprofile">' . $strings['delete'] . '</a>';
			}

			printf(
				'<tr data-id="%1$s" id="profile-row%1$s">
					<td id="label%1$s" data-id="%1$s"><input type="text" style="width: 100%%" name="trackserver_map_profiles[%1$s][label]" value="%2$s"></td>
					<td><textarea id="tile_url%1$s" name="trackserver_map_profiles[%1$s][tile_url]">%3$s</textarea></td>
					<td style="text-align: center;"><input type="checkbox" name="trackserver_map_profiles[%1$s][vector]" %4$s></td>
					<td><textarea id="attribution%1$s" name="trackserver_map_profiles[%1$s][attribution]">%5$s</textarea></td>
					<td><input type="text" style="width: 100%%" id="minzoom%1$s" name="trackserver_map_profiles[%1$s][min_zoom]" value="%6$s"></td>
					<td><input type="text" style="width: 100%%" id="maxzoom%1$s" name="trackserver_map_profiles[%1$s][max_zoom]" value="%7$s"></td>
					<td><input type="text" style="width: 100%%" id="latitude%1$s" name="trackserver_map_profiles[%1$s][default_lat]" value="%8$s"></td>
					<td><input type="text" style="width: 100%%" id="longitude%1$s" name="trackserver_map_profiles[%1$s][default_lon]" value="%9$s"></td>
					<td>%10$s</td>
				</tr>',
				esc_attr( $i ),         // %1
				esc_attr( $label ),     // %2
				esc_html( $url ),       // %3
				esc_html( $vector ),    // %4
				esc_html( $attrib ),    // %5
				esc_attr( $minzoom ),   // %6
				esc_attr( $maxzoom ),   // %7
				esc_attr( $latitude ),  // %8
				esc_attr( $longitude ), // %9
				wp_kses_post( $d ),     // %10
			);
		}

		printf(
			'	</tbody>
			</table>
			<br>
			<a id="add-map-profile-button" title="%1$s" class="button" data-id="0" data-action="addprofile">%2$s</a><br><br>
			<div id="ts_map_profiles_changed" style="color: red; display: none">%3$s</div>',
			esc_attr( $strings['addprofile'] ),
			esc_html( $strings['addprofile'] ),
			esc_html__( "Don't forget to update the map profiles!", 'trackserver' ),
		);
	}

	/**
	 * Get the default map profile
	 *
	 * @since 6.0
	 */
	public function get_default_profile() {
		foreach ( $this->trackserver->map_profiles as $i => $profile ) {
			if ( $i === 0 || $profile['label'] === 'default' ) {
				$map_profile = $profile;
			}
		}
		unset( $map_profile['label'] );  // not needed by client
		return $map_profile;
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
