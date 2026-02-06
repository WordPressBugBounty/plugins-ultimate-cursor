<?php
/**
 * Cache Plugin Compatibility
 * 
 * Handles compatibility with WP Rocket, LiteSpeed, Cloudflare, and other cache plugins.
 * Ensures JavaScript files are not delayed/deferred/combined to prevent chunk loading errors.
 *
 * @package ultimate-cursor
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Ultimate Cursor Cache Compatibility class.
 */
class Ultimate_Cursor_Cache_Compatibility {
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
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for cache plugin compatibility
	 */
	private function init_hooks() {
		// WP Rocket compatibility
		add_filter('rocket_exclude_defer_js', [$this, 'exclude_from_defer'], 10, 1);
		add_filter('rocket_exclude_js', [$this, 'exclude_from_combine'], 10, 1);
		add_filter('rocket_delay_js_exclusions', [$this, 'exclude_from_delay'], 10, 1);
		add_filter('rocket_excluded_inline_js_content', [$this, 'exclude_inline_js'], 10, 1);
		
		// LiteSpeed Cache compatibility
		add_filter('litespeed_optimize_js_excludes', [$this, 'exclude_from_combine'], 10, 1);
		
		// Autoptimize compatibility
		add_filter('autoptimize_filter_js_exclude', [$this, 'exclude_from_autoptimize'], 10, 1);
		
		// W3 Total Cache compatibility
		add_filter('w3tc_minify_js_do_tag_minification', [$this, 'w3tc_exclude_minify'], 10, 3);
		
		// WP Fastest Cache compatibility
		add_filter('wpfc_exclude_current_page', [$this, 'wpfc_check_scripts'], 10, 1);
		
		// Add CORS headers for CDN compatibility
		add_action('wp_enqueue_scripts', [$this, 'add_cors_headers'], 20);
	}

	/**
	 * Exclude from WP Rocket defer
	 *
	 * @param array $excluded_files Array of excluded files
	 * @return array Modified array
	 */
	public function exclude_from_defer($excluded_files) {
		if (!is_array($excluded_files)) {
			$excluded_files = [];
		}

		// Exclude main frontend script and all chunks
		$excluded_files[] = '/ultimate-cursor/build/frontend.js';
		$excluded_files[] = '/ultimate-cursor/build/(.*).js';
		$excluded_files[] = 'ultimate-cursor';
		
		return $excluded_files;
	}

	/**
	 * Exclude from JS combine/minify
	 *
	 * @param array $excluded_files Array of excluded files
	 * @return array Modified array
	 */
	public function exclude_from_combine($excluded_files) {
		if (!is_array($excluded_files)) {
			$excluded_files = [];
		}

		// Exclude all plugin JS files to prevent chunk loading issues
		$excluded_files[] = '/ultimate-cursor/build/';
		$excluded_files[] = 'ultimate-cursor/build';
		$excluded_files[] = '/wp-content/plugins/ultimate-cursor/build/';
		
		return $excluded_files;
	}

	/**
	 * Exclude from WP Rocket delay JS
	 *
	 * @param array $excluded_patterns Array of excluded patterns
	 * @return array Modified array
	 */
	public function exclude_from_delay($excluded_patterns) {
		if (!is_array($excluded_patterns)) {
			$excluded_patterns = [];
		}

		// Exclude the plugin from delay JS execution
		$excluded_patterns[] = 'ultimate-cursor';
		$excluded_patterns[] = 'ultimateCursorData';
		$excluded_patterns[] = '__ultimateCursorPublicPath';
		
		return $excluded_patterns;
	}

	/**
	 * Exclude inline JS from optimization
	 *
	 * @param array $excluded_patterns Array of excluded inline JS patterns
	 * @return array Modified array
	 */
	public function exclude_inline_js($excluded_patterns) {
		if (!is_array($excluded_patterns)) {
			$excluded_patterns = [];
		}

		// Exclude our inline public path script
		$excluded_patterns[] = '__ultimateCursorPublicPath';
		$excluded_patterns[] = 'ultimateCursorData';
		
		return $excluded_patterns;
	}

	/**
	 * Exclude from Autoptimize
	 *
	 * @param string $excluded_js Comma-separated list of excluded JS
	 * @return string Modified list
	 */
	public function exclude_from_autoptimize($excluded_js) {
		$new_excludes = 'ultimate-cursor/build/';
		
		if (empty($excluded_js)) {
			return $new_excludes;
		}
		
		return $excluded_js . ', ' . $new_excludes;
	}

	/**
	 * W3 Total Cache - exclude from minification
	 *
	 * @param bool $do_tag_minification Whether to minify
	 * @param string $script_tag The script tag
	 * @param string $file File path
	 * @return bool Whether to minify
	 */
	public function w3tc_exclude_minify($do_tag_minification, $script_tag, $file) {
		if (strpos($file, 'ultimate-cursor/build') !== false) {
			return false;
		}
		return $do_tag_minification;
	}

	/**
	 * WP Fastest Cache - check if our scripts are on page
	 *
	 * @param bool $exclude Whether to exclude current page
	 * @return bool Modified exclusion
	 */
	public function wpfc_check_scripts($exclude) {
		// Check if our plugin scripts are enqueued
		if (wp_script_is('ultimate-cursor-frontend', 'enqueued')) {
			// Don't exclude, but ensure proper handling
			return $exclude;
		}
		return $exclude;
	}

	/**
	 * Add CORS headers for CDN/Cloudflare compatibility
	 * Ensures chunks can be loaded cross-origin
	 */
	public function add_cors_headers() {
		// Add crossorigin attribute to our scripts for CDN compatibility
		add_filter('script_loader_tag', [$this, 'add_crossorigin_attribute'], 10, 3);
	}

	/**
	 * Add crossorigin attribute to script tags
	 *
	 * @param string $tag The script tag
	 * @param string $handle The script handle
	 * @param string $src The script source
	 * @return string Modified script tag
	 */
	public function add_crossorigin_attribute($tag, $handle, $src) {
		// Only add to our frontend script
		if ($handle === 'ultimate-cursor-frontend') {
			// Check if crossorigin is not already set
			if (strpos($tag, 'crossorigin') === false) {
				// Add crossorigin="anonymous" for CDN compatibility
				$tag = str_replace(' src=', ' crossorigin="anonymous" src=', $tag);
			}
		}
		return $tag;
	}
}

// Initialize the compatibility class
Ultimate_Cursor_Cache_Compatibility::instance();
