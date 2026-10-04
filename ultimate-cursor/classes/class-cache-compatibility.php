<?php
/**
 * Cache Plugin Compatibility
 *
 * Handles compatibility with WP Rocket, LiteSpeed, SiteGround Optimizer, Perfmatters,
 * Autoptimize, WP-Optimize, W3 Total Cache and Cloudflare Rocket Loader.
 * Ensures JavaScript files are not delayed/deferred/combined to prevent chunk loading errors.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
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
	 * Handles of the scripts this plugin enqueues on the frontend.
	 *
	 * @var string[]
	 */
	const SCRIPT_HANDLES = array(
		'ultimate-cursor-frontend',
		'ultimate-cursor-frontend-background',
	);

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
		add_filter( 'rocket_exclude_defer_js', array( $this, 'exclude_from_defer' ), 10, 1 );
		add_filter( 'rocket_exclude_js', array( $this, 'exclude_from_combine' ), 10, 1 );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_from_delay' ), 10, 1 );
		add_filter( 'rocket_excluded_inline_js_content', array( $this, 'exclude_inline_js' ), 10, 1 );

		// LiteSpeed Cache compatibility: combine/minify, and defer/delay.
		add_filter( 'litespeed_optimize_js_excludes', array( $this, 'exclude_from_combine' ), 10, 1 );
		add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'exclude_from_delay' ), 10, 1 );

		// SiteGround Optimizer (these filters take script HANDLES).
		add_filter( 'sgo_js_minify_exclude', array( $this, 'exclude_handles' ), 10, 1 );
		add_filter( 'sgo_javascript_combine_exclude', array( $this, 'exclude_handles' ), 10, 1 );
		add_filter( 'sgo_js_async_exclude', array( $this, 'exclude_handles' ), 10, 1 );
		add_filter( 'sgo_javascript_combine_excluded_inline_content', array( $this, 'exclude_inline_js' ), 10, 1 );

		// Perfmatters "Delay JavaScript".
		add_filter( 'perfmatters_delay_js_exclusions', array( $this, 'exclude_from_delay' ), 10, 1 );

		// WP-Optimize minify/merge.
		add_filter( 'wp-optimize-minify-default-exclusions', array( $this, 'exclude_from_combine' ), 10, 1 );

		// Cloudflare Rocket Loader: opt our script tags AND their inline data
		// out together (excluding only one would run them out of order).
		add_filter( 'script_loader_tag', array( $this, 'add_cfasync_attribute' ), 10, 2 );
		add_filter( 'wp_inline_script_attributes', array( $this, 'add_cfasync_to_inline' ), 10, 1 );

		// Autoptimize compatibility
		add_filter( 'autoptimize_filter_js_exclude', array( $this, 'exclude_from_autoptimize' ), 10, 1 );

		// W3 Total Cache compatibility
		add_filter( 'w3tc_minify_js_do_tag_minification', array( $this, 'w3tc_exclude_minify' ), 10, 3 );

		// Add CORS headers for CDN compatibility
		add_action( 'wp_enqueue_scripts', array( $this, 'add_cors_headers' ), 20 );
	}

	/**
	 * Exclude from WP Rocket defer
	 *
	 * @param array $excluded_files Array of excluded files
	 * @return array Modified array
	 */
	public function exclude_from_defer( $excluded_files ) {
		if ( ! is_array( $excluded_files ) ) {
			$excluded_files = array();
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
	public function exclude_from_combine( $excluded_files ) {
		if ( ! is_array( $excluded_files ) ) {
			$excluded_files = array();
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
	public function exclude_from_delay( $excluded_patterns ) {
		if ( ! is_array( $excluded_patterns ) ) {
			$excluded_patterns = array();
		}

		// Exclude the plugin from delay JS execution
		$excluded_patterns[] = 'ultimate-cursor';
		$excluded_patterns[] = 'ultimateCursorData';
		$excluded_patterns[] = 'ultimateCursorLoader';
		$excluded_patterns[] = '__ultimateCursorPublicPath';
		$excluded_patterns[] = 'ultimateCursorBgData';
		$excluded_patterns[] = 'ultimateCursorBgLoader';
		$excluded_patterns[] = '__ultimateCursorBgPublicPath';

		return $excluded_patterns;
	}

	/**
	 * Exclude our script handles (for optimizers that match by handle).
	 *
	 * @param array $handles Excluded script handles.
	 * @return array Modified array
	 */
	public function exclude_handles( $handles ) {
		if ( ! is_array( $handles ) ) {
			$handles = array();
		}

		foreach ( self::SCRIPT_HANDLES as $handle ) {
			$handles[] = $handle;
		}

		return $handles;
	}

	/**
	 * Add data-cfasync="false" to our script tags so Cloudflare Rocket Loader
	 * leaves them alone.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function add_cfasync_attribute( $tag, $handle ) {
		// $tag also contains the handle's inline scripts (which get the
		// attribute separately), so look for it on the src tag specifically.
		if ( in_array( $handle, self::SCRIPT_HANDLES, true ) && false === strpos( $tag, 'data-cfasync="false" src=' ) ) {
			$tag = str_replace( ' src=', ' data-cfasync="false" src=', $tag );
		}
		return $tag;
	}

	/**
	 * Add data-cfasync="false" to the inline scripts that carry our settings
	 * (ids `{handle}-js-before` / `-js-after` / `-js-extra` / `-js-translations`),
	 * so they keep running ahead of the script they belong to.
	 *
	 * @param array $attributes Inline script tag attributes.
	 * @return array Modified attributes.
	 */
	public function add_cfasync_to_inline( $attributes ) {
		if ( ! is_array( $attributes ) || empty( $attributes['id'] ) ) {
			return $attributes;
		}

		foreach ( self::SCRIPT_HANDLES as $handle ) {
			if ( 0 === strpos( $attributes['id'], $handle . '-js-' ) ) {
				$attributes['data-cfasync'] = 'false';
				break;
			}
		}

		return $attributes;
	}

	/**
	 * Exclude inline JS from optimization
	 *
	 * @param array $excluded_patterns Array of excluded inline JS patterns
	 * @return array Modified array
	 */
	public function exclude_inline_js( $excluded_patterns ) {
		if ( ! is_array( $excluded_patterns ) ) {
			$excluded_patterns = array();
		}

		// Exclude our inline public path script
		$excluded_patterns[] = '__ultimateCursorPublicPath';
		$excluded_patterns[] = 'ultimateCursorData';
		$excluded_patterns[] = 'ultimateCursorLoader';
		$excluded_patterns[] = '__ultimateCursorBgPublicPath';
		$excluded_patterns[] = 'ultimateCursorBgData';
		$excluded_patterns[] = 'ultimateCursorBgLoader';

		return $excluded_patterns;
	}

	/**
	 * Exclude from Autoptimize
	 *
	 * @param string $excluded_js Comma-separated list of excluded JS
	 * @return string Modified list
	 */
	public function exclude_from_autoptimize( $excluded_js ) {
		$new_excludes = 'ultimate-cursor/build/';

		if ( empty( $excluded_js ) ) {
			return $new_excludes;
		}

		return $excluded_js . ', ' . $new_excludes;
	}

	/**
	 * W3 Total Cache - exclude from minification
	 *
	 * @param bool   $do_tag_minification Whether to minify
	 * @param string $script_tag The script tag
	 * @param string $file File path
	 * @return bool Whether to minify
	 */
	public function w3tc_exclude_minify( $do_tag_minification, $script_tag, $file ) {
		if ( strpos( $file, 'ultimate-cursor/build' ) !== false ) {
			return false;
		}
		return $do_tag_minification;
	}

	/**
	 * Add CORS headers for CDN/Cloudflare compatibility
	 * Ensures chunks can be loaded cross-origin
	 */
	public function add_cors_headers() {
		// Add crossorigin attribute to our scripts for CDN compatibility
		add_filter( 'script_loader_tag', array( $this, 'add_crossorigin_attribute' ), 10, 3 );
	}

	/**
	 * Add crossorigin attribute to script tags
	 *
	 * @param string $tag The script tag
	 * @param string $handle The script handle
	 * @param string $src The script source
	 * @return string Modified script tag
	 */
	public function add_crossorigin_attribute( $tag, $handle, $src ) {
		/**
		 * Filter whether the frontend script tag gets crossorigin="anonymous".
		 *
		 * Off by default: with the attribute, a CDN that serves the file from
		 * another host WITHOUT an Access-Control-Allow-Origin header makes the
		 * browser block the script outright, so the cursor never loads. Only
		 * enable it for a CDN that is known to send CORS headers for JS.
		 *
		 * @param bool $add Whether to add the attribute. Default false.
		 */
		if ( ! apply_filters( 'ultimate_cursor_script_crossorigin', false ) ) {
			return $tag;
		}

		// Only add to our frontend script
		if ( $handle === 'ultimate-cursor-frontend' ) {
			// Check if crossorigin is not already set
			if ( strpos( $tag, 'crossorigin' ) === false ) {
				// Add crossorigin="anonymous" for CDN compatibility
				$tag = str_replace( ' src=', ' crossorigin="anonymous" src=', $tag );
			}
		}
		return $tag;
	}
}

// Initialize the compatibility class
Ultimate_Cursor_Cache_Compatibility::instance();
