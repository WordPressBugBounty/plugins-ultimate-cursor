<?php

/**
 * Ultimate Cursor Analytics Initialization
 *
 * @package UltimateCursor
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('wpxero_analytics_init')) {
	function wpxero_analytics_init($custom_params = array()) {
		if (!is_admin()) {
			return;
		}

		require_once dirname(__FILE__) . '/class-analytics-core.php';
		require_once dirname(__FILE__) . '/config.php';

		// Get default parameters from config
		$params = wp_parse_args($custom_params, wpxero_get_analytics_config());

		$analytics = new WPXERO_Analytics($params);

		return $analytics;
	}
}
