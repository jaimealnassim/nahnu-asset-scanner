<?php
/**
 * Uninstall: remove all plugin data from the database.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [ 'nahnuas_domains', 'nahnuas_cookies', 'nahnuas_assets', 'nahnuas_pages', 'nahnuas_scans' ];
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
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
foreach ( $options as $opt ) {
	delete_option( $opt );
}
