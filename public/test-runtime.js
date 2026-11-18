/**
 * Nahnu Asset Scanner — Test Mode runtime.
 *
 * Loaded only for an administrator viewing a page with ?nahnuas_test.
 * Runs scripts that were deferred to DOMContentLoaded or delayed until the
 * first user interaction, and applies stylesheets that load without blocking.
 */
( function () {
	'use strict';

	var state = window.nahnuasRuntime = { delayed: 0, delayedRan: false };

	/**
	 * Make a runnable copy of a held-back script element.
	 *
	 * @param {HTMLScriptElement} old Held-back element.
	 * @return {HTMLScriptElement} Runnable element.
	 */
	function runnable( old ) {
		var s = document.createElement( 'script' );
		var type = old.getAttribute( 'data-nahnuas-type' ) || '';
		for ( var i = 0; i < old.attributes.length; i++ ) {
			var a = old.attributes[ i ];
			if ( 'type' !== a.name && 0 !== a.name.indexOf( 'data-nahnuas-' ) ) {
				s.setAttribute( a.name, a.value );
			}
		}
		if ( type && 'text/javascript' !== type ) {
			s.type = type;
		}
		if ( ! old.src ) {
			s.text = old.text;
		} else if ( ! old.hasAttribute( 'async' ) ) {
			s.async = false;
		}
		s.setAttribute( 'data-nahnuas-ran', old.getAttribute( 'data-nahnuas-test' ) || '' );
		return s;
	}

	/**
	 * Run held-back scripts one after another, in page order.
	 *
	 * @param {string}   type Held-back type.
	 * @param {Function} done Called when every script has run.
	 */
	function runAll( type, done ) {
		var list = Array.prototype.slice.call( document.querySelectorAll( 'script[type="text/nahnuas-' + type + '"]' ) );
		( function next() {
			var old = list.shift();
			if ( ! old ) {
				if ( done ) {
					done();
				}
				return;
			}
			var s = runnable( old );
			var wait = !! s.src && ! s.async;
			if ( wait ) {
				s.onload = s.onerror = next;
			}
			old.parentNode.replaceChild( s, old );
			if ( ! wait ) {
				next();
			}
		}() );
	}

	// Inline code that belongs to a deferred file runs once the file has run.
	document.addEventListener( 'DOMContentLoaded', function () {
		runAll( 'domready' );
	} );

	// Stylesheets set to Defer: apply once downloaded.
	Array.prototype.forEach.call( document.querySelectorAll( 'link[data-nahnuas-media]' ), function ( link ) {
		function swap() {
			link.media = link.getAttribute( 'data-nahnuas-media' ) || 'all';
		}
		if ( link.sheet ) {
			swap();
		} else {
			link.addEventListener( 'load', swap );
		}
	} );

	// Delayed scripts: wait for the first interaction, like cache plugins do.
	state.delayed = document.querySelectorAll( 'script[type="text/nahnuas-delay"]' ).length;
	state.start = start;

	var events = [ 'keydown', 'mousedown', 'mousemove', 'touchstart', 'wheel', 'scroll' ];
	var started = false;

	function start() {
		if ( started ) {
			return;
		}
		started = true;
		events.forEach( function ( e ) {
			window.removeEventListener( e, start, { passive: true } );
		} );

		// Delayed code often waits for DOMContentLoaded or load, which have
		// already fired. While it runs, such listeners are called right away.
		var add = EventTarget.prototype.addEventListener;
		var shim = function ( type, fn, opts ) {
			if ( ( this === document && ( 'DOMContentLoaded' === type || 'readystatechange' === type ) ) ||
				( this === window && ( 'load' === type || 'DOMContentLoaded' === type ) ) ) {
				var target = this;
				setTimeout( function () {
					var ev = new Event( type );
					if ( 'function' === typeof fn ) {
						fn.call( target, ev );
					} else if ( fn && 'function' === typeof fn.handleEvent ) {
						fn.handleEvent( ev );
					}
				}, 0 );
				return;
			}
			return add.call( this, type, fn, opts );
		};
		EventTarget.prototype.addEventListener = shim;

		runAll( 'delay', function () {
			setTimeout( function () {
				if ( EventTarget.prototype.addEventListener === shim ) {
					EventTarget.prototype.addEventListener = add;
				}
				state.delayedRan = true;
				window.dispatchEvent( new CustomEvent( 'nahnuas:delayed' ) );
			}, 50 );
		} );
	}

	if ( state.delayed ) {
		events.forEach( function ( e ) {
			window.addEventListener( e, start, { passive: true } );
		} );
	}

	// -------------------------------------------------------------------------
	// Test runs started by an AI agent: simulate the first interaction, wait
	// for delayed code, then report errors back to the site.
	// -------------------------------------------------------------------------

	var runEl = document.getElementById( 'nahnuas-test-run' );
	if ( ! runEl ) {
		return;
	}
	var run;
	try {
		run = JSON.parse( runEl.textContent );
	} catch ( e ) {
		return;
	}

	function badge( text, done ) {
		var el = document.getElementById( 'nahnuas-test-run-status' );
		if ( ! el ) {
			el = document.createElement( 'div' );
			el.id = 'nahnuas-test-run-status';
			el.setAttribute( 'role', 'status' );
			el.style.cssText = 'all:initial;position:fixed;left:12px;bottom:12px;z-index:2147483647;padding:8px 12px;border-radius:8px;font:600 13px/1.3 system-ui,sans-serif;color:#fff;background:#1D4ED8;box-shadow:0 4px 14px rgba(0,0,0,.25)';
			document.body.appendChild( el );
		}
		el.textContent = text;
		el.setAttribute( 'data-done', done ? '1' : '0' );
		if ( done ) {
			el.style.background = '#15803D';
		}
	}

	function report() {
		var errors = window.nahnuasErrors || [];
		var body = new URLSearchParams();
		body.append( 'action', 'nahnuas_test_report' );
		body.append( 'token', run.token );
		body.append( 'report', JSON.stringify( {
			errors: errors,
			delayed: state.delayed,
			delayedRan: state.delayedRan,
			cssDeferred: document.querySelectorAll( 'link[data-nahnuas-media]' ).length,
			title: document.title,
			loadMs: Math.round( window.performance && performance.now ? performance.now() : 0 ),
		} ) );
		window.fetch( run.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				badge( res && res.success
					? 'Nahnu test report sent: ' + errors.length + ' JavaScript error(s).'
					: 'Nahnu test report failed.', true );
			} )
			.catch( function () {
				badge( 'Nahnu test report failed.', true );
			} );
	}

	function begin() {
		badge( 'Nahnu test running…', false );
		setTimeout( function () {
			if ( state.delayed ) {
				start();
			}
			setTimeout( report, state.delayed ? 3000 : 1500 );
		}, 800 );
	}

	if ( 'complete' === document.readyState ) {
		begin();
	} else {
		window.addEventListener( 'load', begin );
	}
}() );
