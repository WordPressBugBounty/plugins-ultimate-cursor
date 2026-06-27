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
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Delete plugin options
delete_option('ultimate_cursor_settings');
delete_option('ultimate_cursor_background_settings');

// Delete any transients
delete_transient('_ultimate_cursor_welcome_screen_activation_redirect');

// For multisite installations, delete options from all sites
if (is_multisite()) {
	// Get all blog IDs via the core API (avoids a direct, uncached DB query).
	$ultimate_cursor_blog_ids = get_sites(array(
		'fields' => 'ids',
		'number' => 0,
	));

	foreach ($ultimate_cursor_blog_ids as $ultimate_cursor_blog_id) {
		switch_to_blog($ultimate_cursor_blog_id);

		// Delete options for this site
		delete_option('ultimate_cursor_settings');
		delete_option('ultimate_cursor_background_settings');
		delete_transient('_ultimate_cursor_welcome_screen_activation_redirect');

		restore_current_blog();
	}
}

// Note: We don't delete user meta or posts created by the plugin
// as those might be important data the user wants to keep
