<?php
/**
 * Abilities (WordPress Abilities API) so AI agents can scan and test.
 *
 * Every ability is public (REST API and MCP) and requires the
 * manage_options capability. MCP servers that expose WordPress abilities,
 * such as the WordPress MCP Adapter, list them automatically.
 *
 * @package NahnuAS
 */

namespace NahnuAS;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's abilities.
 */
class Abilities {

	/** Ability category slug. */
	public const CATEGORY = 'nahnu-asset-scanner';

	/** Ability name prefix. */
	private const NS = 'nahnu-asset-scanner/';

	/** Most pages one scan may cover. */
	private const MAX_PAGES = 10;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', [ self::class, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ self::class, 'register' ] );
	}

	/**
	 * Register the ability category.
	 */
	public static function register_category(): void {
		wp_register_ability_category( self::CATEGORY, [
			'label'       => __( 'Asset Scanner', 'nahnu-asset-scanner' ),
			'description' => __( 'Scan the front end for scripts, styles, cookies and domains, and test turning scripts and styles off, deferring or delaying them.', 'nahnu-asset-scanner' ),
		] );
	}

	/**
	 * Shared settings for every ability.
	 *
	 * @param bool $readonly   Does not change anything.
	 * @param bool $idempotent Same input, same effect.
	 * @return array<string,mixed>
	 */
	private static function base( bool $readonly, bool $idempotent ): array {
		return [
			'category'            => self::CATEGORY,
			'permission_callback' => static fn(): bool => current_user_can( Admin::CAP ),
			'meta'                => [
				'annotations'  => [
					'readonly'    => $readonly,
					'destructive' => false,
					'idempotent'  => $idempotent,
				],
				'public'       => true,
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			],
		];
	}

	/**
	 * JSON Schema for a set of test rules.
	 *
	 * @return array<string,mixed>
	 */
	private static function rules_schema(): array {
		return [
			'type'                 => 'object',
			'description'          => __( 'Test rules: rule key => action. Keys are "js:" or "css:" followed by host and path without query string (e.g. "js:example.com/wp-includes/js/jquery/jquery.min.js"), or "js#"/"css#" followed by the element id of inline code (e.g. "js#jquery-core-js-after"). Get keys from get-page-assets. Actions: "off", "defer" or "delay" for script files; "off" or "delay" for inline scripts; "off" or "defer" for stylesheet files; "off" for inline styles. An empty string removes a rule (merge mode).', 'nahnu-asset-scanner' ),
			'additionalProperties' => [
				'type' => 'string',
				'enum' => [ '', 'off', 'defer', 'delay' ],
			],
		];
	}

	/**
	 * Register the abilities.
	 */
	public static function register(): void {
		$url_prop = [
			'type'        => 'string',
			'description' => __( 'A front-end page on this site: a full URL or a path such as "/about/". Defaults to the home page.', 'nahnu-asset-scanner' ),
		];

		wp_register_ability( self::NS . 'get-scan-summary', self::base( true, true ) + [
			'label'            => __( 'Get scan summary', 'nahnu-asset-scanner' ),
			'description'      => __( 'Returns the latest scan: when it ran, how many pages, scripts, styles, cookies and domains it found, which page URLs were scanned, and how many test rules the current user has.', 'nahnu-asset-scanner' ),
			'input_schema'     => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false, 'default' => [] ],
			'execute_callback' => [ self::class, 'get_scan_summary' ],
		] );

		wp_register_ability( self::NS . 'run-scan', self::base( false, false ) + [
			'label'            => __( 'Run scan', 'nahnu-asset-scanner' ),
			'description'      => __( 'Scans up to 10 front-end pages and stores every script, stylesheet, cookie and external domain found, with the plugin behind each. Without input it scans the pages chosen on the Pages tab, or the home page plus recent pages and posts. Takes a few seconds per page. Read the results with get-results.', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [
					'urls'     => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'maxItems' => self::MAX_PAGES, 'description' => __( 'Page URLs to scan.', 'nahnu-asset-scanner' ) ],
					'post_ids' => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'maxItems' => self::MAX_PAGES, 'description' => __( 'IDs of published posts or pages to scan.', 'nahnu-asset-scanner' ) ],
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'execute_callback' => [ self::class, 'run_scan' ],
		] );

		wp_register_ability( self::NS . 'get-results', self::base( true, true ) + [
			'label'            => __( 'Get scan results', 'nahnu-asset-scanner' ),
			'description'      => __( 'Lists results of the latest scan for one type: "js" (scripts), "css" (stylesheets), "cookies" (with category and the plugin or service that sets each one) or "domains" (external domains). Supports search, a first/third-party filter and paging.', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [
					'type'        => [ 'type' => 'string', 'enum' => Admin::RESULT_TABS, 'default' => 'js' ],
					'search'      => [ 'type' => 'string', 'default' => '' ],
					'third_party' => [ 'type' => 'string', 'enum' => [ 'all', 'first', 'third' ], 'default' => 'all' ],
					'page'        => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'required'             => [ 'type' ],
				'additionalProperties' => false,
			],
			'execute_callback' => [ self::class, 'get_results' ],
		] );

		wp_register_ability( self::NS . 'get-page-assets', self::base( true, true ) + [
			'label'            => __( 'Get page assets', 'nahnu-asset-scanner' ),
			'description'      => __( 'Renders one page and lists every script and stylesheet on it in page order, with the rule key to use in test rules, the plugin or theme behind it, whether it is third-party, whether it blocks rendering, and the action the current test rules give it.', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [ 'url' => $url_prop ],
				'additionalProperties' => false,
				'default'              => [],
			],
			'execute_callback' => [ self::class, 'get_page_assets' ],
		] );

		wp_register_ability( self::NS . 'get-test-rules', self::base( true, true ) + [
			'label'            => __( 'Get test rules', 'nahnu-asset-scanner' ),
			'description'      => __( 'Returns the current user\'s saved test rules (the ones applied when they open a page with ?nahnuas_test=1).', 'nahnu-asset-scanner' ),
			'input_schema'     => [ 'type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false, 'default' => [] ],
			'execute_callback' => static fn(): array => [ 'rules' => (object) Test_Mode::get_rules() ],
		] );

		wp_register_ability( self::NS . 'set-test-rules', self::base( false, true ) + [
			'label'            => __( 'Set test rules', 'nahnu-asset-scanner' ),
			'description'      => __( 'Saves the current user\'s test rules. They only affect pages that user opens with ?nahnuas_test=1 while logged in, never visitors. Mode "merge" (default) adds or changes the given rules ("" removes one); "replace" replaces all rules (an empty object clears them).', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [
					'rules' => self::rules_schema(),
					'mode'  => [ 'type' => 'string', 'enum' => [ 'merge', 'replace' ], 'default' => 'merge' ],
				],
				'required'             => [ 'rules' ],
				'additionalProperties' => false,
			],
			'execute_callback' => [ self::class, 'set_test_rules' ],
		] );

		wp_register_ability( self::NS . 'run-test', self::base( false, false ) + [
			'label'            => __( 'Run test', 'nahnu-asset-scanner' ),
			'description'      => __( 'Tests a page with scripts or styles turned off, deferred or delayed, without changing the live site. The server renders the page with the rules and returns what changed, render-blocking files before and after, and dependency warnings (scripts that will break because a file they need was held back). It also returns browser_url, a one-time link valid for 30 minutes: open it in a real browser, wait until the page shows "Nahnu test report sent", then call get-test-report with the token to get the JavaScript errors from the browser. Uses the saved test rules unless "rules" is given.', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [
					'url'   => $url_prop,
					'rules' => self::rules_schema(),
				],
				'additionalProperties' => false,
				'default'              => [],
			],
			'execute_callback' => [ self::class, 'run_test' ],
		] );

		wp_register_ability( self::NS . 'get-test-report', self::base( true, true ) + [
			'label'            => __( 'Get test report', 'nahnu-asset-scanner' ),
			'description'      => __( 'Returns a test run started by run-test: the server analysis and, once its browser_url has been opened in a browser, the JavaScript errors, whether delayed scripts ran, and the page load time.', 'nahnu-asset-scanner' ),
			'input_schema'     => [
				'type'                 => 'object',
				'properties'           => [ 'token' => [ 'type' => 'string', 'pattern' => '^[a-z0-9]{32}$' ] ],
				'required'             => [ 'token' ],
				'additionalProperties' => false,
			],
			'execute_callback' => [ self::class, 'get_test_report' ],
		] );
	}

	// -------------------------------------------------------------------------
	// Callbacks
	// -------------------------------------------------------------------------

	/**
	 * Latest scan overview.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_scan_summary(): array {
		$scan = DB::get_latest_scan();
		return [
			'scan'       => $scan ? self::scan_fields( $scan ) : null,
			'pages'      => $scan ? DB::get_page_urls( (int) $scan['id'] ) : [],
			'test_rules' => count( Test_Mode::get_rules() ),
		];
	}

	/**
	 * Run a scan synchronously.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function run_scan( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$pages = [];
		foreach ( (array) ( $input['post_ids'] ?? [] ) as $id ) {
			$post = get_post( (int) $id );
			if ( $post && 'publish' === $post->post_status ) {
				$pages[] = [ 'url' => (string) get_permalink( $post ), 'page_type' => $post->post_type ];
			}
		}
		foreach ( (array) ( $input['urls'] ?? [] ) as $url ) {
			$url = self::site_url( (string) $url );
			if ( is_wp_error( $url ) ) {
				return $url;
			}
			$pages[] = [ 'url' => $url, 'page_type' => 'custom' ];
		}
		if ( ! $pages ) {
			$pages = Scanner::get_pages_to_scan();
		}
		$pages = array_slice( $pages, 0, self::MAX_PAGES );

		$scan_id = DB::create_scan();
		if ( ! $scan_id ) {
			return new \WP_Error( 'nahnuas_scan', __( 'Could not create the scan record.', 'nahnu-asset-scanner' ) );
		}
		DB::reset_domain_cache();

		$report = [];
		foreach ( $pages as $page ) {
			$result   = Scanner::scan_page( $scan_id, $page['url'], $page['page_type'] );
			$report[] = [
				'url'     => $page['url'],
				'success' => ! empty( $result['success'] ),
				'error'   => (string) ( $result['error'] ?? $result['message'] ?? '' ),
			];
		}
		$scan = DB::finalize_scan( $scan_id );

		return [
			'scan'  => $scan ? self::scan_fields( $scan ) : null,
			'pages' => $report,
		];
	}

	/**
	 * Paged results of the latest scan.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>
	 */
	public static function get_results( $input = [] ): array {
		$input = is_array( $input ) ? $input : [];
		$tab   = in_array( $input['type'] ?? '', Admin::RESULT_TABS, true ) ? $input['type'] : 'js';
		$args  = [
			'page'        => max( 1, (int) ( $input['page'] ?? 1 ) ),
			'per_page'    => min( 100, max( 1, (int) ( $input['per_page'] ?? 50 ) ) ),
			'search'      => sanitize_text_field( (string) ( $input['search'] ?? '' ) ),
			'third_party' => [ 'third' => '1', 'first' => '0' ][ $input['third_party'] ?? 'all' ] ?? '',
		];
		$items = Admin::enrich_results( $tab, DB::get_results( $tab, $args ) );
		$total = DB::get_results_count( $tab, $args );

		foreach ( $items as &$item ) {
			unset( $item['asset_url_hash'] );
			if ( isset( $item['attributes_json'] ) ) {
				$item['attributes'] = json_decode( (string) $item['attributes_json'], true ) ?: [];
				unset( $item['attributes_json'] );
			}
			if ( in_array( $tab, [ 'js', 'css' ], true ) && empty( $item['is_inline'] ) && ! empty( $item['asset_url'] ) ) {
				$item['rule_key'] = Test_Mode::file_key( $tab, (string) $item['asset_url'] );
			}
		}
		unset( $item );

		return [
			'type'     => $tab,
			'total'    => $total,
			'page'     => $args['page'],
			'pages'    => (int) ceil( $total / $args['per_page'] ),
			'items'    => $items,
		];
	}

	/**
	 * Scripts and styles of one page with their rule keys.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_page_assets( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$url   = self::site_url( (string) ( $input['url'] ?? '' ) );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$rules  = Test_Mode::get_rules();
		$server = self::render( $url, $rules );
		if ( is_wp_error( $server ) ) {
			return $server;
		}
		return [
			'url'             => $url,
			'scripts'         => $server['scripts'],
			'styles'          => $server['styles'],
			'render_blocking' => $server['render_blocking']['before'],
			'assets'          => $server['assets'],
		];
	}

	/**
	 * Save test rules.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>
	 */
	public static function set_test_rules( $input = [] ): array {
		$input = is_array( $input ) ? $input : [];
		$given = (array) ( $input['rules'] ?? [] );
		$rules = 'replace' === ( $input['mode'] ?? 'merge' ) ? [] : Test_Mode::get_rules();
		foreach ( $given as $key => $action ) {
			unset( $rules[ $key ] );
			if ( '' !== $action ) {
				$rules[ $key ] = $action;
			}
		}
		$clean    = Test_Mode::sanitize_rules( $rules );
		$rejected = array_values( array_diff( array_keys( array_filter( $given, 'strlen' ) ), array_keys( $clean ) ) );

		if ( $clean ) {
			update_user_meta( get_current_user_id(), Test_Mode::META, $clean );
		} else {
			delete_user_meta( get_current_user_id(), Test_Mode::META );
		}
		return [
			'rules'    => (object) $clean,
			'rejected' => $rejected,
			'test_url' => add_query_arg( Test_Mode::PARAM, '1', home_url( '/' ) ),
		];
	}

	/**
	 * Start a test run.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function run_test( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$url   = self::site_url( (string) ( $input['url'] ?? '' ) );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$rules = isset( $input['rules'] )
			? Test_Mode::sanitize_rules( array_filter( (array) $input['rules'], 'strlen' ) )
			: Test_Mode::get_rules();

		$run    = Test_Mode::create_run( $url, $rules );
		$server = self::render_run( $run );
		if ( is_wp_error( $server ) ) {
			return $server;
		}
		unset( $server['assets'] );

		return [
			'token'       => $run['token'],
			'browser_url' => $run['url'],
			'expires_at'  => gmdate( 'c', $run['expires'] ),
			'rules'       => (object) $rules,
			'server'      => $server,
			'next_step'   => __( 'Open browser_url in a browser, wait until the page shows "Nahnu test report sent", then call get-test-report with this token.', 'nahnu-asset-scanner' ),
		];
	}

	/**
	 * Read a test run.
	 *
	 * @param array<string,mixed>|null $input Input.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_test_report( $input = [] ) {
		$input = is_array( $input ) ? $input : [];
		$run   = Test_Mode::get_run( (string) ( $input['token'] ?? '' ), true );
		if ( ! $run ) {
			return new \WP_Error( 'nahnuas_run', __( 'Unknown or expired test run. Start a new one with run-test.', 'nahnu-asset-scanner' ) );
		}
		$server = $run['server'];
		if ( is_array( $server ) ) {
			unset( $server['assets'] );
		}
		return [
			'url'            => $run['url'],
			'rules'          => (object) $run['rules'],
			'server'         => $server,
			'browser_report' => $run['report'],
			'status'         => $run['report'] ? 'complete' : 'waiting_for_browser',
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Fields of a scan row for output.
	 *
	 * @param array<string,mixed> $scan Scan row.
	 * @return array<string,mixed>
	 */
	private static function scan_fields( array $scan ): array {
		return [
			'id'           => (int) $scan['id'],
			'status'       => (string) $scan['status'],
			'started_at'   => (string) $scan['started_at'],
			'completed_at' => (string) ( $scan['completed_at'] ?? '' ),
			'pages'        => (int) $scan['pages_scanned'],
			'scripts'      => (int) $scan['total_js'],
			'styles'       => (int) $scan['total_css'],
			'cookies'      => (int) $scan['total_cookies'],
			'domains'      => (int) $scan['total_domains'],
		];
	}

	/**
	 * Resolve a URL or path to a front-end URL on this site.
	 *
	 * @return string|\WP_Error
	 */
	private static function site_url( string $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			$url = '/';
		}
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$url = home_url( $url );
		}
		$home   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( '' === $host || $host !== $home || ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return new \WP_Error( 'nahnuas_url', __( 'The URL must be a page on this site.', 'nahnu-asset-scanner' ) );
		}
		return esc_url_raw( $url );
	}

	/**
	 * Render a page with rules through a temporary test run and return the
	 * server analysis.
	 *
	 * @param string               $url   Page URL on this site.
	 * @param array<string,string> $rules Rules.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function render( string $url, array $rules ) {
		$run    = Test_Mode::create_run( $url, $rules );
		$result = self::render_run( $run );
		delete_transient( Test_Mode::RUN_PREFIX . $run['token'] );
		return $result;
	}

	/**
	 * Request a test-run URL from this site (loopback) and read the analysis
	 * the page stored while rendering.
	 *
	 * @param array{token:string,url:string,expires:int} $run Run from Test_Mode::create_run().
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function render_run( array $run ) {
		$timeout  = min( 120, max( 5, (int) get_option( 'nahnuas_scan_timeout', Scanner::DEFAULT_TIMEOUT ) ) );
		$response = wp_remote_get( $run['url'], [
			'timeout'    => $timeout,
			'redirection' => 3,
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter for loopback requests to this site.
			'sslverify'  => apply_filters( 'https_local_ssl_verify', false ),
			'user-agent' => 'NahnuAssetScanner/' . NAHNUAS_VERSION . '; ' . home_url( '/' ),
			'headers'    => [ 'Cache-Control' => 'no-cache' ],
		] );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'nahnuas_loopback', sprintf(
				/* translators: %s: error message. */
				__( 'The site could not load its own page: %s', 'nahnu-asset-scanner' ),
				$response->get_error_message()
			) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = Test_Mode::get_run( $run['token'], true );
		if ( ! $data || ! is_array( $data['server'] ) ) {
			return new \WP_Error( 'nahnuas_render', sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The page (HTTP %d) was not rendered by WordPress with test mode. It may be served from a server cache or not be an HTML page.', 'nahnu-asset-scanner' ),
				$code
			) );
		}
		return [ 'http_status' => $code ] + $data['server'];
	}
}
