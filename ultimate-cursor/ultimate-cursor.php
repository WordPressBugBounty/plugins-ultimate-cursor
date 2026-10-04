<?php

/**
 * Plugin Name:                 Ultimate Cursor – Interactive and Animated Custom Cursor and Background Effects Toolkit
 * Plugin URI:                  https://wpxero.com/plugins/ultimate-cursor
 * Description:                 Make Your Website Stand Out with Unique Cursor Effects and Smooth Animations!🚀
 * Version:                     2.5.0
 * Author:                      WPXERO
 * Author URI:                  https://wpxero.com/plugins/ultimate-cursor
 * Requires at least:           6.0
 * Requires PHP:                7.4
 * License:                     GPL3
 * License URI:                 http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:                 ultimate-cursor
 * Domain Path:                 /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'UCA_VERSION' ) ) {
	define( 'UCA_VERSION', '2.5.0' );
}



/**
 * UltimateCursor Class
 */
class UltimateCursor {
	/**
	 * Freemius instance
	 *
	 * @var object
	 */
	private $freemius;
	/**
	 * The single class instance.
	 *
	 * @var $instance
	 */
	private static $instance        = null;
	const VERSION                   = UCA_VERSION;
	const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
	const MINIMUM_PHP_VERSION       = '7.0';
	const PRO_URL                   = 'https://wpxero.com/plugins/ultimate-cursor/pricing';

	/**
	 * Build the pricing URL for a given placement.
	 *
	 * Every upgrade link goes through here (or its JS mirror,
	 * src/utils/pro-url.js) so each touchpoint carries its own campaign tag.
	 * These are plain query parameters on a link the user chooses to open —
	 * nothing is requested or sent from inside WordPress.
	 *
	 * @param string $campaign Short name of the UI spot (e.g. 'plugins-row-link').
	 * @param string $medium   Surface the link lives on.
	 * @return string Pricing URL tagged with utm_source / utm_medium / utm_campaign.
	 */
	public static function get_pro_url( $campaign, $medium = 'dashboard' ) {
		return add_query_arg(
			array(
				'utm_source'   => 'plugin',
				'utm_medium'   => sanitize_key( $medium ),
				'utm_campaign' => sanitize_key( $campaign ),
			),
			self::PRO_URL
		);
	}

	/**
	 * Main Instance
	 * Ensures only one instance of this class exists in memory at any one time.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	/**
	 * Path to the plugin directory
	 *
	 * @var $plugin_path
	 */
	public $plugin_path;

	/**
	 * URL to the plugin directory
	 *
	 * @var $plugin_url
	 */
	public $plugin_url;
	public $minimum_elementor_version;
	public $minimum_php_version;

	/**
	 * Ultimate Cursor constructor.
	 */
	public function __construct() {
		/* We do nothing here! */
	}

	/**
	 * Init options
	 */
	public function init() {
		$this->plugin_path               = plugin_dir_path( __FILE__ );
		$this->plugin_url                = plugin_dir_url( __FILE__ );
		$this->minimum_elementor_version = self::MINIMUM_ELEMENTOR_VERSION;
		$this->minimum_php_version       = self::MINIMUM_PHP_VERSION;

		// include helper files.
		$this->include_dependencies();
		$this->init_freemius();
	}

	/**
	 * Initialize Freemius SDK
	 */
	private function init_freemius() {
		if ( ! isset( $this->freemius ) ) {
			// Skip Freemius init during plugin upgrade/install to prevent memory exhaustion.
			if (
				( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) ||
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the core upgrader action to skip Freemius init, no data is processed.
				( isset( $_REQUEST['action'] ) && in_array( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ), array( 'upload-plugin', 'update-plugin', 'delete-plugin' ), true ) )
			) {
				return $this->freemius;
			}

			// Include Freemius SDK
			if ( file_exists( __DIR__ . '/vendor/freemius/wordpress-sdk/start.php' ) ) {
				require_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';

				try {
					$this->freemius = fs_dynamic_init(
						array(
							'id'                  => '19720',
							'slug'                => 'ultimate-cursor',
							'premium_slug'        => 'ultimate-cursor-pro',
							'type'                => 'plugin',
							'public_key'          => 'pk_fb94765a4f619e83979c2825626c2',
							'is_premium'          => false,
							'is_premium_only'     => false,
							'has_paid_plans'      => true,
							'is_live'             => true,
							'is_org_compliant'    => true,
							'parallel_activation' => array(
								'enabled'                  => true,
								'premium_version_basename' => 'ultimate-cursor-pro/ultimate-cursor-pro.php',
							),
							'menu'                => array(
								'slug'       => 'ultimate-cursor',
								'first-path' => 'admin.php?page=ultimate-cursor',
								'support'    => false,
								'contact'    => false,
								'pricing'    => true,
							),
						)
					);

					$this->skip_first_run_opt_in();

					// Signal that Freemius SDK is initiated
					do_action( 'ultimate_cursor_fs_loaded' );
				} catch ( Exception $e ) {
					// Log error but don't break the plugin
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Error logging gated behind WP_DEBUG for diagnostics only.
						error_log( 'Ultimate Cursor Freemius Error: ' . $e->getMessage() );
					}
				}
			}
		}

		return $this->freemius;
	}


	/**
	 * Don't put the Freemius opt-in screen in front of the plugin.
	 *
	 * By default the SDK replaces the plugin's page with its opt-in screen
	 * (and adds a "one step away" admin notice) until the user answers. For a
	 * user who has not answered yet, record the same choice the "Skip" button
	 * makes — a local flag only; nothing is sent to Freemius — so the first
	 * screen is the product. Opt-in stays available and explicit: through the
	 * SDK's "Opt In" link on the Plugins screen, and through a one-time
	 * invitation in the dashboard after a week of use
	 * (src/admin/components/review-prompt.js → OptInPrompt).
	 *
	 * Not applied while the Pro add-on is active: a buyer needs the opt-in /
	 * license screen to activate their license.
	 */
	private function skip_first_run_opt_in() {
		$fs = $this->freemius;

		if ( ! is_object( $fs ) || class_exists( 'Ultimate_Cursor_Pro' ) ) {
			return;
		}

		/**
		 * Filter whether the Freemius opt-in screen is skipped on first run.
		 *
		 * @param bool $skip Whether to skip. Default true.
		 */
		if ( ! apply_filters( 'ultimate_cursor_skip_first_run_opt_in', true ) ) {
			return;
		}

		if (
			! method_exists( $fs, 'skip_connection' ) ||
			$fs->is_registered() ||
			$fs->is_anonymous() ||
			$fs->is_pending_activation()
		) {
			return;
		}

		$fs->skip_connection();
	}

	/**
	 * URL of the Freemius opt-in screen for a user who has not opted in, or
	 * '' when opting in is not applicable (already connected, Pro active, or
	 * the SDK is unavailable).
	 *
	 * @return string
	 */
	public function get_opt_in_url() {
		$fs = $this->freemius;

		if (
			! is_object( $fs ) ||
			class_exists( 'Ultimate_Cursor_Pro' ) ||
			! method_exists( $fs, 'get_reconnect_url' ) ||
			$fs->is_registered() ||
			! $fs->is_anonymous()
		) {
			return '';
		}

		return $fs->get_reconnect_url();
	}

	/**
	 * Get Freemius instance
	 *
	 * @return object|null
	 */
	public function get_freemius() {
		return $this->freemius;
	}



	/**
	 * Timestamp of the plugin's first activation on this site.
	 *
	 * Stored once and never updated (survives deactivate/reactivate). Used to
	 * hold back upgrade promotion until the user has had time with the plugin.
	 * Sites that were already set up before this was tracked have no known
	 * install date; they are recorded as 1 ("installed long ago").
	 *
	 * @return int Unix timestamp, or 1 when the install predates tracking.
	 */
	public static function get_installed_at() {
		$installed_at = get_option( 'ultimate_cursor_installed_at' );

		if ( false === $installed_at ) {
			$has_existing_setup = false !== get_option( 'ultimate_cursor_settings' )
				|| false !== get_option( 'ultimate_cursor_background_settings' );
			$installed_at       = $has_existing_setup ? 1 : time();
			add_option( 'ultimate_cursor_installed_at', $installed_at, '', false );
		}

		return (int) $installed_at;
	}

	/**
	 * Whether an add-on reports that Pro features are available.
	 *
	 * The free plugin does not check licenses. The Pro add-on answers this
	 * filter (after doing its own license check) and registers its fields
	 * with Ultimate_Cursor_Settings_Schema. Used here only for presentation:
	 * hiding upgrade prompts and the Pro Features page.
	 *
	 * @return bool
	 */
	public static function is_premium_active() {
		/**
		 * Filter whether Pro features are available.
		 *
		 * @param bool $active Default false.
		 */
		return (bool) apply_filters( 'ultimate_cursor_is_premium_active', false );
	}

	/**
	 * Deprecated: the free plugin no longer keeps a list of premium keys.
	 *
	 * @deprecated Add-on fields are registered through the
	 *             'ultimate_cursor_cursor_field_manifest' filter.
	 * @return array Always empty.
	 */
	public static function get_premium_setting_keys() {
		return array();
	}

	/**
	 * Deprecated: the free plugin no longer keeps a list of premium values.
	 *
	 * @deprecated See Ultimate_Cursor_Settings_Schema::get_allowed_values().
	 * @return array Always empty.
	 */
	public static function get_premium_setting_values() {
		return array();
	}

	/**
	 * Deprecated alias of Ultimate_Cursor_Settings_Schema::filter_for_output().
	 *
	 * @deprecated
	 * @param array $settings The settings array to filter.
	 * @return array Settings limited to currently registered fields.
	 */
	public static function sanitize_premium_settings( $settings ) {
		return Ultimate_Cursor_Settings_Schema::filter_for_output( $settings, 'cursor' );
	}

	/**
	 * Include dependencies
	 */
	private function include_dependencies() {
		// Settings schema (field allowlist for REST writes and browser output)
		// must load first — admin/assets/rest all depend on it.
		require_once $this->plugin_path . 'classes/class-settings-schema.php';
		require_once $this->plugin_path . 'classes/class-admin.php';
		require_once $this->plugin_path . 'classes/class-assets.php';
		require_once $this->plugin_path . 'classes/class-rest.php';

		// CRITICAL: Handles CDN CORS headers and prevents "Delay JS" from breaking the cursor
		// Do not remove this unless you want to break compatibility with WP Rocket, LiteSpeed, etc.
		require_once $this->plugin_path . 'classes/class-cache-compatibility.php';
		if ( did_action( 'elementor/loaded' ) ) {
			require_once $this->plugin_path . 'classes/class-elementor.php';
		}

		// Decides at render time whether to show anything (it stays silent
		// when an add-on reports Pro features are available).
		require_once $this->plugin_path . 'classes/class-promo-notice.php';

		// Promotional widget on the WordPress Dashboard (also silent while an
		// add-on reports Pro features are available).
		require_once $this->plugin_path . 'classes/class-dashboard-widget.php';
	}

	/**
	 * Activation Hook
	 */
	public function activation_hook() {
		// Welcome Page Flag.
		set_transient( '_ultimate_cursor_welcome_screen_activation_redirect', true, 30 );
		// Record the first-activation time (no-op on later activations).
		self::get_installed_at();
	}

	/**
	 * Deactivation Hook
	 * Note: We only clean up temporary data here.
	 * Settings are preserved so users don't lose configuration when deactivating/reactivating.
	 */
	public function deactivation_hook() {
		delete_transient( '_ultimate_cursor_welcome_screen_activation_redirect' );
		// Settings are intentionally NOT deleted here - they persist through deactivation
		// Settings will only be deleted if user uninstalls (deletes) the plugin via uninstall.php
	}
}

/**
 * Function works with the Loader class instance
 *
 * @return object UltimateCursor
 */
function ultimate_cursor() {
	return UltimateCursor::instance();
}

add_action( 'plugins_loaded', 'ultimate_cursor' );

/**
 * Get Freemius instance for free plugin
 *
 * @return object|null
 */
function ultimate_cursor_fs() {
	$plugin = ultimate_cursor();
	return $plugin ? $plugin->get_freemius() : null;
}

/**
 * Activation hook callback
 */
function ultimate_cursor_activation_hook() {
	ultimate_cursor()->activation_hook();
}

/**
 * Deactivation hook callback
 */
function ultimate_cursor_deactivation_hook() {
	ultimate_cursor()->deactivation_hook();
}

register_activation_hook( __FILE__, 'ultimate_cursor_activation_hook' );
register_deactivation_hook( __FILE__, 'ultimate_cursor_deactivation_hook' );
