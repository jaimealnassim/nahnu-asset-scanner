<?php
namespace NahnuFAS;

defined( 'ABSPATH' ) || exit;

class DB {

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sqls = [];

		$sqls[] = "CREATE TABLE {$wpdb->prefix}nahnuas_scans (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'scanning',
			pages_scanned int(11) NOT NULL DEFAULT 0,
			total_js int(11) NOT NULL DEFAULT 0,
			total_css int(11) NOT NULL DEFAULT 0,
			total_cookies int(11) NOT NULL DEFAULT 0,
			total_domains int(11) NOT NULL DEFAULT 0,
			notes text DEFAULT NULL,
			PRIMARY KEY (id)
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}nahnuas_pages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			url text NOT NULL,
			page_type varchar(50) DEFAULT 'page',
			status_code int(11) DEFAULT NULL,
			scanned_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY scan_id (scan_id)
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}nahnuas_assets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			page_id bigint(20) unsigned NOT NULL,
			asset_type varchar(10) NOT NULL,
			asset_url text DEFAULT NULL,
			asset_url_hash varchar(32) DEFAULT NULL,
			domain varchar(255) DEFAULT NULL,
			is_inline tinyint(1) NOT NULL DEFAULT 0,
			is_third_party tinyint(1) NOT NULL DEFAULT 0,
			attributes_json text DEFAULT NULL,
			first_seen datetime NOT NULL,
			PRIMARY KEY (id),
			KEY scan_id_type (scan_id, asset_type),
			KEY domain (domain(191))
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}nahnuas_cookies (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			page_id bigint(20) unsigned NOT NULL,
			cookie_name varchar(255) NOT NULL,
			cookie_domain varchar(255) DEFAULT NULL,
			cookie_path varchar(500) DEFAULT '/',
			expiration varchar(100) DEFAULT NULL,
			is_session tinyint(1) NOT NULL DEFAULT 0,
			is_third_party tinyint(1) NOT NULL DEFAULT 0,
			is_secure tinyint(1) NOT NULL DEFAULT 0,
			is_httponly tinyint(1) NOT NULL DEFAULT 0,
			samesite varchar(20) DEFAULT NULL,
			category varchar(50) NOT NULL DEFAULT 'unknown',
			first_seen datetime NOT NULL,
			PRIMARY KEY (id),
			KEY scan_id (scan_id)
		) $charset;";

		$sqls[] = "CREATE TABLE {$wpdb->prefix}nahnuas_domains (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			page_id bigint(20) unsigned NOT NULL,
			domain varchar(255) NOT NULL,
			source_type varchar(20) NOT NULL DEFAULT 'js',
			is_third_party tinyint(1) NOT NULL DEFAULT 0,
			first_seen datetime NOT NULL,
			PRIMARY KEY (id),
			KEY scan_id (scan_id),
			KEY domain (domain)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sqls as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'nahnuas_db_version', NAHNUAS_VERSION );
	}

	// -------------------------------------------------------------------------
	// Scans
	// -------------------------------------------------------------------------

	public static function create_scan(): int {
		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}nahnuas_scans", [
			'started_at' => current_time( 'mysql' ),
			'status'     => 'scanning',
		], [ '%s', '%s' ] );
		return (int) $wpdb->insert_id;
	}

	public static function update_scan( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}nahnuas_scans", $data, [ 'id' => $id ] );
	}

	public static function get_latest_scan(): ?array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a WP core value, no user input
		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}nahnuas_scans ORDER BY id DESC LIMIT 1", ARRAY_A );
		return $row ?: null;
	}

	public static function get_scan( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nahnuas_scans WHERE id = %d", $id ), ARRAY_A );
		return $row ?: null;
	}

	// -------------------------------------------------------------------------
	// Pages
	// -------------------------------------------------------------------------

	public static function create_page( int $scan_id, string $url, string $type = 'page' ): int {
		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}nahnuas_pages", [
			'scan_id'   => $scan_id,
			'url'       => $url,
			'page_type' => $type,
		], [ '%d', '%s', '%s' ] );
		return (int) $wpdb->insert_id;
	}

	public static function update_page( int $id, array $data ): void {
		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}nahnuas_pages", $data, [ 'id' => $id ] );
	}

	// -------------------------------------------------------------------------
	// Assets / Cookies / Domains inserts
	// -------------------------------------------------------------------------

	public static function insert_asset( array $data ): void {
		global $wpdb;
		$data['first_seen'] = $data['first_seen'] ?? current_time( 'mysql' );

		// Hash on URL with query params stripped so ?ver=1.0 and ?ver=2.0 of the
		// same file produce the same hash and collapse into one row per scan.
		if ( ! empty( $data['asset_url'] ) ) {
			$url_no_qs              = strtok( $data['asset_url'], '?' );
			$data['asset_url_hash'] = md5( $url_no_qs );
		} else {
			// Inline asset — unique per occurrence (content differs page-to-page)
			$data['asset_url_hash'] = md5( uniqid( '', true ) );
		}

		// Truncate long URLs
		if ( ! empty( $data['asset_url'] ) && strlen( $data['asset_url'] ) > 2048 ) {
			$data['asset_url'] = substr( $data['asset_url'], 0, 2048 );
		}
		$wpdb->insert( "{$wpdb->prefix}nahnuas_assets", $data );
	}

	public static function insert_cookie( array $data ): void {
		global $wpdb;
		$data['first_seen'] = $data['first_seen'] ?? current_time( 'mysql' );
		// Truncate cookie_name to reasonable max
		if ( ! empty( $data['cookie_name'] ) && strlen( $data['cookie_name'] ) > 255 ) { return; }
		$wpdb->insert( "{$wpdb->prefix}nahnuas_cookies", $data );
	}

	/** In-memory seen-set for domains to avoid N+1 SELECT per insert */
	private static array $domain_seen = [];

	public static function insert_domain( array $data ): void {
		global $wpdb;
		$data['first_seen'] = $data['first_seen'] ?? current_time( 'mysql' );
		// Use an in-memory key to skip the per-row SELECT entirely
		$key = $data['scan_id'] . '|' . $data['page_id'] . '|' . $data['domain'] . '|' . $data['source_type'];
		if ( isset( self::$domain_seen[ $key ] ) ) { return; }
		self::$domain_seen[ $key ] = true;
		$wpdb->insert( "{$wpdb->prefix}nahnuas_domains", $data );
	}

	/** Reset the seen-set between scans */
	public static function reset_domain_cache(): void {
		self::$domain_seen = [];
	}

	// -------------------------------------------------------------------------
	// Results queries
	// -------------------------------------------------------------------------

	public static function get_results( string $tab, array $args = [] ): array {
		$scan_id     = (int) ( $args['scan_id'] ?? 0 );
		$page        = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page    = isset( $args['per_page'] ) ? (int) $args['per_page'] : 10;
		$offset      = ( $page - 1 ) * $per_page;
		$search      = sanitize_text_field( $args['search'] ?? '' );
		$third_party = $args['third_party'] ?? '';

		if ( ! $scan_id ) {
			$latest  = self::get_latest_scan();
			$scan_id = $latest ? (int) $latest['id'] : 0;
		}
		if ( ! $scan_id ) return [];

		switch ( $tab ) {
			case 'js':
			case 'css':
				return self::query_assets( $tab, $scan_id, $search, $third_party, $per_page, $offset );
			case 'cookies':
				return self::query_cookies( $scan_id, $search, $third_party, $per_page, $offset );
			case 'domains':
				return self::query_domains( $scan_id, $search, $third_party, $per_page, $offset );
		}
		return [];
	}

	public static function get_results_count( string $tab, array $args = [] ): int {
		$scan_id     = (int) ( $args['scan_id'] ?? 0 );
		$search      = sanitize_text_field( $args['search'] ?? '' );
		$third_party = $args['third_party'] ?? '';

		if ( ! $scan_id ) {
			$latest  = self::get_latest_scan();
			$scan_id = $latest ? (int) $latest['id'] : 0;
		}
		if ( ! $scan_id ) return 0;

		switch ( $tab ) {
			case 'js':
			case 'css':
				return self::count_assets( $tab, $scan_id, $search, $third_party );
			case 'cookies':
				return self::count_cookies( $scan_id, $search, $third_party );
			case 'domains':
				return self::count_domains( $scan_id, $search, $third_party );
		}
		return 0;
	}

	// -------------------------------------------------------------------------
	// Assets query helpers
	// -------------------------------------------------------------------------

	private static function query_assets( string $type, int $scan_id, string $search, string $third_party, int $limit, int $offset ): array {
		global $wpdb;
		$where  = [ 'a.scan_id = %d', 'a.asset_type = %s', 'a.is_inline = 0' ];
		$params = [ $scan_id, $type ];

		if ( $search ) {
			$where[]  = '(a.asset_url LIKE %s OR a.domain LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $third_party === 'yes' ) $where[] = 'a.is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'a.is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$params[]  = $limit;
		$params[]  = $offset;

		$sql = "SELECT
					MIN(a.asset_url) AS asset_url,
					a.domain,
					a.is_inline,
					a.is_third_party,
					MIN(a.attributes_json) AS attributes_json,
					a.asset_url_hash,
					GROUP_CONCAT(DISTINCT p.url ORDER BY p.url SEPARATOR '|||') AS pages,
					COUNT(DISTINCT p.id) AS page_count,
					MIN(a.first_seen) AS first_seen,
					MAX(a.first_seen) AS last_seen
				FROM {$wpdb->prefix}nahnuas_assets a
				JOIN {$wpdb->prefix}nahnuas_pages p ON a.page_id = p.id
				WHERE {$where_sql}
				GROUP BY a.asset_url_hash, a.domain, a.is_inline
				ORDER BY page_count DESC, MIN(a.asset_url) ASC
				LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: [];
	}

	private static function count_assets( string $type, int $scan_id, string $search, string $third_party ): int {
		global $wpdb;
		$where  = [ 'scan_id = %d', 'asset_type = %s', 'is_inline = 0' ];
		$params = [ $scan_id, $type ];

		if ( $search ) {
			$where[]  = '(asset_url LIKE %s OR domain LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $third_party === 'yes' ) $where[] = 'is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(DISTINCT asset_url_hash) FROM {$wpdb->prefix}nahnuas_assets WHERE {$where_sql}";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	// -------------------------------------------------------------------------
	// Cookie query helpers
	// -------------------------------------------------------------------------

	private static function query_cookies( int $scan_id, string $search, string $third_party, int $limit, int $offset ): array {
		global $wpdb;
		$where  = [ 'c.scan_id = %d' ];
		$params = [ $scan_id ];

		if ( $search ) {
			$where[]  = '(c.cookie_name LIKE %s OR c.cookie_domain LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $third_party === 'yes' ) $where[] = 'c.is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'c.is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$params[]  = $limit;
		$params[]  = $offset;

		$sql = "SELECT
					c.cookie_name, c.cookie_domain, c.cookie_path,
					c.is_third_party, c.expiration, c.is_session,
					c.is_secure, c.is_httponly, c.samesite, c.category,
					GROUP_CONCAT(DISTINCT p.url ORDER BY p.url SEPARATOR '|||') AS pages,
					COUNT(DISTINCT p.id) AS page_count,
					MIN(c.first_seen) AS first_seen,
					MAX(c.first_seen) AS last_seen
				FROM {$wpdb->prefix}nahnuas_cookies c
				JOIN {$wpdb->prefix}nahnuas_pages p ON c.page_id = p.id
				WHERE {$where_sql}
				GROUP BY c.cookie_name, c.cookie_domain
				ORDER BY c.cookie_name ASC
				LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: [];
	}

	private static function count_cookies( int $scan_id, string $search, string $third_party ): int {
		global $wpdb;
		$where  = [ 'scan_id = %d' ];
		$params = [ $scan_id ];

		if ( $search ) {
			$where[]  = '(cookie_name LIKE %s OR cookie_domain LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $third_party === 'yes' ) $where[] = 'is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(DISTINCT CONCAT(cookie_name,'|',COALESCE(cookie_domain,''))) FROM {$wpdb->prefix}nahnuas_cookies WHERE {$where_sql}";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	// -------------------------------------------------------------------------
	// Domain query helpers
	// -------------------------------------------------------------------------

	private static function query_domains( int $scan_id, string $search, string $third_party, int $limit, int $offset ): array {
		global $wpdb;
		$where  = [ 'd.scan_id = %d' ];
		$params = [ $scan_id ];

		if ( $search ) {
			$where[]  = 'd.domain LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $third_party === 'yes' ) $where[] = 'd.is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'd.is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$params[]  = $limit;
		$params[]  = $offset;

		$sql = "SELECT
					d.domain, d.is_third_party,
					GROUP_CONCAT(DISTINCT d.source_type ORDER BY d.source_type SEPARATOR ', ') AS source_types,
					GROUP_CONCAT(DISTINCT p.url ORDER BY p.url SEPARATOR '|||') AS pages,
					COUNT(DISTINCT p.id) AS page_count,
					COUNT(*) AS item_count,
					MIN(d.first_seen) AS first_seen,
					MAX(d.first_seen) AS last_seen
				FROM {$wpdb->prefix}nahnuas_domains d
				JOIN {$wpdb->prefix}nahnuas_pages p ON d.page_id = p.id
				WHERE {$where_sql}
				GROUP BY d.domain, d.is_third_party
				ORDER BY item_count DESC, d.domain ASC
				LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A ) ?: [];
	}

	private static function count_domains( int $scan_id, string $search, string $third_party ): int {
		global $wpdb;
		$where  = [ 'scan_id = %d' ];
		$params = [ $scan_id ];

		if ( $search ) {
			$where[]  = 'domain LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		if ( $third_party === 'yes' ) $where[] = 'is_third_party = 1';
		elseif ( $third_party === 'no' ) $where[] = 'is_third_party = 0';

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(DISTINCT domain) FROM {$wpdb->prefix}nahnuas_domains WHERE {$where_sql}";
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	// -------------------------------------------------------------------------
	// Finalize / housekeeping
	// -------------------------------------------------------------------------

	public static function finalize_scan( int $scan_id ): ?array {
		global $wpdb;

		$total_js = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT asset_url_hash) FROM {$wpdb->prefix}nahnuas_assets WHERE scan_id=%d AND asset_type='js' AND is_inline=0",
			$scan_id
		) );
		$total_css = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT asset_url_hash) FROM {$wpdb->prefix}nahnuas_assets WHERE scan_id=%d AND asset_type='css' AND is_inline=0",
			$scan_id
		) );
		$total_cookies = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT CONCAT(cookie_name,'|',COALESCE(cookie_domain,''))) FROM {$wpdb->prefix}nahnuas_cookies WHERE scan_id=%d",
			$scan_id
		) );
		$total_domains = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT domain) FROM {$wpdb->prefix}nahnuas_domains WHERE scan_id=%d",
			$scan_id
		) );
		$pages_scanned = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nahnuas_pages WHERE scan_id=%d AND status_code IS NOT NULL",
			$scan_id
		) );

		self::update_scan( $scan_id, [
			'status'         => 'complete',
			'completed_at'   => current_time( 'mysql' ),
			'total_js'       => $total_js,
			'total_css'      => $total_css,
			'total_cookies'  => $total_cookies,
			'total_domains'  => $total_domains,
			'pages_scanned'  => $pages_scanned,
		] );

		return self::get_scan( $scan_id );
	}

	public static function clear_all(): void {
		global $wpdb;
		foreach ( [ 'nahnuas_domains', 'nahnuas_cookies', 'nahnuas_assets', 'nahnuas_pages', 'nahnuas_scans' ] as $t ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from hardcoded array, no user input
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$t}" );
		}
	}

	// -------------------------------------------------------------------------
	// Post picker helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch published posts/pages for the Pages picker.
	 * Returns [ 'rows' => WP_Post[], 'total' => int, 'types' => array ]
	 */
	public static function browse_posts( string $search, string $type, int $page, int $per = 10 ): array {
		global $wpdb;

		$all_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $all_types['attachment'] );

		// Values come from get_post_types() — registered post type slugs, not user input.
		// We still pass them through prepare() via %s placeholders to satisfy static analysis.
		$type_names = ( $type && isset( $all_types[ $type ] ) ) ? [ $type ] : array_keys( $all_types );
		if ( empty( $type_names ) ) { $type_names = [ 'post', 'page' ]; }

		$offset       = max( 0, ( $page - 1 ) * $per );
		$placeholders = implode( ', ', array_fill( 0, count( $type_names ), '%s' ) );

		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// COUNT query — post types + search term, all via prepare()
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ({$placeholders}) AND post_title LIKE %s",
					array_merge( [ 'publish' ], $type_names, [ $like ] )
				)
			);

			// Data query — same conditions + pagination
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ({$placeholders}) AND post_title LIKE %s ORDER BY post_title ASC LIMIT %d OFFSET %d",
					array_merge( [ 'publish' ], $type_names, [ $like, $per, $offset ] )
				)
			) ?: [];

		} else {
			// COUNT query — post types only
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ({$placeholders})",
					array_merge( [ 'publish' ], $type_names )
				)
			);

			// Data query — post types + pagination
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN ({$placeholders}) ORDER BY post_title ASC LIMIT %d OFFSET %d",
					array_merge( [ 'publish' ], $type_names, [ $per, $offset ] )
				)
			) ?: [];
		}

		$types = [];
		foreach ( $all_types as $k => $obj ) {
			$types[] = [ 'k' => $k, 'l' => $obj->labels->singular_name ?: $obj->label ];
		}

		return [
			'rows'      => $rows,
			'total'     => $total,
			'all_types' => $all_types,
			'types'     => $types,
		];
	}
}
