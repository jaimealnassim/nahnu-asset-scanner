<?php
/**
 * Plugin Name:       Nahnu Asset Scanner
 * Plugin URI:        https://frontendassetscanner.com
 * Description:       Scan your site's front end for JavaScript, CSS, cookies and external domains, then safely test turning scripts and styles off, deferring or delaying them before you change your cache plugin.
 * Version:           1.0.2
 * Requires at least: 7.1
 * Requires PHP:      8.3
 * Author:            Nahnu Plugins
 * Author URI:        https://www.nahnuplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nahnu-asset-scanner
 * Domain Path:       /languages
 *
 * @package NahnuAS
 */

namespace NahnuAS;

defined( 'ABSPATH' ) || exit;

define( 'NAHNUAS_VERSION', '1.0.2' );
define( 'NAHNUAS_FILE', __FILE__ );
define( 'NAHNUAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAHNUAS_URL', plugin_dir_url( __FILE__ ) );

require_once NAHNUAS_DIR . 'includes/class-db.php';
require_once NAHNUAS_DIR . 'includes/class-scanner.php';
require_once NAHNUAS_DIR . 'includes/class-admin.php';
require_once NAHNUAS_DIR . 'includes/class-plugin-map.php';
require_once NAHNUAS_DIR . 'includes/class-plugin-cookies.php';
require_once NAHNUAS_DIR . 'includes/class-test-mode.php';
require_once NAHNUAS_DIR . 'includes/class-abilities.php';

register_activation_hook( __FILE__, [ DB::class, 'install' ] );

/*
 * Run dbDelta when the stored schema version differs from the plugin version.
 * The activation hook does not fire on updates (including automatic updates),
 * so this keeps the tables current after every upgrade. It is a single
 * autoloaded option read on admin requests.
 */
add_action( 'admin_init', static function (): void {
	if ( get_option( 'nahnuas_db_version' ) !== NAHNUAS_VERSION ) {
		DB::install();
	}
} );

// Privacy policy guide text.
add_action( 'admin_init', static function (): void {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content = '<p><strong>Nahnu Asset Scanner</strong> ' .
		esc_html__( 'makes no external HTTP requests and collects no data about your visitors. All cookie identification is done using data bundled within the plugin. Nothing is sent to any third-party service.', 'nahnu-asset-scanner' ) .
		'</p>';
	wp_add_privacy_policy_content( 'Nahnu Asset Scanner', wp_kses_post( $content ) );
} );

// "Open Scanner" link on the Plugins screen. The scanner is a top-level menu page,
// so its URL is admin.php?page=… (tools.php?page=… is rejected by WordPress).
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), static function ( array $links ): array {
	$open = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=nahnu-asset-scanner' ) ),
		esc_html__( 'Open Scanner', 'nahnu-asset-scanner' )
	);
	array_unshift( $links, $open );
	return $links;
} );

add_action( 'plugins_loaded', [ Admin::class, 'init' ] );
add_action( 'plugins_loaded', [ Test_Mode::class, 'init' ] );
add_action( 'plugins_loaded', [ Abilities::class, 'init' ] );
