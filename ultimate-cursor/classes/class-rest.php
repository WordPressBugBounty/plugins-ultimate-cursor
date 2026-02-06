<?php

/**
 * Rest API functions
 *
 * @package ultimate cursor
 */

if (! defined('ABSPATH')) {
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
		if (is_null(self::$instance)) {
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
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Update Settings.
		register_rest_route(
			$namespace,
			'/update_settings/',
			[
				'methods'             => ['POST'],
				'callback'            => [$this, 'update_settings'],
				'permission_callback' => [$this, 'update_settings_permission'],
			]
		);
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_settings_permission() {
		if (! current_user_can('manage_options')) {
			return $this->error('user_dont_have_permission', __('User don\'t have permissions to change options.', 'ultimate-cursor'), true);
		}

		return true;
	}


	/**
	 * Update Settings.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_settings(WP_REST_Request $req) {
		$new_settings = $req->get_param('settings');

		if (is_array($new_settings)) {
			$current_settings = get_option('ultimate_cursor_settings', []);
			update_option('ultimate_cursor_settings', array_merge($current_settings, $new_settings));
		}

		return $this->success(true);
	}


	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success($response) {
		return new WP_REST_Response(
			[
				'success'  => true,
				'response' => $response,
			],
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
	public function error($code, $response, $true_error = false) {
		if ($true_error) {
			return new WP_Error($code, $response, ['status' => 401]);
		}

		return new WP_REST_Response(
			[
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			],
			401
		);
	}
}
Ultimate_Cursor_Rest::instance();
