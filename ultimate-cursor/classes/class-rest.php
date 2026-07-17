<?php

/**
 * Rest API functions
 *
 * @package ultimate cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Ultimate_Cursor_Rest
 */
class Ultimate_Cursor_Rest extends WP_REST_Controller {
	/**
	 * The single class instance.
	 *
	 * @var $instance
	 */
	private static $instance = null;

	/**
	 * Get instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ultimate/cursor/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Ultimate_Cursor_Rest constructor.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		$settings_args = array(
			'settings' => array(
				'description' => __( 'Settings object to merge into the stored option.', 'ultimate-cursor' ),
				'type'        => 'object',
				'required'    => true,
			),
		);

		// Update Settings.
		register_rest_route(
			$namespace,
			'/update_settings/',
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'update_settings_permission' ),
				'args'                => $settings_args,
			)
		);

		// Update Background Settings.
		register_rest_route(
			$namespace,
			'/update_background_settings/',
			array(
				'methods'             => array( 'POST' ),
				'callback'            => array( $this, 'update_background_settings' ),
				'permission_callback' => array( $this, 'update_settings_permission' ),
				'args'                => $settings_args,
			)
		);
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool|WP_Error
	 */
	public function update_settings_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to change these options.', 'ultimate-cursor' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Update Settings.
	 *
	 * Input runs the schema-allowlist + premium-input-gate pipeline in
	 * Ultimate_Cursor_Settings_Schema::prepare_for_storage(). Unknown keys
	 * are dropped; premium fields are stripped from the payload (not from
	 * storage) when no valid license exists, so stored pro configuration
	 * stays dormant across a license lapse instead of being destroyed.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_settings( WP_REST_Request $req ) {
		$new_settings = $req->get_param( 'settings' );

		if ( is_array( $new_settings ) ) {
			$merged = Ultimate_Cursor_Settings_Schema::prepare_for_storage(
				$new_settings,
				get_option( 'ultimate_cursor_settings', array() ),
				'cursor'
			);

			update_option( 'ultimate_cursor_settings', $merged );
		}

		return $this->success( true );
	}


	/**
	 * Update Background Settings.
	 *
	 * Same pipeline as update_settings() — see there for the gating rationale.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_background_settings( WP_REST_Request $req ) {
		$new_settings = $req->get_param( 'settings' );

		if ( is_array( $new_settings ) ) {
			$merged = Ultimate_Cursor_Settings_Schema::prepare_for_storage(
				$new_settings,
				get_option( 'ultimate_cursor_background_settings', array() ),
				'background'
			);

			update_option( 'ultimate_cursor_background_settings', $merged );
		}

		return $this->success( true );
	}

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success( $response ) {
		return new WP_REST_Response(
			array(
				'success'  => true,
				'response' => $response,
			),
			200
		);
	}

	/**
	 * Error rest.
	 *
	 * @param mixed   $code       error code.
	 * @param mixed   $response   response data.
	 * @param boolean $true_error use true error response to stop the code processing.
	 * @return mixed
	 */
	public function error( $code, $response, $true_error = false ) {
		if ( $true_error ) {
			return new WP_Error( $code, $response, array( 'status' => 401 ) );
		}

		return new WP_REST_Response(
			array(
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			),
			401
		);
	}
}
Ultimate_Cursor_Rest::instance();
