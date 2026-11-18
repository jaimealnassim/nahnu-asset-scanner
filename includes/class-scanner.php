<?php
namespace NahnuAS;

defined( 'ABSPATH' ) || exit;

class Scanner {

	/** Default per-page timeout in seconds (matches the Settings tab default). */
	public const DEFAULT_TIMEOUT = 15;

	// -------------------------------------------------------------------------
	// Known third-party vendors for badge detection
	// -------------------------------------------------------------------------

	/**
	 * Known third-party vendors (domain => [ name, category ]), loaded from
	 * data/vendors.json. Only used to label domains found by a scan.
	 *
	 * @var array<string,array{0:string,1:string}>|null
	 */
	private static ?array $vendors = null;

	/**
	 * The vendor table.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function vendors(): array {
		if ( null === self::$vendors ) {
			$data          = wp_json_file_decode( NAHNUAS_DIR . 'data/vendors.json', [ 'associative' => true ] );
			self::$vendors = is_array( $data ) ? $data : [];
		}
		return self::$vendors;
	}


	private static array $badge_map = [
		'analytics' => [ 'label' => 'Analytics',    'class' => 'analytics' ],
		'marketing' => [ 'label' => 'Marketing',     'class' => 'marketing' ],
		'payment'   => [ 'label' => 'Payment',       'class' => 'payment' ],
		'consent'   => [ 'label' => 'Consent',       'class' => 'consent' ],
		'cdn'       => [ 'label' => 'CDN',           'class' => 'cdn' ],
		'chat'      => [ 'label' => 'Chat',          'class' => 'chat' ],
		'security'  => [ 'label' => 'Security',      'class' => 'security' ],
		'media'     => [ 'label' => 'Media',         'class' => 'media' ],
		'social'    => [ 'label' => 'Social',        'class' => 'social' ],
	];

	// -------------------------------------------------------------------------
	// Vendor / badge lookups (public — used by Admin)
	// -------------------------------------------------------------------------

	public static function get_vendor_info( string $domain ): array {
		$d       = strtolower( trim( $domain, '.' ) );
		$vendors = self::vendors();

		if ( isset( $vendors[ $d ] ) ) {
			return [ 'name' => $vendors[ $d ][0], 'category' => $vendors[ $d ][1] ];
		}

		$parts = explode( '.', $d );
		while ( count( $parts ) > 2 ) {
			array_shift( $parts );
			$parent = implode( '.', $parts );
			if ( isset( $vendors[ $parent ] ) ) {
				return [ 'name' => $vendors[ $parent ][0], 'category' => $vendors[ $parent ][1] ];
			}
		}
		return [];
	}

	public static function get_badge( string $domain ): array {
		$vendor = self::get_vendor_info( $domain );
		if ( empty( $vendor ) ) return [];
		$badge = self::$badge_map[ $vendor['category'] ] ?? null;
		if ( ! $badge ) return [];
		return [ 'label' => $badge['label'], 'class' => $badge['class'], 'vendor' => $vendor['name'] ];
	}

	// -------------------------------------------------------------------------
	// URL list builder
	// -------------------------------------------------------------------------

	public static function get_pages_to_scan(): array {
		$limit = 10;
		$urls  = [];

		// --- Picker mode: user chose specific posts/pages ---
		$selected_ids = get_option( 'nahnuas_selected_post_ids', [] );
		if ( ! empty( $selected_ids ) && is_array( $selected_ids ) ) {
			foreach ( array_slice( $selected_ids, 0, $limit ) as $id ) {
				$post = get_post( (int) $id );
				if ( ! $post || $post->post_status !== 'publish' ) { continue; }
				$url = get_permalink( $post->ID );
				if ( $url ) {
					$urls[ $url ] = $post->post_type;
				}
			}
			// Still honour custom URLs on top of the picker selection
			$custom = trim( get_option( 'nahnuas_custom_urls', '' ) );
			if ( $custom ) {
				foreach ( explode( "\n", $custom ) as $line ) {
					$u = trim( $line );
					if ( wp_http_validate_url( $u ) ) {
						$urls[ $u ] = 'custom';
					}
				}
			}
			$result = [];
			foreach ( array_slice( $urls, 0, $limit, true ) as $url => $type ) {
				$result[] = [ 'url' => $url, 'page_type' => $type ];
			}
			return $result;
		}

		// --- Auto-discover mode (no specific selection) ---
		// Home
		$home = trailingslashit( home_url( '/' ) );
		$urls[ $home ] = 'home';

		// WP Pages
		if ( get_option( 'nahnuas_include_pages', '1' ) === '1' ) {
			$pages = get_posts( [
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'numberposts'            => 5,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			] );
			foreach ( $pages as $p ) {
				$url = get_permalink( $p->ID );
				if ( $url && $url !== $home ) $urls[ $url ] = 'page';
			}
		}

		// WP Posts
		if ( get_option( 'nahnuas_include_posts', '1' ) === '1' ) {
			$posts = get_posts( [
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'numberposts'            => 5,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			] );
			foreach ( $posts as $p ) {
				$url = get_permalink( $p->ID );
				if ( $url ) $urls[ $url ] = 'post';
			}
		}

		// WooCommerce
		if ( get_option( 'nahnuas_include_products', '1' ) === '1' && function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'shop' => 'wc-shop', 'cart' => 'wc-cart', 'checkout' => 'wc-checkout', 'myaccount' => 'wc-account' ] as $slug => $type ) {
				$pid = wc_get_page_id( $slug );
				if ( $pid > 0 ) {
					$url = get_permalink( $pid );
					if ( $url ) $urls[ $url ] = $type;
				}
			}
			$products = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'update_post_term_cache' => false, 'update_post_meta_cache' => false ] );
			if ( $products ) {
				$url = get_permalink( $products[0]->ID );
				if ( $url ) $urls[ $url ] = 'wc-product';
			}
		}

		// Custom URLs
		$custom = trim( get_option( 'nahnuas_custom_urls', '' ) );
		if ( $custom ) {
			foreach ( explode( "\n", $custom ) as $line ) {
				$u = trim( $line );
				if ( wp_http_validate_url( $u ) ) {
					$urls[ $u ] = 'custom';
				}
			}
		}

		$result = [];
		foreach ( array_slice( $urls, 0, $limit, true ) as $url => $type ) {
			$result[] = [ 'url' => $url, 'page_type' => $type ];
		}
		return $result;
	}

	// -------------------------------------------------------------------------
	// Page scanner
	// -------------------------------------------------------------------------

	public static function scan_page( int $scan_id, string $url, string $page_type = 'page' ): array {
		$page_id = DB::create_page( $scan_id, $url, $page_type );
		$now     = current_time( 'mysql' );
		$timeout = min( 120, max( 5, (int) get_option( 'nahnuas_scan_timeout', self::DEFAULT_TIMEOUT ) ) );

		[ $code, $html, $raw_cookies, $error ] = self::fetch_page( $url, $timeout );

		if ( 0 === $code ) {
			DB::update_page( $page_id, [ 'status_code' => 0, 'scanned_at' => $now ] );
			return [
				'success' => false,
				'error'   => $error ?: __( 'The page could not be fetched.', 'nahnu-asset-scanner' ),
			];
		}

		DB::update_page( $page_id, [ 'status_code' => $code, 'scanned_at' => $now ] );

		if ( $code >= 400 || '' === $html ) {
			return [
				'success' => false,
				/* translators: %d: HTTP status code, e.g. 404. */
				'error'   => sprintf( __( 'The page returned HTTP %d.', 'nahnu-asset-scanner' ), $code ),
			];
		}

		// --- JS / CSS / Domain extraction from DOM ---
		$dom = self::parse_html( $html );
		if ( $dom ) {
			self::extract_js( $dom, $scan_id, $page_id, $url, $now );
			self::extract_css( $dom, $scan_id, $page_id, $url, $now );
			self::extract_domains_from_dom( $dom, $scan_id, $page_id, $url, $now );
		}

		// --- Cookie extraction: HTTP Set-Cookie headers ---
		self::store_raw_cookies( $raw_cookies, $scan_id, $page_id, $url, $now );

		// --- Cookie extraction: cookies declared in HTML/JS source ---
		// Limit to first 500KB to avoid regex on enormous pages
		if ( ! empty( $html ) ) {
			$html_slice = strlen( $html ) > 512000 ? substr( $html, 0, 512000 ) : $html;
			self::extract_cookies_from_html( $html_slice, $scan_id, $page_id, $url, $now );
		}

		return [ 'success' => true, 'status_code' => $code ];
	}

	/**
	 * Fetch a page with the WordPress HTTP API.
	 *
	 * wp_safe_remote_get() re-validates every redirect hop against internal/private
	 * addresses. Every Set-Cookie line is read with wp_remote_retrieve_header(),
	 * which returns an array when the response carries more than one, so no
	 * transport-level hooks are needed.
	 *
	 * @return array{0:int, 1:string, 2:string[], 3:string} [http_code, body, set_cookie_values, error]
	 */
	private static function fetch_page( string $url, int $timeout ): array {
		if ( ! wp_http_validate_url( $url ) ) {
			return [ 0, '', [], __( 'The URL was rejected as unsafe.', 'nahnu-asset-scanner' ) ];
		}

		// Loopback requests to this site may use a self-signed certificate on local
		// and staging installs; core uses the same filter for its own loopbacks.
		$is_own_site = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === self::site_host();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- deliberately reuses the core filter.
		$sslverify   = $is_own_site ? (bool) apply_filters( 'https_local_ssl_verify', false ) : true;

		$response = wp_safe_remote_get( $url, [
			'timeout'     => $timeout,
			'redirection' => 5,
			'sslverify'   => $sslverify,
			'user-agent'  => 'Mozilla/5.0 (compatible; NahnuAssetScanner/' . NAHNUAS_VERSION . '; +' . home_url( '/' ) . ')',
			'headers'     => [
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.5',
				'Cache-Control'   => 'no-cache',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return [ 0, '', [], $response->get_error_message() ];
		}

		$set_cookie = wp_remote_retrieve_header( $response, 'set-cookie' );
		$cookies    = array_values( array_filter( array_map( 'strval', (array) $set_cookie ), 'strlen' ) );

		return [
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response ),
			$cookies,
			'',
		];
	}

	/**
	 * Extract cookies set inline in the HTML — things like document.cookie = "...",
	 * WooCommerce fragment cookies, consent scripts, etc.
	 * This catches JS-set cookies that HTTP headers never carry.
	 */
	private static function extract_cookies_from_html( string $html, int $scan_id, int $page_id, string $url, string $now ): void {
		$site_host = self::site_host();
		$found     = [];

		// 1. document.cookie = "name=value" assignments
		preg_match_all(
			'/document\s*\.\s*cookie\s*=\s*["\']([^"\']+)["\']/i',
			$html,
			$m1
		);
		foreach ( $m1[1] as $raw ) {
			$found[] = $raw;
		}

		// 2. Explicit cookie-name keys in inline config objects, as used by consent
		//    and analytics scripts: {"cookieName":"cmplz_banner-status"},
		//    {"cookie_name":"_my_consent"}, {"cookieKey":"..."}.
		//    (A broad match on any quoted "wp-…" string was removed in 1.0.2: it
		//    reported script/style element IDs such as "wp-a11y-js" as cookies.)
		preg_match_all(
			'/["\']cookie[_-]?(?:name|key)["\']\s*:\s*["\']([a-zA-Z0-9_.-]{3,79})["\']/i',
			$html,
			$m2
		);
		foreach ( $m2[1] as $name ) {
			$found[] = $name . '=';
		}

		$seen = [];
		foreach ( $found as $raw ) {
			$parsed = self::parse_set_cookie( $raw );
			$name   = $parsed['name'];
			if ( empty( $name ) || strlen( $name ) > 80 || isset( $seen[ $name ] ) ) { continue; }
			$seen[ $name ] = true;

			DB::insert_cookie( [
				'scan_id'        => $scan_id,
				'page_id'        => $page_id,
				'cookie_name'    => $name,
				'cookie_domain'  => $site_host,
				'cookie_path'    => '/',
				'expiration'     => null,
				'is_session'     => 1,
				'is_third_party' => 0,
				'is_secure'      => 0,
				'is_httponly'    => 0,
				'samesite'       => null,
				'category'       => self::guess_cookie_category( $name, $site_host ),
				'first_seen'     => $now,
			] );
		}
	}

	// -------------------------------------------------------------------------
	// HTML helpers
	// -------------------------------------------------------------------------

	private static function parse_html( string $html ): ?\DOMDocument {
		if ( '' === trim( $html ) ) {
			return null;
		}
		$dom      = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $loaded ? $dom : null;
	}

	private static function extract_js( \DOMDocument $dom, int $scan_id, int $page_id, string $base_url, string $now ): void {
		$site_host = self::site_host();
		foreach ( $dom->getElementsByTagName( 'script' ) as $el ) {
			$src = trim( $el->getAttribute( 'src' ) );
			if ( empty( $src ) ) {
				// Inline script
				$content = trim( $el->textContent );
				if ( empty( $content ) ) continue;
				DB::insert_asset( [
					'scan_id'         => $scan_id,
					'page_id'         => $page_id,
					'asset_type'      => 'js',
					'asset_url'       => '',
					'domain'          => $site_host,
					'is_inline'       => 1,
					'is_third_party'  => 0,
					'attributes_json' => wp_json_encode( [
						'type'    => $el->getAttribute( 'type' ) ?: 'text/javascript',
						'preview' => mb_substr( $content, 0, 140 ),
					] ),
					'first_seen' => $now,
				] );
			} else {
				$abs    = self::make_absolute( $src, $base_url );
				$domain = self::extract_domain( $abs ) ?: $site_host;
				$is3p   = self::is_third_party( $domain, $site_host );
				DB::insert_asset( [
					'scan_id'         => $scan_id,
					'page_id'         => $page_id,
					'asset_type'      => 'js',
					'asset_url'       => $abs,
					'domain'          => $domain,
					'is_inline'       => 0,
					'is_third_party'  => $is3p ? 1 : 0,
					'attributes_json' => wp_json_encode( [
						'defer'   => $el->hasAttribute( 'defer' ) ? 1 : 0,
						'async'   => $el->hasAttribute( 'async' ) ? 1 : 0,
						'type'    => $el->getAttribute( 'type' ) ?: 'text/javascript',
						'version' => self::extract_version( $abs ),
					] ),
					'first_seen' => $now,
				] );
				DB::insert_domain( [ 'scan_id' => $scan_id, 'page_id' => $page_id, 'domain' => $domain, 'source_type' => 'js', 'is_third_party' => $is3p ? 1 : 0, 'first_seen' => $now ] );
			}
		}
	}

	private static function extract_css( \DOMDocument $dom, int $scan_id, int $page_id, string $base_url, string $now ): void {
		$site_host = self::site_host();

		// <link rel="stylesheet"> and <link rel="preload" as="style">
		foreach ( $dom->getElementsByTagName( 'link' ) as $el ) {
			$rel = strtolower( trim( $el->getAttribute( 'rel' ) ) );
			$href = trim( $el->getAttribute( 'href' ) );
			if ( empty( $href ) ) continue;
			if ( $rel === 'preload' && strtolower( $el->getAttribute( 'as' ) ) !== 'style' ) continue;
			if ( $rel !== 'stylesheet' && $rel !== 'preload' ) continue;

			$abs    = self::make_absolute( $href, $base_url );
			$domain = self::extract_domain( $abs ) ?: $site_host;
			$is3p   = self::is_third_party( $domain, $site_host );
			DB::insert_asset( [
				'scan_id'         => $scan_id,
				'page_id'         => $page_id,
				'asset_type'      => 'css',
				'asset_url'       => $abs,
				'domain'          => $domain,
				'is_inline'       => 0,
				'is_third_party'  => $is3p ? 1 : 0,
				'attributes_json' => wp_json_encode( [
					'media'   => $el->getAttribute( 'media' ) ?: 'all',
					'version' => self::extract_version( $abs ),
					'rel'     => $rel,
				] ),
				'first_seen' => $now,
			] );
			DB::insert_domain( [ 'scan_id' => $scan_id, 'page_id' => $page_id, 'domain' => $domain, 'source_type' => 'css', 'is_third_party' => $is3p ? 1 : 0, 'first_seen' => $now ] );
		}

		// Inline <style>
		foreach ( $dom->getElementsByTagName( 'style' ) as $el ) {
			$content = trim( $el->textContent );
			if ( empty( $content ) ) continue;
			DB::insert_asset( [
				'scan_id'         => $scan_id,
				'page_id'         => $page_id,
				'asset_type'      => 'css',
				'asset_url'       => '',
				'domain'          => $site_host,
				'is_inline'       => 1,
				'is_third_party'  => 0,
				'attributes_json' => wp_json_encode( [
					'media'   => $el->getAttribute( 'media' ) ?: 'all',
					'preview' => mb_substr( $content, 0, 100 ),
				] ),
				'first_seen' => $now,
			] );
		}
	}

	private static function extract_domains_from_dom( \DOMDocument $dom, int $scan_id, int $page_id, string $base_url, string $now ): void {
		$site_host = self::site_host();
		$seen      = [];

		$map = [
			'img'    => [ 'src'  => 'image' ],
			'iframe' => [ 'src'  => 'iframe' ],
			'source' => [ 'src'  => 'media' ],
			'video'  => [ 'src'  => 'media' ],
			'audio'  => [ 'src'  => 'media' ],
		];

		foreach ( $map as $tag => $attrs ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $el ) {
				foreach ( $attrs as $attr => $source_type ) {
					$url = trim( $el->getAttribute( $attr ) );
					if ( '' === $url || preg_match( '#^(data|blob|javascript|about|mailto|tel):#i', $url ) ) {
						continue;
					}
					$abs    = self::make_absolute( $url, $base_url );
					$domain = self::extract_domain( $abs );
					if ( empty( $domain ) ) continue;
					$key = $domain . '|' . $source_type;
					if ( isset( $seen[ $key ] ) ) continue;
					$seen[ $key ] = true;
					$is3p = self::is_third_party( $domain, $site_host );
					DB::insert_domain( [ 'scan_id' => $scan_id, 'page_id' => $page_id, 'domain' => $domain, 'source_type' => $source_type, 'is_third_party' => $is3p ? 1 : 0, 'first_seen' => $now ] );
				}
			}
		}

		// Font preloads
		foreach ( $dom->getElementsByTagName( 'link' ) as $el ) {
			if ( strtolower( $el->getAttribute( 'as' ) ) !== 'font' ) continue;
			$href = trim( $el->getAttribute( 'href' ) );
			if ( empty( $href ) ) continue;
			$abs    = self::make_absolute( $href, $base_url );
			$domain = self::extract_domain( $abs );
			if ( empty( $domain ) ) continue;
			$key  = $domain . '|font';
			if ( isset( $seen[ $key ] ) ) continue;
			$seen[ $key ] = true;
			$is3p = self::is_third_party( $domain, $site_host );
			DB::insert_domain( [ 'scan_id' => $scan_id, 'page_id' => $page_id, 'domain' => $domain, 'source_type' => 'font', 'is_third_party' => $is3p ? 1 : 0, 'first_seen' => $now ] );
		}
	}

	// -------------------------------------------------------------------------
	// Cookie detection from response headers
	// -------------------------------------------------------------------------

	private static function store_raw_cookies( array $raw_cookies, int $scan_id, int $page_id, string $url, string $now ): void {
		$site_host = self::site_host();

		foreach ( $raw_cookies as $raw ) {
			$parsed = self::parse_set_cookie( $raw );
			if ( empty( $parsed['name'] ) ) continue;

			$c_domain = $parsed['domain'] ?: $site_host;
			$is3p     = self::is_third_party( $c_domain, $site_host );

			DB::insert_cookie( [
				'scan_id'       => $scan_id,
				'page_id'       => $page_id,
				'cookie_name'   => $parsed['name'],
				'cookie_domain' => $c_domain,
				'cookie_path'   => $parsed['path'] ?: '/',
				'expiration'    => $parsed['expires'] ?: null,
				'is_session'    => empty( $parsed['expires'] ) && empty( $parsed['max-age'] ) ? 1 : 0,
				'is_third_party'=> $is3p ? 1 : 0,
				'is_secure'     => $parsed['secure'] ? 1 : 0,
				'is_httponly'   => $parsed['httponly'] ? 1 : 0,
				'samesite'      => $parsed['samesite'] ?: null,
				'category'      => self::guess_cookie_category( $parsed['name'], $c_domain ),
				'first_seen'    => $now,
			] );
		}
	}

	private static function parse_set_cookie( string $str ): array {
		$parts  = array_map( 'trim', explode( ';', $str ) );
		$result = [ 'name' => '', 'value' => '', 'path' => '/', 'domain' => '', 'expires' => '', 'max-age' => '', 'samesite' => '', 'secure' => false, 'httponly' => false ];

		foreach ( $parts as $i => $part ) {
			if ( $i === 0 ) {
				$eq = strpos( $part, '=' );
				$result['name']  = $eq !== false ? trim( substr( $part, 0, $eq ) ) : $part;
				$result['value'] = $eq !== false ? trim( substr( $part, $eq + 1 ) ) : '';
				continue;
			}
			$low = strtolower( $part );
			if ( $low === 'secure' )   { $result['secure']   = true; continue; }
			if ( $low === 'httponly' ) { $result['httponly'] = true; continue; }
			$eq = strpos( $part, '=' );
			if ( $eq === false ) continue;
			$k = strtolower( trim( substr( $part, 0, $eq ) ) );
			$v = trim( substr( $part, $eq + 1 ) );
			switch ( $k ) {
				case 'path':     $result['path']     = $v; break;
				case 'domain':   $result['domain']   = ltrim( $v, '.' ); break;
				case 'expires':  $result['expires']  = $v; break;
				case 'max-age':  $result['max-age']  = $v; $result['expires'] = $v ? gmdate( 'Y-m-d H:i:s', time() + (int) $v ) . ' UTC' : ''; break;
				case 'samesite': $result['samesite'] = $v; break;
			}
		}
		return $result;
	}

	private static function guess_cookie_category( string $name, string $domain ): string {
		// Check comprehensive WordPress plugin cookie database first
		$match = Plugin_Cookies::lookup( $name );
		if ( $match ) { return $match['category']; }

		$n = strtolower( $name );
		$d = strtolower( $domain );

		// Analytics
		if ( preg_match( '/^(_ga|_gid|_gat|__utm|_hjsession|_hjid|_hjfirstseen|_hjincludedinsession|_pk_|matomo_|_clck|_clsk|ahoy_|amplitude_id)/i', $n ) ) return 'analytics';
		if ( strpos( $d, 'google-analytics' ) !== false || strpos( $d, 'hotjar' ) !== false || strpos( $d, 'mixpanel' ) !== false ) return 'analytics';

		// Marketing / advertising
		if ( preg_match( '/^(_fbp|_fbc|fr|fb_|__gads|_gcl_|ide|datr|guest_id|muc_|personalization_id|tt_|_tt|anj|uuid2|tuuid|aam_|demdex|dpm|s_vi|s_fid|pardot|__hstc|hubspotutk|__hssc|__hssrc)/i', $n ) ) return 'marketing';
		if ( strpos( $d, 'facebook' ) !== false || strpos( $d, 'doubleclick' ) !== false || strpos( $d, 'adnxs' ) !== false ) return 'marketing';

		// Preferences
		if ( preg_match( '/^(lang|locale|currency|tz|timezone|theme|color_mode|dark_mode|pref_|user_pref|country|region)/i', $n ) ) return 'preferences';

		return 'unknown';
	}

	// -------------------------------------------------------------------------
	// URL / domain utilities
	// -------------------------------------------------------------------------

	public static function extract_domain( string $url ): string {
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		return strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Resolve a src/href found in the page against the page URL.
	 * Uses core's resolver, which handles ./, ../, query-only and protocol-relative URLs.
	 */
	private static function make_absolute( string $url, string $base_url ): string {
		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}
		if ( ! class_exists( '\WP_Http' ) ) {
			require_once ABSPATH . WPINC . '/class-wp-http.php';
		}
		return \WP_Http::make_absolute_url( $url, $base_url );
	}

	/**
	 * Registrable domain ("example.com", "example.co.uk") for a host.
	 * Handles the common two-part country suffixes so that two unrelated sites
	 * on .co.uk are not treated as the same party.
	 */
	public static function base_domain( string $host ): string {
		$labels = explode( '.', strtolower( trim( $host, '.' ) ) );
		$count  = count( $labels );
		if ( $count <= 2 ) {
			return implode( '.', $labels );
		}
		$second = $labels[ $count - 2 ];
		$tld    = $labels[ $count - 1 ];
		$take   = ( 2 === strlen( $tld ) && in_array( $second, [ 'co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'or', 'ne', 'go' ], true ) ) ? 3 : 2;
		return implode( '.', array_slice( $labels, -$take ) );
	}

	private static function is_third_party( string $domain, string $site_host ): bool {
		// Strip port numbers (e.g. dev.example.com:8080 → dev.example.com).
		$domain    = strtolower( explode( ':', $domain )[0] );
		$site_host = strtolower( explode( ':', $site_host )[0] );

		if ( '' === $domain || $domain === $site_host ) {
			return false;
		}

		// Compare registrable domains so cdn.example.com is first-party to www.example.com.
		return self::base_domain( $site_host ) !== self::base_domain( $domain );
	}

	private static function site_host(): string {
		// Always the bare host: no port, lower-case.
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return explode( ':', $host )[0];
	}

	private static function extract_version( string $url ): string {
		$q = (string) wp_parse_url( $url, PHP_URL_QUERY );
		if ( '' === $q ) {
			return '';
		}
		parse_str( $q, $params );
		$version = $params['ver'] ?? $params['v'] ?? $params['version'] ?? '';
		return is_scalar( $version ) ? (string) $version : '';
	}
}
