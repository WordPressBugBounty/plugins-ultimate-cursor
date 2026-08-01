<?php

/**
 * Plugin admin functions.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Cursor Admin class.
 */
class Ultimate_Cursor_Admin {
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ultimate_Cursor_Admin constructor.
	 */
	private function __construct() {
		add_action( 'admin_init', array( $this, 'redirect_to_welcome_screen' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
		add_action( 'in_admin_header', array( $this, 'disable_admin_notices' ), PHP_INT_MAX );

		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		// Enqueue media uploader
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_uploader' ) );

		add_filter( 'plugin_action_links_ultimate-cursor/ultimate-cursor.php', array( $this, 'ultimate_cursor_settings_link' ) );
		add_action( 'wp_ajax_ultimate_cursor_install_plugin', array( $this, 'ajax_install_plugin' ) );

		// Registered on 'admin_init' (same hook the Freemius SDK uses for its own
		// action links) — 'ultimate_cursor_fs_loaded' fires during 'plugins_loaded',
		// too early to call __() without tripping the _load_textdomain_just_in_time notice.
		add_action( 'admin_init', array( $this, 'add_promotional_action_link' ) );
	}


	public function disable_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple page check, not processing form data
		if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'ultimate-cursor' ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
		}
	}
	public function ultimate_cursor_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=ultimate-cursor&sub_page=settings' ) ) . '">' . esc_html__( 'Settings', 'ultimate-cursor' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Add an "Upgrade to Pro" promotional link to the plugin's row actions on
	 * wp-admin/plugins.php, via the Freemius SDK so it merges correctly with
	 * the SDK's own action links. Styled inline since add_plugin_action_link()
	 * renders the label raw with no class hook to target from CSS.
	 */
	public function add_promotional_action_link() {
		if ( Ultimate_Cursor_License_Gate::is_premium_active() ) {
			return;
		}

		$fs = ultimate_cursor_fs();
		if ( ! $fs ) {
			return;
		}

		// WP core's standard admin green — Freemius's own submenu "Upgrade" green
		// (.fs-submenu-item.pricing.upgrade-mode, #adff2f) is tuned for the dark
		// admin-menu background and washes out on the light plugin row here.
		$label = '<span style="color:#00a32a;font-weight:600;">' . esc_html__( 'Upgrade to Pro', 'ultimate-cursor' ) . '</span>';

		// Live pricing page, not $fs->get_upgrade_url() (that resolves to the local
		// in-dashboard checkout URL). Mirrors Ultimate_Cursor_Dashboard_Widget::PRICING_URL —
		// not referenced directly since that class isn't loaded when the pro plugin is active.
		$fs->add_plugin_action_link(
			$label,
			esc_url( 'https://wpxero.com/plugins/ultimate-cursor/pricing' ),
			true,
			7,
			'get-pro'
		);
	}

	public function enqueue_media_uploader() {
		// Only load the (heavy) media library JS on our own settings screen,
		// not across the entire wp-admin.
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_ultimate-cursor' !== $screen->id ) {
			return;
		}
		wp_enqueue_media();
	}

	public function ajax_install_plugin() {
		check_ajax_referer( 'ultimate_cursor_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( __( 'You do not have permission to install plugins.', 'ultimate-cursor' ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		if ( empty( $slug ) ) {
			wp_send_json_error( __( 'No plugin slug provided.', 'ultimate-cursor' ) );
		}

		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		// Check if already installed
		$plugin_file = $this->get_plugin_file( $slug );

		if ( ! $plugin_file ) {
			// Needs installation
			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
			if ( is_wp_error( $api ) ) {
				wp_send_json_error( $api->get_error_message() );
			}

			$status = install_plugin_install_status( $api );
			if ( $status['status'] === 'install' || $status['status'] === 'update_available' ) {
				$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
				$result   = $upgrader->install( $api->download_link );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( $result->get_error_message() );
				} elseif ( $result === false ) {
					wp_send_json_error( __( 'Installation failed.', 'ultimate-cursor' ) );
				}
			}

			// Find installed file
			$plugin_file = $this->get_plugin_file( $slug );
		}

		if ( $plugin_file ) {
			if ( ! is_plugin_active( $plugin_file ) ) {
				$activate = activate_plugin( $plugin_file );
				if ( is_wp_error( $activate ) ) {
					wp_send_json_error( $activate->get_error_message() );
				}
			}
			wp_send_json_success( array( 'message' => __( 'Plugin installed and activated successfully!', 'ultimate-cursor' ) ) );
		}

		wp_send_json_error( __( 'Could not locate the plugin file after installation.', 'ultimate-cursor' ) );
	}

	private function get_plugin_file( $slug ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		foreach ( $plugins as $plugin_path => $plugin_data ) {
			if ( strpos( $plugin_path, $slug . '/' ) === 0 ) {
				return $plugin_path;
			}
		}
		return false;
	}

	/**
	 * Redirect to Welcome page after activation.
	 */
	public function redirect_to_welcome_screen() {
		// Bail if no activation redirect.
		if ( ! get_transient( '_ultimate_cursor_welcome_screen_activation_redirect' ) ) {
			return;
		}

		// Delete the redirect transient.
		delete_transient( '_ultimate_cursor_welcome_screen_activation_redirect' );

		// Bail if activating from network, or bulk.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Redirect to welcome page.
		wp_safe_redirect( admin_url( 'admin.php?page=ultimate-cursor&sub_page=settings' ) );
		exit;
	}

	/**
	 * Register admin menu.
	 *
	 * Add new Ultimate Cursor Settings admin menu.
	 */
	public function register_admin_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			esc_html__( 'Ultimate Cursor', 'ultimate-cursor' ),
			esc_html__( 'Ultimate Cursor', 'ultimate-cursor' ),
			'manage_options',
			'ultimate-cursor',
			array( $this, 'print_admin_page' ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( ultimate_cursor()->plugin_path . 'assets/images/admin-icon.svg' ) ),
			'58.7'
		);

		// Labels intentionally match the in-app header navigation
		// (src/admin/pages/index.js) so both menus read the same. The
		// top-level slug IS the Cursors page (default once configured) —
		// there is no separate Dashboard overview.
		add_submenu_page(
			'ultimate-cursor',
			esc_html__( 'Cursors', 'ultimate-cursor' ),
			esc_html__( 'Cursors', 'ultimate-cursor' ),
			'manage_options',
			'ultimate-cursor'
		);
		add_submenu_page(
			'ultimate-cursor',
			esc_html__( 'Background', 'ultimate-cursor' ),
			esc_html__( 'Background', 'ultimate-cursor' ),
			'manage_options',
			'admin.php?page=ultimate-cursor&sub_page=background'
		);
		add_submenu_page(
			'ultimate-cursor',
			esc_html__( 'Support', 'ultimate-cursor' ),
			esc_html__( 'Support', 'ultimate-cursor' ),
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
	public function admin_body_class( $classes ) {
		$screen = get_current_screen();

		if ( ! $screen || 'toplevel_page_ultimate-cursor' !== $screen->id ) {
			return $classes;
		}

		$classes .= ' ultimate-cursor-admin-page';

		// Sub page.
		$page_name = 'welcome';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple page check, not processing form data
		if ( isset( $_GET['sub_page'] ) && sanitize_text_field( wp_unslash( $_GET['sub_page'] ) ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple page check, not processing form data
			$page_name = esc_attr( sanitize_text_field( wp_unslash( $_GET['sub_page'] ) ) );
		}

		$classes .= ' ultimate-cursor-admin-page-' . $page_name;

		// Is first loading after plugin activation redirect.
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['is_first_loading'] ) ) {
			$classes .= ' ultimate-cursor-admin-first-loading';
		}

		return $classes;
	}
}


Ultimate_Cursor_Admin::instance();
