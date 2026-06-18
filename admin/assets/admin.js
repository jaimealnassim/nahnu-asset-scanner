/**
 * Nahnu Asset Scanner — Admin JS
 * by Nahnu Plugins
 */
/* global nahnuasData */
(function () {
	'use strict';

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	const FAS = {
		currentTab:   'pages',
		tabLoaded:    {},           // { js: bool, css: bool, … }
		tabPages:     {},           // { js: 1, css: 1, … }
		tabSearch:    {},           // { js: '', css: '', … }
		tabFilter:    {},           // { js: '', css: '', … }
		scanning:     false,
		scanId:       null,
		scanCancelled: false,
		searchTimers: {},
	};

	// -------------------------------------------------------------------------
	// DOM ready
	// -------------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		bindTabNav();
		bindRunScan();
		bindCancelScan();
		bindExportButtons();
		bindSettingsForm();
		bindClearData();
		bindSearchInputs();
		bindFilterSelects();

		// Always boot the Pages picker tab on load
		PagePicker.init();



	} );

	// -------------------------------------------------------------------------
	// Tab navigation
	// -------------------------------------------------------------------------

	function bindTabNav() {
		document.querySelectorAll( '.fas-tab-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				switchTab( btn.dataset.tab );
			} );
		} );
	}

	function switchTab( tab ) {
		FAS.currentTab = tab;

		// Update button states
		document.querySelectorAll( '.fas-tab-btn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'fas-tab-btn--active', btn.dataset.tab === tab );
		} );

		// Show/hide panels
		document.querySelectorAll( '.fas-panel' ).forEach( function ( panel ) {
			panel.classList.toggle( 'fas-panel--active', panel.dataset.panel === tab );
		} );

		// Load data tabs on first visit
		if ( tab === 'pages' ) {
			// nothing - PagePicker handles its own state
		} else if ( tab !== 'settings' ) {
			if ( ! FAS.tabLoaded[ tab ] ) {
				loadTab( tab );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Load tab data
	// -------------------------------------------------------------------------

	function loadTab( tab, force ) {
		if ( ! force && FAS.tabLoaded[ tab ] ) {
			return;
		}

		const page   = FAS.tabPages[ tab ]   || 1;
		const search = FAS.tabSearch[ tab ]   || '';
		const filter = FAS.tabFilter[ tab ]   || '';

		showTableLoading( tab );

		fasAjax( 'nahnuas_get_results', {
			tab:         tab,
			paged:       page,
			search:      search,
			third_party: filter,
		} ).then( function ( res ) {
			if ( res.success ) {
				FAS.tabLoaded[ tab ] = true;
				renderTable( tab, res.data );
				renderPagination( tab, res.data );
			} else {
				showTableEmpty( tab, 'Error loading results.' );
			}
		} ).catch( function () {
			showTableEmpty( tab, 'Request failed.' );
		} );
	}

	// -------------------------------------------------------------------------
	// Render table
	// -------------------------------------------------------------------------

	function renderTable( tab, data ) {
		const wrap = document.getElementById( 'fas-table-' + tab );
		if ( ! wrap ) { return; }

		if ( ! data.items || data.items.length === 0 ) {
			wrap.innerHTML = '<div class="fas-table-empty">' +
				'<div>' + escHtml( nahnuasData.strings.noResults ) + '</div>' +
				'</div>';
			return;
		}

		let html = '<table class="fas-table"><thead><tr>';
		html += theadForTab( tab );
		html += '</tr></thead><tbody>';

		data.items.forEach( function ( item ) {
			html += '<tr>' + renderRow( tab, item ) + '</tr>';
		} );

		html += '</tbody></table>';
		wrap.innerHTML = html;
	}

	function theadForTab( tab ) {
		switch ( tab ) {
			case 'js':
				return th('File URL') + th('Domain') + th('Source') + th('Defer') + th('Async') +
				       th('Version') + th('Pages');
			case 'css':
				return th('File URL') + th('Domain') + th('Source') + th('Media') +
				       th('Version') + th('Pages');
			case 'cookies':
				return th('Name') + th('Domain') + th('Source') + th('Session') +
				       th('Expires') + th('Secure') + th('HttpOnly') + th('SameSite') +
				       th('Category') + th('Pages');
			case 'domains':
				return th('Domain') + th('Types') + th('Source') + th('Pages') + th('Count');
		}
		return '';
	}

	function th( label ) {
		return '<th>' + escHtml( label ) + '</th>';
	}

	function renderRow( tab, item ) {
		switch ( tab ) {
			case 'js':   return renderJsRow( item );
			case 'css':  return renderCssRow( item );
			case 'cookies': return renderCookieRow( item );
			case 'domains': return renderDomainRow( item );
		}
		return '';
	}

	function isThirdParty( item ) {
		if ( item.is_inline == 1 ) { return false; }
		if ( ! item.asset_url ) { return item.is_third_party == 1; }
		try {
			const assetHost = new URL( item.asset_url ).hostname.toLowerCase().replace( /:\d+$/, '' );
			const siteHost  = ( nahnuasData.siteHost || '' ).toLowerCase().replace( /:\d+$/, '' );
			if ( ! siteHost || ! assetHost ) { return !! item.is_third_party; }
			const assetBase = assetHost.split('.').slice(-2).join('.');
			const siteBase  = siteHost.split('.').slice(-2).join('.');
			return assetBase !== siteBase;
		} catch (e) { return !! item.is_third_party; }
	}

	function sourceTag( url, thirdParty ) {
		if ( thirdParty ) {
			return '<span class="fas-tag fas-tag--third">3rd-party</span>';
		}
		const plugin = extractPlugin( url );
		if ( plugin ) {
			return '<span class="fas-tag fas-tag--plugin" title="' + escHtml( url ) + '">' + escHtml( plugin ) + '</span>';
		}
		return '<span class="fas-tag fas-tag--first">1st-party</span>';
	}

	function renderJsRow( item ) {
		const thirdParty = isThirdParty( item );
		const badge  = renderVendorBadge( item.badge );
		const isInlineJs = item.is_inline == 1;
		const source = isInlineJs
			? '<span class="fas-tag fas-tag--inline">Inline</span>'
			: sourceTag( item.asset_url, thirdParty );

		const attrs   = safeJsonParse( item.attributes_json, {} );
		const fileUrl = isInlineJs ? '' : formatFileUrl( item.asset_url, thirdParty );
		const domainCell = thirdParty
			? '<span class="fas-table__domain">' + escHtml( item.domain || '—' ) + '</span>' + badge + trackerTag( item )
			: '<span class="fas-tag fas-tag--first">1st-party</span>';

		return td( '<span class="fas-table__url" title="' + escHtml( item.asset_url || '' ) + '">' + ( isInlineJs ? '<em style="color:#9CA3AF">Inline</em>' : fileUrl + copyBtn( item.asset_url ) ) + '</span>' ) +
		       td( isInlineJs ? '' : domainCell ) +
		       td( source ) +
		       td( boolCheck( attrs.defer ) ) +
		       td( boolCheck( attrs.async ) ) +
		       td( escHtml( attrs.version || item.version || '—' ) ) +
		       td( renderPagesList( item.pages ) );
	}

	function renderCssRow( item ) {
		const thirdParty = isThirdParty( item );
		const badge  = renderVendorBadge( item.badge );
		const isInlineCss = item.is_inline == 1;
		const source = isInlineCss
			? '<span class="fas-tag fas-tag--inline">Inline</span>'
			: sourceTag( item.asset_url, thirdParty );

		const attrs   = safeJsonParse( item.attributes_json, {} );
		const fileUrl = isInlineCss ? '' : formatFileUrl( item.asset_url, thirdParty );
		const domainCell = thirdParty
			? '<span class="fas-table__domain">' + escHtml( item.domain || '—' ) + '</span>' + badge + trackerTag( item )
			: '<span class="fas-tag fas-tag--first">1st-party</span>';

		return td( '<span class="fas-table__url" title="' + escHtml( item.asset_url || '' ) + '">' + ( isInlineCss ? '<em style="color:#9CA3AF">Inline style</em>' : fileUrl + copyBtn( item.asset_url ) ) + '</span>' ) +
		       td( isInlineCss ? '' : domainCell ) +
		       td( source ) +
		       td( escHtml( attrs.media || 'all' ) ) +
		       td( escHtml( isInlineCss ? '—' : ( attrs.version || item.version || '—' ) ) ) +
		       td( renderPagesList( item.pages ) );
	}

	function renderCookieRow( item ) {
		const thirdParty = isThirdPartyDomain( item.cookie_domain );
		let sourceCell;
		if ( thirdParty ) {
			sourceCell = '<span class="fas-tag fas-tag--third">3rd-party</span>';
		} else {
			// plugin_source set by Plugin_Cookies (WP-specific) or OCD (commercial trackers)
			const vendor = item.plugin_source || item.ocd_platform || guessCookiePlugin( item.cookie_name );
			const desc   = item.cookie_description || item.ocd_description || '';
			sourceCell = vendor
				? '<span class="fas-tag fas-tag--plugin" title="' + escHtml( desc ) + '">' + escHtml( vendor ) + '</span>'
				: '<span class="fas-tag fas-tag--first">1st-party (plugin)</span>';
		}

		return td( '<strong>' + escHtml( item.cookie_name || '' ) + '</strong>' + copyBtn( item.cookie_name ) ) +
		       td( escHtml( item.cookie_domain || '—' ) + ( item.cookie_domain ? copyBtn( item.cookie_domain ) : '' ) ) +
		       td( sourceCell ) +
		       td( boolCheck( item.is_session == 1, 'Session', 'Persistent' ) ) +
		       td( item.expiration ? escHtml( item.expiration ) : '—' ) +
		       td( boolCheck( item.is_secure == 1 ) ) +
		       td( boolCheck( item.is_httponly == 1 ) ) +
		       td( escHtml( item.samesite || '—' ) ) +
		       td( renderCategoryBadge( item.category ) ) +
		       td( renderPagesList( item.pages ) );
	}

	function renderDomainRow( item ) {
		const thirdParty = isThirdPartyDomain( item.domain );
		const badge = renderVendorBadge( item.badge );
		const types = ( item.source_types || '' ).split( ',' )
			.map( function ( t ) { return '<span class="fas-tag fas-tag--inline">' + escHtml( t.trim() ) + '</span>'; } )
			.join( ' ' );


		if ( item.tracker_name ) {
			const privLink = item.tracker_privacy
				? ' <a href="' + escHtml( item.tracker_privacy ) + '" target="_blank" rel="noopener" style="color:#1D4ED8;font-size:11px;" title="Privacy policy">↗</a>'
				: '';
			trackerInfo = '<div class="fas-tracker-info">' +
				'<span class="fas-tracker-name">' + escHtml( item.tracker_name ) + '</span>' +
				( item.tracker_company ? ' <span class="fas-tracker-company">' + escHtml( item.tracker_company ) + '</span>' : '' ) +
				( item.tracker_category ? ' <span class="fas-tag fas-tag--tracker">' + escHtml( item.tracker_category ) + '</span>' : '' ) +
				privLink +
				'</div>';
		}

		return td( '<strong class="fas-table__domain">' + escHtml( item.domain || '' ) + '</strong>' + badge + copyBtn( item.domain ) + trackerInfo ) +
		       td( types ) +
		       td( partyTag( thirdParty ) ) +
		       td( renderPagesList( item.pages ) ) +
		       td( escHtml( String( item.occurrences || 1 ) ) );
	}

	function isThirdPartyDomain( domain ) {
		if ( ! domain ) { return false; }
		const siteHost = ( nahnuasData.siteHost || '' ).toLowerCase().replace( /:\d+$/, '' );
		const d        = domain.toLowerCase().replace( /:\d+$/, '' );
		if ( ! siteHost || d === siteHost ) { return false; }
		const siteBase   = siteHost.split('.').slice(-2).join('.');
		const domainBase = d.split('.').slice(-2).join('.');
		return siteBase !== domainBase;
	}

	function td( content ) {
		return '<td>' + content + '</td>';
	}

	// -------------------------------------------------------------------------
	// Pagination
	// -------------------------------------------------------------------------

	function renderPagination( tab, data ) {
		const wrap = document.getElementById( 'fas-pagination-' + tab );
		if ( ! wrap ) { return; }

		const total   = data.pages || 1;
		const current = data.current_page || 1;

		if ( total <= 1 ) {
			wrap.innerHTML = '';
			return;
		}

		let html = '';

		// Prev
		html += pageBtn( '&laquo;', current > 1 ? current - 1 : null, tab, current === 1 );

		// Page numbers with ellipsis
		const pages = buildPageRange( current, total );
		pages.forEach( function ( p ) {
			if ( p === '…' ) {
				html += '<span class="fas-page-ellipsis">…</span>';
			} else {
				html += pageBtn( p, p, tab, false, p === current );
			}
		} );

		// Next
		html += pageBtn( '&raquo;', current < total ? current + 1 : null, tab, current === total );

		wrap.innerHTML = html;

		// Bind clicks
		wrap.querySelectorAll( '.fas-page-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const pageNum = parseInt( btn.dataset.page, 10 );
				if ( isNaN( pageNum ) ) { return; }
				FAS.tabPages[ tab ] = pageNum;
				FAS.tabLoaded[ tab ] = false;
				loadTab( tab );
			} );
		} );
	}

	function pageBtn( label, page, tab, disabled, active ) {
		const cls = 'fas-page-btn' +
			( active ? ' fas-page-btn--active' : '' );
		const dis = disabled ? ' disabled' : '';
		const data = page !== null ? ' data-page="' + page + '"' : '';
		return '<button class="' + cls + '"' + dis + data + '>' + label + '</button>';
	}

	function buildPageRange( current, total ) {
		if ( total <= 7 ) {
			return range( 1, total );
		}
		const pages = [];
		pages.push( 1 );
		if ( current > 4 ) { pages.push( '…' ); }
		const start = Math.max( 2, current - 2 );
		const end   = Math.min( total - 1, current + 2 );
		for ( let i = start; i <= end; i++ ) { pages.push( i ); }
		if ( current < total - 3 ) { pages.push( '…' ); }
		pages.push( total );
		return pages;
	}

	function range( a, b ) {
		const arr = [];
		for ( let i = a; i <= b; i++ ) { arr.push( i ); }
		return arr;
	}

	// -------------------------------------------------------------------------
	// Search & filter
	// -------------------------------------------------------------------------

	function bindSearchInputs() {
		document.querySelectorAll( '.fas-search' ).forEach( function ( input ) {
			input.addEventListener( 'input', function () {
				const tab = input.dataset.tab;
				clearTimeout( FAS.searchTimers[ tab ] );
				FAS.searchTimers[ tab ] = setTimeout( function () {
					FAS.tabSearch[ tab ]  = input.value;
					FAS.tabPages[ tab ]   = 1;
					FAS.tabLoaded[ tab ]  = false;
					loadTab( tab );
				}, 400 );
			} );
		} );
	}

	function bindFilterSelects() {
		document.querySelectorAll( '.fas-filter-party' ).forEach( function ( sel ) {
			sel.addEventListener( 'change', function () {
				const tab = sel.dataset.tab;
				FAS.tabFilter[ tab ]  = sel.value;
				FAS.tabPages[ tab ]   = 1;
				FAS.tabLoaded[ tab ]  = false;
				loadTab( tab );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Scan engine
	// -------------------------------------------------------------------------

	function bindRunScan() {
		const btn = document.getElementById( 'fas-run-scan' );
		if ( btn ) {
			btn.addEventListener( 'click', runScan );
		}
	}

	function bindCancelScan() {
		const btn = document.getElementById( 'fas-cancel-scan' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				FAS.scanCancelled = true;
			} );
		}
	}

	async function runScan() {
		if ( FAS.scanning ) { return; }

		FAS.scanning     = true;
		FAS.scanCancelled = false;

		// Reset tab state
		[ 'js', 'css', 'cookies', 'domains' ].forEach( function ( t ) {
			FAS.tabLoaded[ t ] = false;
			FAS.tabPages[ t ]  = 1;
		} );

		setStatusBadge( 'scanning', 'Scanning…' );
		showProgress( true );
		setProgress( 0, 'Starting scan…', '' );
		disableRunBtn( true );

		try {
			// 1. Start scan
			const start = await fasAjax( 'nahnuas_start_scan', {} );
			if ( ! start.success ) {
				throw new Error( start.data ? start.data.message : 'Start failed' );
			}

			const { scan_id, pages, total } = start.data;
			FAS.scanId = scan_id;

			// 2. Scan each page
			let scanned = 0;

			for ( let i = 0; i < pages.length; i++ ) {
				if ( FAS.scanCancelled ) {
					break;
				}

				const p = pages[ i ];
				setProgress(
					Math.round( ( i / total ) * 100 ),
					truncateUrl( p.url, 70 ),
					( i + 1 ) + ' / ' + total
				);

				await fasAjax( 'nahnuas_scan_page', {
					scan_id:   scan_id,
					url:       p.url,
					page_type: p.page_type,
					index:     i,
				} );

				scanned++;
			}

			// 3. Finalize
			setProgress( 95, 'Finalizing…', '' );
			const complete = await fasAjax( 'nahnuas_complete_scan', { scan_id: scan_id } );
			if ( complete.success && complete.data.scan ) {
				updateStatusBar( complete.data.scan );
			}

			if ( FAS.scanCancelled ) {
				setStatusBadge( 'idle', 'Cancelled' );
			} else {
				setStatusBadge( 'complete', 'Complete' );
				setProgress( 100, 'Scan complete!', scanned + ' / ' + total );
			}

			// 4. Load current tab
			loadTab( FAS.currentTab, true );

		} catch ( err ) {
			setStatusBadge( 'failed', 'Scan failed' );
			setProgress( 0, 'Error: ' + err.message, '' );
		} finally {
			FAS.scanning = false;
			disableRunBtn( false );
			setTimeout( function () { showProgress( false ); }, 3000 );
		}
	}

	// -------------------------------------------------------------------------
	// Export CSV
	// -------------------------------------------------------------------------

	function bindExportButtons() {
		document.querySelectorAll( '.fas-btn--export' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const tab = btn.dataset.tab;
				exportCsv( tab );
			} );
		} );
	}

	function exportCsv( tab ) {
		const form = document.createElement( 'form' );
		form.method  = 'POST';
		form.action  = nahnuasData.ajaxUrl;
		form.style.display = 'none';

		[ [ 'action', 'nahnuas_export_csv' ], [ 'tab', tab ], [ 'nahnuas_nonce', nahnuasData.nonce ] ]
			.forEach( function ( pair ) {
				const input = document.createElement( 'input' );
				input.type  = 'hidden';
				input.name  = pair[0];
				input.value = pair[1];
				form.appendChild( input );
			} );

		document.body.appendChild( form );
		form.submit();
		document.body.removeChild( form );
	}

	// -------------------------------------------------------------------------
	// Settings form
	// -------------------------------------------------------------------------

	function bindSettingsForm() {
		const saveBtn = document.getElementById( 'fas-save-settings' );
		if ( ! saveBtn ) { return; }

		saveBtn.addEventListener( 'click', async function () {
			saveBtn.disabled = true;

			const inputVal = function ( id ) {
				const el = document.getElementById( id );
				return el ? el.value : '';
			};

			const data = {
				nahnuas_scan_timeout: inputVal( 'nahnuas-scan-timeout' ),
				nahnuas_custom_urls:  inputVal( 'nahnuas-custom-urls' ),
			};

			try {
				const res = await fasAjax( 'nahnuas_save_settings', data );
				if ( res.success ) {
					const notice = document.getElementById( 'fas-settings-saved' );
					if ( notice ) {
						notice.style.display = 'inline';
						setTimeout( function () { notice.style.display = 'none'; }, 2500 );
					}
				}
			} finally {
				saveBtn.disabled = false;
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Clear data
	// -------------------------------------------------------------------------

	function bindClearData() {
		const btn = document.getElementById( 'fas-clear-data' );
		if ( ! btn ) { return; }

		btn.addEventListener( 'click', async function () {
			if ( ! window.confirm( nahnuasData.strings.confirmClear ) ) { return; }

			btn.disabled = true;

			try {
				const res = await fasAjax( 'nahnuas_clear_data', {} );
				if ( res.success ) {
					[ 'js', 'css', 'cookies', 'domains' ].forEach( function ( t ) {
						FAS.tabLoaded[ t ] = false;
					} );
					updateStatEl( 'fas-stat-pages',   '0' );
					updateStatEl( 'fas-stat-js',      '0' );
					updateStatEl( 'fas-stat-css',     '0' );
					updateStatEl( 'fas-stat-cookies', '0' );
					updateStatEl( 'fas-stat-domains', '0' );
					setStatusBadge( 'idle', 'Idle — no scan yet' );
					loadTab( FAS.currentTab, true );
				}
			} finally {
				btn.disabled = false;
			}
		} );
	}

	// -------------------------------------------------------------------------
	// AJAX helper
	// -------------------------------------------------------------------------

	function fasAjax( action, data ) {
		const body = new URLSearchParams( Object.assign( {
			action: action,
			nonce:  nahnuasData.nonce,
		}, data ) );

		return fetch( nahnuasData.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
			body:        body.toString(),
		} ).then( function ( res ) {
			return res.text();
		} ).then( function ( text ) {
			// Strip any stray PHP notices/warnings prepended before the JSON
			const jsonStart = text.indexOf( '{' );
			if ( jsonStart === -1 ) {
				const preview = text.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 200 );
				throw new Error( 'No JSON in response: ' + preview );
			}
			try {
				return JSON.parse( text.slice( jsonStart ) );
			} catch ( e ) {
				const preview = text.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 200 );
				throw new Error( 'JSON parse failed. Response: ' + preview );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// UI helpers
	// -------------------------------------------------------------------------

	function showProgress( visible ) {
		const wrap = document.getElementById( 'fas-progress-wrap' );
		if ( wrap ) { wrap.style.display = visible ? 'block' : 'none'; }
	}

	function setProgress( pct, text, count ) {
		const bar  = document.getElementById( 'fas-progress-bar' );
		const txt  = document.getElementById( 'fas-progress-text' );
		const cnt  = document.getElementById( 'fas-progress-count' );
		if ( bar ) { bar.style.width = pct + '%'; }
		if ( txt ) { txt.textContent = text; }
		if ( cnt ) { cnt.textContent = count; }
	}

	function disableRunBtn( state ) {
		const btn = document.getElementById( 'fas-run-scan' );
		if ( btn ) { btn.disabled = state; }
	}

	function setStatusBadge( status, label ) {
		const badge = document.getElementById( 'fas-status-badge' );
		if ( ! badge ) { return; }
		badge.className = 'fas-badge fas-badge--' + status;
		badge.textContent = label;
	}

	function updateStatusBar( scan ) {
		updateStatEl( 'fas-stat-pages',   scan.pages_scanned   || 0 );
		updateStatEl( 'fas-stat-js',      scan.total_js        || 0 );
		updateStatEl( 'fas-stat-css',     scan.total_css       || 0 );
		updateStatEl( 'fas-stat-cookies', scan.total_cookies   || 0 );
		updateStatEl( 'fas-stat-domains', scan.total_domains   || 0 );
	}

	function updateStatEl( id, value ) {
		const el = document.getElementById( id );
		if ( el ) { el.textContent = value; }
	}

	function showTableLoading( tab ) {
		const wrap = document.getElementById( 'fas-table-' + tab );
		if ( wrap ) {
			wrap.innerHTML = '<div class="fas-table-loading">' +
				'<div class="fas-spinner"></div>' +
				'<div>Loading…</div></div>';
		}
		const pg = document.getElementById( 'fas-pagination-' + tab );
		if ( pg ) { pg.innerHTML = ''; }
	}

	function showTableEmpty( tab, msg ) {
		const wrap = document.getElementById( 'fas-table-' + tab );
		if ( wrap ) {
			wrap.innerHTML = '<div class="fas-table-empty">' + escHtml( msg || 'No results found.' ) + '</div>';
		}
	}

	// -------------------------------------------------------------------------
	// Render helpers
	// -------------------------------------------------------------------------

	function partyTag( isThird ) {
		if ( isThird ) {
			return '<span class="fas-tag fas-tag--third">3rd-party</span>';
		}
		return '<span class="fas-tag fas-tag--first">1st-party</span>';
	}

	
	function trackerTag( item ) {
		if ( ! item.tracker_name ) { return ''; }
		const priv = item.tracker_privacy
			? ' <a href="' + escHtml( item.tracker_privacy ) + '" target="_blank" rel="noopener" class="fas-tracker-privacy" title="Privacy policy">↗</a>'
			: '';
		const cat = item.tracker_category
			? ' <span class="fas-tag fas-tag--tracker">' + escHtml( item.tracker_category ) + '</span>'
			: '';
		return '<div class="fas-tracker-info">' +
			'<span class="fas-tracker-name">' + escHtml( item.tracker_name ) + '</span>' +
			( item.tracker_company ? ' · <span class="fas-tracker-company">' + escHtml( item.tracker_company ) + '</span>' : '' ) +
			cat + priv +
			'</div>';
	}

	/** Extract a human-readable plugin/theme/core label from a first-party asset URL */
	function extractPlugin( url ) {
		if ( ! url ) { return null; }
		try {
			const path = new URL( url ).pathname;

			// Plugin — check server-side map first, then slug-to-title fallback
			const pluginMatch = path.match( /\/wp-content\/plugins\/([^/]+)\// );
			if ( pluginMatch ) {
				const slug = pluginMatch[1];
				const map  = nahnuasData.pluginMap || {};
				return map[ slug ] || map[ slug.toLowerCase() ] || slugToTitle( slug );
			}

			// Theme — check map then slug-to-title
			const themeMatch = path.match( /\/wp-content\/themes\/([^/]+)\// );
			if ( themeMatch ) {
				const slug = themeMatch[1];
				const map  = nahnuasData.pluginMap || {};
				const name = map[ slug ] || map[ slug.toLowerCase() ] || slugToTitle( slug );
				return name + ' (theme)';
			}

			// WP core
			if ( path.match( /\/wp-includes\// ) ) { return 'WordPress core'; }
			if ( path.match( /\/wp-admin\// ) )    { return 'WordPress admin'; }

			return null;
		} catch (e) { return null; }
	}

	function slugToTitle( slug ) {
		return slug.replace( /-/g, ' ' )
		           .replace( /\b\w/g, function (c) { return c.toUpperCase(); } );
	}

	/** Guess which plugin set a first-party cookie based on the cookie name */
	function guessCookiePlugin( name ) {
		if ( ! name ) { return null; }
		const n = name.toLowerCase();

		const map = [
			[ /^(wordpress_|wp-settings|wp_lang|wp_postpass|wordpressuser|wordpresspass|comment_author)/, 'WordPress' ],
			[ /^(woocommerce|wc_|wp_woocommerce|store_notice|woocommerce_recently|woocommerce_cart|woocommerce_session)/, 'WooCommerce' ],
			[ /^(wp_gravityforms|gf_|gravityforms)/, 'Gravity Forms' ],
			[ /^(elementor|e-page)/, 'Elementor' ],
			[ /^(bricks)/, 'Bricks' ],
			[ /^(yoast|wpseo)/, 'Yoast SEO' ],
			[ /^(rank_math)/, 'Rank Math' ],
			[ /^(cookielawinfo|cli_|viewed_cookie_policy)/, 'CookieYes' ],
			[ /^(cookieconsent)/, 'Cookiebot' ],
			[ /^(complianz|cmplz)/, 'Complianz' ],
			[ /^(pll_language)/, 'Polylang' ],
			[ /^(wpml_|icl_)/, 'WPML' ],
			[ /^(edd_|easy_digital)/, 'Easy Digital Downloads' ],
			[ /^(learndash|sfwd)/, 'LearnDash' ],
			[ /^(wplms)/, 'WPLMS' ],
			[ /^(fluent_|fluentcrm)/, 'FluentCRM' ],
			[ /^(surecart)/, 'SureCart' ],
		];

		for ( const [ regex, label ] of map ) {
			if ( regex.test( n ) ) { return label; }
		}
		return null;
	}

	function boolCheck( val, trueLabel, falseLabel ) {
		if ( val ) {
			return '<span class="fas-check-yes">' + escHtml( trueLabel || '✓' ) + '</span>';
		}
		return '<span class="fas-check-no">' + escHtml( falseLabel || '—' ) + '</span>';
	}

	function renderVendorBadge( badge ) {
		if ( ! badge || ! badge.label ) { return ''; }
		return '<span class="fas-vendor-badge fas-vendor--' + escHtml( badge.class || '' ) + '">' +
		       escHtml( badge.label ) + '</span>';
	}

	function renderCategoryBadge( cat ) {
		if ( ! cat || cat === 'unknown' ) {
			return '<span class="fas-tag fas-cat--unknown">Uncategorized</span>';
		}
		// Normalise common OCD variants
		const labels = {
			'functional':          'Functional',
			'necessary':           'Necessary',
			'preferences':         'Preferences',
			'preference':          'Preferences',
			'analytics':           'Analytics',
			'statistics':          'Analytics',
			'performance':         'Analytics',
			'marketing':           'Marketing',
			'tracking':            'Marketing',
			'socialmedia':         'Social Media',
			'social':              'Social Media',
			'audiovideoplayer':    'Media Player',
			'customerinteraction': 'Support',
			'consent':             'Consent',
			'hosting':             'Hosting',
			'utilities':           'Utilities',
			'siteanalytics':       'Analytics',
			'advertising':         'Advertising',
		};
		const key   = cat.toLowerCase().replace( /[_\s-]/g, '' );
		const label = labels[ cat.toLowerCase() ] || labels[ key ] || ( cat.charAt(0).toUpperCase() + cat.slice(1) );
		return '<span class="fas-tag fas-cat--' + escHtml( cat.toLowerCase() ) + '">' + escHtml( label ) + '</span>';
	}

	function formatFileUrl( url, isThirdParty ) {
		if ( ! url ) { return '—'; }
		if ( isThirdParty ) {
			// Third-party: show full URL truncated
			return '<a href="' + escHtml( url ) + '" target="_blank" rel="noopener">' + escHtml( truncateUrl( url, 55 ) ) + '</a>';
		}
		// First-party: strip the origin, show just the path
		try {
			const u = new URL( url );
			const path = u.pathname + ( u.search ? u.search : '' );
			return '<a href="' + escHtml( url ) + '" target="_blank" rel="noopener">' + escHtml( truncateUrl( path, 55 ) ) + '</a>';
		} catch ( e ) {
			return escHtml( truncateUrl( url, 55 ) );
		}
	}

	function renderPagesList( pages ) {
		if ( ! pages ) { return '—'; }

		const list = pages.split( ',' ).map( function ( u ) { return u.trim(); } ).filter( Boolean );
		if ( list.length === 0 ) { return '—'; }

		function pageLink( url ) {
			let name = url;
			try {
				const u = new URL( url );
				// Use last non-empty path segment as the name, fallback to "Home"
				const parts = u.pathname.split( '/' ).filter( Boolean );
				name = parts.length ? parts[ parts.length - 1 ].replace( /-/g, ' ' ) : 'Home';
				// Capitalise first letter
				name = name.charAt(0).toUpperCase() + name.slice(1);
			} catch (e) { /* keep original */ }
			return '<a href="' + escHtml( url ) + '" target="_blank" rel="noopener" title="' + escHtml( url ) + '">' + escHtml( name ) + '</a>';
		}

		let html = '<span class="fas-table__pages">' + pageLink( list[0] );

		if ( list.length > 1 ) {
			const restLinks = list.slice(1).map( pageLink ).join( ', ' );
			html += ' <span class="fas-table__pages-more" title="' + escHtml( list.slice(1).join('\n') ) + '">+' + ( list.length - 1 ) + ' more</span>';
		}

		html += '</span>';
		return html;
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function copyBtn( text ) {
		if ( ! text ) { return ''; }
		return '<button class="fas-copy-btn" data-copy="' + escHtml( text ) + '" title="Copy" onclick="(function(b){' +
			'navigator.clipboard.writeText(b.dataset.copy).then(function(){' +
			'var o=b.textContent;b.textContent=\'✓\';b.classList.add(\'fas-copy-btn--ok\');' +
			'setTimeout(function(){b.textContent=o;b.classList.remove(\'fas-copy-btn--ok\')},1200);' +
			'});})(this);event.stopPropagation();">⎘</button>';
	}

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function truncateUrl( url, len ) {
		if ( ! url ) { return ''; }
		if ( url.length <= len ) { return url; }
		return url.slice( 0, len ) + '…';
	}

	function safeJsonParse( str, fallback ) {
		try {
			return JSON.parse( str );
		} catch ( e ) {
			return fallback;
		}
	}

	// =========================================================================
	// PagePicker — Pages tab
	// =========================================================================

	const PagePicker = ( function () {

		const MAX = 10;
		var sel      = [];   // selected IDs (int[])
		var allRows  = [];   // current page rows
		var curPage  = 1;
		var totPages = 1;
		var totItems = 0;
		var typesLoaded = false;
		var searchTimer = null;

		function init() {
			// Table already rendered by PHP — just bind events
			// Seed sel[] from currently checked checkboxes in the PHP-rendered table
			document.querySelectorAll( '#fpp-table-wrap input[type=checkbox]:checked' ).forEach( function ( cb ) {
				var row = cb.closest( 'tr' );
				if ( row ) { sel.push( parseInt( row.dataset.id, 10 ) ); }
			} );
			updateCounter();
			bindRows();

			// Bind pagination buttons from PHP-rendered pager
			bindPagerBtns();

			var srch = document.getElementById( 'fpp-search' );
			if ( srch ) {
				srch.value = '';
				srch.addEventListener( 'input', function () {
					clearTimeout( searchTimer );
					searchTimer = setTimeout( function () { load( 1 ); }, 350 );
				} );
			}

			var tf = document.getElementById( 'fpp-type-filter' );
			if ( tf ) { tf.addEventListener( 'change', function () { load( 1 ); } ); }

			var clr = document.getElementById( 'fpp-clear-sel' );
			if ( clr ) { clr.addEventListener( 'click', function () { sel = []; renderTable(); updateCounter(); } ); }

			var sav = document.getElementById( 'fpp-save' );
			if ( sav ) { sav.addEventListener( 'click', save ); }
		}

		function bindRows() {
			var wrap = document.getElementById( 'fpp-table-wrap' );
			if ( ! wrap ) { return; }
			wrap.querySelectorAll( 'tbody tr' ).forEach( function ( row ) {
				row.addEventListener( 'click', function ( e ) {
					var cb = row.querySelector( 'input' );
					if ( ! cb || cb.disabled ) { return; }
					if ( e.target !== cb ) { cb.checked = ! cb.checked; }
					toggle( parseInt( row.dataset.id, 10 ), cb.checked );
				} );
				var cb = row.querySelector( 'input' );
				if ( cb ) {
					cb.addEventListener( 'change', function () {
						toggle( parseInt( row.dataset.id, 10 ), cb.checked );
					} );
				}
			} );
		}

		function bindPagerBtns() {
			var pg = document.getElementById( 'fpp-pagination' );
			if ( ! pg ) { return; }
			pg.querySelectorAll( '.fpp-pg:not([disabled])' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () { load( parseInt( b.dataset.p, 10 ) ); } );
			} );
		}

		function load( p ) {
			curPage = p || curPage;
			var wrap = document.getElementById( 'fpp-table-wrap' );
			if ( wrap ) { wrap.innerHTML = '<p class="fpp-msg">Loading&#8230;</p>'; }
			var pg = document.getElementById( 'fpp-pagination' );
			if ( pg ) { pg.innerHTML = ''; }

			var srch = document.getElementById( 'fpp-search' );
			var tf   = document.getElementById( 'fpp-type-filter' );

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', nahnuasData.ajaxUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.onload = function () {
				var raw = xhr.responseText || '';
				var start = raw.indexOf( '{' );
				if ( start === -1 ) { showErr( 'Bad response: ' + raw.slice( 0, 300 ) ); return; }
				var res;
				try { res = JSON.parse( raw.slice( start ) ); }
				catch ( e ) { showErr( 'JSON error: ' + raw.slice( 0, 300 ) ); return; }

				if ( ! res.success ) { showErr( res.data && res.data.message ? res.data.message : 'Request failed' ); return; }

				var d = res.data;
				allRows  = d.items   || [];
				totItems = d.total   || 0;
				totPages = d.pages   || 1;
				curPage  = d.page    || 1;

				if ( allRows.length === 0 && d.debug ) {
					showErr( 'No results. types=[' + d.debug.types.join(',') + '] total=' + d.debug.total );
					return;
				}

				// Seed sel on first XHR (search/filter clears initial sel state)
				if ( d.sel_ids && sel.length === 0 ) { sel = d.sel_ids.slice(); }

				if ( ! typesLoaded && d.types && d.types.length ) {
					typesLoaded = true;
					var tf2 = document.getElementById( 'fpp-type-filter' );
					if ( tf2 ) {
						d.types.forEach( function ( t ) {
							var o = document.createElement( 'option' );
							o.value = t.k; o.textContent = t.l;
							tf2.appendChild( o );
						} );
					}
				}

				updateCounter();
				renderTable();
				renderPager();
			};
			xhr.onerror = function () { showErr( 'Network error' ); };
			xhr.timeout = 20000;
			xhr.ontimeout = function () { showErr( 'Timeout' ); };

			xhr.send( 'action=fas_picker_browse&nonce=' + encodeURIComponent( nahnuasData.nonce ) +
				'&search=' + encodeURIComponent( srch ? srch.value : '' ) +
				'&ptype='  + encodeURIComponent( tf   ? tf.value   : '' ) +
				'&paged='  + curPage );
		}

		function renderTable() {
			var wrap = document.getElementById( 'fpp-table-wrap' );
			if ( ! wrap ) { return; }
			if ( ! allRows.length ) {
				var srch2 = document.getElementById( 'fpp-search' );
				var msg = srch2 && srch2.value ? 'No pages found for "' + srch2.value + '".' : 'No published pages or posts found.';
				wrap.innerHTML = '<p class="fpp-msg">' + esc( msg ) + '</p>';
				return;
			}
			var atMax = sel.length >= MAX;
			var html = '<table class="fpp-table"><thead><tr>' +
				'<th class="fpp-tc"></th><th>Title</th><th>Type</th>' +
				'</tr></thead><tbody>';
			allRows.forEach( function ( item ) {
				var checked  = sel.indexOf( item.id ) !== -1;
				var disabled = atMax && ! checked;
				html += '<tr class="' + ( checked ? 'fpp-checked' : '' ) + '" data-id="' + item.id + '">' +
					'<td class="fpp-tc"><input type="checkbox"' +
						( checked  ? ' checked'   : '' ) +
						( disabled ? ' disabled'  : '' ) +
						'></td>' +
					'<td class="fpp-tt">' + esc( item.title ) + '</td>' +
					'<td><span class="fpp-badge fpp-bt-' + esc( item.type ) + '">' + esc( item.label ) + '</span></td>' +
					'</tr>';
			} );
			html += '</tbody></table>';
			wrap.innerHTML = html;
			bindRows();
		}

		function toggle( id, add ) {
			if ( add ) {
				if ( sel.length >= MAX ) { return; }
				if ( sel.indexOf( id ) === -1 ) { sel.push( id ); }
			} else {
				sel = sel.filter( function ( x ) { return x !== id; } );
			}
			updateCounter();
			renderTable();
		}

		function renderPager() {
			var wrap = document.getElementById( 'fpp-pagination' );
			if ( ! wrap ) { return; }
			if ( totPages <= 1 ) {
				wrap.innerHTML = totItems ? '<span class="fpp-pinfo">Showing all ' + totItems + ' items</span>' : '';
				return;
			}
			var s = ( curPage - 1 ) * 10 + 1;
			var e = Math.min( curPage * 10, totItems );
			var html = '<span class="fpp-pinfo">Showing ' + s + '&#8211;' + e + ' of ' + totItems + '</span><div class="fpp-pages">';
			html += btn( '&laquo;', curPage - 1, curPage === 1 );
			pageRange( curPage, totPages ).forEach( function ( p ) {
				if ( p === '...' ) { html += '<span class="fpp-pdot">&#8230;</span>'; }
				else { html += btn( p, p, false, p === curPage ); }
			} );
			html += btn( '&raquo;', curPage + 1, curPage === totPages );
			html += '</div>';
			wrap.innerHTML = html;
			bindPagerBtns();
		}

		function btn( label, p, disabled, active ) {
			return '<button class="fpp-pg' + ( active ? ' fpp-pg-on' : '' ) + '"' +
				( disabled ? ' disabled' : '' ) + ' data-p="' + p + '">' + label + '</button>';
		}

		function pageRange( c, t ) {
			if ( t <= 7 ) { var r = []; for ( var i = 1; i <= t; i++ ) r.push(i); return r; }
			var p = [1];
			if ( c > 4 ) p.push( '...' );
			var s = Math.max( 2, c - 2 ), e = Math.min( t - 1, c + 2 );
			for ( var i = s; i <= e; i++ ) p.push(i);
			if ( c < t - 3 ) p.push( '...' );
			p.push( t );
			return p;
		}

		function updateCounter() {
			var el = document.getElementById( 'fpp-count' );
			if ( el ) { el.textContent = sel.length; }
		}

		function save() {
			var sav = document.getElementById( 'fpp-save' );
			if ( sav ) { sav.disabled = true; sav.textContent = 'Saving…'; }
			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', nahnuasData.ajaxUrl, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			xhr.onload = function () {
				if ( sav ) { sav.disabled = false; sav.textContent = 'Save Selection'; }
				var notice = document.getElementById( 'fpp-saved-notice' );
				if ( notice ) { notice.style.display = 'inline'; setTimeout( function () { notice.style.display = 'none'; }, 2500 ); }
			};
			xhr.onerror = function () { if ( sav ) { sav.disabled = false; sav.textContent = 'Save Selection'; } };
			xhr.send( 'action=fas_picker_save&nonce=' + encodeURIComponent( nahnuasData.nonce ) + '&ids=' + encodeURIComponent( JSON.stringify( sel ) ) );
		}

		function showErr( msg ) {
			var wrap = document.getElementById( 'fpp-table-wrap' );
			if ( wrap ) { wrap.innerHTML = '<p class="fpp-msg fpp-msg-err">' + esc( msg ) + '</p>'; }
		}

		function esc( s ) {
			return String( s ).replace( /&/g,'&amp;' ).replace( /</g,'&lt;' ).replace( />/g,'&gt;' ).replace( /"/g,'&quot;' );
		}

		return { init: init };
	}() );

}());