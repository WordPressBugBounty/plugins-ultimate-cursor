<?php

/**
 * Plugin Name:                 Ultimate Cursor – Interactive and Animated Cursor Effects Toolkit
 * Plugin URI:                  https://wordpress.org/plugins/ultimate-cursor
 * Description:                 Make Your Website Stand Out with Unique Cursor Effects and Smooth Animations!🚀
 * Version:                     1.7.4
 * Author:                      WPXERO
 * Author URI:                  https://wpxero.com/ultimate-cursor
 * Requires at least:           6.0
 * Requires PHP:                7.4
 * License:                     GPL3
 * License URI:                 http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:                 ultimate-cursor
 * Elementor requires at least: 3.0.0
 * Elementor tested up to:      3.28.4
 */


if (! defined('ABSPATH')) {
	exit;
}

if (! defined('UCA_VERSION')) {
	define('UCA_VERSION', '1.7.4');
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
		$this->init_freemius();

		// hooks.
		add_filter('user_has_cap', [$this, 'user_has_cap'], 10, 4);

		// Disable Freemius license activation notice
		// add_filter('fs_show_trial_notice_ultimate_cursor', '__return_false');

		// init freemius.
	}

	public function init_freemius() {
		if (!function_exists('ultimate_cursor_fs')) {
			// Create a helper function for easy SDK access.
			function ultimate_cursor_fs() {
				global $ultimate_cursor_fs;

				if (!isset($ultimate_cursor_fs)) {
					// Activate multisite network integration.
					if (!defined('WP_FS__PRODUCT_19720_MULTISITE')) {
						define('WP_FS__PRODUCT_19720_MULTISITE', true);
					}

					// Include Freemius SDK.
					$ultimate_cursor_fs = fs_dynamic_init(array(
						'id'                  => '19720',
						'slug'                => 'ultimate-cursor',
						'premium_slug'        => 'ultimate-cursor-pro',
						'type'                => 'plugin',
						'public_key'          => 'pk_fb94765a4f619e83979c2825626c2',
						'is_premium'          => false,
						'is_premium_only'     => false,
						'has_paid_plans'      => true,
						'is_live'             => true,
						'is_org_compliant'    => true,
						'parallel_activation' => array(
							'enabled'                  => true,
							'premium_version_basename' => 'ultimate-cursor-pro/ultimate-cursor-pro.php',
						),
						'menu'                => array(
							'slug'        => 'ultimate-cursor',
							'first-path'  => 'admin.php?page=ultimate-cursor',
							'support'     => false,
							'contact'     => false,
							'pricing'     => true,
						),
					));
				}

				return $ultimate_cursor_fs;
			}

			// Init Freemius.
			ultimate_cursor_fs();
			do_action('ultimate_cursor_fs_loaded');
		}
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
		require_once $this->plugin_path . 'vendor/freemius/wordpress-sdk/start.php';
		if (did_action('elementor/loaded')) {
			require_once $this->plugin_path . 'classes/class-elementor.php';
		}
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
add_action('admin_notices', function () {
	if (function_exists('ultimate_cursor_fs')) {
		ultimate_cursor_fs();
	}
});
register_activation_hook(__FILE__, [ultimate_cursor(), 'activation_hook']);
register_deactivation_hook(__FILE__, [ultimate_cursor(), 'deactivation_hook']);

/**
 * Get menu parameters for premium features
 * This function is called when the pro version is active
 */
function get_menu_params__premium_only() {
	return array(
		'slug'        => 'ultimate-cursor',
		'first-path'  => 'admin.php?page=ultimate-cursor',
		'account'     => true,
		'support'     => false,
		'contact'     => false,
		'pricing'     => true,
	);
}
