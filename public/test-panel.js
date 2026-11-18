/**
 * Nahnu Asset Scanner — Test Mode panel.
 *
 * A floating panel, rendered inside a Shadow DOM so neither the theme's CSS
 * nor a "CSS off" test can affect it. Lists every script and stylesheet on
 * the page and lets the administrator choose On / Defer / Delay / Off.
 */
/* global nahnuasTest */
( function () {
	'use strict';

	if ( 'undefined' === typeof nahnuasTest ) {
		return;
	}

	var cfg = nahnuasTest;
	var t = cfg.strings;
	var STORE = 'nahnuasTestPanel';

	var ui = {
		assets: [],
		warnings: [],
		saved: Object.assign( {}, cfg.rules || {} ),
		draft: Object.assign( {}, cfg.rules || {} ),
		tab: 'js',
		filter: 'all',
		search: '',
		open: true,
		busy: false,
		root: null,
		host: null,
	};

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	function esc( s ) {
		return String( null === s || undefined === s ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function readPref() {
		try {
			return JSON.parse( window.localStorage.getItem( STORE ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function writePref() {
		try {
			window.localStorage.setItem( STORE, JSON.stringify( { open: ui.open, tab: ui.tab } ) );
		} catch ( e ) {
			// Storage unavailable (private mode); the panel still works.
		}
	}

	function actionsFor( a ) {
		if ( ! a.key ) {
			return [];
		}
		if ( 'js' === a.kind ) {
			return a.inline ? [ 'delay', 'off' ] : [ 'defer', 'delay', 'off' ];
		}
		return a.inline ? [ 'off' ] : [ 'defer', 'off' ];
	}

	function draftAction( a ) {
		return a.key ? ( ui.draft[ a.key ] || '' ) : '';
	}

	function pendingCount() {
		var keys = {};
		Object.keys( ui.draft ).concat( Object.keys( ui.saved ) ).forEach( function ( k ) {
			keys[ k ] = 1;
		} );
		return Object.keys( keys ).filter( function ( k ) {
			return ( ui.draft[ k ] || '' ) !== ( ui.saved[ k ] || '' );
		} ).length;
	}

	function activeCount() {
		return Object.keys( ui.saved ).length;
	}

	function errors() {
		return window.nahnuasErrors || [];
	}

	function urlWith( value ) {
		var u = new URL( window.location.href );
		if ( null === value ) {
			u.searchParams.delete( cfg.param );
		} else {
			u.searchParams.set( cfg.param, value );
		}
		return u.toString();
	}

	function countKind( kind ) {
		return ui.assets.filter( function ( a ) {
			return a.kind === kind;
		} ).length;
	}

	function visible() {
		var q = ui.search.toLowerCase();
		return ui.assets.filter( function ( a ) {
			if ( a.kind !== ui.tab ) {
				return false;
			}
			if ( 'changed' === ui.filter && ! draftAction( a ) && ! ( cfg.paused ? false : a.action ) ) {
				return false;
			}
			if ( 'third' === ui.filter && ! a.third ) {
				return false;
			}
			if ( q ) {
				return ( a.label + ' ' + a.url + ' ' + a.owner + ' ' + a.id ).toLowerCase().indexOf( q ) !== -1;
			}
			return true;
		} ).sort( function ( x, y ) {
			// Code that cannot be targeted goes last; otherwise keep page order.
			return ( x.key ? 0 : 1 ) - ( y.key ? 0 : 1 ) || x.i - y.i;
		} );
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	var ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M9 3h6M10 3v6.5L4.6 18.3A1.8 1.8 0 0 0 6.1 21h11.8a1.8 1.8 0 0 0 1.5-2.7L14 9.5V3"/><path d="M7.5 15h9"/></svg>';

	function render() {
		var root = ui.root;
		var errs = errors().length + ui.warnings.length;
		var pend = pendingCount();

		if ( ! ui.open ) {
			root.innerHTML =
				'<button type="button" class="pill" data-act="expand" title="' + esc( t.expand ) + '">' +
				'<span class="pill-icon">' + ICON + '</span>' +
				'<span>' + esc( t.title ) + '</span>' +
				( activeCount() ? '<span class="count">' + activeCount() + '</span>' : '' ) +
				( errs ? '<span class="count count--err">' + errs + '</span>' : '' ) +
				'</button>';
			return;
		}

		var tabs = [
			[ 'js', t.scripts, countKind( 'js' ) ],
			[ 'css', t.styles, countKind( 'css' ) ],
			[ 'errors', t.errors, errs ],
		];

		var html =
			'<section class="panel" role="dialog" aria-label="' + esc( t.title ) + '">' +
			'<header class="head">' +
			'<div class="brand"><span class="logo">' + ICON + '</span><div><strong>' + esc( t.title ) + '</strong>' +
			'<small>' + esc( t.onlyYou ) + '</small></div></div>' +
			'<button type="button" class="icon-btn" data-act="collapse" aria-label="' + esc( t.collapse ) + '" title="' + esc( t.collapse ) + '">' +
			'<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 10a1 1 0 0 1 1-1h10a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1z"/></svg></button>' +
			'</header>' +

			'<div class="mode">' +
			'<div class="seg seg--mode" role="group" aria-label="' + esc( t.pauseHint ) + '">' +
			'<a href="' + esc( urlWith( '1' ) ) + '" class="' + ( cfg.paused ? '' : 'on' ) + '" aria-current="' + ( cfg.paused ? 'false' : 'true' ) + '">' + esc( t.rulesActive ) + ( activeCount() ? ' <b>' + activeCount() + '</b>' : '' ) + '</a>' +
			'<a href="' + esc( urlWith( 'pause' ) ) + '" class="' + ( cfg.paused ? 'on' : '' ) + '" aria-current="' + ( cfg.paused ? 'true' : 'false' ) + '">' + esc( t.rulesPaused ) + '</a>' +
			'</div>' +
			'<span class="hint">' + esc( t.pauseHint ) + '</span>' +
			'</div>' +

			'<nav class="tabs" role="tablist">' +
			tabs.map( function ( tb ) {
				return '<button type="button" role="tab" data-tab="' + tb[ 0 ] + '" class="tab' + ( ui.tab === tb[ 0 ] ? ' tab--on' : '' ) +
					( 'errors' === tb[ 0 ] && tb[ 2 ] ? ' tab--err' : '' ) + '" aria-selected="' + ( ui.tab === tb[ 0 ] ) + '">' +
					esc( tb[ 1 ] ) + ' <span>' + tb[ 2 ] + '</span></button>';
			} ).join( '' ) +
			'</nav>';

		if ( 'errors' === ui.tab ) {
			html += renderErrors();
		} else {
			html += renderAssets();
		}

		html +=
			'<footer class="foot">' +
			'<div class="foot-row">' +
			'<span class="pending' + ( pend ? ' pending--on' : '' ) + '">' + ( pend ? esc( t.pending ) + ' <b>' + pend + '</b>' : '' ) + '</span>' +
			'<button type="button" class="btn btn--ghost" data-act="reset"' + ( Object.keys( ui.draft ).length ? '' : ' disabled' ) + '>' + esc( t.reset ) + '</button>' +
			'<button type="button" class="btn btn--primary" data-act="apply"' + ( pend && ! ui.busy ? '' : ' disabled' ) + '>' + esc( ui.busy ? t.saving : t.apply ) + '</button>' +
			'</div>' +
			'<div class="links"><a href="' + esc( cfg.exitUrl ) + '">' + esc( t.exit ) + '</a><a href="' + esc( cfg.adminUrl ) + '">' + esc( t.scanner ) + '</a></div>' +
			'</footer></section>';

		var scroll = root.querySelector( '.list' );
		var top = scroll ? scroll.scrollTop : 0;
		var active = ui.host.shadowRoot.activeElement;
		var focused = !! active && active.classList.contains( 'search' );
		root.innerHTML = html;
		var list = root.querySelector( '.list' );
		if ( list ) {
			list.scrollTop = top;
		}
		if ( focused ) {
			var s = root.querySelector( '.search' );
			s.focus();
			s.setSelectionRange( s.value.length, s.value.length );
		}
	}

	function renderAssets() {
		var js = 'js' === ui.tab;
		var bulk = js
			? [ [ 'jsOff', t.allJsOff ], [ 'jsDefer', t.allJsDefer ], [ 'jsDelay', t.allJsDelay ], [ 'thirdDelay', t.thirdJsDelay ] ]
			: [ [ 'cssOff', t.allCssOff ], [ 'cssDefer', t.allCssDefer ] ];

		var rows = visible();
		var html =
			'<div class="tools">' +
			'<input type="search" class="search" placeholder="' + esc( t.search ) + '" value="' + esc( ui.search ) + '" aria-label="' + esc( t.search ) + '">' +
			'<div class="chips" role="group">' +
			[ [ 'all', t.all ], [ 'changed', t.changed ], [ 'third', t.thirdParty ] ].map( function ( f ) {
				return '<button type="button" data-filter="' + f[ 0 ] + '" class="chip' + ( ui.filter === f[ 0 ] ? ' chip--on' : '' ) + '" aria-pressed="' + ( ui.filter === f[ 0 ] ) + '">' + esc( f[ 1 ] ) + '</button>';
			} ).join( '' ) +
			'</div>' +
			'<div class="bulk"><span>' + esc( t.bulk ) + '</span>' +
			bulk.map( function ( b ) {
				return '<button type="button" class="link" data-bulk="' + b[ 0 ] + '">' + esc( b[ 1 ] ) + '</button>';
			} ).join( '' ) +
			'</div></div>';

		if ( js && document.querySelector( 'script[type="text/nahnuas-delay"]' ) ) {
			var ran = window.nahnuasRuntime && window.nahnuasRuntime.delayedRan;
			html += '<p class="note' + ( ran ? ' note--ok' : '' ) + '">' + esc( ran ? t.delayRan : t.delayWaiting ) + '</p>';
		}

		var tips = { on: '', off: t.legendOff, defer: t.legendDefer, delay: t.legendDelay };
		html += '<ul class="list">';
		if ( ! rows.length ) {
			html += '<li class="empty">' + esc( t.nothing ) + '</li>';
		}
		rows.forEach( function ( a ) {
			var current = draftAction( a );
			var opts = actionsFor( a );
			var changed = current !== ( ui.saved[ a.key ] || '' );
			// Inline code printed after a file follows that file unless it has its own rule.
			var inherited = ! current && a.follows && ! cfg.paused ? ( 'domready' === a.action ? '' : a.action ) : '';
			html += '<li class="row' + ( current ? ' row--' + current : '' ) + ( changed ? ' row--changed' : '' ) + '">' +
				'<div class="meta">' +
				'<span class="name" title="' + esc( a.url || a.preview ) + '">' + esc( a.label ) + '</span>' +
				'<span class="sub">' +
				( a.inline ? '<em class="tag">' + esc( t.inline ) + '</em>' : '' ) +
				( a.third ? '<em class="tag tag--third">' + esc( t.thirdParty ) + '</em>' : '' ) +
				( a.owner ? '<span>' + esc( a.owner ) + '</span>' : '' ) +
				( a.follows && ! current ? '<span class="follows">→ ' + esc( a.follows ) + '</span>' : '' ) +
				( ! a.key ? '<span class="muted">' + esc( t.noId ) + '</span>' : '' ) +
				'</span></div>';
			if ( opts.length ) {
				html += '<div class="seg" role="group" aria-label="' + esc( a.label ) + '">' +
					[ '' ].concat( opts ).map( function ( o ) {
						var label = '' === o ? t.on : t[ o ];
						var on = inherited ? inherited === o : current === o;
						return '<button type="button" data-key="' + esc( a.key ) + '" data-action="' + o + '" class="opt opt--' + ( o || 'on' ) +
							( on ? ' is-on' : '' ) + ( on && inherited ? ' is-auto' : '' ) + '" aria-pressed="' + ( current === o ) + '"' +
							( tips[ o || 'on' ] ? ' title="' + esc( tips[ o || 'on' ] ) + '"' : '' ) + '>' + esc( label ) + '</button>';
					} ).join( '' ) +
					'</div>';
			}
			html += '</li>';
		} );
		html += '</ul>';

		return html;
	}

	function renderErrors() {
		var list = errors();
		var html = '<p class="note">' + esc( t.errorsHint ) + '</p><ul class="list list--errors">';
		ui.warnings.forEach( function ( w ) {
			html += '<li class="err err--warn"><span class="err-type">' + esc( t.likely ) + '</span><div><strong>' + esc( w.message ) + '</strong></div></li>';
		} );
		if ( ! list.length && ! ui.warnings.length ) {
			html += '<li class="empty empty--ok">' + esc( t.noErrors ) + '</li>';
		}
		list.forEach( function ( e ) {
			var src = e.source ? String( e.source ).replace( /^https?:\/\/[^/]+/, '' ) : '';
			html += '<li class="err"><span class="err-type">' + esc( e.type ) + '</span><div><strong>' + esc( e.message ) + '</strong>' +
				( src ? '<small>' + esc( src ) + ( e.line ? ':' + esc( e.line ) : '' ) + '</small>' : '' ) + '</div></li>';
		} );
		return html + '</ul>';
	}

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	function setRule( key, action ) {
		if ( ! key ) {
			return;
		}
		if ( action ) {
			ui.draft[ key ] = action;
		} else {
			delete ui.draft[ key ];
		}
	}

	function bulk( kind ) {
		ui.assets.forEach( function ( a ) {
			if ( ! a.key ) {
				return;
			}
			var opts = actionsFor( a );
			var want = {
				jsOff: 'js' === a.kind && 'off',
				jsDefer: 'js' === a.kind && ! a.inline && 'defer',
				jsDelay: 'js' === a.kind && 'delay',
				thirdDelay: 'js' === a.kind && a.third && 'delay',
				cssOff: 'css' === a.kind && 'off',
				cssDefer: 'css' === a.kind && ! a.inline && 'defer',
			}[ kind ];
			if ( want && opts.indexOf( want ) !== -1 ) {
				setRule( a.key, want );
			}
		} );
		render();
	}

	function save() {
		if ( ui.busy ) {
			return;
		}
		ui.busy = true;
		render();
		var body = new URLSearchParams();
		body.append( 'action', 'nahnuas_test_save' );
		body.append( 'nonce', cfg.nonce );
		body.append( 'rules', JSON.stringify( ui.draft ) );
		window.fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					throw new Error( 'save' );
				}
				window.location.href = urlWith( '1' );
			} )
			.catch( function () {
				ui.busy = false;
				render();
				window.alert( t.saveFailed ); // eslint-disable-line no-alert
			} );
	}

	function onClick( e ) {
		var el = e.target.closest( 'button, a' );
		if ( ! el ) {
			return;
		}
		var d = el.dataset;
		if ( d.act ) {
			if ( 'collapse' === d.act || 'expand' === d.act ) {
				ui.open = 'expand' === d.act;
				writePref();
			} else if ( 'reset' === d.act ) {
				ui.draft = {};
			} else if ( 'apply' === d.act ) {
				save();
				return;
			}
			render();
		} else if ( d.tab ) {
			ui.tab = d.tab;
			writePref();
			render();
		} else if ( d.filter ) {
			ui.filter = d.filter;
			render();
		} else if ( d.bulk ) {
			bulk( d.bulk );
		} else if ( undefined !== d.action && d.key ) {
			setRule( d.key, d.action );
			render();
		}
	}

	// Keep test mode on while browsing the site.
	function keepParam( e ) {
		var a = e.target.closest && e.target.closest( 'a[href]' );
		if ( ! a || e.defaultPrevented || e.button > 0 ) {
			return;
		}
		var u;
		try {
			u = new URL( a.href, window.location.href );
		} catch ( err ) {
			return;
		}
		if ( u.origin !== window.location.origin || u.searchParams.has( cfg.param ) || /\/wp-(admin|login)/.test( u.pathname ) || /^(#|javascript:)/i.test( a.getAttribute( 'href' ) || '' ) ) {
			return;
		}
		u.searchParams.set( cfg.param, cfg.paused ? 'pause' : '1' );
		a.href = u.toString();
	}

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	function boot() {
		var data = document.getElementById( 'nahnuas-test-data' );
		try {
			ui.assets = data ? JSON.parse( data.textContent ) : [];
		} catch ( e ) {
			ui.assets = [];
		}
		var warn = document.getElementById( 'nahnuas-test-warnings' );
		try {
			ui.warnings = warn ? JSON.parse( warn.textContent ) : [];
		} catch ( e ) {
			ui.warnings = [];
		}
		var pref = readPref();
		ui.open = false !== pref.open;
		if ( [ 'js', 'css', 'errors' ].indexOf( pref.tab ) !== -1 ) {
			ui.tab = pref.tab;
		}

		ui.host = document.createElement( 'div' );
		ui.host.id = 'nahnuas-test-root';
		ui.host.setAttribute( 'style', 'all:initial;position:fixed;right:16px;bottom:16px;z-index:2147483646;visibility:hidden;' );
		ui.root = ui.host.attachShadow( { mode: 'open' } );

		var css = document.createElement( 'link' );
		css.rel = 'stylesheet';
		css.href = cfg.cssUrl;
		var show = function () {
			ui.host.style.visibility = 'visible';
		};
		css.addEventListener( 'load', show );
		setTimeout( show, 2000 );
		ui.root.appendChild( css );

		var mount = document.createElement( 'div' );
		mount.className = 'mount';
		ui.root.appendChild( mount );
		ui.root = mount;

		document.body.appendChild( ui.host );

		mount.addEventListener( 'click', onClick );
		mount.addEventListener( 'input', function ( e ) {
			if ( e.target.classList.contains( 'search' ) ) {
				ui.search = e.target.value;
				render();
			}
		} );
		document.addEventListener( 'click', keepParam, true );
		document.addEventListener( 'auxclick', keepParam, true );
		window.addEventListener( 'nahnuas:error', render );
		window.addEventListener( 'nahnuas:delayed', render );
		render();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
