/**
 * CoreX navigation behavior (Spec 058 / M3) — buildless, framework-free, jQuery-free.
 *
 * Adds only the increments the WordPress core navigation block does not provide:
 *
 *   - Mega-menu panels: each is a native `<details class="corex-mega"><summary>…` block,
 *     so it is keyboard-operable and fully usable with no JS (summary toggles the panel,
 *     all links reachable). The script only adds the conveniences `<details>` lacks:
 *     opening one panel closes its siblings, and Escape or an outside click closes the
 *     open panel and returns focus to its summary. On narrow viewports the same markup
 *     is an accordion (no hover dependency).
 *
 *   - Transparent/sticky header state: a `[data-corex-header="transparent"]` element
 *     gets `data-corex-header-state="top|scrolled"` toggled on a passive, rAF-throttled
 *     scroll listener so CSS can resolve a transparent header to a solid, readable one.
 *     The JS only flips state; the visual transition (and its reduced-motion gating)
 *     lives in CSS, so the module is inherently reduced-motion-safe.
 *
 * Exposed as `window.Corex.navigation = { init, destroy }` for testing; auto-inits on
 * DOMContentLoaded. Loaded only where a CoreX header/navigation renders (Principle VI).
 */
( function ( window, document ) {
	'use strict';

	var SCROLL_THRESHOLD = 8;

	function megas( root ) {
		return Array.prototype.slice.call(
			root.querySelectorAll( 'details.corex-mega' )
		);
	}

	function summaryOf( details ) {
		return details.querySelector( 'summary' );
	}

	var bound = [];

	function init( root ) {
		root = root || document;

		var all = megas( root );

		all.forEach( function ( details ) {
			function onToggle() {
				// Opening one mega panel closes its siblings (single-open behavior).
				if ( details.open ) {
					all.forEach( function ( other ) {
						if ( other !== details ) {
							other.open = false;
						}
					} );
				}
			}

			function onKeydown( event ) {
				if ( event.key === 'Escape' && details.open ) {
					details.open = false;
					var summary = summaryOf( details );
					if ( summary ) {
						summary.focus();
					}
				}
			}

			details.addEventListener( 'toggle', onToggle );
			details.addEventListener( 'keydown', onKeydown );
			bound.push( { details: details, onToggle: onToggle, onKeydown: onKeydown } );
		} );

		function onOutside( event ) {
			all.forEach( function ( details ) {
				if ( details.open && ! details.contains( event.target ) ) {
					details.open = false;
				}
			} );
		}
		document.addEventListener( 'click', onOutside );
		bound.push( { document: true, onOutside: onOutside } );

		initHeaderState( root );
	}

	var headerCleanup = null;

	function initHeaderState( root ) {
		var header = root.querySelector
			? root.querySelector( '[data-corex-header="transparent"]' )
			: null;
		if ( ! header ) {
			return;
		}

		var ticking = false;
		function apply() {
			header.setAttribute(
				'data-corex-header-state',
				window.scrollY > SCROLL_THRESHOLD ? 'scrolled' : 'top'
			);
			ticking = false;
		}
		function onScroll() {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( apply );
			}
		}

		apply();
		window.addEventListener( 'scroll', onScroll, { passive: true } );
		headerCleanup = function () {
			window.removeEventListener( 'scroll', onScroll );
		};
	}

	function destroy() {
		bound.forEach( function ( entry ) {
			if ( entry.document ) {
				document.removeEventListener( 'click', entry.onOutside );
				return;
			}
			entry.details.removeEventListener( 'toggle', entry.onToggle );
			entry.details.removeEventListener( 'keydown', entry.onKeydown );
		} );
		bound = [];
		if ( headerCleanup ) {
			headerCleanup();
			headerCleanup = null;
		}
	}

	window.Corex = window.Corex || {};
	window.Corex.navigation = { init: init, destroy: destroy };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}
} )( window, document );
