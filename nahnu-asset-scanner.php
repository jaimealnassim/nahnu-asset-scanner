<?php
/**
 * Plugin Name: Nahnu Asset Scanner
 * Plugin URI: https://frontendassetscanner.com
 * Description: Manually scan your site's front end to detect JavaScript files, CSS files, cookies, and external domains. Helps configure cache optimization and cookie consent settings accurately.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Nahnu Plugins
 * Author URI: https://www.nahnuplugins.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: frontend-asset-scanner
 * Domain Path: /languages
 */

namespace NahnuFAS;

defined( 'ABSPATH' ) || exit;

define( 'NAHNUAS_VERSION', '1.0.0' );
define( 'NAHNUAS_FILE', __FILE__ );
define( 'NAHNUAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAHNUAS_URL', plugin_dir_url( __FILE__ ) );

require_once NAHNUAS_DIR . 'includes/class-db.php';
require_once NAHNUAS_DIR . 'includes/class-scanner.php';
require_once NAHNUAS_DIR . 'includes/class-admin.php';
require_once NAHNUAS_DIR . 'includes/class-plugin-map.php';
require_once NAHNUAS_DIR . 'includes/class-plugin-cookies.php';

register_activation_hook( __FILE__, [ '\NahnuFAS\DB', 'install' ] );


// Privacy policy
add_action( 'admin_init', function () {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) { return; }
	$content = '<p><strong>Nahnu Asset Scanner</strong> ' .
		esc_html__( 'makes no external HTTP requests and collects no data about your visitors. All cookie identification is done using data bundled within the plugin. Nothing is sent to any third-party service.', 'nahnu-asset-scanner' ) .
		'</p>';
	wp_add_privacy_policy_content( 'Nahnu Asset Scanner', wp_kses_post( $content ) );
} );

// Handy action links on Plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
	$extra = [
		'<a href="' . esc_url( admin_url( 'tools.php?page=nahnu-asset-scanner' ) ) . '">' . esc_html__( 'Open Scanner', 'nahnu-asset-scanner' ) . '</a>',
	];
	return array_merge( $extra, $links );
} );

add_action( 'plugins_loaded', function () {
	\NahnuFAS\Admin::init();
} );
