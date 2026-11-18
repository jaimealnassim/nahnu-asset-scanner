<?php
/**
 * Uninstall: remove all plugin tables and options.
 *
 * On multisite every site has its own tables and options, so each site is cleaned.
 *
 * @package NahnuAS
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Drop the plugin tables and delete its options for the current site.
 */
function nahnuas_uninstall_site(): void {
	global $wpdb;

	$tables = [ 'nahnuas_domains', 'nahnuas_cookies', 'nahnuas_assets', 'nahnuas_pages', 'nahnuas_scans' ];
	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	$options = [
		'nahnuas_db_version',
		'nahnuas_pages_per_scan',
		'nahnuas_include_pages',
		'nahnuas_include_posts',
		'nahnuas_include_products',
		'nahnuas_scan_timeout',
		'nahnuas_custom_urls',
		'nahnuas_selected_post_ids',
	];
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Test runs started by AI agents (short-lived transients).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_nahnuas_run_' ) . '%', $wpdb->esc_like( '_transient_timeout_nahnuas_run_' ) . '%' ) );
}

// Test Mode rules are stored per user (user meta is shared by the whole network).
delete_metadata( 'user', 0, 'nahnuas_test_rules', '', true );

if ( is_multisite() ) {
	foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $nahnuas_site_id ) {
		switch_to_blog( (int) $nahnuas_site_id );
		nahnuas_uninstall_site();
		restore_current_blog();
	}
} else {
	nahnuas_uninstall_site();
}
