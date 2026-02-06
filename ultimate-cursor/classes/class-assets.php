<?php

/**
 * Plugin assets functions.
 *
 * @package ultimate-cursor
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Ultimate Cursor Assets class.
 */
class Ultimate_Cursor_Assets {
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
	 * Ultimate_Cursor_Assets constructor.
	 */
	private function __construct() {
		if (!is_admin()) {
			add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		}
		add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
	}

	/**
	 * Loads the asset file for the given script or style.
	 * Returns a default if the asset file is not found.
	 *
	 * @param string $filepath The name of the file without the extension.
	 *
	 * @return array The asset file contents.
	 */
	public function get_asset_file($filepath) {
		$asset_path = ultimate_cursor()->plugin_path . $filepath . '.asset.php';

		if (file_exists($asset_path)) {
			return include $asset_path;
		}

		return [
			'dependencies' => [],
			'version'      => UCA_VERSION,
		];
	}

	/**
	 * Enqueue frontend assets with cache-proof chunk loading
	 */
	public function enqueue_frontend_assets() {
		// Prevent multiple executions
		static $executed = false;
		if ($executed) {
			return;
		}
		$executed = true;

		$settings = get_option('ultimate_cursor_settings', array());
		$asset_data = $this->get_asset_file('build/frontend');

		// Normalize enableMultipleCursors to boolean
		$enable_multiple = isset($settings['enableMultipleCursors']) &&
			($settings['enableMultipleCursors'] === true || $settings['enableMultipleCursors'] === '1' || $settings['enableMultipleCursors'] === 1);

		// FORCE CHECK: If Pro plugin is not active, disable multiple cursors
		// This ensures the feature doesn't work even if enabled in DB
		if (!class_exists('Ultimate_Cursor_Pro')) {
			$enable_multiple = false;
			$settings['enableMultipleCursors'] = false;
		}

		// Check if we should load the script
		$should_load = false;

		if ($enable_multiple) {
			$should_load = true;
		} elseif ((isset($settings['effect']) && $settings['effect'] !== 'none') || (isset($settings['cursorType']) && $settings['cursorType'] !== null)) {
			$should_load = true;
		}

		if ($should_load) {
			// Get frontend.js file path for cache busting
			$frontend_js_path = ultimate_cursor()->plugin_path . 'build/frontend.js';
			$frontend_js_url = ultimate_cursor()->plugin_url . 'build/frontend.js';
			
			// Add filemtime-based cache busting to version
			$version = $asset_data['version'];
			if (file_exists($frontend_js_path)) {
				$version .= '.' . filemtime($frontend_js_path);
			}

			// Enqueue the script with cache-busting version
			wp_enqueue_script(
				'ultimate-cursor-frontend',
				$frontend_js_url,
				$asset_data['dependencies'],
				$version,
				array(
					'in_footer' => true,
					'strategy' => 'defer', // Defer for optimal loading
				)
			);

			// CRITICAL: Inject public path BEFORE the main script loads
			// This ensures webpack knows where to load dynamic chunks from
			// even when the main script is cached/minified by WP Rocket, LiteSpeed, etc.
			$public_path_script = sprintf(
				'window.__ultimateCursorPublicPath = %s;',
				wp_json_encode(ultimate_cursor()->plugin_url . 'build/')
			);
			
			wp_add_inline_script(
				'ultimate-cursor-frontend',
				$public_path_script,
				'before' // Execute BEFORE the main script
			);

			// Then localize script data (after public path is set)
			wp_localize_script(
				'ultimate-cursor-frontend',
				'ultimateCursorData',
				$settings
			);
		}
	}


	/**
	 * Enqueue admin pages assets.
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		wp_add_inline_style('wp-admin', '.php-error #adminmenuback, .php-error #adminmenuwrap { margin-top: 0px !important; }');


		if ('toplevel_page_ultimate-cursor' !== $screen->id) {
			return;
		}

		$asset_data = $this->get_asset_file('build/admin');

		wp_enqueue_script(
			'ultimate-cursor-admin',
			ultimate_cursor()->plugin_url . 'build/admin.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		// Pass the cursor images
		$cursor_images = [];
		$cursor_shapes = [];

		$cursor_dir =  ultimate_cursor()->plugin_path . 'assets/cursors/';
		$cursor_url =  ultimate_cursor()->plugin_url . 'assets/cursors/';
		$cursor_shapes_dir =  ultimate_cursor()->plugin_path . 'assets/shapes/';
		$cursor_shapes_url =  ultimate_cursor()->plugin_url . 'assets/shapes/';

		$extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'cur'];

		foreach ($extensions as $ext) {
			$files = glob($cursor_dir . '*.' . $ext);
			if ($files) {
				foreach ($files as $file) {
					$cursor_images[] = $cursor_url . basename($file);
				}
			}
		}

		foreach ($extensions as $ext) {
			$files = glob($cursor_shapes_dir . '*.' . $ext);
			if ($files) {
				foreach ($files as $file) {
					$cursor_shapes[] = $cursor_shapes_url . basename($file);
				}
			}
		}



		wp_localize_script(
			'ultimate-cursor-admin',
			'ultimateCursorAdminData',
			[
				'settings' => (function() {
					$settings = get_option('ultimate_cursor_settings', array());
					// FORCE CHECK: If Pro plugin is not active, disable multiple cursors in admin too
					if (!class_exists('Ultimate_Cursor_Pro') && isset($settings['enableMultipleCursors'])) {
						$settings['enableMultipleCursors'] = false;
					}
					return $settings;
				})(),
				'cursors' => $cursor_images,
				'plugin_url' => ultimate_cursor()->plugin_url,
				'version' => UCA_VERSION,
				'shapes' => $cursor_shapes,
				'isPro' => class_exists('Ultimate_Cursor_Pro'),
				'proUrl' => 'https://wpxero.com/plugins/ultimate-cursor/pricing',
			]
		);

		wp_enqueue_style(
			'ultimate-cursor-admin',
			ultimate_cursor()->plugin_url . 'build/style-admin.css',
			[],
			$asset_data['version']
		);

		wp_enqueue_style('wp-components');
	}
}

Ultimate_Cursor_Assets::instance();
