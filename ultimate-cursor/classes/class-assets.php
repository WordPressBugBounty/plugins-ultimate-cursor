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
			add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_background_assets']);
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

		// SERVER-SIDE PREMIUM GATE: Sanitize settings before sending to frontend.
		// This strips premium-only fields if no valid license exists,
		// preventing bypasses even if premium values were injected into the DB.
		$settings = UltimateCursor::sanitize_premium_settings($settings);

		// Normalize enableMultipleCursors to boolean
		$enable_multiple = isset($settings['enableMultipleCursors']) &&
			($settings['enableMultipleCursors'] === true || $settings['enableMultipleCursors'] === '1' || $settings['enableMultipleCursors'] === 1);

		// FORCE CHECK: If premium is not valid, disable multiple cursors
		// This ensures the feature doesn't work even if enabled in DB
		if (!UltimateCursor::is_premium_active()) {
			$enable_multiple = false;
			$settings['enableMultipleCursors'] = false;
			unset($settings['cursorConfigurations']);
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
	 * Enqueue frontend background animation assets with performance-first approach.
	 * Three.js is lazy-loaded only when background animation is enabled.
	 */
	public function enqueue_frontend_background_assets() {
		// Prevent multiple executions
		static $executed = false;
		if ($executed) {
			return;
		}
		$executed = true;

		$bg_settings = get_option('ultimate_cursor_background_settings', array());

		// Only load if background animation is enabled
		if (empty($bg_settings['enabled'])) {
			return;
		}

		$enable_multiple = !empty($bg_settings['enableMultipleBackgrounds']);

		if ($enable_multiple) {
			// Multiple backgrounds mode: check each config's scope individually.
			// Filter out configs that don't match the current page.
			$configs = isset($bg_settings['backgroundConfigurations']) && is_array($bg_settings['backgroundConfigurations'])
				? $bg_settings['backgroundConfigurations']
				: array();

			$filtered_configs = array();
			foreach ($configs as $config) {
				$config_scope = isset($config['scope']) ? $config['scope'] : 'entire-website';

				if ($config_scope === 'specific-pages') {
					$specific_pages = isset($config['specificPages']) ? $config['specificPages'] : '';
					if ($this->is_matching_page($specific_pages)) {
						$filtered_configs[] = $config;
					}
				} else {
					// 'entire-website' or 'css-selector' — always include
					$filtered_configs[] = $config;
				}
			}

			// Don't load the script if no configs match the current page
			if (empty($filtered_configs)) {
				return;
			}

			// Pass only the matching configs to the frontend
			$bg_settings['backgroundConfigurations'] = array_values($filtered_configs);
		} else {
			// Single background mode: check top-level scope
			$scope = isset($bg_settings['scope']) ? $bg_settings['scope'] : 'entire-website';

			if ($scope === 'specific-pages') {
				$specific_pages = isset($bg_settings['specificPages']) ? $bg_settings['specificPages'] : '';
				if (!$this->is_matching_page($specific_pages)) {
					return;
				}
			}
		}

		$asset_data = $this->get_asset_file('build/frontend-background');

		$bg_js_path = ultimate_cursor()->plugin_path . 'build/frontend-background.js';
		$bg_js_url = ultimate_cursor()->plugin_url . 'build/frontend-background.js';

		// Add filemtime-based cache busting
		$version = $asset_data['version'];
		if (file_exists($bg_js_path)) {
			$version .= '.' . filemtime($bg_js_path);
		}

		wp_enqueue_script(
			'ultimate-cursor-frontend-background',
			$bg_js_url,
			$asset_data['dependencies'],
			$version,
			array(
				'in_footer' => true,
				'strategy' => 'defer',
			)
		);

		// Inject public path for chunk loading
		$public_path_script = sprintf(
			'window.__ultimateCursorBgPublicPath = %s;',
			wp_json_encode(ultimate_cursor()->plugin_url . 'build/')
		);

		wp_add_inline_script(
			'ultimate-cursor-frontend-background',
			$public_path_script,
			'before'
		);

		// Use wp_add_inline_script + wp_json_encode instead of wp_localize_script
		// to preserve data types (numbers, booleans) in backgroundConfigurations.
		// wp_localize_script converts all scalar values to strings which breaks rendering.
		$bg_data_script = sprintf(
			'var ultimateCursorBgData = %s;',
			wp_json_encode($bg_settings)
		);

		wp_add_inline_script(
			'ultimate-cursor-frontend-background',
			$bg_data_script,
			'before'
		);
	}

	/**
	 * Check if the current page matches the specific pages list.
	 *
	 * @param string $pages_string Comma-separated list of page slugs, URLs, or IDs.
	 * @return bool
	 */
	private function is_matching_page($pages_string) {
		if (empty($pages_string)) {
			return false;
		}

		$pages = array_map('trim', explode(',', $pages_string));
		$current_url = $_SERVER['REQUEST_URI'];
		$current_slug = trim(parse_url($current_url, PHP_URL_PATH), '/');

		foreach ($pages as $page) {
			$page = trim($page, '/');

			if (empty($page)) {
				continue;
			}

			// Match by post ID
			if (is_numeric($page) && is_single($page)) {
				return true;
			}

			// Match by slug or URL path
			if ($current_slug === $page || $current_url === $page) {
				return true;
			}

			// Match home page
			if ($page === '/' || $page === 'home') {
				if (is_front_page() || is_home()) {
					return true;
				}
			}

			// Partial match (for nested slugs)
			if (!empty($page) && strpos($current_slug, $page) !== false) {
				return true;
			}
		}

		return false;
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
				'settings' => (function () {
					$settings = get_option('ultimate_cursor_settings', array());
					// SERVER-SIDE PREMIUM GATE: Sanitize admin settings output.
					// This ensures premium fields are stripped if license is invalid.
					$settings = UltimateCursor::sanitize_premium_settings($settings);
					return $settings;
				})(),
				'backgroundSettings' => get_option('ultimate_cursor_background_settings', array()),
				'cursors' => $cursor_images,
				'plugin_url' => ultimate_cursor()->plugin_url,
				'version' => UCA_VERSION,
				'shapes' => $cursor_shapes,
				// isPro requires BOTH pro plugin active AND valid Freemius license
				'isPro' => UltimateCursor::is_premium_active(),
				'isLicenseValid' => UltimateCursor::is_premium_active(),
				'proUrl' => 'https://wpxero.com/plugins/ultimate-cursor/pricing',
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('ultimate_cursor_admin_nonce'),
				'activePlugins' => (function () {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					$active = get_option('active_plugins', array());
					if (is_multisite()) {
						$active = array_merge($active, array_keys(get_site_option('active_sitewide_plugins', array())));
					}
					$slugs = array();
					foreach ($active as $plugin) {
						$dirname = dirname($plugin);
						if ($dirname !== '.') {
							$slugs[] = $dirname;
						}
					}
					return $slugs;
				})(),
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
