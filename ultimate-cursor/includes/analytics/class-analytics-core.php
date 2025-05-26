<?php

/**
 * Ultimate Cursor Analytics Core
 *
 * @package UltimateCursor
 * @since 1.0.0
 */

// Check if WordPress is loaded
if (!defined('ABSPATH')) {
	exit;
}

// Make sure WordPress functions are available
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(ABSPATH . 'wp-admin/includes/plugin.php');

class UltimateCursor_Analytics {
	private $version;
	private $product_id;
	private $plugin_name;
	private $plugin_title;
	private $api_endpoint;
	private $slug;
	private $core_file;
	private $plugin_deactivate_id;
	private $public_key;
	private $is_premium;
	private $popup_notice;
	private $deactivate_feedback;
	private $text_domain;
	private $plugin_msg;
	private $analytics_key;
	private $nonce;
	private $params;

	public function __construct($params) {
		$this->params = $params;
		$this->version = $params['sdk_version'];
		$this->product_id = $params['product_id'];
		$this->plugin_name = $params['plugin_name'];
		$this->plugin_title = $params['plugin_title'];
		$this->api_endpoint = $params['api_endpoint'];
		$this->slug = $params['slug'];
		$this->core_file = $params['core_file'];
		$this->plugin_deactivate_id = $params['plugin_deactivate_id'];
		$this->public_key = $params['public_key'];
		$this->is_premium = $params['is_premium'];
		$this->popup_notice = $params['popup_notice'];
		$this->deactivate_feedback = $params['deactivate_feedback'];
		$this->text_domain = $params['text_domain'];
		$this->plugin_msg = $params['plugin_msg'];

		$this->analytics_key = 'uc_' . md5($this->plugin_name);
		$this->nonce = wp_create_nonce($this->analytics_key);

		$this->init_hooks();
	}

	private function init_hooks() {
		$this->setup_analytics();
		add_action('wp_ajax_uc_analytics_data', array($this, 'handle_analytics_request'));
		add_action('wp_ajax_uc_dismiss_notice', array($this, 'handle_notice_dismissal'));

		if ($this->deactivate_feedback) {
			add_action('wp_ajax_uc_deactivate_feedback', array($this, 'handle_deactivate_feedback'));
			add_action('admin_footer', array($this, 'deactivate_feedback_form'));

			// Add script to handle deactivation
			add_action('admin_enqueue_scripts', array($this, 'enqueue_deactivation_scripts'));
		}
	}

	public function setup_analytics() {
		$this->maybe_show_notice();
		$this->maybe_send_analytics();
	}

	private function maybe_show_notice() {
		$notice_status = get_option('uc_notice_status_' . $this->analytics_key, false);
		if (!$notice_status) {
			add_action('admin_notices', array($this, 'display_analytics_notice'));
		}
	}

	public function display_analytics_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$notice_content = $this->plugin_msg;
		$notice_title = $this->plugin_title;
		$nonce = $this->nonce;
		$text_domain = $this->text_domain;

		include dirname(__FILE__) . '/templates/notice.php';
	}

	public function deactivate_feedback_form() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$nonce = $this->nonce;
		$text_domain = $this->text_domain;

		include dirname(__FILE__) . '/templates/deactivate-feedback.php';
	}

	public function handle_analytics_request() {
		check_ajax_referer($this->analytics_key, 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

		switch ($action) {
			case 'enable':
				update_option('uc_analytics_status_' . $this->analytics_key, 'enabled');
				$this->send_initial_analytics();
				break;
			case 'disable':
				update_option('uc_analytics_status_' . $this->analytics_key, 'disabled');
				break;
		}

		wp_send_json_success();
	}

	private function send_initial_analytics() {
		$data = $this->get_website_data();
		$this->send_data_to_server($data);
	}

	private function get_website_data() {
		// Get current user data
		$current_user = wp_get_current_user();
		if (!$current_user || !$current_user->exists()) {
			return array();
		}

		// Get admin user data
		$users = get_users([
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
			'paged'   => 1,
		]);

		$admin_user = (is_array($users) && !empty($users)) ? $users[0] : false;

		// Get user names
		$first_name = $current_user->first_name;
		$last_name = $current_user->last_name;

		if (empty($first_name) && empty($last_name)) {
			$first_name = $current_user->display_name;
			$last_name = '';
		}

		if ($admin_user) {
			$first_name = $admin_user->first_name ? $admin_user->first_name : $admin_user->display_name;
			$last_name = $admin_user->last_name;
		}

		$website_data = array(
			'website_name'           => get_bloginfo('name'),
			'wp_version'             => get_bloginfo('version'),
			'php_version'            => phpversion(),
		);

		$data = array(
			'product_id' => $this->product_id,
			'public_key' => $this->public_key,
			'plugin_name' => $this->plugin_name,
			'plugin_version' => $this->version,
			'first_name' => $first_name,
			'last_name' => $last_name,
			'email' => $current_user->user_email,
			'user_role' => !empty($current_user->roles) ? $current_user->roles[0] : '',
			'website_url' => $current_user->user_url,
			'website_data' => $website_data,
		);
		return $data;
	}

	private function send_data_to_server($data, $endpoint = null) {
		$endpoint = $endpoint ? $endpoint : $this->api_endpoint;

		$response = wp_remote_post($endpoint, array(
			'body' => json_encode($data),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-API-KEY'    => $this->public_key,
			),
			'timeout' => 60,
		));

	}

	public function handle_notice_dismissal() {
		check_ajax_referer($this->analytics_key, 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		update_option('uc_notice_status_' . $this->analytics_key, 'dismissed');
		wp_send_json_success();
	}

	public function handle_deactivate_feedback() {
		check_ajax_referer($this->analytics_key, 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized');
		}

		$feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';
		$reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';

		$data = array(
			'feedback' => [
				'reason' => $reason,
				'feedback' => $feedback
			]
		);

		//merge data with website data
		$data = array_merge($data, $this->get_website_data());

		// Send to deactivation endpoint
		$deactivation_endpoint = $this->api_endpoint . '/deactivate';
		$this->send_data_to_server($data, $deactivation_endpoint);
		wp_send_json_success();
	}

	private function maybe_send_analytics() {
		// Check if analytics is enabled
		$analytics_status = get_option('uc_analytics_status_' . $this->analytics_key, false);
		if ($analytics_status !== 'enabled') {
			return;
		}
		$data = $this->get_website_data();
		$this->send_data_to_server($data);
	}

	public function enqueue_deactivation_scripts() {
		wp_enqueue_script('jquery');
		wp_enqueue_style('wp-jquery-ui-dialog');
		wp_enqueue_script('jquery-ui-dialog');
	}
}
