<?php
/**
 * Plugin_Map — WordPress plugin slug → readable name mapping.
 *
 * Used to convert URL paths like /wp-content/plugins/woocommerce/
 * into "WooCommerce".  Inspired by FAZ Cookie Manager's Known_Providers approach.
 *
 * @package NahnuFAS
 */

namespace NahnuFAS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugin_Map {

	/**
	 * Map of plugin folder slug → display name.
	 * Covers the most common 100+ WordPress plugins.
	 */
	private static array $map = [
		// E-commerce
		'woocommerce'                    => 'WooCommerce',
		'woocommerce-payments'           => 'WooPayments',
		'woo-gutenberg-products-block'   => 'WooCommerce Blocks',
		'easy-digital-downloads'         => 'Easy Digital Downloads',
		'surecart'                       => 'SureCart',
		'fluentcart'                     => 'FluentCart',
		'give'                           => 'GiveWP',

		// Page builders
		'elementor'                      => 'Elementor',
		'elementor-pro'                  => 'Elementor Pro',
		'bricks'                         => 'Bricks Builder',
		'bricksforge'                    => 'Bricksforge',
		'beaver-builder-plugin'          => 'Beaver Builder',
		'bb-plugin'                      => 'Beaver Builder',
		'divi'                           => 'Divi',
		'visual-composer'                => 'WPBakery',
		'js_composer'                    => 'WPBakery',
		'oxygen'                         => 'Oxygen',
		'breakdance'                     => 'Breakdance',
		'kadence-blocks'                 => 'Kadence Blocks',
		'ultimate-addons-for-gutenberg'  => 'Spectra',
		'spectra'                        => 'Spectra',

		// SEO
		'wordpress-seo'                  => 'Yoast SEO',
		'rank-math-seo'                  => 'Rank Math',
		'all-in-one-seo-pack'            => 'All-in-One SEO',
		'seopress'                       => 'SEOPress',
		'slim-seo'                       => 'Slim SEO',
		'the-seo-framework'              => 'The SEO Framework',

		// Caching & Performance
		'w3-total-cache'                 => 'W3 Total Cache',
		'wp-super-cache'                 => 'WP Super Cache',
		'wp-rocket'                      => 'WP Rocket',
		'litespeed-cache'                => 'LiteSpeed Cache',
		'autoptimize'                    => 'Autoptimize',
		'sg-cachepress'                  => 'SiteGround Optimizer',
		'swift-performance-lite'         => 'Swift Performance',
		'hummingbird-performance'        => 'Hummingbird',
		'wp-fastest-cache'               => 'WP Fastest Cache',
		'flying-pages'                   => 'Flying Pages',
		'asset-cleanup'                  => 'WP Asset CleanUp',
		'wp-asset-clean-up'              => 'WP Asset CleanUp',
		'flying-scripts'                 => 'Flying Scripts',

		// Security
		'wordfence'                      => 'Wordfence',
		'wordfence-login-security'       => 'Wordfence Login Security',
		'really-simple-ssl'              => 'Really Simple SSL',
		'really-simple-security'         => 'Really Simple Security',
		'ithemes-security'               => 'iThemes Security',
		'sucuri-scanner'                 => 'Sucuri Security',
		'all-in-one-wp-security-and-firewall' => 'All-in-One Security',
		'shield-security'                => 'Shield Security',
		'wp-cerber'                      => 'Cerber Security',
		'limit-login-attempts-reloaded'  => 'Limit Login Attempts',
		'loginizer'                      => 'Loginizer',
		'two-factor'                     => 'Two Factor',

		// Forms
		'gravityforms'                   => 'Gravity Forms',
		'gravityformspaypal'             => 'Gravity Forms PayPal',
		'contact-form-7'                 => 'Contact Form 7',
		'wpcf7'                          => 'Contact Form 7',
		'wpforms'                        => 'WPForms',
		'wpforms-lite'                   => 'WPForms',
		'ninja-forms'                    => 'Ninja Forms',
		'fluentform'                     => 'Fluent Forms',
		'forminator'                     => 'Forminator',
		'sureforms'                      => 'SureForms',
		'formidable'                     => 'Formidable Forms',
		'happyforms'                     => 'HappyForms',

		// Email / CRM
		'mailpoet'                       => 'MailPoet',
		'fluentcrm'                      => 'FluentCRM',
		'groundhogg'                     => 'Groundhogg',
		'wp-mail-smtp'                   => 'WP Mail SMTP',
		'newsletter'                     => 'The Newsletter Plugin',
		'mailster'                       => 'Mailster',
		'mc4wp'                          => 'Mailchimp for WP',
		'activecampaign-for-woocommerce' => 'ActiveCampaign',

		// Media & CDN
		'wp-media-cloud'                 => 'WP Media Cloud',
		'amazon-s3-and-cloudfront'       => 'WP Offload Media',
		'as3cf-assets'                   => 'WP Offload Media Assets',
		'cloudflare'                     => 'Cloudflare',
		'bunnycdn'                       => 'BunnyCDN',
		'smush'                          => 'Smush',
		'imagify'                        => 'Imagify',
		'shortpixel-image-optimiser'     => 'ShortPixel',
		'ewww-image-optimizer'           => 'EWWW Image Optimizer',

		// Multilingual
		'sitepress-multilingual-cms'     => 'WPML',
		'polylang'                       => 'Polylang',
		'translatepress-multilingual'    => 'TranslatePress',
		'weglot'                         => 'Weglot',

		// Analytics & Tracking
		'google-site-kit'                => 'Site Kit by Google',
		'monsterinsights'                => 'MonsterInsights',
		'exactmetrics'                   => 'ExactMetrics',
		'independent-analytics'          => 'Independent Analytics',
		'analytify'                      => 'Analytify',
		'pixel-cat'                      => 'Pixel Cat',
		'facebook-for-woocommerce'       => 'Facebook for WooCommerce',

		// Consent / GDPR
		'cookieyes-cookie-consent'       => 'CookieYes',
		'cookie-law-info'                => 'CookieYes',
		'complianz-gdpr'                 => 'Complianz',
		'cookiebot'                      => 'Cookiebot',
		'real-cookie-banner'             => 'Real Cookie Banner',
		'borlabs-cookie'                 => 'Borlabs Cookie',

		// Backup
		'updraftplus'                    => 'UpdraftPlus',
		'backwpup'                       => 'BackWPup',
		'wpvivid-backups'                => 'WPvivid Backup',
		'all-in-one-wp-migration'        => 'All-in-One WP Migration',
		'duplicator'                     => 'Duplicator',

		// Membership / LMS
		'memberpress'                    => 'MemberPress',
		'learndash'                      => 'LearnDash',
		'lifterlms'                      => 'LifterLMS',
		'tutor'                          => 'Tutor LMS',
		'woocommerce-memberships'        => 'WooCommerce Memberships',
		'paid-memberships-pro'           => 'Paid Memberships Pro',

		// Popups / Marketing
		'optinmonster'                   => 'OptinMonster',
		'popup-maker'                    => 'Popup Maker',
		'surebigcart'                    => 'SureTriggers',
		'wp-statistics'                  => 'WP Statistics',
		'jetpack'                        => 'Jetpack',

		// Utilities
		'advanced-custom-fields'         => 'ACF',
		'secure-custom-fields'           => 'Secure Custom Fields',
		'meta-box'                       => 'Meta Box',
		'pods'                           => 'Pods',
		'toolset-types'                  => 'Toolset',
		'query-monitor'                  => 'Query Monitor',
		'user-role-editor'               => 'User Role Editor',
		'woocommerce-gateway-stripe'     => 'Stripe for WooCommerce',
		'woocommerce-paypal-payments'    => 'PayPal for WooCommerce',
		'tablepress'                     => 'TablePress',
		'the-events-calendar'            => 'The Events Calendar',
		'wpml-string-translation'        => 'WPML String Translation',
		'happy-files-pro'                => 'HappyFiles',
		'happyfiles-pro'                 => 'HappyFiles',
		'wp-grid-builder'                => 'WP Grid Builder',
		'facet-wp'                       => 'FacetWP',
		'facetwp'                        => 'FacetWP',
		'acss'                           => 'Automatic CSS',
		'automatic-css'                  => 'Automatic CSS',

		// Themes (common)
		'generatepress'                  => 'GeneratePress',
		'astra'                          => 'Astra',
		'hello-elementor'                => 'Hello Elementor',
		'storefront'                     => 'Storefront',
		'twentytwentyfive'               => 'Twenty Twenty-Five',
		'twentytwentyfour'               => 'Twenty Twenty-Four',
	];

	/**
	 * Look up a plugin slug → display name.
	 * Returns null if not in map (caller should fall back to slug-to-title).
	 */
	public static function get_name( string $slug ): ?string {
		return self::$map[ strtolower( $slug ) ] ?? null;
	}

	/**
	 * Return the full map — used to pass to JS via wp_localize_script.
	 */
	public static function get_map(): array {
		return self::$map;
	}
}
