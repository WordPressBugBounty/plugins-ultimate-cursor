<?php

/**
 * Plugin admin functions.
 *
 * @package ultimate-cursor
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Ultimate Cursor Admin class.
 */
class Ultimate_Cursor_Admin {
	/**
	 * Ultimate_Cursor_Admin constructor.
	 */
	public function __construct() {
		add_action('admin_init', [$this, 'redirect_to_welcome_screen']);
		add_action('admin_menu', [$this, 'register_admin_menu'], 20);
		add_action('in_admin_header', [$this, 'disable_admin_notices'], PHP_INT_MAX);

		add_filter('admin_body_class', [$this, 'admin_body_class']);
		// Enqueue media uploader
		add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);

		add_filter('plugin_action_links_ultimate-cursor/ultimate-cursor.php', [$this, 'ultimate_cursor_settings_link']);
		add_action('admin_init', [$this, 'extenderx_sdk_xero_plugin']);
	}


	public function disable_admin_notices() {

		if (isset($_GET['page']) && $_GET['page'] === 'ultimate-cursor') {
			remove_all_actions('admin_notices');
			remove_all_actions('all_admin_notices');
			remove_all_actions('network_admin_notices');
		}
	}
	public function ultimate_cursor_settings_link($links) {
		$settings_link = '<a href="' . admin_url('admin.php?page=ultimate-cursor&sub_page=settings') . '">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	public function enqueue_media_uploader() {
		wp_enqueue_media();
	}

	/**
	 * Redirect to Welcome page after activation.
	 */
	public function redirect_to_welcome_screen() {
		// Bail if no activation redirect.
		if (! get_transient('_ultimate_cursor_welcome_screen_activation_redirect')) {
			return;
		}

		// Delete the redirect transient.
		delete_transient('_ultimate_cursor_welcome_screen_activation_redirect');

		// Bail if activating from network, or bulk.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (is_network_admin() || isset($_GET['activate-multi'])) {
			return;
		}

		// Redirect to welcome page.
		wp_safe_redirect(admin_url('admin.php?page=ultimate-cursor&sub_page=settings'));
	}

	/**
	 * Register admin menu.
	 *
	 * Add new Ultimate Cursor Settings admin menu.
	 */
	public function register_admin_menu() {
		if (! current_user_can('manage_options')) {
			return;
		}

		add_menu_page(
			esc_html__('Ultimate Cursor', 'ultimate-cursor'),
			esc_html__('Ultimate Cursor', 'ultimate-cursor'),
			'manage_options',
			'ultimate-cursor',
			[$this, 'print_admin_page'],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'data:image/svg+xml;base64,' . base64_encode(file_get_contents(ultimate_cursor()->plugin_path . 'assets/images/admin-icon.svg')),
			'58.7'
		);

		add_submenu_page(
			'ultimate-cursor',
			'',
			esc_html__('Welcome', 'ultimate-cursor'),
			'manage_options',
			'ultimate-cursor'
		);
		add_submenu_page(
			'ultimate-cursor',
			'',
			esc_html__('Settings', 'ultimate-cursor'),
			'manage_options',
			'admin.php?page=ultimate-cursor&sub_page=settings'
		);
		add_submenu_page(
			'ultimate-cursor',
			'',
			esc_html__('Multiple Cursors', 'ultimate-cursor'),
			'manage_options',
			'admin.php?page=ultimate-cursor&sub_page=settings&tab=multiple'
		);
		add_submenu_page(
			'ultimate-cursor',
			'',
			esc_html__('Discussions', 'ultimate-cursor'),
			'manage_options',
			'https://wordpress.org/support/plugin/ultimate-cursor/'
		);
	}

	/**
	 * Print admin page.
	 */
	public function print_admin_page() {
?>
		<div class="ultimate-cursor-admin-root"></div>
<?php
	}

	/**
	 * Add page class to body.
	 *
	 * @param string $classes - body classes.
	 */
	public function admin_body_class($classes) {
		$screen = get_current_screen();

		if ('toplevel_page_ultimate-cursor' !== $screen->id) {
			return $classes;
		}

		$classes .= ' ultimate-cursor-admin-page';

		// Sub page.
		$page_name = 'welcome';

		// phpcs:ignore WordPress.Security.NonceVerification
		if (isset($_GET['sub_page']) && $_GET['sub_page']) {
			// phpcs:ignore WordPress.Security.NonceVerification
			$page_name = esc_attr(sanitize_text_field($_GET['sub_page']));
		}

		$classes .= ' ultimate-cursor-admin-page-' . $page_name;

		// Is first loading after plugin activation redirect.
		// phpcs:ignore WordPress.Security.NonceVerification
		if (isset($_GET['is_first_loading'])) {
			$classes .= ' ultimate-cursor-admin-first-loading';
		}

		return $classes;
	}


	/**
	 * SDK Integration
	 */


	/**
	 * SDK Integration
	 */

	public  function extenderx_sdk_xero_plugin() {
		require_once ultimate_cursor()->plugin_path . 'includes/analytics/init.php';
		wpxero_analytics_init();
	}
}


new Ultimate_Cursor_Admin();
