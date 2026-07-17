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
