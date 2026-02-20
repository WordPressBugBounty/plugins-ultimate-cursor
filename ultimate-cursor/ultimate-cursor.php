<?php

/**
 * Plugin Name:                 Ultimate Cursor – Interactive and Animated Cursor Effects Toolkit
 * Plugin URI:                  https://wordpress.org/plugins/ultimate-cursor
 * Description:                 Make Your Website Stand Out with Unique Cursor Effects and Smooth Animations!🚀
 * Version:                     1.9.2
 * Author:                      WPXERO
 * Author URI:                  https://wpxero.com/plugins/ultimate-cursor
 * Requires at least:           6.0
 * Requires PHP:                7.4
 * License:                     GPL3
 * License URI:                 http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:                 ultimate-cursor
 */


if (! defined('ABSPATH')) {
	exit;
}

if (! defined('UCA_VERSION')) {
	define('UCA_VERSION', '1.9.2');
}



/**
 * UltimateCursor Class
 */
class UltimateCursor {
	/**
	 * Freemius instance
	 * @var object
	 */
	private $freemius;
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
	}

	/**
	 * Initialize Freemius SDK
	 */
	private function init_freemius() {
		if (!isset($this->freemius)) {
			// Skip Freemius init during plugin upgrade/install to prevent memory exhaustion.
			if (
				(defined('WP_INSTALLING') && WP_INSTALLING) ||
				(isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('upload-plugin', 'update-plugin', 'delete-plugin'), true))
			) {
				return $this->freemius;
			}

			// Include Freemius SDK
			if (file_exists(dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php')) {
				require_once dirname(__FILE__) . '/vendor/freemius/wordpress-sdk/start.php';

				try {
					$this->freemius = fs_dynamic_init(array(
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

					// Signal that Freemius SDK is initiated
					do_action('ultimate_cursor_fs_loaded');
				} catch (Exception $e) {
					// Log error but don't break the plugin
					if (defined('WP_DEBUG') && WP_DEBUG) {
						error_log('Ultimate Cursor Freemius Error: ' . $e->getMessage());
					}
				}
			}
		}

		return $this->freemius;
	}


	/**
	 * Get Freemius instance
	 *
	 * @return object|null
	 */
	public function get_freemius() {
		return $this->freemius;
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

		// CRITICAL: Handles CDN CORS headers and prevents "Delay JS" from breaking the cursor
		// Do not remove this unless you want to break compatibility with WP Rocket, LiteSpeed, etc.
		require_once $this->plugin_path . 'classes/class-cache-compatibility.php';
		if (did_action('elementor/loaded')) {
			require_once $this->plugin_path . 'classes/class-elementor.php';
		}

		if (!class_exists('Ultimate_Cursor_Pro')) {
			require_once $this->plugin_path . 'classes/class-dashboard-widget.php';
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
	 * Note: We only clean up temporary data here.
	 * Settings are preserved so users don't lose configuration when deactivating/reactivating.
	 */
	public function deactivation_hook() {
		delete_transient('_ultimate_cursor_welcome_screen_activation_redirect');
		// Settings are intentionally NOT deleted here - they persist through deactivation
		// Settings will only be deleted if user uninstalls (deletes) the plugin via uninstall.php
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

/**
 * Get Freemius instance for free plugin
 *
 * @return object|null
 */
function ultimate_cursor_fs() {
	$plugin = ultimate_cursor();
	return $plugin ? $plugin->get_freemius() : null;
}

/**
 * Activation hook callback
 */
function ultimate_cursor_activation_hook() {
	ultimate_cursor()->activation_hook();
}

/**
 * Deactivation hook callback
 */
function ultimate_cursor_deactivation_hook() {
	ultimate_cursor()->deactivation_hook();
}

register_activation_hook(__FILE__, 'ultimate_cursor_activation_hook');
register_deactivation_hook(__FILE__, 'ultimate_cursor_deactivation_hook');
