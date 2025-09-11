<?php

if ( ! defined( 'TRACKSERVER_PLUGIN_DIR' ) ) {
	die( 'No, sorry.' );
}
class Trackserver_Rest_Api {

	// Singleton.
	protected static $instance;
	private $trackserver; // Reference to the main object

	/**
	 * Trackserver_Rest_Api constructor
	 *
	 * @param object $trackserver Reference to the main object
	 *
	 * @since 6.0
	 */
	public function __construct( $trackserver ) {
		$this->trackserver = $trackserver;
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
	 * Initialize the REST API. Use one handler (dispatcher) for all routes.
	 *
	 * @since 6.0
	 */
	public function rest_api_init() {

		$routes = array(
			array(
				'route'   => '/add-map-profile',
				'methods' => array( 'POST' ),
				'cap'     => 'manage_options',
			),
		);

		foreach ( $routes as $rte ) {
			register_rest_route(
				'trackserver/v1',
				$rte['route'],
				array(
					'methods'  => $rte['methods'],
					'callback' => array( &$this, 'handle_rest_request' ),
					'permission_callback' => function () use ( $rte ) {
						return current_user_can( $rte['cap'] );
					},
				),
			);
		}
	}

	/**
	 * Handler for all Trackserver REST API calls. Based on the matched route,
	 * include the necessary class(es) and dispatch the route-specific handler.
	 *
	 * @since 6.0
	 */
	public function handle_rest_request( WP_REST_Request $request ) {
		// https://developer.wordpress.org/reference/classes/wp_rest_request/
		$route = $request->get_route();

		switch ( $route ) {
			case '/trackserver/v1/add-map-profile':
				require_once TRACKSERVER_PLUGIN_DIR . 'class-trackserver-map-profiles.php';
				return Trackserver_Map_Profiles::get_instance( $this->trackserver )->handle_rest_add_map_profile( $request );
				break;
		}

		// return an empty object for unhandled routes. If this is ever returned, it should be considered a bug.
		return new stdClass;
	}
}
