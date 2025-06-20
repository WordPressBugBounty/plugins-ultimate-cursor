<?php

/**
 * Plugin Name:                 Ultimate Cursor – Best Cursor Animation Plugin for WordPress
 * Plugin URI:                  https://wordpress.org/plugins/ultimate-cursor
 * Description:                 Make Your Website Stand Out with Unique Cursor Effects and Smooth Animations!🚀
 * Version:                     1.4.4
 * Author:                      WPXERO
 * Author URI:                  https://wpxero.com/ultimate-cursor
 * Requires at least:           6.0
 * Requires PHP:                7.4
 * License:                     GPL v2 or later
 * License URI:                 https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:                 ultimate-cursor
 * Elementor requires at least: 3.0.0
 * Elementor tested up to:      3.28.4
 */


if (! defined('ABSPATH')) {
	exit;
}

if (! defined('UCA_VERSION')) {
	define('UCA_VERSION', '1.4.4');
}



/**
 * UltimateCursor Class
 */
class UltimateCursor {
	/**
	 * The single class instance.
	 *
	 * @var $instance
	 */
	private static $instance = null;
	const VERSION                   = UCA_VERSION;
	const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
	const MINIMUM_PHP_VERSION       = '7.0';
	/**
	 * Main Instance
	 * Ensures only one instance of this class exists in memory at any one time.
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Path to the plugin directory
	 *
	 * @var $plugin_path
	 */
	public $plugin_path;

	/**
	 * URL to the plugin directory
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;
	public $minimum_elementor_version;
	public $minimum_php_version;

	/**
	 * Ultimate Cursor constructor.
	 */
	public function __construct() {
		/* We do nothing here! */
	}

	/**
	 * Init options
	 */
	public function init() {
		$this->plugin_path = plugin_dir_path(__FILE__);
		$this->plugin_url  = plugin_dir_url(__FILE__);
		$this->minimum_elementor_version = self::MINIMUM_ELEMENTOR_VERSION;
		$this->minimum_php_version = self::MINIMUM_PHP_VERSION;


		// include helper files.
		$this->include_dependencies();

		// hooks.
		add_action('init', [$this, 'init_hook']);
		add_filter('user_has_cap', [$this, 'user_has_cap'], 10, 4);
	}


	public function user_has_cap($allcaps, $caps, $args, $user) {
		if (is_user_logged_in() && in_array('upload_files', $caps)) {
			$allcaps['upload_files'] = true;
		}
		return $allcaps;
	}

	/**
	 * Include dependencies
	 */
	private function include_dependencies() {
		require_once $this->plugin_path . 'classes/class-admin.php';
		require_once $this->plugin_path . 'classes/class-assets.php';
		require_once $this->plugin_path . 'classes/class-rest.php';
		if (did_action('elementor/loaded')) {
			require_once $this->plugin_path . 'classes/class-elementor.php';
		}
	}

	/**
	 * Init Hook
	 */
	public function init_hook() {
		// load textdomain.
		load_plugin_textdomain('ultimate-cursor', false, basename(dirname(__FILE__)) . '/languages');
	}


	/**
	 * Activation Hook
	 */
	public function activation_hook() {
		// Welcome Page Flag.
		set_transient('_ultimate_cursor_welcome_screen_activation_redirect', true, 30);
	}

	/**
	 * Deactivation Hook
	 */
	public function deactivation_hook() {
		// Initialize analytics to handle deactivation feedback
		require_once $this->plugin_path . 'includes/analytics/init.php';

		// Add action to show feedback form before deactivation
		add_action('admin_footer', function () {
			$analytics = wpxero_analytics_init();

			// Show the feedback form
			$analytics->deactivate_feedback_form();
		});
	}
}

/**
 * Function works with the Loader class instance
 *
 * @return object UltimateCursor
 */
function ultimate_cursor() {
	return UltimateCursor::instance();
}
add_action('plugins_loaded', 'ultimate_cursor');

register_activation_hook(__FILE__, [ultimate_cursor(), 'activation_hook']);
register_deactivation_hook(__FILE__, [ultimate_cursor(), 'deactivation_hook']);
