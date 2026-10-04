<?php

/**
 * Plugin assets functions.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ultimate_Cursor_Assets constructor.
	 */
	private function __construct() {
		if ( ! is_admin() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_background_assets' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Loads the asset file for the given script or style.
	 * Returns a default if the asset file is not found.
	 *
	 * @param string $filepath The name of the file without the extension.
	 *
	 * @return array The asset file contents.
	 */
	public function get_asset_file( $filepath ) {
		$asset_path = ultimate_cursor()->plugin_path . $filepath . '.asset.php';

		if ( file_exists( $asset_path ) ) {
			return include $asset_path;
		}

		return array(
			'dependencies' => array(),
			'version'      => UCA_VERSION,
		);
	}

	/**
	 * Whether the current frontend request is a page-builder editing canvas
	 * or the Customizer preview.
	 *
	 * Frontend builders render the page on the frontend (is_admin() is false),
	 * so without this the custom cursor — and its `cursor: none` rule — would
	 * load inside the editing canvas and get in the way of selecting, dragging
	 * and saving. A normal "Preview" of a post is NOT an editor and still gets
	 * the cursor.
	 *
	 * @return bool
	 */
	public static function is_editor_context() {
		$is_editor = false;

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			$is_editor = true;
		}

		// Builders that expose an API for "the editor is open on this request".
		if ( ! $is_editor ) {
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
				$is_editor = true;
			} elseif ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
				$is_editor = true;
			} elseif ( class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
				$is_editor = true;
			} elseif ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				$is_editor = true;
			} elseif ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
				$is_editor = true;
			} elseif ( function_exists( 'fusion_is_preview_frame' ) && fusion_is_preview_frame() ) {
				$is_editor = true;
			} elseif ( function_exists( 'fusion_is_builder_frame' ) && fusion_is_builder_frame() ) {
				$is_editor = true;
			}
		}

		// Fallback: the query flags those builders (and a few without a
		// public API) put on their editing canvas URL.
		if ( ! $is_editor ) {
			$editor_flags = array(
				'elementor-preview', // Elementor.
				'et_fb',             // Divi.
				'fl_builder',        // Beaver Builder.
				'bricks',            // Bricks.
				'fb-edit',           // Avada Live.
				'builder',           // Avada Live (canvas frame, with builder_id).
				'vc_editable',       // WPBakery.
				'ct_builder',        // Oxygen.
				'brizy-edit',        // Brizy.
				'brizy-edit-iframe', // Brizy.
				'tve',               // Thrive Architect.
				'breakdance',        // Breakdance.
				'breakdance_iframe', // Breakdance.
			);

			foreach ( $editor_flags as $flag ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presence check of a builder's own URL flag; no data is processed.
				if ( isset( $_GET[ $flag ] ) ) {
					// `builder` alone is too generic — Avada pairs it with builder_id.
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only presence check.
					if ( 'builder' === $flag && ! isset( $_GET['builder_id'] ) ) {
						continue;
					}
					$is_editor = true;
					break;
				}
			}
		}

		/**
		 * Filter whether the current request is treated as an editor canvas,
		 * in which case the cursor and background scripts are not loaded.
		 *
		 * Return false to show the cursor inside a builder; return true to
		 * add support for a builder this list does not know.
		 *
		 * @param bool $is_editor Whether an editor canvas was detected.
		 */
		return (bool) apply_filters( 'ultimate_cursor_is_editor_context', $is_editor );
	}

	/**
	 * Enqueue frontend assets with cache-proof chunk loading
	 */
	public function enqueue_frontend_assets() {
		// Prevent multiple executions
		static $executed = false;
		if ( $executed ) {
			return;
		}
		$executed = true;

		// Stay out of page-builder editing canvases and the Customizer preview.
		if ( self::is_editor_context() ) {
			return;
		}

		$settings   = get_option( 'ultimate_cursor_settings', array() );
		$asset_data = $this->get_asset_file( 'build/frontend' );

		// Only fields (and values) the schema currently knows reach the
		// browser. Anything stored by an add-on that is not registered right
		// now — including values injected straight into the DB — is left out.
		$settings = Ultimate_Cursor_Settings_Schema::filter_for_output( $settings, 'cursor' );

		/**
		 * Filter the cursor settings sent to the current page.
		 *
		 * This plugin shows one cursor across the whole site. An add-on that
		 * targets cursors at specific pages can return false here to keep
		 * the cursor scripts off a page none of its cursors apply to.
		 *
		 * @param array|false $settings Cursor settings for output.
		 */
		$settings = apply_filters( 'ultimate_cursor_frontend_cursor_settings', $settings );
		if ( ! is_array( $settings ) ) {
			return;
		}

		// Normalize enableMultipleCursors to boolean
		$enable_multiple = isset( $settings['enableMultipleCursors'] ) &&
			( $settings['enableMultipleCursors'] === true || $settings['enableMultipleCursors'] === '1' || $settings['enableMultipleCursors'] === 1 );

		// Check if we should load the script
		$should_load = false;

		if ( $enable_multiple ) {
			$should_load = true;
		} elseif ( ( isset( $settings['effect'] ) && $settings['effect'] !== 'none' ) || ( isset( $settings['cursorType'] ) && $settings['cursorType'] !== null ) ) {
			$should_load = true;
		}

		if ( $should_load ) {
			// Get frontend.js file path for cache busting
			$frontend_js_path = ultimate_cursor()->plugin_path . 'build/frontend.js';
			$frontend_js_url  = ultimate_cursor()->plugin_url . 'build/frontend.js';

			// Add filemtime-based cache busting to version
			$version = $asset_data['version'];
			if ( file_exists( $frontend_js_path ) ) {
				$version .= '.' . filemtime( $frontend_js_path );
			}

			// The runtime (React + wp packages + build/frontend.js) is only
			// REGISTERED. What is enqueued is a small dependency-free loader
			// that fetches the runtime in the browser when a cursor will
			// actually render — so touch/mobile visitors with the cursor
			// hidden don't download React for nothing. Registering the runtime
			// keeps its handle available for the translation lookup below.
			wp_register_script(
				'ultimate-cursor-frontend-runtime',
				$frontend_js_url,
				$asset_data['dependencies'],
				$version,
				true
			);

			$loader_asset   = $this->get_asset_file( 'build/frontend-loader' );
			$loader_js_path = ultimate_cursor()->plugin_path . 'build/frontend-loader.js';
			$loader_version = $loader_asset['version'];
			if ( file_exists( $loader_js_path ) ) {
				$loader_version .= '.' . filemtime( $loader_js_path );
			}

			// Keeps the long-standing 'ultimate-cursor-frontend' handle so
			// existing dequeue/exclusion rules still address the cursor.
			wp_enqueue_script(
				'ultimate-cursor-frontend',
				ultimate_cursor()->plugin_url . 'build/frontend-loader.js',
				$loader_asset['dependencies'],
				$loader_version,
				array(
					'in_footer' => true,
					'strategy'  => 'defer', // Defer for optimal loading
				)
			);

			// CRITICAL: Inject public path BEFORE the runtime loads
			// This ensures webpack knows where to load dynamic chunks from
			// even when the main script is cached/minified by WP Rocket, LiteSpeed, etc.
			$public_path_script = sprintf(
				'window.__ultimateCursorPublicPath = %s;',
				wp_json_encode( ultimate_cursor()->plugin_url . 'build/' )
			);

			wp_add_inline_script(
				'ultimate-cursor-frontend',
				$public_path_script,
				'before' // Execute BEFORE the main script
			);

			// Use wp_add_inline_script + wp_json_encode instead of
			// wp_localize_script to preserve data types (numbers, booleans).
			// wp_localize_script casts every scalar to a string, which breaks
			// components that do arithmetic on their settings (e.g. the
			// snowflake cursor's fall speed turned "1" + Math.random() into
			// string concatenation, rendering particles at NaN coordinates).
			$cursor_data_script = sprintf(
				'var ultimateCursorData = %s;',
				wp_json_encode( $settings )
			);

			wp_add_inline_script(
				'ultimate-cursor-frontend',
				$cursor_data_script,
				'before'
			);

			$loader_config = $this->get_runtime_loader_config(
				'ultimate-cursor-frontend-runtime',
				$asset_data['dependencies'],
				$frontend_js_url,
				$version,
				'cursor'
			);

			wp_add_inline_script(
				'ultimate-cursor-frontend',
				sprintf( 'var ultimateCursorLoader = %s;', wp_json_encode( $loader_config ) ),
				'before'
			);
		}
	}

	/**
	 * Build what a frontend loader (cursor or background) needs to fetch its
	 * runtime in the browser: the runtime's dependencies in load order, the
	 * runtime URL, and the JS translations WordPress would have printed inline
	 * had the runtime been enqueued (the wp_set_script_translations()
	 * equivalent for a script that is only registered).
	 *
	 * @param string   $runtime_handle Handle the runtime is registered under.
	 * @param string[] $dependencies   The runtime's dependency handles.
	 * @param string   $runtime_url    Runtime script URL (no version).
	 * @param string   $version        Cache-busting version.
	 * @param string   $context        Which runtime: 'cursor' or 'background'.
	 * @return array { deps, addons, main, i18n }
	 */
	private function get_runtime_loader_config( $runtime_handle, $dependencies, $runtime_url, $version, $context ) {
		$translations = load_script_textdomain(
			$runtime_handle,
			'ultimate-cursor',
			ultimate_cursor()->plugin_path . 'languages'
		);

		return array(
			'deps'   => $this->get_script_dependency_urls( $dependencies ),
			'addons' => $this->get_addon_scripts( $context ),
			'main'   => esc_url_raw(
				apply_filters(
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied so CDN/URL rewriting treats this script like any enqueued one.
					'script_loader_src',
					add_query_arg( 'ver', $version, $runtime_url ),
					$runtime_handle
				)
			),
			'i18n'   => array(
				'domain'     => 'ultimate-cursor',
				'isRtl'      => is_rtl(),
				'localeData' => $translations ? json_decode( $translations, true ) : null,
			),
		);
	}

	/**
	 * Scripts an add-on wants loaded with a frontend runtime: after the shared
	 * dependencies (React, wp-hooks, wp-i18n are available to them) and before
	 * the runtime itself, so components they register through
	 * `@wordpress/hooks` are in place when the runtime starts.
	 *
	 * @param string $context 'cursor' or 'background'.
	 * @return array[] List of { handle, src, global }.
	 */
	private function get_addon_scripts( $context ) {
		/**
		 * Filter the add-on scripts loaded with a frontend runtime.
		 *
		 * @param array[] $scripts List of array( 'handle' => string, 'src' => string ).
		 * @param string  $context 'cursor' or 'background'.
		 */
		$scripts = apply_filters( 'ultimate_cursor_frontend_addon_scripts', array(), $context );
		$clean   = array();

		foreach ( (array) $scripts as $script ) {
			if ( ! is_array( $script ) || empty( $script['handle'] ) || empty( $script['src'] ) ) {
				continue;
			}
			$clean[] = array(
				'handle' => sanitize_key( $script['handle'] ),
				'src'    => esc_url_raw( $script['src'] ),
				'global' => '',
			);
		}

		return $clean;
	}

	/**
	 * Resolve script handles to the URLs the frontend loader must fetch, in
	 * dependency order (each handle's own dependencies first).
	 *
	 * `global` is the window property the script defines; the loader skips a
	 * dependency whose global already exists (the theme or another plugin
	 * enqueued it), so nothing is loaded twice.
	 *
	 * @param string[] $handles Registered script handles.
	 * @return array[] List of { handle, src, global }.
	 */
	private function get_script_dependency_urls( $handles ) {
		$scripts = wp_scripts();
		$globals = array(
			'react'             => 'React',
			'react-dom'         => 'ReactDOM',
			'react-jsx-runtime' => 'ReactJSXRuntime',
			'wp-hooks'          => 'wp.hooks',
			'wp-i18n'           => 'wp.i18n',
		);
		$ordered = array();
		$seen    = array();

		$walk = function ( $handle ) use ( &$walk, &$ordered, &$seen, $scripts, $globals ) {
			if ( isset( $seen[ $handle ] ) || ! isset( $scripts->registered[ $handle ] ) ) {
				return;
			}
			$seen[ $handle ] = true;
			$script          = $scripts->registered[ $handle ];

			foreach ( (array) $script->deps as $dependency ) {
				$walk( $dependency );
			}

			// Alias handles (no file of their own) only group dependencies.
			if ( ! $script->src ) {
				return;
			}

			$src = $script->src;
			if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( $scripts->content_url && 0 === strpos( $src, $scripts->content_url ) ) ) {
				$src = $scripts->base_url . $src;
			}

			if ( null !== $script->ver ) {
				$src = add_query_arg( 'ver', $script->ver ? $script->ver : $scripts->default_version, $src );
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter, applied so CDN/URL rewriting treats these like any printed script.
			$src = apply_filters( 'script_loader_src', $src, $handle );
			if ( ! $src ) {
				return;
			}

			$ordered[] = array(
				'handle' => $handle,
				'src'    => esc_url_raw( $src ),
				'global' => isset( $globals[ $handle ] ) ? $globals[ $handle ] : '',
			);
		};

		foreach ( (array) $handles as $handle ) {
			$walk( $handle );
		}

		return $ordered;
	}


	/**
	 * Enqueue frontend background animation assets with performance-first approach.
	 * Three.js is lazy-loaded only when background animation is enabled.
	 */
	public function enqueue_frontend_background_assets() {
		// Prevent multiple executions
		static $executed = false;
		if ( $executed ) {
			return;
		}
		$executed = true;

		// Stay out of page-builder editing canvases and the Customizer preview.
		if ( self::is_editor_context() ) {
			return;
		}

		$bg_settings = get_option( 'ultimate_cursor_background_settings', array() );

		// Only fields (and values) the schema currently knows reach the browser.
		$bg_settings = Ultimate_Cursor_Settings_Schema::filter_for_output( $bg_settings, 'background' );

		// Only load if background animation is enabled
		if ( empty( $bg_settings['enabled'] ) ) {
			return;
		}

		/**
		 * Filter the background settings sent to the current page.
		 *
		 * This plugin shows one background across the whole site. An add-on
		 * that targets backgrounds at specific pages can drop the ones that
		 * do not apply here, or return false to load nothing on this page.
		 *
		 * @param array|false $bg_settings Background settings for output.
		 */
		$bg_settings = apply_filters( 'ultimate_cursor_frontend_background_settings', $bg_settings );
		if ( ! is_array( $bg_settings ) || empty( $bg_settings['enabled'] ) ) {
			return;
		}

		$asset_data = $this->get_asset_file( 'build/frontend-background' );

		$bg_js_path = ultimate_cursor()->plugin_path . 'build/frontend-background.js';
		$bg_js_url  = ultimate_cursor()->plugin_url . 'build/frontend-background.js';

		// Add filemtime-based cache busting
		$version = $asset_data['version'];
		if ( file_exists( $bg_js_path ) ) {
			$version .= '.' . filemtime( $bg_js_path );
		}

		// Same arrangement as the cursor: the runtime (React + wp packages +
		// build/frontend-background.js) is only REGISTERED; a small
		// dependency-free loader is enqueued and fetches the runtime in the
		// browser when a background will actually render (not under reduced
		// motion, and only if a selector-targeted background has a match).
		wp_register_script(
			'ultimate-cursor-frontend-background-runtime',
			$bg_js_url,
			$asset_data['dependencies'],
			$version,
			true
		);

		$loader_asset   = $this->get_asset_file( 'build/frontend-background-loader' );
		$loader_js_path = ultimate_cursor()->plugin_path . 'build/frontend-background-loader.js';
		$loader_version = $loader_asset['version'];
		if ( file_exists( $loader_js_path ) ) {
			$loader_version .= '.' . filemtime( $loader_js_path );
		}

		// Keeps the long-standing handle so existing dequeue/exclusion rules
		// still address the background.
		wp_enqueue_script(
			'ultimate-cursor-frontend-background',
			ultimate_cursor()->plugin_url . 'build/frontend-background-loader.js',
			$loader_asset['dependencies'],
			$loader_version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Inject public path for chunk loading
		$public_path_script = sprintf(
			'window.__ultimateCursorBgPublicPath = %s;',
			wp_json_encode( ultimate_cursor()->plugin_url . 'build/' )
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
			wp_json_encode( $bg_settings )
		);

		wp_add_inline_script(
			'ultimate-cursor-frontend-background',
			$bg_data_script,
			'before'
		);

		$bg_loader_config = $this->get_runtime_loader_config(
			'ultimate-cursor-frontend-background-runtime',
			$asset_data['dependencies'],
			$bg_js_url,
			$version,
			'background'
		);

		/**
		 * Filter CSS selectors of which at least one must match an element
		 * for the background runtime to be downloaded. Empty (the default)
		 * means no such requirement. For add-ons that render backgrounds
		 * only inside specific elements.
		 *
		 * @param string[] $selectors   CSS selectors.
		 * @param array    $bg_settings Background settings for output.
		 */
		$required_selectors = apply_filters( 'ultimate_cursor_background_required_selectors', array(), $bg_settings );
		if ( is_array( $required_selectors ) && ! empty( $required_selectors ) ) {
			$bg_loader_config['requireAny'] = array_values( array_filter( array_map( 'strval', $required_selectors ) ) );
		}

		wp_add_inline_script(
			'ultimate-cursor-frontend-background',
			sprintf( 'var ultimateCursorBgLoader = %s;', wp_json_encode( $bg_loader_config ) ),
			'before'
		);
	}

	/**
	 * Enqueue admin pages assets.
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen || 'toplevel_page_ultimate-cursor' !== $screen->id ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', '.php-error #adminmenuback, .php-error #adminmenuwrap { margin-top: 0px !important; }' );

		$asset_data = $this->get_asset_file( 'build/admin' );

		wp_enqueue_script(
			'ultimate-cursor-admin',
			ultimate_cursor()->plugin_url . 'build/admin.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		// Load JS translations so the React dashboard's __() strings are translatable.
		wp_set_script_translations(
			'ultimate-cursor-admin',
			'ultimate-cursor',
			ultimate_cursor()->plugin_path . 'languages'
		);

		// Pass the cursor images
		$cursor_images = array();
		$cursor_shapes = array();

		$cursor_dir        = ultimate_cursor()->plugin_path . 'assets/cursors/';
		$cursor_url        = ultimate_cursor()->plugin_url . 'assets/cursors/';
		$cursor_shapes_dir = ultimate_cursor()->plugin_path . 'assets/shapes/';
		$cursor_shapes_url = ultimate_cursor()->plugin_url . 'assets/shapes/';

		$extensions = array( 'png', 'jpg', 'jpeg', 'gif', 'svg', 'cur' );

		foreach ( $extensions as $ext ) {
			$files = glob( $cursor_dir . '*.' . $ext );
			if ( $files ) {
				foreach ( $files as $file ) {
					$cursor_images[] = $cursor_url . basename( $file );
				}
			}
		}

		foreach ( $extensions as $ext ) {
			$files = glob( $cursor_shapes_dir . '*.' . $ext );
			if ( $files ) {
				foreach ( $files as $file ) {
					$cursor_shapes[] = $cursor_shapes_url . basename( $file );
				}
			}
		}

		// Use wp_add_inline_script + wp_json_encode instead of wp_localize_script:
		// localize casts top-level scalars to strings ('isPro' => "1"/""), and the
		// dashboard treats these as real booleans. Same rule as the frontend paths.
		$admin_data = array(
				// Same output filter as the frontend: the dashboard only ever
				// sees fields the schema currently knows.
				'settings'           => Ultimate_Cursor_Settings_Schema::filter_for_output(
					get_option( 'ultimate_cursor_settings', array() ),
					'cursor'
				),
				'backgroundSettings' => Ultimate_Cursor_Settings_Schema::filter_for_output(
					get_option( 'ultimate_cursor_background_settings', array() ),
					'background'
				),
				'cursors'            => $cursor_images,
				'plugin_url'         => ultimate_cursor()->plugin_url,
				'version'            => UCA_VERSION,
				/**
				 * Filter the shape thumbnails offered in the dashboard.
				 *
				 * The free plugin ships shapes 1–5. An add-on appends the URLs
				 * of the shapes it provides (file name = shape id, e.g. 12.svg).
				 *
				 * @param string[] $cursor_shapes Shape image URLs.
				 */
				'shapes'             => array_values( array_filter( (array) apply_filters( 'ultimate_cursor_admin_shapes', $cursor_shapes ), 'is_string' ) ),
				// Reported by the Pro add-on (see UltimateCursor::is_premium_active()).
				'isPro'              => UltimateCursor::is_premium_active(),
				'isLicenseValid'     => UltimateCursor::is_premium_active(),
				// Untagged base — src/utils/pro-url.js adds the per-placement UTM tags.
				'proUrl'             => UltimateCursor::PRO_URL,
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'ultimate_cursor_admin_nonce' ),
				// For the one-time review request (src/admin/components/review-prompt.js).
				'installedAt'        => UltimateCursor::get_installed_at(),
				'reviewDismissed'    => (bool) get_user_meta( get_current_user_id(), 'ultimate_cursor_review_dismissed', true ),
				// For the one-time opt-in invitation ('' = not applicable).
				'optInUrl'           => esc_url_raw( ultimate_cursor()->get_opt_in_url() ),
				'optInDismissed'     => (bool) get_user_meta( get_current_user_id(), 'ultimate_cursor_optin_dismissed', true ),
				'activePlugins'      => ( function () {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
					$active = get_option( 'active_plugins', array() );
					if ( is_multisite() ) {
						$active = array_merge( $active, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
					}
					$slugs = array();
					foreach ( $active as $plugin ) {
						$dirname = dirname( $plugin );
						if ( $dirname !== '.' ) {
							$slugs[] = $dirname;
						}
					}
					return $slugs;
				} )(),
		);

		$encoded = wp_json_encode( $admin_data );
		wp_add_inline_script(
			'ultimate-cursor-admin',
			sprintf( 'var ultimateCursorAdminData = %s;', $encoded !== false ? $encoded : '{}' ),
			'before'
		);

		wp_enqueue_style(
			'ultimate-cursor-admin',
			ultimate_cursor()->plugin_url . 'build/style-admin.css',
			array(),
			$asset_data['version']
		);

		// RTL locales: swap in the rtlcss-generated stylesheet the build emits.
		wp_style_add_data( 'ultimate-cursor-admin', 'rtl', 'replace' );

		// @wordpress/scripts splits stylesheets: `style.scss` imports land in
		// build/style-admin.css (above), while any other-named CSS import
		// (e.g. the control kit's kit.scss) is emitted to build/admin.css.
		// Enqueue it too so those component styles actually load.
		$admin_css = ultimate_cursor()->plugin_path . 'build/admin.css';
		if ( file_exists( $admin_css ) ) {
			wp_enqueue_style(
				'ultimate-cursor-admin-components',
				ultimate_cursor()->plugin_url . 'build/admin.css',
				array( 'ultimate-cursor-admin' ),
				$asset_data['version']
			);
			wp_style_add_data( 'ultimate-cursor-admin-components', 'rtl', 'replace' );
		}

		wp_enqueue_style( 'wp-components' );
	}
}

Ultimate_Cursor_Assets::instance();
