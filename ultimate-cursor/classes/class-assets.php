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
	 * Ultimate_Cursor_Assets constructor.
	 */
	public function __construct() {
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
	 * Enqueue editor assets
	 */
	public function enqueue_frontend_assets() {
		$settings = get_option('ultimate_cursor_settings', array());
		$asset_data = $this->get_asset_file('build/frontend');
		//load if iffect !== 'none'
		if (isset($settings['effect']) && $settings['effect'] !== 'none' || isset($settings['cursorType']) && $settings['cursorType'] !== null) {

			wp_enqueue_script(
				'ultimate-cursor-editor',
				ultimate_cursor()->plugin_url . 'build/frontend.js',
				$asset_data['dependencies'],
				$asset_data['version'],
				true
			);
		}


		wp_localize_script(
			'ultimate-cursor-editor',
			'ultimateCursorData',
			$settings
		);
	}


	/**
	 * Enqueue admin pages assets.
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

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
				'settings' => get_option('ultimate_cursor_settings', array()),
				'cursors' => $cursor_images,
				'plugin_url' => ultimate_cursor()->plugin_url,
				'version' => UCA_VERSION,
				'shapes' => $cursor_shapes,
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

new Ultimate_Cursor_Assets();
