<?php
/**
 * Admin class — menu, enqueue, render, AJAX handlers.
 *
 * @package NahnuAS
 */

namespace NahnuAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/** @var string Nonce action */
	const NONCE = 'nahnuas_nonce';

	/** @var string Required capability */
	const CAP = 'manage_options';

	/** @var string[] Result tabs that can be queried or exported. */
	const RESULT_TABS = [ 'js', 'css', 'cookies', 'domains' ];

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public static function init(): void {
		$self = new self();

		add_action( 'admin_menu',             [ $self, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts',  [ $self, 'enqueue' ] );

		// AJAX — logged-in only
		add_action( 'wp_ajax_nahnuas_start_scan',    [ $self, 'ajax_start_scan' ] );
		add_action( 'wp_ajax_nahnuas_scan_page',     [ $self, 'ajax_scan_page' ] );
		add_action( 'wp_ajax_nahnuas_complete_scan', [ $self, 'ajax_complete_scan' ] );
		add_action( 'wp_ajax_nahnuas_get_results',   [ $self, 'ajax_get_results' ] );
		add_action( 'wp_ajax_nahnuas_export_csv',    [ $self, 'ajax_export_csv' ] );
		add_action( 'wp_ajax_nahnuas_clear_data',    [ $self, 'ajax_clear_data' ] );
		add_action( 'wp_ajax_nahnuas_save_settings', [ $self, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_nahnuas_picker_browse', [ $self, 'ajax_picker_browse' ] );
		add_action( 'wp_ajax_nahnuas_picker_save',   [ $self, 'ajax_picker_save' ] );
	}

	// -------------------------------------------------------------------------
	// Menu & enqueue
	// -------------------------------------------------------------------------

	public function register_menu(): void {
		add_menu_page(
			__( 'Nahnu Asset Scanner', 'nahnu-asset-scanner' ),
			__( 'Asset Scanner', 'nahnu-asset-scanner' ),
			self::CAP,
			'nahnu-asset-scanner',
			[ $this, 'render_page' ],
			'dashicons-search',
			80
		);
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_nahnu-asset-scanner' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'nahnuas-admin',
			NAHNUAS_URL . 'admin/assets/admin.css',
			[],
			NAHNUAS_VERSION
		);

		wp_enqueue_script(
			'nahnuas-admin',
			NAHNUAS_URL . 'admin/assets/admin.js',
			[],
			NAHNUAS_VERSION,
			true
		);

		$latest = DB::get_latest_scan();

		wp_localize_script( 'nahnuas-admin', 'nahnuasData', [
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( self::NONCE ),
			'latestScan' => $latest ?: null,
			'siteHost'   => strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ),
			'pluginMap'  => Plugin_Map::get_map(),
			'testRules'  => (object) Test_Mode::get_rules(),
			'testParam'  => Test_Mode::PARAM,
			'strings'    => [
				'scanning'          => __( 'Scanning…', 'nahnu-asset-scanner' ),
				'scanComplete'      => __( 'Scan complete', 'nahnu-asset-scanner' ),
				'scanFailed'        => __( 'Scan failed', 'nahnu-asset-scanner' ),
				'scanCancelled'     => __( 'Scan cancelled', 'nahnu-asset-scanner' ),
				'noResults'         => __( 'No results found.', 'nahnu-asset-scanner' ),
				'confirmClear'      => __( 'Delete all scan data? This cannot be undone.', 'nahnu-asset-scanner' ),
				'settingsSaved'     => __( 'Settings saved.', 'nahnu-asset-scanner' ),
				'fetching'          => __( 'Fetching…', 'nahnu-asset-scanner' ),
				'refresh'           => __( 'Refresh', 'nahnu-asset-scanner' ),
				'networkError'      => __( 'Network error. Please try again.', 'nahnu-asset-scanner' ),
				'testColumn'        => __( 'Test', 'nahnu-asset-scanner' ),
				'testOn'            => __( 'On', 'nahnu-asset-scanner' ),
				'testOff'           => __( 'Off', 'nahnu-asset-scanner' ),
				'testDefer'         => __( 'Defer', 'nahnu-asset-scanner' ),
				'testDelay'         => __( 'Delay', 'nahnu-asset-scanner' ),
				'testSaved'         => __( 'Test rule saved. Open a page in test mode to see the effect.', 'nahnu-asset-scanner' ),
				'testRemoved'       => __( 'Test rule removed.', 'nahnu-asset-scanner' ),
				'testInline'        => __( 'Inline code is tested from the front-end panel.', 'nahnu-asset-scanner' ),
				'testNone'          => __( 'No test rules yet. Pick Off, Defer or Delay in the Scripts or Styles tab, or open a page in test mode and use the panel.', 'nahnu-asset-scanner' ),
				'testRemove'        => __( 'Remove', 'nahnu-asset-scanner' ),
				'confirmClearRules' => __( 'Remove all of your test rules?', 'nahnu-asset-scanner' ),
				'script'            => __( 'Script', 'nahnu-asset-scanner' ),
				'style'             => __( 'Style', 'nahnu-asset-scanner' ),
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nahnu-asset-scanner' ) );
		}

		$latest = DB::get_latest_scan();
		?>
		<div class="wrap fas-page">
		<div class="fas-wrap">

			<!-- Header -->
			<div class="fas-header">
				<div class="fas-header__brand">
					<svg class="fas-logo" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="16" cy="16" r="11" stroke="currentColor" stroke-width="2.5"/>
						<line x1="24" y1="24" x2="33" y2="33" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"/>
						<circle cx="16" cy="16" r="6" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 2"/>
					</svg>
					<div>
						<span class="fas-header__title"><?php esc_html_e( 'Nahnu Asset Scanner', 'nahnu-asset-scanner' ); ?></span>
						<span class="fas-header__by"><?php esc_html_e( 'by', 'nahnu-asset-scanner' ); ?> <a href="https://www.nahnuplugins.com" target="_blank" rel="noopener" style="color:rgba(255,255,255,.85);text-decoration:none;">Nahnu Plugins</a></span>
					</div>
				</div>
				<div class="fas-header__actions">
					<a class="fas-btn fas-btn--onblue" href="<?php echo esc_url( add_query_arg( Test_Mode::PARAM, '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M9 3h6M10 3v6.5L4.6 18.3A1.8 1.8 0 0 0 6.1 21h11.8a1.8 1.8 0 0 0 1.5-2.7L14 9.5V3"/><path d="M7.5 15h9"/></svg>
						<?php esc_html_e( 'Test on front end', 'nahnu-asset-scanner' ); ?>
					</a>
					<button id="fas-run-scan" class="fas-btn fas-btn--primary">
						<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
						<?php esc_html_e( 'Run Scan', 'nahnu-asset-scanner' ); ?>
					</button>
				</div>
			</div>

			<!-- Status bar -->
			<div class="fas-status-bar">
				<div class="fas-status-bar__left">
					<span id="fas-status-badge" class="fas-badge fas-badge--<?php echo $latest ? esc_attr( $latest['status'] ) : 'idle'; ?>">
						<?php
						if ( $latest ) {
							echo esc_html( ucfirst( $latest['status'] ) );
						} else {
							esc_html_e( 'Idle — no scan yet', 'nahnu-asset-scanner' );
						}
						?>
					</span>
					<?php if ( $latest && $latest['completed_at'] ) : ?>
					<span class="fas-status-bar__meta">
						<?php
						printf(
							/* translators: date/time */
							esc_html__( 'Last scan: %s', 'nahnu-asset-scanner' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $latest['completed_at'] ) ) )
						);
						?>
					</span>
					<?php endif; ?>
				</div>
				<div id="fas-scan-totals" class="fas-status-bar__totals">
					<?php if ( $latest ) : ?>
					<span class="fas-stat"><strong id="fas-stat-pages"><?php echo (int) $latest['pages_scanned']; ?></strong> <?php esc_html_e( 'pages', 'nahnu-asset-scanner' ); ?></span>
					<span class="fas-stat"><strong id="fas-stat-js"><?php echo (int) $latest['total_js']; ?></strong> JS</span>
					<span class="fas-stat"><strong id="fas-stat-css"><?php echo (int) $latest['total_css']; ?></strong> CSS</span>
					<span class="fas-stat"><strong id="fas-stat-cookies"><?php echo (int) $latest['total_cookies']; ?></strong> <?php esc_html_e( 'cookies', 'nahnu-asset-scanner' ); ?></span>
					<span class="fas-stat"><strong id="fas-stat-domains"><?php echo (int) $latest['total_domains']; ?></strong> <?php esc_html_e( 'domains', 'nahnu-asset-scanner' ); ?></span>
					<?php else : ?>
					<span class="fas-stat"><strong id="fas-stat-pages">0</strong> <?php esc_html_e( 'pages', 'nahnu-asset-scanner' ); ?></span>
					<span class="fas-stat"><strong id="fas-stat-js">0</strong> JS</span>
					<span class="fas-stat"><strong id="fas-stat-css">0</strong> CSS</span>
					<span class="fas-stat"><strong id="fas-stat-cookies">0</strong> <?php esc_html_e( 'cookies', 'nahnu-asset-scanner' ); ?></span>
					<span class="fas-stat"><strong id="fas-stat-domains">0</strong> <?php esc_html_e( 'domains', 'nahnu-asset-scanner' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Progress bar (hidden by default) -->
			<div id="fas-progress-wrap" class="fas-progress-wrap" style="display:none;">
				<div class="fas-progress-bar-track">
					<div id="fas-progress-bar" class="fas-progress-bar" style="width:0%"></div>
				</div>
				<div class="fas-progress-meta">
					<span id="fas-progress-text"><?php esc_html_e( 'Preparing scan…', 'nahnu-asset-scanner' ); ?></span>
					<span id="fas-progress-count"></span>
					<button id="fas-cancel-scan" class="fas-btn fas-btn--ghost fas-btn--sm">
						<?php esc_html_e( 'Cancel', 'nahnu-asset-scanner' ); ?>
					</button>
				</div>
			</div>

			<!-- Tab nav -->
			<div class="fas-tabs">
				<?php
				$counts = [
					'js'      => $latest ? (int) $latest['total_js'] : 0,
					'css'     => $latest ? (int) $latest['total_css'] : 0,
					'cookies' => $latest ? (int) $latest['total_cookies'] : 0,
					'domains' => $latest ? (int) $latest['total_domains'] : 0,
				];
				$rules_count = count( Test_Mode::get_rules() );
				?>
				<nav class="fas-tab-nav" role="tablist">
					<button class="fas-tab-btn fas-tab-btn--active" data-tab="pages" role="tab"><?php esc_html_e( 'Pages', 'nahnu-asset-scanner' ); ?></button>
					<span class="fas-tab-sep" aria-hidden="true"></span>
					<button class="fas-tab-btn" data-tab="js" role="tab"><?php esc_html_e( 'Scripts', 'nahnu-asset-scanner' ); ?> <span class="fas-tab-count" id="fas-count-js"><?php echo absint( $counts['js'] ); ?></span></button>
					<button class="fas-tab-btn" data-tab="css" role="tab"><?php esc_html_e( 'Styles', 'nahnu-asset-scanner' ); ?> <span class="fas-tab-count" id="fas-count-css"><?php echo absint( $counts['css'] ); ?></span></button>
					<button class="fas-tab-btn" data-tab="cookies" role="tab"><?php esc_html_e( 'Cookies', 'nahnu-asset-scanner' ); ?> <span class="fas-tab-count" id="fas-count-cookies"><?php echo absint( $counts['cookies'] ); ?></span></button>
					<button class="fas-tab-btn" data-tab="domains" role="tab"><?php esc_html_e( 'Domains', 'nahnu-asset-scanner' ); ?> <span class="fas-tab-count" id="fas-count-domains"><?php echo absint( $counts['domains'] ); ?></span></button>
					<span class="fas-tab-sep" aria-hidden="true"></span>
					<button class="fas-tab-btn fas-tab-btn--test" data-tab="test" role="tab"><?php esc_html_e( 'Test Lab', 'nahnu-asset-scanner' ); ?> <span class="fas-tab-count fas-tab-count--test" id="fas-count-test"<?php echo $rules_count ? '' : ' hidden'; ?>><?php echo absint( $rules_count ); ?></span></button>
					<span class="fas-tab-spacer" aria-hidden="true"></span>
					<button class="fas-tab-btn fas-tab-btn--settings" data-tab="settings" role="tab"><?php esc_html_e( 'Settings', 'nahnu-asset-scanner' ); ?></button>
					<button class="fas-tab-btn fas-tab-btn--settings" data-tab="about" role="tab"><?php esc_html_e( 'About', 'nahnu-asset-scanner' ); ?></button>
				</nav>

				<!-- ============================================================
				     Pages to Scan tab
				     ============================================================ -->
				<div id="fas-panel-pages" class="fas-panel fas-panel--active" data-panel="pages">

					<?php
					$selected_ids = array_map( 'intval', (array) get_option( 'nahnuas_selected_post_ids', [] ) );
					$browse       = DB::browse_posts( '', '', 1, 10 );
					$all_types    = $browse['all_types'];
					$total        = $browse['total'];
					$rows         = $browse['rows'];
					?>

					<ol class="fas-steps">
						<li class="fas-step fas-step--current">
							<span class="fas-step__num">1</span>
							<div><strong><?php esc_html_e( 'Choose pages', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'Tick up to 10 below, or leave all unticked to scan your latest content.', 'nahnu-asset-scanner' ); ?></span></div>
						</li>
						<li class="fas-step<?php echo $latest ? ' fas-step--done' : ''; ?>">
							<span class="fas-step__num">2</span>
							<div><strong><?php esc_html_e( 'Run the scan', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'See every script, style, cookie and domain, with the plugin behind it.', 'nahnu-asset-scanner' ); ?></span></div>
						</li>
						<li class="fas-step<?php echo $rules_count ? ' fas-step--done' : ''; ?>">
							<span class="fas-step__num">3</span>
							<div><strong><?php esc_html_e( 'Test changes', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'Turn files off, defer or delay them on the live page. Only you see it.', 'nahnu-asset-scanner' ); ?></span></div>
						</li>
					</ol>

					<div class="fpp-intro">
						<p><?php esc_html_e( 'Pages to scan', 'nahnu-asset-scanner' ); ?></p>
						<span class="fpp-counter"><span id="fpp-count"><?php echo absint( count( $selected_ids ) ); ?></span> / 10 <?php esc_html_e( 'selected', 'nahnu-asset-scanner' ); ?></span>
					</div>

					<div class="fpp-toolbar">
						<input type="search" id="fpp-search" class="fpp-search" placeholder="<?php esc_attr_e( 'Search by title…', 'nahnu-asset-scanner' ); ?>" autocomplete="off">
						<select id="fpp-type-filter" class="fpp-type-filter">
							<option value=""><?php esc_html_e( 'All types', 'nahnu-asset-scanner' ); ?></option>
							<?php foreach ( $all_types as $k => $obj ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $obj->labels->singular_name ?: $obj->label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div id="fpp-table-wrap" class="fpp-table-wrap">
					<?php if ( $rows ) : ?>
						<table class="fpp-table">
							<thead><tr><th class="fpp-tc"></th><th><?php esc_html_e( 'Title', 'nahnu-asset-scanner' ); ?></th><th><?php esc_html_e( 'Type', 'nahnu-asset-scanner' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $rows as $row ) :
								$checked = in_array( (int) $row->ID, $selected_ids, true );
								$pt      = $all_types[ $row->post_type ] ?? null;
								$label   = $pt ? ( $pt->labels->singular_name ?: $pt->label ) : ucfirst( $row->post_type );
							?>
								<tr class="<?php echo $checked ? 'fpp-checked' : ''; ?>" data-id="<?php echo absint( $row->ID ); ?>">
									<td class="fpp-tc"><input type="checkbox" <?php checked( $checked ); ?>></td>
									<td class="fpp-tt"><?php echo esc_html( $row->post_title ?: __( '(no title)', 'nahnu-asset-scanner' ) ); ?></td>
									<td><span class="fpp-badge fpp-bt-<?php echo esc_attr( $row->post_type ); ?>"><?php echo esc_html( $label ); ?></span></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="fpp-msg"><?php esc_html_e( 'No published pages or posts found.', 'nahnu-asset-scanner' ); ?></p>
					<?php endif; ?>
					</div>

					<div id="fpp-pagination" class="fpp-pagination">
					<?php if ( $total > 10 ) :
						$tot_pages = (int) ceil( $total / 10 );
					?>
						<span class="fpp-pinfo"><?php
							/* translators: %s: total number of published items. */
							printf( esc_html__( 'Showing 1–10 of %s', 'nahnu-asset-scanner' ), esc_html( number_format_i18n( $total ) ) );
						?></span>
						<div class="fpp-pages">
							<button class="fpp-pg fpp-pg-on" data-p="1">1</button>
							<?php for ( $i = 2; $i <= min( $tot_pages, 5 ); $i++ ) : ?>
								<button class="fpp-pg" data-p="<?php echo absint( $i ); ?>"><?php echo absint( $i ); ?></button>
							<?php endfor; ?>
							<?php if ( $tot_pages > 5 ) : ?>
								<span class="fpp-pdot">&hellip;</span>
								<button class="fpp-pg" data-p="<?php echo absint( $tot_pages ); ?>"><?php echo absint( $tot_pages ); ?></button>
							<?php endif; ?>
							<button class="fpp-pg" data-p="2">&raquo;</button>
						</div>
					<?php endif; ?>
					</div>

					<div class="fpp-footer">
						<button id="fpp-clear-sel" class="fas-btn fas-btn--ghost fas-btn--sm"><?php esc_html_e( 'Clear all', 'nahnu-asset-scanner' ); ?></button>
						<div class="fpp-footer-right">
							<span class="fpp-hint"><?php esc_html_e( 'Selections persist across pages.', 'nahnu-asset-scanner' ); ?></span>
							<button id="fpp-save" class="fas-btn fas-btn--solid"><?php esc_html_e( 'Save Selection', 'nahnu-asset-scanner' ); ?></button>
							<span id="fpp-saved-notice" class="fpp-saved-notice" style="display:none;"><?php esc_html_e( 'Saved!', 'nahnu-asset-scanner' ); ?></span>
						</div>
					</div>

				</div><!-- /#fas-panel-pages -->

				<!-- JS panel -->
				<div id="fas-panel-js" class="fas-panel" data-panel="js">
					<div class="fas-toolbar">
						<input type="search" class="fas-search" data-tab="js" placeholder="<?php esc_attr_e( 'Search JS files…', 'nahnu-asset-scanner' ); ?>">
						<select class="fas-filter-party" data-tab="js">
							<option value=""><?php esc_html_e( 'All sources', 'nahnu-asset-scanner' ); ?></option>
							<option value="1"><?php esc_html_e( 'Third-party only', 'nahnu-asset-scanner' ); ?></option>
							<option value="0"><?php esc_html_e( 'First-party only', 'nahnu-asset-scanner' ); ?></option>
						</select>
						<button class="fas-btn fas-btn--export fas-btn--sm" data-tab="js">
							<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
							CSV
						</button>
					</div>
					<div class="fas-table-wrap" id="fas-table-js"></div>
					<div class="fas-pagination" id="fas-pagination-js"></div>
				</div>

				<!-- CSS panel -->
				<div id="fas-panel-css" class="fas-panel" data-panel="css">
					<div class="fas-toolbar">
						<input type="search" class="fas-search" data-tab="css" placeholder="<?php esc_attr_e( 'Search CSS files…', 'nahnu-asset-scanner' ); ?>">
						<select class="fas-filter-party" data-tab="css">
							<option value=""><?php esc_html_e( 'All sources', 'nahnu-asset-scanner' ); ?></option>
							<option value="1"><?php esc_html_e( 'Third-party only', 'nahnu-asset-scanner' ); ?></option>
							<option value="0"><?php esc_html_e( 'First-party only', 'nahnu-asset-scanner' ); ?></option>
						</select>
						<button class="fas-btn fas-btn--export fas-btn--sm" data-tab="css">
							<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
							CSV
						</button>
					</div>
					<div class="fas-table-wrap" id="fas-table-css"></div>
					<div class="fas-pagination" id="fas-pagination-css"></div>
				</div>

				<!-- Cookies panel -->
				<div id="fas-panel-cookies" class="fas-panel" data-panel="cookies">
					<div class="fas-toolbar">
						<input type="search" class="fas-search" data-tab="cookies" placeholder="<?php esc_attr_e( 'Search cookies…', 'nahnu-asset-scanner' ); ?>">
						<select class="fas-filter-party" data-tab="cookies">
							<option value=""><?php esc_html_e( 'All sources', 'nahnu-asset-scanner' ); ?></option>
							<option value="1"><?php esc_html_e( 'Third-party only', 'nahnu-asset-scanner' ); ?></option>
							<option value="0"><?php esc_html_e( 'First-party only', 'nahnu-asset-scanner' ); ?></option>
						</select>
						<button class="fas-btn fas-btn--export fas-btn--sm" data-tab="cookies">
							<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
							CSV
						</button>
					</div>
					<div class="fas-table-wrap" id="fas-table-cookies"></div>
					<div class="fas-pagination" id="fas-pagination-cookies"></div>
				</div>

				<!-- Domains panel -->
				<div id="fas-panel-domains" class="fas-panel" data-panel="domains">
					<div class="fas-toolbar">
						<input type="search" class="fas-search" data-tab="domains" placeholder="<?php esc_attr_e( 'Search domains…', 'nahnu-asset-scanner' ); ?>">
						<select class="fas-filter-party" data-tab="domains">
							<option value=""><?php esc_html_e( 'All sources', 'nahnu-asset-scanner' ); ?></option>
							<option value="1"><?php esc_html_e( 'Third-party only', 'nahnu-asset-scanner' ); ?></option>
							<option value="0"><?php esc_html_e( 'First-party only', 'nahnu-asset-scanner' ); ?></option>
						</select>
						<button class="fas-btn fas-btn--export fas-btn--sm" data-tab="domains">
							<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
							CSV
						</button>
					</div>
					<div class="fas-table-wrap" id="fas-table-domains"></div>
					<div class="fas-pagination" id="fas-pagination-domains"></div>
				</div>

				<!-- Test Lab panel -->
				<div id="fas-panel-test" class="fas-panel" data-panel="test">
					<?php
					$test_urls = [ home_url( '/' ) ];
					if ( $latest ) {
						$test_urls = array_values( array_unique( array_merge( $test_urls, DB::get_page_urls( (int) $latest['id'] ) ) ) );
					}
					?>
					<div class="fas-lab-hero">
						<div>
							<h2><?php esc_html_e( 'Test before you optimize', 'nahnu-asset-scanner' ); ?></h2>
							<p><?php esc_html_e( 'Cache plugins can break a site when the wrong file is turned off, deferred or delayed. Try it here first: the page opens with your test rules and a panel to change them. Only you see it. Visitors and your cache are never affected.', 'nahnu-asset-scanner' ); ?></p>
						</div>
						<div class="fas-lab-open">
							<label for="fas-lab-url"><?php esc_html_e( 'Page to test', 'nahnu-asset-scanner' ); ?></label>
							<div class="fas-lab-open__row">
								<select id="fas-lab-url">
									<?php foreach ( $test_urls as $test_url ) : ?>
										<option value="<?php echo esc_url( $test_url ); ?>"><?php echo esc_html( untrailingslashit( preg_replace( '#^https?://[^/]+#', '', $test_url ) ) ?: '/' ); ?></option>
									<?php endforeach; ?>
								</select>
								<a id="fas-lab-go" class="fas-btn fas-btn--solid" href="<?php echo esc_url( add_query_arg( Test_Mode::PARAM, '1', home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in test mode', 'nahnu-asset-scanner' ); ?> &#8599;</a>
							</div>
							<span class="fas-lab-open__tip">
								<?php
								printf(
									/* translators: %s: the query string that turns test mode on, e.g. ?nahnuas_test=1 */
									esc_html__( 'Or add %s to any page address while logged in, or use "Test assets" in the admin bar.', 'nahnu-asset-scanner' ),
									'<code>?' . esc_html( Test_Mode::PARAM ) . '=1</code>'
								);
								?>
							</span>
						</div>
					</div>

					<div class="fas-lab-modes">
						<div class="fas-lab-mode fas-lab-mode--off">
							<strong><?php esc_html_e( 'Off', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'The file is not loaded. Shows whether anything on the page needs it.', 'nahnu-asset-scanner' ); ?></span>
						</div>
						<div class="fas-lab-mode fas-lab-mode--defer">
							<strong><?php esc_html_e( 'Defer', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'Scripts run after the page HTML is read. Stylesheets load without blocking the first paint.', 'nahnu-asset-scanner' ); ?></span>
						</div>
						<div class="fas-lab-mode fas-lab-mode--delay">
							<strong><?php esc_html_e( 'Delay', 'nahnu-asset-scanner' ); ?></strong>
							<span><?php esc_html_e( 'Scripts wait for the first scroll, tap, click or key press. Best for chat widgets, trackers and ads.', 'nahnu-asset-scanner' ); ?></span>
						</div>
					</div>

					<div class="fas-card">
						<div class="fas-card__header">
							<?php esc_html_e( 'Your test rules', 'nahnu-asset-scanner' ); ?>
							<button type="button" id="fas-lab-clear" class="fas-btn fas-btn--ghost fas-btn--sm fas-card__header-action"><?php esc_html_e( 'Remove all', 'nahnu-asset-scanner' ); ?></button>
						</div>
						<div id="fas-lab-rules" class="fas-lab-rules"></div>
					</div>

					<div class="fas-card fas-lab-ai">
						<div class="fas-card__header">
							<?php esc_html_e( 'AI agents & MCP', 'nahnu-asset-scanner' ); ?>
							<span class="fas-card__header-hint"><?php esc_html_e( 'Abilities API', 'nahnu-asset-scanner' ); ?></span>
						</div>
						<div class="fas-card__body">
							<p><?php esc_html_e( 'This plugin registers WordPress abilities, so an AI assistant connected through MCP (for example with the WordPress MCP Adapter) can scan your site and run these tests for you. Each ability requires an administrator account.', 'nahnu-asset-scanner' ); ?></p>
							<ul class="fas-lab-ai__list">
								<li><code>run-scan</code>, <code>get-scan-summary</code>, <code>get-results</code>: <?php esc_html_e( 'scan pages and read scripts, styles, cookies and domains.', 'nahnu-asset-scanner' ); ?></li>
								<li><code>get-page-assets</code>: <?php esc_html_e( 'every script and stylesheet on a page, with its rule key.', 'nahnu-asset-scanner' ); ?></li>
								<li><code>get-test-rules</code>, <code>set-test-rules</code>: <?php esc_html_e( 'the rules you see in this tab.', 'nahnu-asset-scanner' ); ?></li>
								<li><code>run-test</code>, <code>get-test-report</code>: <?php esc_html_e( 'render a page with rules, list render-blocking files and scripts that will break, and collect real JavaScript errors from a browser through a one-time link that expires after 30 minutes.', 'nahnu-asset-scanner' ); ?></li>
							</ul>
							<p class="fas-lab-ai__try"><?php esc_html_e( 'Try asking your assistant: "Scan my home page, delay every third-party script in a test, and tell me what breaks."', 'nahnu-asset-scanner' ); ?></p>
						</div>
					</div>

					<p class="fas-lab-note"><?php esc_html_e( 'When a page still works with a rule, make the same change in your cache or performance plugin (for example WP Rocket, LiteSpeed Cache, Perfmatters or FlyingPress). Rules here are saved for your user only and are never applied to visitors.', 'nahnu-asset-scanner' ); ?></p>
				</div><!-- /#fas-panel-test -->

				<!-- Settings panel -->
				<div id="fas-panel-settings" class="fas-panel" data-panel="settings">
					<div class="fas-settings-grid">

						<div class="fas-card">
							<div class="fas-card__header"><?php esc_html_e( 'Scan Options', 'nahnu-asset-scanner' ); ?></div>
							<div class="fas-card__body">
								<form id="fas-settings-form">
									<div class="fas-field-row">
										<label for="nahnuas-scan-timeout"><?php esc_html_e( 'Page timeout (seconds)', 'nahnu-asset-scanner' ); ?></label>
										<input id="nahnuas-scan-timeout" name="nahnuas_scan_timeout" type="number" min="5" max="120" value="<?php echo absint( get_option( 'nahnuas_scan_timeout', Scanner::DEFAULT_TIMEOUT ) ); ?>" style="width:80px;">
										<span style="font-size:12px;color:#6B7280;margin-left:8px;"><?php esc_html_e( '5–120 seconds. Increase for slow pages.', 'nahnu-asset-scanner' ); ?></span>
									</div>
									<div class="fas-field-row" style="margin-top:16px;">
										<label for="nahnuas-custom-urls"><?php esc_html_e( 'Additional URLs to scan', 'nahnu-asset-scanner' ); ?></label>
										<textarea id="nahnuas-custom-urls" name="nahnuas_custom_urls" rows="4" style="width:100%;font-family:monospace;font-size:12px;" placeholder="https://example.com/page-not-in-picker"><?php echo esc_textarea( get_option( 'nahnuas_custom_urls', '' ) ); ?></textarea>
										<span style="font-size:12px;color:#6B7280;"><?php esc_html_e( 'One URL per line. Use for pages not available in the picker (e.g. external checkout pages).', 'nahnu-asset-scanner' ); ?></span>
									</div>
									<div style="margin-top:16px;">
										<button type="button" id="fas-save-settings" class="fas-btn fas-btn--solid fas-btn--sm"><?php esc_html_e( 'Save', 'nahnu-asset-scanner' ); ?></button>
										<span id="fas-settings-status" style="font-size:12px;margin-left:10px;color:#6B7280;"></span>
									</div>
								</form>
							</div>
						</div>

						<div class="fas-card">
							<div class="fas-card__header"><?php esc_html_e( 'Data', 'nahnu-asset-scanner' ); ?></div>
							<div class="fas-card__body">
								<p style="font-size:13px;color:#374151;margin-bottom:12px;"><?php esc_html_e( 'Delete all scan data from the database. This cannot be undone.', 'nahnu-asset-scanner' ); ?></p>
								<button id="fas-clear-data" class="fas-btn fas-btn--danger fas-btn--sm"><?php esc_html_e( 'Clear All Data', 'nahnu-asset-scanner' ); ?></button>
							</div>
						</div>

					</div><!-- /.fas-settings-grid -->
				</div><!-- /#fas-panel-settings -->

				<!-- About panel -->
				<div id="fas-panel-about" class="fas-panel" data-panel="about">

					<h3 class="fas-about-section-title"><?php esc_html_e( 'Using with Cache &amp; Performance Plugins', 'nahnu-asset-scanner' ); ?></h3>
					<div class="fas-use-cases-grid">

						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M9 3h6M10 3v6.5L4.6 18.3A1.8 1.8 0 0 0 6.1 21h11.8a1.8 1.8 0 0 0 1.5-2.7L14 9.5V3"/><path d="M7.5 15h9"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'Test Lab', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'Preview any page with scripts or stylesheets turned off, deferred or delayed before you change your cache plugin. Add ?nahnuas_test=1 to a page address while logged in as an administrator. A panel lists every file on the page with the plugin behind it and shows any JavaScript errors your changes cause. Only you see the test; visitors always get the normal page.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>

						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'JS Defer &amp; Delay', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'The JS tab lists every script loaded on your pages with its file path, version, defer/async status, first- or third-party origin, and the plugin that owns it. Use it to decide which scripts are safe to defer or delay in WP Rocket, LiteSpeed Cache, NitroPack, or Hummingbird. First-party plugin scripts such as the WooCommerce cart usually need to load immediately; third-party trackers such as Google Analytics or Hotjar are good candidates for delay.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>

						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'CSS Exclusions', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'The CSS tab identifies every stylesheet and its owning plugin. When combining or minifying CSS causes layout breaks, copy the file path directly into your cache plugin\'s exclusion list — WP Rocket, LiteSpeed Cache, and W3 Total Cache all support path-based exclusions. Render-blocking stylesheets from page builders like Elementor or Divi typically need to stay excluded from aggressive minification.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>

						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'Cache Bypass for Dynamic Pages', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'The Cookies tab lists every cookie your site sets. Cache plugins use cookies to decide whether to serve a cached page or bypass the cache — e.g. WooCommerce cart cookies, WPML language cookies, and login cookies must all bypass the cache. This tab gives you the exact cookie names to enter into your cache plugin\'s bypass rules, replacing guesswork with a real scan.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>


						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'Cookie Consent Configuration', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'Before configuring Complianz, CookieYes, or Real Cookie Banner, run a scan to get a complete, categorized cookie inventory. The plugin identifies 600+ known cookies from 400+ plugins and services — each tagged as Necessary, Preferences, Analytics, or Marketing — so you can populate your consent management platform with accurate data rather than relying on a generic auto-scan.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>

						<div class="fas-use-case-card">
							<div class="fas-use-case-card__icon">
								<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
							</div>
							<div>
								<strong><?php esc_html_e( 'Export &amp; Document', 'nahnu-asset-scanner' ); ?></strong>
								<p><?php esc_html_e( 'Every tab has a CSV export. Use this to document your site\'s asset inventory, share a JS/CSS list with a developer optimizing your Core Web Vitals, or keep a record of which third-party scripts were present on a given date. Particularly useful for agencies managing multiple client sites.', 'nahnu-asset-scanner' ); ?></p>
							</div>
						</div>


					</div><!-- /.fas-use-cases-grid -->

					<!-- Credits section -->
					<h3 class="fas-about-section-title"><?php esc_html_e( 'Open Source Libraries & Credits', 'nahnu-asset-scanner' ); ?></h3>

					<div class="fas-credits-grid">




						<div class="fas-credit-card">
							<div class="fas-credit-card__head">
								<span class="fas-credit-card__name">WordPress Plugin Cookie Database</span>
								<span class="fas-credit-card__license">Bundled</span>
							</div>
							<p class="fas-credit-card__desc"><?php esc_html_e( '600+ cookie patterns covering 400+ WordPress plugins and services, built and maintained as part of this plugin. Covers WooCommerce, membership plugins, LMS platforms, builders, security plugins, analytics services, ad networks, and more. No external API needed: the data is updated with plugin releases.', 'nahnu-asset-scanner' ); ?></p>
							<div class="fas-credit-card__meta">
								<span>&#128100; Nahnu Plugins</span>
								<a href="https://www.nahnuplugins.com" target="_blank" rel="noopener">nahnuplugins.com</a>
							</div>
						</div>

						<div class="fas-credit-card">
							<div class="fas-credit-card__head">
								<span class="fas-credit-card__name">WordPress</span>
								<span class="fas-credit-card__license">GPL-2.0+</span>
							</div>
							<p class="fas-credit-card__desc"><?php esc_html_e( 'Built on WordPress core APIs: the WP HTTP API, dbDelta, the Options API, and admin-ajax. Follows WordPress coding standards and the plugin review guidelines.', 'nahnu-asset-scanner' ); ?></p>
							<div class="fas-credit-card__meta">
								<span>&#128100; WordPress.org</span>
								<a href="https://wordpress.org" target="_blank" rel="noopener">wordpress.org</a>
							</div>
						</div>


					</div><!-- /.fas-credits-grid -->

					<div class="fas-about-footer">
						<p><?php printf(
							/* translators: 1: license name linked to the license text, 2: author name linked to the author website. */
							esc_html__( 'Nahnu Asset Scanner is free software released under the %1$s license. Built with &#10084; by %2$s.', 'nahnu-asset-scanner' ),
							'<a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener">GPL-2.0+</a>',
							'<a href="https://www.nahnuplugins.com" target="_blank" rel="noopener">Nahnu Plugins</a>'
						); ?></p>
					</div>

				</div><!-- /#fas-panel-about -->

						</div><!-- /.fas-tabs -->

		</div><!-- /.fas-wrap -->
		</div><!-- /.wrap -->
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX: Page picker — browse all posts
	// -------------------------------------------------------------------------

	public function ajax_picker_browse(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$type   = isset( $_POST['ptype'] ) ? sanitize_key( wp_unslash( $_POST['ptype'] ) ) : '';
		$page   = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$per    = 10;

		$selected_ids = array_map( 'intval', (array) get_option( 'nahnuas_selected_post_ids', [] ) );

		// All SQL lives in DB::browse_posts() and goes through $wpdb->prepare().
		$browse    = DB::browse_posts( $search, $type, $page, $per );
		$all_types = $browse['all_types'];
		$total     = (int) $browse['total'];

		$items = [];
		foreach ( $browse['rows'] as $row ) {
			$pt      = $all_types[ $row->post_type ] ?? null;
			$items[] = [
				'id'    => (int) $row->ID,
				'title' => $row->post_title ?: __( '(no title)', 'nahnu-asset-scanner' ),
				'type'  => $row->post_type,
				'label' => $pt ? ( $pt->labels->singular_name ?: $pt->label ) : ucfirst( $row->post_type ),
				'sel'   => in_array( (int) $row->ID, $selected_ids, true ),
			];
		}

		wp_send_json_success( [
			'items'   => $items,
			'total'   => $total,
			'pages'   => max( 1, (int) ceil( $total / $per ) ),
			'page'    => $page,
			'sel_ids' => $selected_ids,
			'types'   => $browse['types'],
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Start scan
	// -------------------------------------------------------------------------

	public function ajax_start_scan(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$scan_id = DB::create_scan();
		if ( ! $scan_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not create the scan record.', 'nahnu-asset-scanner' ) ] );
		}
		DB::reset_domain_cache();

		$pages = Scanner::get_pages_to_scan();

		wp_send_json_success( [
			'scan_id' => $scan_id,
			'pages'   => $pages,
			'total'   => count( $pages ),
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Scan single page
	// -------------------------------------------------------------------------

	public function ajax_scan_page(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$scan_id   = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		$url       = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$page_type = isset( $_POST['page_type'] ) ? sanitize_key( wp_unslash( $_POST['page_type'] ) ) : 'page';
		$index     = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;

		if ( ! $scan_id || ! $url ) {
			wp_send_json_error( [ 'message' => __( 'Missing scan ID or URL.', 'nahnu-asset-scanner' ) ] );
		}

		// Only allow http/https — block file://, ftp://, internal IPs, etc.
		if ( ! wp_http_validate_url( $url ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid URL.', 'nahnu-asset-scanner' ) ] );
		}

		$result = Scanner::scan_page( $scan_id, $url, $page_type );

		wp_send_json_success( array_merge( $result, [ 'index' => $index, 'url' => $url ] ) );
	}

	// -------------------------------------------------------------------------
	// AJAX: Complete scan
	// -------------------------------------------------------------------------

	public function ajax_complete_scan(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$scan_id = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		if ( ! $scan_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing scan ID.', 'nahnu-asset-scanner' ) ] );
		}

		$scan = DB::finalize_scan( $scan_id );

		wp_send_json_success( [ 'scan' => $scan ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Get results
	// -------------------------------------------------------------------------

	public function ajax_get_results(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$tab    = $this->requested_tab();
		$page   = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		// '1' = third-party only, '0' = first-party only, '' = all. DB::normalize_party() maps it.
		$third_party = isset( $_POST['third_party'] ) ? sanitize_key( wp_unslash( $_POST['third_party'] ) ) : '';

		$args = [
			'page'        => $page,
			'search'      => $search,
			'third_party' => $third_party,
		];

		$items = DB::get_results( $tab, $args );
		$total = DB::get_results_count( $tab, $args );

		$items = self::enrich_results( $tab, $items );

		wp_send_json_success( [
			'items'        => $items,
			'total'        => $total,
			'pages'        => (int) ceil( $total / 10 ),
			'current_page' => $page,
			'tab'          => $tab,
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Export CSV  (POST form submit — not a wp_send_json handler)
	// -------------------------------------------------------------------------

	public function ajax_export_csv(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'nahnu-asset-scanner' ), '', [ 'response' => 403 ] );
		}
		$nonce = isset( $_POST['nahnuas_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nahnuas_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'Your session expired. Reload the page and try again.', 'nahnu-asset-scanner' ), '', [ 'response' => 403 ] );
		}

		$tab   = $this->requested_tab();
		$items = DB::get_results( $tab, [
			'page'        => 1,
			'per_page'    => 100000,
			'search'      => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'third_party' => isset( $_POST['third_party'] ) ? sanitize_key( wp_unslash( $_POST['third_party'] ) ) : '',
		] );

		$filename = 'nahnu-asset-scanner-' . $tab . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$f = static fn( array $row, string $key, $default = '' ) => $row[ $key ] ?? $default;

		// Pages are stored as |||-separated URLs; show the last path segment of each.
		$pages_str = static function ( $raw ): string {
			if ( empty( $raw ) ) {
				return '';
			}
			$names = [];
			foreach ( array_filter( array_map( 'trim', explode( '|||', (string) $raw ) ) ) as $u ) {
				$parts   = array_filter( explode( '/', trim( (string) wp_parse_url( $u, PHP_URL_PATH ), '/' ) ) );
				$last    = $parts ? end( $parts ) : 'Home';
				$names[] = ucfirst( str_replace( '-', ' ', $last ) );
			}
			return implode( ', ', $names );
		};

		$out = fopen( 'php://output', 'w' );

		// Every row goes through write_csv_row(), which neutralises spreadsheet formulas
		// in every cell and passes an explicit escape argument (PHP 8.4+ deprecation).
		switch ( $tab ) {
			case 'js':
				$this->write_csv_row( $out, [ 'URL', 'Domain', 'Source', 'Inline', 'Defer', 'Async', 'Version', 'Pages' ] );
				foreach ( $items as $r ) {
					$attrs = json_decode( (string) $f( $r, 'attributes_json', '{}' ), true ) ?: [];
					$this->write_csv_row( $out, [
						$f( $r, 'asset_url' ),
						$f( $r, 'domain' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$f( $r, 'is_inline' ) ? 'yes' : 'no',
						! empty( $attrs['defer'] ) ? 'yes' : 'no',
						! empty( $attrs['async'] ) ? 'yes' : 'no',
						$attrs['version'] ?? '',
						$pages_str( $f( $r, 'pages' ) ),
					] );
				}
				break;

			case 'css':
				$this->write_csv_row( $out, [ 'URL', 'Domain', 'Source', 'Inline', 'Media', 'Version', 'Pages' ] );
				foreach ( $items as $r ) {
					$attrs = json_decode( (string) $f( $r, 'attributes_json', '{}' ), true ) ?: [];
					$this->write_csv_row( $out, [
						$f( $r, 'asset_url' ),
						$f( $r, 'domain' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$f( $r, 'is_inline' ) ? 'yes' : 'no',
						$attrs['media'] ?? 'all',
						$attrs['version'] ?? '',
						$pages_str( $f( $r, 'pages' ) ),
					] );
				}
				break;

			case 'cookies':
				$this->write_csv_row( $out, [ 'Name', 'Domain', 'Path', 'Source', 'Plugin / Service', 'Session', 'Expires', 'Secure', 'HttpOnly', 'SameSite', 'Category', 'Pages' ] );
				foreach ( $items as $r ) {
					$match = Plugin_Cookies::lookup( (string) $f( $r, 'cookie_name' ) );
					$this->write_csv_row( $out, [
						$f( $r, 'cookie_name' ),
						$f( $r, 'cookie_domain' ),
						$f( $r, 'cookie_path' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$match['plugin'] ?? '',
						$f( $r, 'is_session' ) ? 'yes' : 'no',
						$f( $r, 'expiration' ),
						$f( $r, 'is_secure' ) ? 'yes' : 'no',
						$f( $r, 'is_httponly' ) ? 'yes' : 'no',
						$f( $r, 'samesite' ),
						$f( $r, 'category' ),
						$pages_str( $f( $r, 'pages' ) ),
					] );
				}
				break;

			case 'domains':
				$this->write_csv_row( $out, [ 'Domain', 'Source Types', 'Source', 'Pages', 'Occurrences' ] );
				foreach ( $items as $r ) {
					$this->write_csv_row( $out, [
						$f( $r, 'domain' ),
						$f( $r, 'source_types' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$pages_str( $f( $r, 'pages' ) ),
						(int) $f( $r, 'item_count', 0 ),
					] );
				}
				break;
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream, not a file.
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX: Clear data
	// -------------------------------------------------------------------------

	public function ajax_clear_data(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		DB::clear_all();

		wp_send_json_success( [ 'message' => __( 'All scan data cleared.', 'nahnu-asset-scanner' ) ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Save settings
	// -------------------------------------------------------------------------

	public function ajax_save_settings(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$scan_timeout = isset( $_POST['nahnuas_scan_timeout'] ) ? max( 5, min( 120, absint( wp_unslash( $_POST['nahnuas_scan_timeout'] ) ) ) ) : 15;
		$custom_urls  = isset( $_POST['nahnuas_custom_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nahnuas_custom_urls'] ) ) : '';

		update_option( 'nahnuas_scan_timeout', $scan_timeout );
		update_option( 'nahnuas_custom_urls',  $custom_urls );

		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'nahnu-asset-scanner' ) ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Picker — save selected IDs
	// -------------------------------------------------------------------------

	public function ajax_picker_save(): void {
		$this->authorize( check_ajax_referer( self::NONCE, 'nonce', false ) );

		$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '[]';
		$ids = json_decode( $raw, true );
		if ( ! is_array( $ids ) ) { $ids = []; }
		$ids = array_values( array_slice( array_unique( array_filter( array_map( 'intval', $ids ), static fn( int $id ): bool => $id > 0 ) ), 0, 10 ) );

		update_option( 'nahnuas_selected_post_ids', $ids );

		wp_send_json_success( [ 'ids' => $ids, 'count' => count( $ids ) ] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Stop the request unless the nonce (checked by the caller with
	 * check_ajax_referer()) is valid and the user may manage options.
	 *
	 * @param int|false $nonce_ok Return value of check_ajax_referer( …, false ).
	 */
	/**
	 * Add vendor badges and cookie plugin names to result rows.
	 *
	 * @param string                           $tab   Result tab.
	 * @param array<int,array<string,mixed>>   $items Rows from DB::get_results().
	 * @return array<int,array<string,mixed>>
	 */
	public static function enrich_results( string $tab, array $items ): array {
		foreach ( $items as &$item ) {
			if ( ! empty( $item['domain'] ) ) {
				$item['badge'] = Scanner::get_badge( $item['domain'] );
			}
			// Name the plugin/service behind each cookie from the bundled database.
			if ( 'cookies' === $tab && ! empty( $item['cookie_name'] ) ) {
				$match = Plugin_Cookies::lookup( $item['cookie_name'] );
				if ( $match ) {
					$item['plugin_source']      = $match['plugin'];
					$item['cookie_description'] = $match['description'];
				}
			}
		}
		unset( $item );
		return $items;
	}

	private function authorize( int|false $nonce_ok ): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'nahnu-asset-scanner' ) ], 403 );
		}
		if ( false === $nonce_ok ) {
			wp_send_json_error( [ 'message' => __( 'Your session expired. Reload the page and try again.', 'nahnu-asset-scanner' ) ], 403 );
		}
	}

	/** The requested results tab, limited to the known tabs (defaults to js). */
	private function requested_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'js';
		return in_array( $tab, self::RESULT_TABS, true ) ? $tab : 'js';
	}

	/**
	 * Write one CSV row with every cell neutralised against formula injection.
	 * Cells starting with = + - @ tab or CR are prefixed with a tab so spreadsheet
	 * apps treat them as text. The escape argument is passed explicitly because its
	 * implicit default is deprecated as of PHP 8.4.
	 *
	 * @param resource $handle Output stream.
	 * @param array    $cells  Row values.
	 */
	private function write_csv_row( $handle, array $cells ): void {
		$safe = array_map(
			static function ( $value ): string {
				$value = (string) $value;
				if ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
					return "\t" . $value;
				}
				return $value;
			},
			$cells
		);
		fputcsv( $handle, $safe, ',', '"', '' );
	}
}
