<?php

/**
 * Uninstall Ultimate Cursor
 *
 * This file runs when the plugin is deleted (uninstalled) from WordPress.
 * It cleans up all plugin data from the database.
 *
 * @package ultimate-cursor
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Fire the Freemius uninstall event so the dashboard sees this uninstall.
 *
 * WordPress core's uninstall_plugin() includes this file INSTEAD OF firing the
 * 'uninstall_{plugin}' action (see wp-admin/includes/plugin.php) whenever a custom
 * uninstall.php exists — so the uninstall hook the Freemius SDK registers via
 * register_uninstall_hook() on deactivation never runs, and _uninstall_plugin_event()
 * is never called. Re-initializing the SDK here and calling it directly is the
 * documented Freemius workaround for plugins with their own uninstall.php.
 */
function ultimate_cursor_fire_freemius_uninstall_event() {
	if ( ! file_exists( __DIR__ . '/vendor/freemius/wordpress-sdk/start.php' ) ) {
		return;
	}

	require_once __DIR__ . '/vendor/freemius/wordpress-sdk/start.php';

	if ( ! function_exists( 'fs_dynamic_init' ) ) {
		return;
	}

	$fs = fs_dynamic_init(
		array(
			'id'               => '19720',
			'slug'             => 'ultimate-cursor',
			'premium_slug'     => 'ultimate-cursor-pro',
			'type'             => 'plugin',
			'public_key'       => 'pk_fb94765a4f619e83979c2825626c2',
			'is_premium'       => false,
			'is_premium_only'  => false,
			'has_paid_plans'   => true,
			'is_live'          => true,
			'is_org_compliant' => true,
		)
	);

	if ( ! is_object( $fs ) ) {
		return;
	}

	// Don't fire when the sibling version (premium) is still active — this delete
	// is a version swap, not a real uninstall. Mirrors Freemius::_uninstall_plugin_hook().
	if (
		is_plugin_active( $fs->get_plugin_basename() ) ||
		is_plugin_active( $fs->premium_plugin_basename() )
	) {
		return;
	}

	$fs->_uninstall_plugin_event();
}
ultimate_cursor_fire_freemius_uninstall_event();

/**
 * Delete all plugin data for a single site.
 */
function ultimate_cursor_delete_site_data() {
	delete_option( 'ultimate_cursor_settings' );
	delete_option( 'ultimate_cursor_background_settings' );
	delete_transient( '_ultimate_cursor_welcome_screen_activation_redirect' );

	// Remove per-user promo-dismissal meta (keys: uc_dismissed_promo_*).
	delete_metadata( 'user', 0, 'uc_dismissed_promo_widget', '', true );
	delete_metadata( 'user', 0, 'uc_dismissed_promo_notice', '', true );
}

// Delete plugin data for the current site.
ultimate_cursor_delete_site_data();

// For multisite installations, delete options from all sites
if ( is_multisite() ) {
	// Get all blog IDs via the core API (avoids a direct, uncached DB query).
	$ultimate_cursor_blog_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $ultimate_cursor_blog_ids as $ultimate_cursor_blog_id ) {
		switch_to_blog( $ultimate_cursor_blog_id );
		ultimate_cursor_delete_site_data();
		restore_current_blog();
	}
}

// Note: We don't delete posts created by the plugin, as those might be
// important data the user wants to keep.
