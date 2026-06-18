<?php
/**
 * Admin class — menu, enqueue, render, AJAX handlers.
 *
 * @package NahnuFAS
 */

namespace NahnuFAS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	/** @var string Nonce action */
	const NONCE = 'nahnuas_nonce';

	/** @var string Required capability */
	const CAP = 'manage_options';

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
			'siteHost'   => strtolower( (string) parse_url( home_url( '/' ), PHP_URL_HOST ) ),
			'pluginMap'  => Plugin_Map::get_map(),
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
						<span class="fas-header__by">by <a href="https://www.nahnuplugins.com" target="_blank" rel="noopener" style="color:rgba(255,255,255,.85);text-decoration:none;">Nahnu Plugins</a></span>
					</div>
				</div>
				<div class="fas-header__actions">
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

			<?php
			// ── Stale database banner ──────────────────────────────────────────────
			$_banner_stale = [];
			// FAZ is optional — only warn about core databases
			foreach ( [
											] as $_db_name => $_db_s ) {
				if ( ! $_db_s['loaded'] ) {
					$_banner_stale[] = $_db_name . ' (not loaded)';
				} elseif ( $_db_s['fetched'] && ( time() - $_db_s['fetched'] > WEEK_IN_SECONDS ) ) {
					$_days = round( ( time() - $_db_s['fetched'] ) / DAY_IN_SECONDS );
					$_banner_stale[] = $_db_name . ' (' . $_days . 'd old)';
				}
			}
			if ( ! empty( $_banner_stale ) ) : ?>
			<?php endif; ?>

			<!-- Tab nav -->
			<div class="fas-tabs">
				<nav class="fas-tab-nav" role="tablist">
					<button class="fas-tab-btn fas-tab-btn--active" data-tab="pages" role="tab">Pages</button>
					<button class="fas-tab-btn" data-tab="js" role="tab">JS</button>
					<button class="fas-tab-btn" data-tab="css" role="tab">CSS</button>
					<button class="fas-tab-btn" data-tab="cookies" role="tab"><?php esc_html_e( 'Cookies', 'nahnu-asset-scanner' ); ?></button>
					<button class="fas-tab-btn" data-tab="domains" role="tab"><?php esc_html_e( 'Domains', 'nahnu-asset-scanner' ); ?></button>
					<button class="fas-tab-btn fas-tab-btn--settings" data-tab="settings" role="tab"><?php esc_html_e( 'Settings', 'nahnu-asset-scanner' ); ?></button>
					<button class="fas-tab-btn fas-tab-btn--settings" data-tab="about" role="tab"><?php esc_html_e( 'About', 'nahnu-asset-scanner' ); ?></button>
				</nav>

				<!-- ============================================================
				     Pages to Scan tab
				     ============================================================ -->
				<div id="fas-panel-pages" class="fas-panel fas-panel--active" data-panel="pages">

					<?php
					global $wpdb;
					$selected_ids = array_map( 'intval', (array) get_option( 'nahnuas_selected_post_ids', [] ) );
					$all_types    = get_post_types( [ 'public' => true ], 'objects' );
					unset( $all_types['attachment'] );
					$type_names   = array_keys( $all_types );
					if ( empty( $type_names ) ) { $type_names = [ 'post', 'page' ]; }
					$in           = "'" . implode( "','", array_map( 'esc_sql', $type_names ) ) . "'";
					$total        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($in)" ); // $in is esc_sql'd from get_post_types()
					$rows         = $wpdb->get_results( "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ($in) ORDER BY post_title ASC LIMIT 10" );
					?>

					<div class="fpp-intro">
						<p><?php esc_html_e( 'Check up to 10 pages or posts. These will be scanned when you click Run Scan. Leave all unchecked to auto-discover published content.', 'nahnu-asset-scanner' ); ?></p>
						<span class="fpp-counter"><span id="fpp-count"><?php echo count( $selected_ids ); ?></span> / 10 <?php esc_html_e( 'selected', 'nahnu-asset-scanner' ); ?></span>
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
									<td class="fpp-tt"><?php echo esc_html( $row->post_title ?: '(no title)' ); ?></td>
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
						<span class="fpp-pinfo"><?php printf( esc_html__( 'Showing 1–10 of %d', 'nahnu-asset-scanner' ), $total ); ?></span>
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
							<button id="fpp-save" class="fas-btn fas-btn--primary"><?php esc_html_e( 'Save Selection', 'nahnu-asset-scanner' ); ?></button>
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

				<!-- Settings panel -->
				<div id="fas-panel-settings" class="fas-panel" data-panel="settings">
					<div class="fas-settings-grid">

						<div class="fas-card">
							<div class="fas-card__header"><?php esc_html_e( 'Scan Options', 'nahnu-asset-scanner' ); ?></div>
							<div class="fas-card__body">
								<form id="fas-settings-form">
									<div class="fas-field-row">
										<label for="fas-scan-timeout"><?php esc_html_e( 'Page timeout (seconds)', 'nahnu-asset-scanner' ); ?></label>
										<input id="nahnuas-scan-timeout" name="nahnuas_scan_timeout" type="number" min="5" max="120" value="<?php echo absint( get_option( 'nahnuas_scan_timeout', 15 ) ); ?>" style="width:80px;">
										<span style="font-size:12px;color:#6B7280;margin-left:8px;"><?php esc_html_e( '5–120 seconds. Increase for slow pages.', 'nahnu-asset-scanner' ); ?></span>
									</div>
									<div class="fas-field-row" style="margin-top:16px;">
										<label for="nahnuas-custom-urls"><?php esc_html_e( 'Additional URLs to scan', 'nahnu-asset-scanner' ); ?></label>
										<textarea id="nahnuas-custom-urls" name="nahnuas_custom_urls" rows="4" style="width:100%;font-family:monospace;font-size:12px;" placeholder="https://example.com/page-not-in-picker"><?php echo esc_textarea( get_option( 'nahnuas_custom_urls', '' ) ); ?></textarea>
										<span style="font-size:12px;color:#6B7280;"><?php esc_html_e( 'One URL per line. Use for pages not available in the picker (e.g. external checkout pages).', 'nahnu-asset-scanner' ); ?></span>
									</div>
									<div style="margin-top:16px;">
										<button type="button" id="fas-save-settings" class="fas-btn fas-btn--primary fas-btn--sm"><?php esc_html_e( 'Save', 'nahnu-asset-scanner' ); ?></button>
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
							<p class="fas-credit-card__desc"><?php esc_html_e( '600+ cookie patterns covering 400+ WordPress plugins and services — built and maintained as part of this plugin. Inspired by the FAZ Cookie Manager project (Fabio D&#39;Alessandro, GPL-3.0) and the broader WordPress ecosystem. Covers WooCommerce, membership plugins, LMS platforms, builders, security plugins, analytics services, ad networks, and more. No external API needed — updated with plugin releases.', 'nahnu-asset-scanner' ); ?></p>
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
							<p class="fas-credit-card__desc"><?php esc_html_e( 'Built on WordPress core APIs: WP_Query, dbDelta, the WP HTTP API, WP-Cron, and the Settings API. Follows WordPress coding standards and the plugin review guidelines.', 'nahnu-asset-scanner' ); ?></p>
							<div class="fas-credit-card__meta">
								<span>&#128100; WordPress.org</span>
								<a href="https://wordpress.org" target="_blank" rel="noopener">wordpress.org</a>
							</div>
						</div>


					</div><!-- /.fas-credits-grid -->

					<div class="fas-about-footer">
						<p><?php printf(
							esc_html__( 'Nahnu Asset Scanner is free software released under the %s license. Built with &#10084; by %s.', 'nahnu-asset-scanner' ),
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
		$this->verify_nonce();
		global $wpdb;

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$type   = isset( $_POST['ptype'] )  ? sanitize_key( wp_unslash( $_POST['ptype'] ) )          : '';
		$page   = isset( $_POST['paged'] )  ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) )                       : 1;
		$per    = 10;
		$offset = ( $page - 1 ) * $per;

		$selected_ids = array_map( 'intval', (array) get_option( 'nahnuas_selected_post_ids', [] ) );

		// Get public post types, exclude attachment
		$all_types = get_post_types( [ 'public' => true ], 'objects' );
		unset( $all_types['attachment'] );

		if ( $type && isset( $all_types[ $type ] ) ) {
			$type_names = [ $type ];
		} else {
			$type_names = array_keys( $all_types );
		}

		if ( empty( $type_names ) ) {
			$type_names = [ 'post', 'page' ];
		}

		$in = "'" . implode( "','", array_map( 'esc_sql', $type_names ) ) . "'";

		$search_sql = '';
		if ( $search !== '' ) {
			$like       = '%' . $wpdb->esc_like( $search ) . '%';
			$search_sql = $wpdb->prepare( ' AND post_title LIKE %s', $like );
		}

		$where = "post_status = 'publish' AND post_type IN ($in)$search_sql";
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE $where" ); // $in safe: esc_sql'd from get_post_types(); $search_sql uses prepare()
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE $where ORDER BY post_title ASC LIMIT %d OFFSET %d",
			$per, $offset
		) );

		$items = [];
		foreach ( $rows as $row ) {
			$pt      = $all_types[ $row->post_type ] ?? null;
			$items[] = [
				'id'    => (int) $row->ID,
				'title' => $row->post_title ?: '(no title)',
				'type'  => $row->post_type,
				'label' => $pt ? ( $pt->labels->singular_name ?: $pt->label ) : ucfirst( $row->post_type ),
				'sel'   => in_array( (int) $row->ID, $selected_ids, true ),
			];
		}

		$types = [];
		foreach ( $all_types as $k => $obj ) {
			$types[] = [ 'k' => $k, 'l' => $obj->labels->singular_name ?: $obj->label ];
		}

		wp_send_json_success( [
			'items'   => $items,
			'total'   => $total,
			'pages'   => max( 1, (int) ceil( $total / $per ) ),
			'page'    => $page,
			'sel_ids' => $selected_ids,
			'types'   => $types,
			'debug'   => [ 'where' => $where, 'types' => $type_names, 'total' => $total ],
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Start scan
	// -------------------------------------------------------------------------

	public function ajax_start_scan(): void {
		$this->verify_nonce();

		// Create scan record
		$scan_id = DB::create_scan();
		if ( ! $scan_id ) {
			wp_send_json_error( [ 'message' => 'Failed to create scan record.' ] );
			return;
		}
		DB::reset_domain_cache(); // clear in-memory dedup cache for fresh scan

		// Ensure cookie database is loaded — fetch in background if not yet cached.
		// This runs once (transient lasts 7 days) so it doesn't slow down every scan.

		// Get page list
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
		$this->verify_nonce();

		$scan_id   = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		$url       = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$page_type = isset( $_POST['page_type'] ) ? sanitize_text_field( wp_unslash( $_POST['page_type'] ) ) : 'page';
		$index     = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;

		if ( ! $scan_id || ! $url ) {
			wp_send_json_error( [ 'message' => 'Missing scan_id or url.' ] );
			return;
		}

		// Only allow http/https — block file://, ftp://, internal IPs, etc.
		if ( ! wp_http_validate_url( $url ) ) {
			wp_send_json_error( [ 'message' => 'Invalid URL.' ] );
			return;
		}

		$result = Scanner::scan_page( $scan_id, $url, $page_type );

		wp_send_json_success( array_merge( $result, [ 'index' => $index, 'url' => $url ] ) );
	}

	// -------------------------------------------------------------------------
	// AJAX: Complete scan
	// -------------------------------------------------------------------------

	public function ajax_complete_scan(): void {
		$this->verify_nonce();

		$scan_id = isset( $_POST['scan_id'] ) ? absint( wp_unslash( $_POST['scan_id'] ) ) : 0;
		if ( ! $scan_id ) {
			wp_send_json_error( [ 'message' => 'Missing scan_id.' ] );
			return;
		}

		$scan = DB::finalize_scan( $scan_id );

		wp_send_json_success( [ 'scan' => $scan ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Get results
	// -------------------------------------------------------------------------

	public function ajax_get_results(): void {
		$this->verify_nonce();

		$tab         = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'js';
		$page        = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$third_party = ( isset( $_POST['third_party'] ) && $_POST['third_party'] !== '' ) ? absint( sanitize_text_field( wp_unslash( $_POST['third_party'] ) ) ) : null;

		$args = [
			'page'        => $page,
			'search'      => $search,
			'third_party' => $third_party,
		];

		$items = DB::get_results( $tab, $args );
		$total = DB::get_results_count( $tab, $args );
		$pages = (int) ceil( $total / 10 );

		foreach ( $items as &$item ) {
			// $items rows are ARRAY_A — use array notation throughout
			// (object notation on arrays silently fails in PHP 8+)

			// Vendor badge for JS/CSS/Domains
			if ( ! empty( $item['domain'] ) ) {
				$item['badge'] = Scanner::get_badge( $item['domain'] );
			}

		}
		unset( $item );

		wp_send_json_success( [
			'items'        => $items,
			'total'        => $total,
			'pages'        => $pages,
			'current_page' => $page,
			'tab'          => $tab,
		] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Export CSV  (POST form submit — not a wp_send_json handler)
	// -------------------------------------------------------------------------

	public function ajax_export_csv(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Insufficient permissions.' );
		}
		if ( ! isset( $_POST['nahnuas_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nahnuas_nonce'] ) ), self::NONCE ) ) {
			wp_die( 'Bad nonce.' );
		}

		$tab   = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'js';
		$items = DB::get_results( $tab, [ 'page' => 1, 'per_page' => 99999 ] );

		$filename = 'fas-' . $tab . '-export-' . gmdate( 'Ymd-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Helper: rows come back as assoc arrays; this normalises field access
		$f = function( $row, string $key, $default = '' ) {
			return is_array( $row ) ? ( $row[ $key ] ?? $default ) : ( $row->$key ?? $default );
		};

		// Pages are stored as ||| separated URLs — convert to comma-separated titles
		$pages_str = function( $raw ): string {
			if ( empty( $raw ) ) { return ''; }
			$urls  = array_filter( array_map( 'trim', explode( '|||', $raw ) ) );
			$names = array_map( function( $u ) {
				try {
					$path  = parse_url( $u, PHP_URL_PATH );
					$parts = array_filter( explode( '/', trim( $path ?? '', '/' ) ) );
					$last  = $parts ? end( $parts ) : 'Home';
					return ucfirst( str_replace( '-', ' ', $last ) );
				} catch ( \Exception $e ) {
					return $u;
				}
			}, $urls );
			return implode( ', ', $names );
		};

		// CSV injection guard: prefix cells starting with =, +, -, @, tab, CR with a tab.
		// These characters can trigger formula execution in spreadsheet apps.
		$csv_safe = function( $val ): string {
			$val = (string) $val;
			if ( $val !== '' && in_array( $val[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
				return "\t" . $val;
			}
			return $val;
		};

		$out = fopen( 'php://output', 'w' );

		switch ( $tab ) {
			case 'js':
				fputcsv( $out, [ 'URL', 'Domain', 'Source', 'Inline', 'Defer', 'Async', 'Version', 'Pages' ] );
				foreach ( $items as $r ) {
					$attrs = json_decode( (string) $f( $r, 'attributes_json', '{}' ), true ) ?: [];
					fputcsv( $out, [
						$f( $r, 'asset_url' ),
						$f( $r, 'domain' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$f( $r, 'is_inline' ) ? 'yes' : 'no',
						! empty( $attrs['defer'] ) ? 'yes' : 'no',
						! empty( $attrs['async'] ) ? 'yes' : 'no',
						$csv_safe( $attrs['version'] ?? $f( $r, 'version', '' ) ),
						$csv_safe( $pages_str( $f( $r, 'pages' ) ) ),
					] );
				}
				break;

			case 'css':
				fputcsv( $out, [ 'URL', 'Domain', 'Source', 'Inline', 'Media', 'Version', 'Pages' ] );
				foreach ( $items as $r ) {
					$attrs = json_decode( (string) $f( $r, 'attributes_json', '{}' ), true ) ?: [];
					fputcsv( $out, [
						$f( $r, 'asset_url' ),
						$f( $r, 'domain' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$f( $r, 'is_inline' ) ? 'yes' : 'no',
						$csv_safe( $attrs['media'] ?? $f( $r, 'media', 'all' ) ),
						$csv_safe( $attrs['version'] ?? $f( $r, 'version', '' ) ),
						$csv_safe( $pages_str( $f( $r, 'pages' ) ) ),
					] );
				}
				break;

			case 'cookies':
				fputcsv( $out, [ 'Name', 'Domain', 'Path', 'Source', 'Session', 'Expires', 'Secure', 'HttpOnly', 'SameSite', 'Category', 'Pages' ] );
				foreach ( $items as $r ) {
					fputcsv( $out, [
						$csv_safe( $f( $r, 'cookie_name' ) ),
						$csv_safe( $f( $r, 'cookie_domain' ) ),
						$csv_safe( $f( $r, 'cookie_path' ) ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$f( $r, 'is_session' ) ? 'yes' : 'no',
						$csv_safe( $f( $r, 'expiration' ) ),
						$f( $r, 'is_secure' ) ? 'yes' : 'no',
						$f( $r, 'is_httponly' ) ? 'yes' : 'no',
						$csv_safe( $f( $r, 'samesite' ) ),
						$f( $r, 'category' ),
						$csv_safe( $pages_str( $f( $r, 'pages' ) ) ),
					] );
				}
				break;

			case 'domains':
				fputcsv( $out, [ 'Domain', 'Source Types', 'Source', 'Pages', 'Occurrences' ] );
				foreach ( $items as $r ) {
					fputcsv( $out, [
						$f( $r, 'domain' ),
						$f( $r, 'source_types' ),
						$f( $r, 'is_third_party' ) ? 'third-party' : 'first-party',
						$csv_safe( $pages_str( $f( $r, 'pages' ) ) ),
						$f( $r, 'item_count', 0 ),
					] );
				}
				break;
		}

		fclose( $out );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX: Clear data
	// -------------------------------------------------------------------------

	public function ajax_clear_data(): void {
		$this->verify_nonce();

		DB::clear_all();

		wp_send_json_success( [ 'message' => 'All scan data cleared.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Save settings
	// -------------------------------------------------------------------------

	public function ajax_save_settings(): void {
		$this->verify_nonce();

		$scan_timeout = isset( $_POST['nahnuas_scan_timeout'] ) ? max( 5, min( 120, absint( wp_unslash( $_POST['nahnuas_scan_timeout'] ) ) ) ) : 15;
		$custom_urls  = isset( $_POST['nahnuas_custom_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nahnuas_custom_urls'] ) ) : '';

		update_option( 'nahnuas_scan_timeout', $scan_timeout );
		update_option( 'nahnuas_custom_urls',  $custom_urls );

		wp_send_json_success( [ 'message' => 'Settings saved.' ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Picker — save selected IDs
	// -------------------------------------------------------------------------

	public function ajax_picker_save(): void {
		$this->verify_nonce();

		$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '[]';
		$ids = json_decode( $raw, true );
		if ( ! is_array( $ids ) ) { $ids = []; }
		$ids = array_values( array_slice( array_unique( array_map( 'intval', $ids ) ), 0, 10 ) );

		update_option( 'nahnuas_selected_post_ids', $ids );

		wp_send_json_success( [ 'ids' => $ids, 'count' => count( $ids ) ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: Refresh Open Cookie Database
	// -------------------------------------------------------------------------






	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function verify_nonce(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce.' ], 403 );
		}
	}
}
