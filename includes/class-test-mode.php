<?php
/**
 * Front-end Test Mode.
 *
 * An administrator opens any front-end URL with ?nahnuas_test=1 to preview the
 * page with chosen scripts and stylesheets turned off, deferred or delayed.
 * The rules are stored per user, only apply to that administrator's own
 * requests that carry the query parameter, and are never cached. Visitors
 * and the saved site are never changed.
 *
 * @package NahnuAS
 */

namespace NahnuAS;

defined( 'ABSPATH' ) || exit;

/**
 * Test Mode controller.
 */
class Test_Mode {

	/** Query parameter that turns test mode on. */
	public const PARAM = 'nahnuas_test';

	/** User meta key holding the current user's rules. */
	public const META = 'nahnuas_test_rules';

	/** Nonce action for saving rules. */
	public const NONCE = 'nahnuas_test';

	/** Transient prefix for AI / API test runs. */
	public const RUN_PREFIX = 'nahnuas_run_';

	/** How long a test run link stays valid. */
	public const RUN_TTL = 30 * MINUTE_IN_SECONDS;

	/** Upper limit on stored rules. */
	private const MAX_RULES = 500;

	/** Script types the browser executes. */
	private const JS_TYPES = [ '', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript', 'application/x-javascript', 'text/jscript' ];

	/**
	 * Allowed actions per asset kind.
	 *
	 * Inline scripts cannot be deferred by the browser, and inline styles can
	 * only be switched off.
	 */
	private const ACTIONS = [
		'js'         => [ 'off', 'defer', 'delay' ],
		'js-inline'  => [ 'off', 'delay' ],
		'css'        => [ 'off', 'defer' ],
		'css-inline' => [ 'off' ],
	];

	/** @var bool|null Cached result of is_active(). */
	private static ?bool $active = null;

	/** @var array<string,mixed>|null Test run loaded from a token, if any. */
	private static ?array $run = null;

	/** @var string Token of the current test run. */
	private static string $run_token = '';

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'start' ], -1000 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ], 1000 );
		add_action( 'admin_bar_menu', [ self::class, 'admin_bar' ], 90 );
		add_action( 'wp_ajax_nahnuas_test_save', [ self::class, 'ajax_save' ] );
		add_action( 'wp_ajax_nahnuas_test_report', [ self::class, 'ajax_report' ] );
		add_action( 'wp_ajax_nopriv_nahnuas_test_report', [ self::class, 'ajax_report' ] );
	}

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	/**
	 * The raw value of the query parameter, or null when it is absent.
	 */
	private static function param_value(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch; only changes what the current administrator sees.
		if ( ! isset( $_GET[ self::PARAM ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		return sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) );
	}

	/**
	 * True on a front-end request that carries the parameter and either comes
	 * from an administrator or holds a valid test-run token.
	 */
	public static function is_active(): bool {
		if ( null === self::$active ) {
			$value        = self::param_value();
			self::$active = null !== $value
				&& ! is_admin()
				&& ! wp_doing_ajax()
				&& ! wp_is_json_request()
				&& ! is_feed()
				&& ! is_robots();
			if ( self::$active ) {
				self::$run = self::get_run( (string) $value );
				if ( self::$run ) {
					self::$run_token = (string) $value;
				} else {
					self::$active = current_user_can( Admin::CAP );
				}
			}
		}
		return self::$active;
	}

	/**
	 * True when the page is rendered for a token-based test run (AI / API).
	 */
	public static function is_run(): bool {
		return self::is_active() && null !== self::$run;
	}

	/**
	 * The rules that apply to the page being rendered.
	 *
	 * @return array<string,string>
	 */
	private static function active_rules(): array {
		if ( self::is_run() ) {
			return self::sanitize_rules( (array) ( self::$run['rules'] ?? [] ) );
		}
		return self::is_paused() ? [] : self::get_rules();
	}

	// -------------------------------------------------------------------------
	// Test runs (used by the abilities for AI agents)
	// -------------------------------------------------------------------------

	/**
	 * Create a test run: a one-time link that renders a page with the given
	 * rules for anyone holding it, logged out, for RUN_TTL seconds.
	 *
	 * The page is rendered as a visitor would see it (no admin session), so the
	 * link only exposes public content. A real browser that opens it reports
	 * JavaScript errors back to the site.
	 *
	 * @param string               $url   Front-end URL on this site.
	 * @param array<string,string> $rules Rules to apply.
	 * @return array{token:string,url:string,expires:int}
	 */
	public static function create_run( string $url, array $rules ): array {
		$token = strtolower( wp_generate_password( 32, false, false ) );
		$run   = [
			'user'    => get_current_user_id(),
			'url'     => $url,
			'rules'   => self::sanitize_rules( $rules ),
			'created' => time(),
			'server'  => null,
			'report'  => null,
		];
		set_transient( self::RUN_PREFIX . $token, $run, self::RUN_TTL );
		return [
			'token'   => $token,
			'url'     => add_query_arg( self::PARAM, $token, remove_query_arg( self::PARAM, $url ) ),
			'expires' => time() + self::RUN_TTL,
		];
	}

	/**
	 * Load a test run by token.
	 *
	 * @param string $token Token.
	 * @param bool   $fresh Re-read from the database.
	 * @return array<string,mixed>|null
	 */
	public static function get_run( string $token, bool $fresh = false ): ?array {
		if ( ! preg_match( '/^[a-z0-9]{32}$/', $token ) ) {
			return null;
		}
		if ( $fresh && ! wp_using_ext_object_cache() ) {
			// Another request (the rendered page or the browser report) may have
			// updated the run; drop this request's cached copy of the option.
			wp_cache_delete( '_transient_' . self::RUN_PREFIX . $token, 'options' );
			wp_cache_delete( '_transient_timeout_' . self::RUN_PREFIX . $token, 'options' );
		}
		$run = get_transient( self::RUN_PREFIX . $token );
		return is_array( $run ) ? $run : null;
	}

	/**
	 * Store one part of a test run ("server" analysis or browser "report").
	 *
	 * @param string $token Token.
	 * @param string $part  "server" or "report".
	 * @param array  $data  Data.
	 */
	private static function update_run( string $token, string $part, array $data ): void {
		$run = self::get_run( $token );
		if ( ! $run ) {
			return;
		}
		$run[ $part ] = $data;
		$left         = max( 60, (int) $run['created'] + self::RUN_TTL - time() );
		set_transient( self::RUN_PREFIX . $token, $run, $left );
	}

	/**
	 * True when the page is shown with the rules paused (for comparison).
	 */
	public static function is_paused(): bool {
		return 'pause' === self::param_value();
	}

	/**
	 * The current user's rules: rule key => action.
	 *
	 * @return array<string,string>
	 */
	public static function get_rules( int $user_id = 0 ): array {
		$user_id = $user_id ?: get_current_user_id();
		$rules   = get_user_meta( $user_id, self::META, true );
		return is_array( $rules ) ? self::sanitize_rules( $rules ) : [];
	}

	/**
	 * Keep only well-formed keys with an action allowed for their kind.
	 *
	 * Keys look like "js:host/path/file.js", "js#element-id",
	 * "css:host/path/file.css" or "css#element-id".
	 *
	 * @param array<mixed> $rules Raw rules.
	 * @return array<string,string>
	 */
	public static function sanitize_rules( array $rules ): array {
		$clean = [];
		foreach ( $rules as $key => $action ) {
			if ( ! is_string( $key ) || ! is_string( $action ) || strlen( $key ) > 500 ) {
				continue;
			}
			if ( ! preg_match( '/^(js|css)([:#])(\S+)$/', $key, $m ) ) {
				continue;
			}
			$kind = $m[1] . ( '#' === $m[2] ? '-inline' : '' );
			if ( in_array( $action, self::ACTIONS[ $kind ], true ) ) {
				$clean[ $key ] = $action;
			}
			if ( count( $clean ) >= self::MAX_RULES ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * Rule key for a file URL: kind, host and path, without scheme or query.
	 *
	 * The admin screen builds the same key in JavaScript from scanned URLs.
	 */
	public static function file_key( string $kind, string $url ): string {
		$url  = \WP_Http::make_absolute_url( $url, home_url( '/' ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return $kind . ':' . $host . ( '' === $path ? '/' : $path );
	}

	/**
	 * The current front-end URL, with or without the test parameter.
	 */
	private static function current_url( ?string $param = null ): string {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$url  = set_url_scheme( 'http://' . $host . $uri );
		$url  = remove_query_arg( self::PARAM, $url );
		return null === $param ? $url : add_query_arg( self::PARAM, $param, $url );
	}

	// -------------------------------------------------------------------------
	// Front end
	// -------------------------------------------------------------------------

	/**
	 * Start buffering the page and make sure nothing caches it.
	 */
	public static function start(): void {
		if ( ! self::is_active() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- standard constant honored by cache plugins.
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		// Not flushable: a stray ob_flush() must not hand us half a page.
		ob_start( [ self::class, 'process' ], 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE );
	}

	/**
	 * Load the floating test panel.
	 */
	public static function enqueue(): void {
		if ( ! self::is_active() || self::is_run() ) {
			return;
		}
		wp_enqueue_script( 'nahnuas-test-panel', NAHNUAS_URL . 'public/test-panel.js', [], NAHNUAS_VERSION, [ 'in_footer' => true ] );
		wp_localize_script( 'nahnuas-test-panel', 'nahnuasTest', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( self::NONCE ),
			'param'    => self::PARAM,
			'paused'   => self::is_paused(),
			'rules'    => (object) self::get_rules(),
			'cssUrl'   => NAHNUAS_URL . 'public/test-panel.css?ver=' . rawurlencode( NAHNUAS_VERSION ),
			'adminUrl' => admin_url( 'admin.php?page=nahnu-asset-scanner#test' ),
			'exitUrl'  => self::current_url(),
			'strings'  => [
				'title'        => __( 'Asset Test Mode', 'nahnu-asset-scanner' ),
				'scripts'      => __( 'Scripts', 'nahnu-asset-scanner' ),
				'styles'       => __( 'Styles', 'nahnu-asset-scanner' ),
				'errors'       => __( 'Errors', 'nahnu-asset-scanner' ),
				'search'       => __( 'Filter by file or plugin…', 'nahnu-asset-scanner' ),
				'all'          => __( 'All', 'nahnu-asset-scanner' ),
				'changed'      => __( 'Changed', 'nahnu-asset-scanner' ),
				'thirdParty'   => __( '3rd-party', 'nahnu-asset-scanner' ),
				'on'           => __( 'On', 'nahnu-asset-scanner' ),
				'off'          => __( 'Off', 'nahnu-asset-scanner' ),
				'defer'        => __( 'Defer', 'nahnu-asset-scanner' ),
				'delay'        => __( 'Delay', 'nahnu-asset-scanner' ),
				'inline'       => __( 'Inline', 'nahnu-asset-scanner' ),
				'noId'         => __( 'Inline code without an id cannot be targeted.', 'nahnu-asset-scanner' ),
				'apply'        => __( 'Apply & reload', 'nahnu-asset-scanner' ),
				'saving'       => __( 'Saving…', 'nahnu-asset-scanner' ),
				'reset'        => __( 'Reset all', 'nahnu-asset-scanner' ),
				'pending'      => __( 'Unsaved changes', 'nahnu-asset-scanner' ),
				'rulesActive'  => __( 'Rules on', 'nahnu-asset-scanner' ),
				'rulesPaused'  => __( 'Rules paused', 'nahnu-asset-scanner' ),
				'pauseHint'    => __( 'Compare with and without your rules.', 'nahnu-asset-scanner' ),
				'exit'         => __( 'Exit test mode', 'nahnu-asset-scanner' ),
				'scanner'      => __( 'Open scanner', 'nahnu-asset-scanner' ),
				'bulk'         => __( 'Quick actions', 'nahnu-asset-scanner' ),
				'allJsOff'     => __( 'All JS off', 'nahnu-asset-scanner' ),
				'allJsDefer'   => __( 'Defer all JS', 'nahnu-asset-scanner' ),
				'allJsDelay'   => __( 'Delay all JS', 'nahnu-asset-scanner' ),
				'thirdJsDelay' => __( 'Delay 3rd-party JS', 'nahnu-asset-scanner' ),
				'allCssOff'    => __( 'All CSS off', 'nahnu-asset-scanner' ),
				'allCssDefer'  => __( 'Defer all CSS', 'nahnu-asset-scanner' ),
				'noErrors'     => __( 'No JavaScript errors on this page.', 'nahnu-asset-scanner' ),
				'likely'       => __( 'Likely', 'nahnu-asset-scanner' ),
				'errorsHint'   => __( 'Errors that appear after a change usually mean that file is not safe to change.', 'nahnu-asset-scanner' ),
				'delayWaiting' => __( 'Delayed scripts wait for the first scroll, tap, click or key press.', 'nahnu-asset-scanner' ),
				'delayRan'     => __( 'Delayed scripts have run.', 'nahnu-asset-scanner' ),
				'nothing'      => __( 'Nothing matches.', 'nahnu-asset-scanner' ),
				'saveFailed'   => __( 'Could not save. Please try again.', 'nahnu-asset-scanner' ),
				'collapse'     => __( 'Minimize', 'nahnu-asset-scanner' ),
				'expand'       => __( 'Open test panel', 'nahnu-asset-scanner' ),
				'onlyYou'      => __( 'Only you see these changes. Visitors get the normal page.', 'nahnu-asset-scanner' ),
				'legendOff'    => __( 'Off: not loaded at all.', 'nahnu-asset-scanner' ),
				'legendDefer'  => __( 'Defer: JS runs after the HTML is parsed; CSS loads without blocking the page.', 'nahnu-asset-scanner' ),
				'legendDelay'  => __( 'Delay: JS waits for the first user interaction.', 'nahnu-asset-scanner' ),
			],
		] );
	}

	/**
	 * Admin bar shortcut to enter or leave test mode.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public static function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( is_admin() || ! current_user_can( Admin::CAP ) ) {
			return;
		}
		$active = self::is_active();
		$count  = count( self::get_rules() );
		$title  = $active
			? __( 'Exit asset test', 'nahnu-asset-scanner' )
			: __( 'Test assets', 'nahnu-asset-scanner' );
		if ( ! $active && $count ) {
			/* translators: %s: number of active test rules. */
			$title .= ' ' . sprintf( _n( '(%s rule)', '(%s rules)', $count, 'nahnu-asset-scanner' ), number_format_i18n( $count ) );
		}
		$bar->add_node( [
			'id'    => 'nahnuas-test',
			'title' => '<span class="ab-icon dashicons dashicons-search" style="top:2px" aria-hidden="true"></span>' . esc_html( $title ),
			'href'  => esc_url( $active ? self::current_url() : self::current_url( '1' ) ),
			'meta'  => [ 'title' => __( 'Preview this page with scripts or styles turned off, deferred or delayed. Only you see it.', 'nahnu-asset-scanner' ) ],
		] );
	}

	/**
	 * Output buffer callback: apply the rules and add the panel data.
	 *
	 * @param string $html Page HTML.
	 */
	public static function process( string $html ): string {
		if ( ! self::is_html_response( $html ) ) {
			return $html;
		}

		$rules    = self::active_rules();
		$assets   = self::collect( $html, $rules );
		$warnings = self::dependency_warnings( $assets );
		if ( $rules ) {
			$html = self::apply( $html, $assets );
		}

		$json  = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;
		$clean = array_map(
			static fn( array $a ): array => array_diff_key( $a, [ 'parent' => 1, 'handle' => 1 ] ),
			$assets
		);

		$extra = '';
		if ( self::is_run() ) {
			self::update_run( self::$run_token, 'server', self::summarize( $assets, $warnings ) );
			$extra = '<script type="application/json" id="nahnuas-test-run">' . wp_json_encode( [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'token'   => self::$run_token,
			], $json ) . '</script>' . "\n";
		}

		$head = wp_get_inline_script_tag( self::error_catcher(), [ 'id' => 'nahnuas-test-errors' ] );
		$foot = '<script type="application/json" id="nahnuas-test-data">' . wp_json_encode( $clean, $json ) . '</script>' . "\n"
			. '<script type="application/json" id="nahnuas-test-warnings">' . wp_json_encode( $warnings, $json ) . '</script>' . "\n"
			. $extra
			. wp_get_script_tag( [
				'src' => NAHNUAS_URL . 'public/test-runtime.js?ver=' . rawurlencode( NAHNUAS_VERSION ),
				'id'  => 'nahnuas-test-runtime-js',
			] );

		$html = (string) preg_replace( '/<head\b[^>]*>/i', '$0' . "\n" . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $head ), $html, 1 );
		$pos  = strripos( $html, '</body>' );
		return false === $pos ? $html . $foot : substr_replace( $html, $foot, $pos, 0 );
	}

	/**
	 * Only touch complete HTML documents.
	 *
	 * @param string $html Buffered output.
	 */
	private static function is_html_response( string $html ): bool {
		if ( ! preg_match( '/<html\b/i', $html ) ) {
			return false;
		}
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Small script placed first in <head> that records JavaScript errors and
	 * failed file loads so the panel can show them.
	 */
	private static function error_catcher(): string {
		return 'window.nahnuasErrors=[];(function(w){function p(e){w.nahnuasErrors.push(e);try{w.dispatchEvent(new CustomEvent("nahnuas:error",{detail:e}));}catch(x){}}'
			. 'w.addEventListener("error",function(e){var t=e.target;if(t&&t!==w&&(t.src||t.href)){if(!/^(SCRIPT|LINK)$/.test(t.tagName)){return;}p({type:"load",message:"Failed to load",source:t.src||t.href});return;}'
			. 'p({type:"js",message:e.message||"Error",source:e.filename||"",line:e.lineno||0});},true);'
			. 'w.addEventListener("unhandledrejection",function(e){var r=e.reason;p({type:"promise",message:String(r&&r.message||r),source:""});});})(window);';
	}

	/**
	 * Walk every executable script, stylesheet link and style element in order.
	 *
	 * The callback receives the processor and a description of the tag. Both
	 * passes (collect and apply) use this walker so their indexes line up.
	 *
	 * @param \WP_HTML_Tag_Processor $p        Processor.
	 * @param callable               $callback fn( WP_HTML_Tag_Processor $p, array $tag, int $index ).
	 */
	private static function walk( \WP_HTML_Tag_Processor $p, callable $callback ): void {
		$index   = 0;
		$in_body = false;
		while ( $p->next_tag() ) {
			$tag = $p->get_tag();
			if ( 'BODY' === $tag ) {
				$in_body = true;
			}
			if ( ! in_array( $tag, [ 'SCRIPT', 'LINK', 'STYLE' ], true ) ) {
				continue;
			}
			$id = (string) $p->get_attribute( 'id' );
			if ( str_starts_with( $id, 'nahnuas-' ) ) {
				continue;
			}

			$info = null;
			if ( 'SCRIPT' === $tag ) {
				$type = strtolower( trim( (string) $p->get_attribute( 'type' ) ) );
				if ( ! in_array( $type, self::JS_TYPES, true ) ) {
					continue;
				}
				$src = trim( (string) $p->get_attribute( 'src' ) );
				if ( '' !== $src ) {
					$info = [ 'kind' => 'js', 'inline' => false, 'url' => $src ];
				} else {
					$text = trim( (string) $p->get_modifiable_text() );
					if ( '' === $text ) {
						continue;
					}
					$info = [ 'kind' => 'js', 'inline' => true, 'text' => $text ];
				}
				$info['type']  = $type;
				$info['async'] = null !== $p->get_attribute( 'async' );
				$info['defer'] = null !== $p->get_attribute( 'defer' );
			} elseif ( 'LINK' === $tag ) {
				$rel  = strtolower( (string) $p->get_attribute( 'rel' ) );
				$href = trim( (string) $p->get_attribute( 'href' ) );
				if ( '' === $href || ! in_array( 'stylesheet', preg_split( '/\s+/', $rel ), true ) ) {
					continue;
				}
				$info = [ 'kind' => 'css', 'inline' => false, 'url' => $href, 'media' => strtolower( trim( (string) $p->get_attribute( 'media' ) ) ) ];
			} else {
				$text = trim( (string) $p->get_modifiable_text() );
				if ( '' === $text ) {
					continue;
				}
				$info = [ 'kind' => 'css', 'inline' => true, 'text' => $text ];
			}

			$info['id']   = $id;
			$info['head'] = ! $in_body;
			$callback( $p, $info, $index );
			++$index;
		}
	}

	/**
	 * First pass: list every asset and work out the action for each.
	 *
	 * @param string               $html  Page HTML.
	 * @param array<string,string> $rules Rules.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect( string $html, array $rules ): array {
		$assets = [];
		$by_id  = [];
		self::walk(
			new \WP_HTML_Tag_Processor( $html ),
			static function ( \WP_HTML_Tag_Processor $p, array $info, int $index ) use ( $rules, &$assets, &$by_id ): void {
				$kind = $info['kind'];
				if ( $info['inline'] ) {
					$key   = '' !== $info['id'] ? $kind . '#' . $info['id'] : '';
					$label = '' !== $info['id'] ? $info['id'] : __( '(inline, no id)', 'nahnu-asset-scanner' );
					$url   = '';
					$owner = '';
					$third = false;
				} else {
					$url   = \WP_Http::make_absolute_url( $info['url'], home_url( '/' ) );
					$key   = self::file_key( $kind, $url );
					$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
					$label = basename( $path );
					if ( ! str_contains( $label, '.' ) ) {
						// Extension-less endpoints such as js.stripe.com/v3/ read better with their host.
						$label = (string) wp_parse_url( $url, PHP_URL_HOST ) . rtrim( $path, '/' );
					}
					$owner = self::owner( $url );
					$third = self::is_third_party( $url );
				}
				$asset = [
					'i'       => $index,
					'key'     => $key,
					'kind'    => $kind,
					'inline'  => $info['inline'],
					'label'   => $label,
					'url'     => $url,
					'id'      => $info['id'],
					'owner'   => $owner,
					'third'   => $third,
					'module'  => 'module' === ( $info['type'] ?? '' ),
					'action'  => '' !== $key ? ( $rules[ $key ] ?? '' ) : '',
					'preview' => $info['inline'] ? mb_substr( $info['text'], 0, 120 ) : '',
					'follows'  => '',
					'parent'   => null,
					'handle'   => preg_match( '/^(.+)-js$/', $info['id'], $hm ) ? $hm[1] : '',
					'blocking' => self::is_blocking( $info ),
				];

				// WordPress prints helper code next to a script as "{handle}-js-before",
				// "-js-extra", "-js-translations" and "-js-after". Code printed after
				// the file needs the file, so it follows the file's action.
				if ( 'js' === $kind && preg_match( '/^(.+-js)-after$/', $info['id'], $m ) ) {
					$asset['parent'] = $m[1];
				}
				$assets[ $index ] = $asset;
				if ( '' !== $info['id'] ) {
					$by_id[ $info['id'] ] = $index;
				}
			}
		);

		foreach ( $assets as $index => $asset ) {
			if ( null === $asset['parent'] || '' !== $asset['action'] || ! isset( $by_id[ $asset['parent'] ] ) ) {
				continue;
			}
			$parent = $assets[ $by_id[ $asset['parent'] ] ];
			if ( '' !== $parent['action'] ) {
				$assets[ $index ]['action']  = 'defer' === $parent['action'] ? 'domready' : $parent['action'];
				$assets[ $index ]['follows'] = $parent['label'];
			}
		}

		return array_values( $assets );
	}

	/**
	 * Second pass: rewrite the tags that have an action.
	 *
	 * Turning a script off or delaying it changes its type to one the browser
	 * does not run (so a file is not even downloaded); the runtime script runs
	 * delayed code later. Stylesheets are switched off through rel or media.
	 *
	 * @param string                         $html   Page HTML.
	 * @param array<int,array<string,mixed>> $assets Assets from collect().
	 */
	private static function apply( string $html, array $assets ): string {
		if ( ! array_filter( array_column( $assets, 'action' ) ) ) {
			return $html;
		}
		$p = new \WP_HTML_Tag_Processor( $html );
		self::walk(
			$p,
			static function ( \WP_HTML_Tag_Processor $p, array $info, int $index ) use ( $assets ): void {
				$action = $assets[ $index ]['action'] ?? '';
				if ( '' === $action ) {
					return;
				}
				$p->set_attribute( 'data-nahnuas-test', $action );

				if ( 'js' === $info['kind'] ) {
					$type = (string) $p->get_attribute( 'type' );
					switch ( $action ) {
						case 'off':
							$p->set_attribute( 'type', 'text/nahnuas-off' );
							break;
						case 'defer':
							$p->remove_attribute( 'async' );
							$p->set_attribute( 'defer', true );
							break;
						case 'delay':
						case 'domready':
							$p->set_attribute( 'data-nahnuas-type', '' === $type ? 'text/javascript' : $type );
							$p->set_attribute( 'type', 'text/nahnuas-' . $action );
							break;
					}
					return;
				}

				if ( $info['inline'] ) {
					// Inline style: off.
					$p->set_attribute( 'media', 'not all' );
				} elseif ( 'off' === $action ) {
					$p->set_attribute( 'rel', 'nahnuas-off' );
				} else {
					// Defer: load without blocking rendering, then apply.
					$media = (string) $p->get_attribute( 'media' );
					$p->set_attribute( 'data-nahnuas-media', '' === $media ? 'all' : $media );
					$p->set_attribute( 'media', 'print' );
				}
			}
		);
		return $p->get_updated_html();
	}

	/**
	 * Whether a tag blocks the first paint as written in the page.
	 *
	 * @param array<string,mixed> $info Tag description from walk().
	 */
	private static function is_blocking( array $info ): bool {
		if ( $info['inline'] ) {
			return false;
		}
		if ( 'js' === $info['kind'] ) {
			return $info['head'] && ! $info['async'] && ! $info['defer'] && 'module' !== $info['type'];
		}
		return ! in_array( $info['media'] ?? '', [ 'print', 'not all' ], true );
	}

	/**
	 * Find scripts that will break because a file they depend on (according
	 * to the WordPress script registry) is off, delayed or deferred while
	 * they still run earlier.
	 *
	 * @param array<int,array<string,mixed>> $assets Assets from collect().
	 * @return array<int,array<string,string>>
	 */
	private static function dependency_warnings( array $assets ): array {
		$by_handle = [];
		foreach ( $assets as $asset ) {
			if ( 'js' === $asset['kind'] && ! $asset['inline'] && '' !== $asset['handle'] ) {
				$by_handle[ $asset['handle'] ] = $asset;
			}
		}
		$registry = wp_scripts()->registered;
		$warnings = [];
		foreach ( $by_handle as $handle => $asset ) {
			$deps = self::resolve_deps( isset( $registry[ $handle ] ) ? (array) $registry[ $handle ]->deps : [], $registry );
			// jQuery Migrate needs jQuery but does not declare it.
			if ( 'jquery-migrate' === $handle ) {
				$deps[] = 'jquery-core';
			}
			foreach ( array_unique( $deps ) as $dep ) {
				if ( ! isset( $by_handle[ $dep ] ) ) {
					continue;
				}
				$needs = $by_handle[ $dep ];
				$own   = $asset['action'];
				$msg   = match ( true ) {
					/* translators: 1: script that breaks, 2: script it needs. */
					'off' === $needs['action'] && 'off' !== $own => __( '%1$s still loads but needs %2$s, which is off.', 'nahnu-asset-scanner' ),
					/* translators: 1: script that breaks, 2: script it needs. */
					'delay' === $needs['action'] && ! in_array( $own, [ 'delay', 'off' ], true ) => __( '%1$s runs right away but needs %2$s, which is delayed until the first interaction.', 'nahnu-asset-scanner' ),
					/* translators: 1: script that breaks, 2: script it needs. */
					'defer' === $needs['action'] && '' === $own => __( '%1$s runs right away but needs %2$s, which is deferred.', 'nahnu-asset-scanner' ),
					default => '',
				};
				if ( '' !== $msg ) {
					$warnings[] = [
						'script'    => $asset['label'],
						'key'       => $asset['key'],
						'needs'     => $needs['label'],
						'needs_key' => $needs['key'],
						'message'   => sprintf( $msg, $asset['label'], $needs['label'] ),
					];
				}
			}
		}
		return $warnings;
	}

	/**
	 * Replace alias handles (registered without a file, such as "jquery")
	 * with the handles they stand for.
	 *
	 * @param string[]               $deps     Dependency handles.
	 * @param array<string,\_WP_Dependency> $registry Registered scripts.
	 * @param int                    $depth    Recursion guard.
	 * @return string[]
	 */
	private static function resolve_deps( array $deps, array $registry, int $depth = 0 ): array {
		$out = [];
		foreach ( $deps as $dep ) {
			$item = $registry[ $dep ] ?? null;
			if ( $item && ! $item->src && $depth < 5 ) {
				$out = array_merge( $out, self::resolve_deps( (array) $item->deps, $registry, $depth + 1 ) );
			} else {
				$out[] = $dep;
			}
		}
		return $out;
	}

	/**
	 * Server-side summary of a rendered test page.
	 *
	 * @param array<int,array<string,mixed>> $assets   Assets from collect().
	 * @param array<int,array<string,string>> $warnings Dependency warnings.
	 * @return array<string,mixed>
	 */
	public static function summarize( array $assets, array $warnings ): array {
		$changes = [];
		$count   = [ 'scripts' => 0, 'styles' => 0 ];
		$before  = 0;
		$after   = 0;
		foreach ( $assets as $asset ) {
			++$count[ 'js' === $asset['kind'] ? 'scripts' : 'styles' ];
			if ( $asset['blocking'] ) {
				++$before;
				if ( '' === $asset['action'] ) {
					++$after;
				}
			}
			if ( '' !== $asset['action'] ) {
				$changes[] = [
					'kind'    => $asset['kind'],
					'label'   => $asset['label'],
					'key'     => $asset['key'],
					'owner'   => $asset['owner'],
					'action'  => 'domready' === $asset['action'] ? 'defer' : $asset['action'],
					'follows' => $asset['follows'],
				];
			}
		}
		return [
			'rendered_at'     => time(),
			'scripts'         => $count['scripts'],
			'styles'          => $count['styles'],
			'render_blocking' => [ 'before' => $before, 'after' => $after ],
			'changes'         => $changes,
			'warnings'        => $warnings,
			'assets'          => array_map(
				static fn( array $a ): array => [
					'kind'     => $a['kind'],
					'label'    => $a['label'],
					'key'      => $a['key'],
					'owner'    => $a['owner'],
					'url'      => $a['url'],
					'inline'   => $a['inline'],
					'third'    => $a['third'],
					'blocking' => $a['blocking'],
					'action'   => 'domready' === $a['action'] ? 'defer' : $a['action'],
					'follows'  => $a['follows'],
				],
				$assets
			),
		];
	}

	/**
	 * Human-readable owner of a file: plugin, theme, WordPress core or host.
	 */
	public static function owner( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( preg_match( '#/plugins/([^/]+)/#', $path, $m ) ) {
			return Plugin_Map::get_name( $m[1] ) ?? ucwords( str_replace( [ '-', '_' ], ' ', $m[1] ) );
		}
		if ( preg_match( '#/themes/([^/]+)/#', $path, $m ) ) {
			/* translators: %s: theme name. */
			return sprintf( __( '%s (theme)', 'nahnu-asset-scanner' ), ucwords( str_replace( [ '-', '_' ], ' ', $m[1] ) ) );
		}
		if ( str_contains( $path, '/wp-includes/' ) ) {
			return __( 'WordPress core', 'nahnu-asset-scanner' );
		}
		return strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	/**
	 * Whether a URL is served from another site.
	 */
	private static function is_third_party( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		return '' !== $host && Scanner::base_domain( $host ) !== Scanner::base_domain( $site );
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	/**
	 * Receive the browser report of a test run.
	 *
	 * Open to logged-out requests because the test run link is opened by an AI
	 * agent's browser that may not be logged in. The 32-character token in the
	 * request is the credential: it must match a stored, unexpired test run,
	 * and the report is only stored on that run.
	 */
	public static function ajax_report(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- authenticated by the test-run token, see above.
		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		if ( ! self::get_run( $token ) ) {
			wp_send_json_error( [ 'message' => 'Unknown or expired test run.' ], 404 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded JSON; every field is sanitized below.
		$raw = isset( $_POST['report'] ) ? (string) wp_unslash( $_POST['report'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( strlen( $raw ) > 100000 ) {
			wp_send_json_error( [ 'message' => 'Report too large.' ], 413 );
		}
		$data   = json_decode( $raw, true );
		$data   = is_array( $data ) ? $data : [];
		$errors = [];
		foreach ( array_slice( (array) ( $data['errors'] ?? [] ), 0, 100 ) as $error ) {
			if ( ! is_array( $error ) ) {
				continue;
			}
			$errors[] = [
				'type'    => sanitize_key( (string) ( $error['type'] ?? 'js' ) ),
				'message' => mb_substr( sanitize_text_field( (string) ( $error['message'] ?? '' ) ), 0, 500 ),
				'source'  => mb_substr( esc_url_raw( (string) ( $error['source'] ?? '' ) ), 0, 500 ),
				'line'    => absint( $error['line'] ?? 0 ),
			];
		}
		self::update_run( $token, 'report', [
			'received_at'   => time(),
			'errors'        => $errors,
			'delayed'       => absint( $data['delayed'] ?? 0 ),
			'delayed_ran'   => ! empty( $data['delayedRan'] ),
			'css_deferred'  => absint( $data['cssDeferred'] ?? 0 ),
			'title'         => mb_substr( sanitize_text_field( (string) ( $data['title'] ?? '' ) ), 0, 200 ),
			'load_ms'       => absint( $data['loadMs'] ?? 0 ),
		] );
		wp_send_json_success( [ 'errors' => count( $errors ) ] );
	}

	/**
	 * Save rules for the current user.
	 *
	 * Accepts either "rules" (a JSON object that replaces every rule) or a
	 * single "key" with an "action" (empty to remove that rule).
	 */
	public static function ajax_save(): void {
		$nonce_ok = check_ajax_referer( self::NONCE, 'nonce', false ) || check_ajax_referer( Admin::NONCE, 'nonce', false );
		if ( ! current_user_can( Admin::CAP ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to do this.', 'nahnu-asset-scanner' ) ], 403 );
		}
		if ( ! $nonce_ok ) {
			wp_send_json_error( [ 'message' => __( 'Your session expired. Please reload the page.', 'nahnu-asset-scanner' ) ], 403 );
		}

		$user_id = get_current_user_id();
		// Rule keys contain URL paths, which sanitize_text_field() would alter
		// (it strips %-encoded octets). They are validated by sanitize_rules():
		// a fixed key pattern without whitespace and an allow-listed action.
		if ( isset( $_POST['rules'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded JSON is validated by sanitize_rules().
			$raw   = json_decode( wp_unslash( $_POST['rules'] ), true );
			$rules = self::sanitize_rules( is_array( $raw ) ? $raw : [] );
		} else {
			$rules = self::get_rules( $user_id );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by sanitize_rules() below.
			$key    = isset( $_POST['key'] ) ? (string) wp_unslash( $_POST['key'] ) : '';
			$action = isset( $_POST['test_action'] ) ? sanitize_key( wp_unslash( $_POST['test_action'] ) ) : '';
			unset( $rules[ $key ] );
			if ( '' !== $action ) {
				$rules = self::sanitize_rules( $rules + [ $key => $action ] );
			}
		}

		if ( $rules ) {
			update_user_meta( $user_id, self::META, $rules );
		} else {
			delete_user_meta( $user_id, self::META );
		}
		wp_send_json_success( [ 'rules' => (object) $rules ] );
	}
}
