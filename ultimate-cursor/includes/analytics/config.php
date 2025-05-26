<?php

/**
 * Ultimate Cursor Analytics Configuration
 *
 * @package UltimateCursor
 * @since 1.3.7
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Get analytics configuration parameters
 *
 * @return array Analytics configuration
 */
if (!function_exists('wpxero_get_analytics_config')) {
	function wpxero_get_analytics_config() {
		return array(
			'sdk_version'   => '1.0.0',
			'product_id'    => '1',
			'plugin_name'   => 'ULTIMATE CURSOR',
			'plugin_title'  => 'Greetings from WPXERO! 🌟',
			'api_endpoint'  => 'https://wpxero.com/wp-json/dci/v1/data-insights',
			'slug'          => 'ultimate-cursor',
			'core_file'     => false,
			'plugin_deactivate_id' => false,
			'public_key'    => 'pk_qqHdZ0n1dOExHyt1My3cozP3LkdwBmDF',
			'is_premium'    => false,
			'popup_notice'  => false,
			'deactivate_feedback' => true,
			'text_domain'   => 'ultimate-cursor',
			'plugin_msg'    => 'Make a big impact on WordPress by sharing non-sensitive plugin data. Get valuable updates and be part of something amazing!',
		);
	}
}
