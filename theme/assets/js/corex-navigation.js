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
 *   - Transparent/sticky header state: a `.corex-header--transparent` or
 *     `.corex-header--sticky` element gets `data-corex-header-state="top|scrolled"`
 *     toggled on a passive, rAF-throttled scroll listener so CSS can resolve a
 *     transparent header to a solid one and give a solid sticky header its scrolled
 *     elevation. The JS only flips state; the visual transition (and its reduced-motion
 *     gating) lives in CSS, so the module is inherently reduced-motion-safe.
 *
 *   - Search overlay: a `[data-corex-search-toggle]` button reveals its
 *     `[data-corex-search-panel]` (referenced by `aria-controls`). Opening moves focus
 *     to the first field, sets `aria-expanded`, and unhides the panel; Escape or an
 *     outside click closes it and returns focus to the toggle. Purely progressive — with
 *     no JS the panel is a normal (visible) search form and the toggle a no-op link/anchor.
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
			bound.push( {
				details: details,
				onToggle: onToggle,
				onKeydown: onKeydown,
			} );
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
		initSearch( root );
	}

	var headerCleanup = null;

	function initHeaderState( root ) {
		var headers = root.querySelectorAll
			? Array.prototype.slice.call(
					root.querySelectorAll(
						'.corex-header--transparent, .corex-header--sticky'
					)
			  )
			: [];
		if ( ! headers.length ) {
			return;
		}

		var ticking = false;
		function apply() {
			var state = window.scrollY > SCROLL_THRESHOLD ? 'scrolled' : 'top';
			headers.forEach( function ( header ) {
				header.setAttribute( 'data-corex-header-state', state );
			} );
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

	function panelFor( toggle, root ) {
		var id = toggle.getAttribute( 'aria-controls' );
		if ( id ) {
			var byId = document.getElementById( id );
			if ( byId ) {
				return byId;
			}
		}
		var header = toggle.closest ? toggle.closest( '.corex-header' ) : root;
		return ( header || root ).querySelector( '[data-corex-search-panel]' );
	}

	function initSearch( root ) {
		var toggles = root.querySelectorAll
			? Array.prototype.slice.call(
					root.querySelectorAll( '[data-corex-search-toggle]' )
			  )
			: [];

		toggles.forEach( function ( toggle ) {
			var panel = panelFor( toggle, root );
			if ( ! panel ) {
				return;
			}

			function setOpen( open ) {
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				if ( open ) {
					panel.removeAttribute( 'hidden' );
					var field = panel.querySelector(
						'input, [href], button, select, textarea'
					);
					if ( field ) {
						field.focus();
					}
				} else {
					panel.setAttribute( 'hidden', '' );
				}
			}

			function onToggleClick( event ) {
				event.preventDefault();
				setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
			}

			function onKeydown( event ) {
				if (
					event.key === 'Escape' &&
					toggle.getAttribute( 'aria-expanded' ) === 'true'
				) {
					setOpen( false );
					toggle.focus();
				}
			}

			function onOutsideSearch( event ) {
				if (
					toggle.getAttribute( 'aria-expanded' ) === 'true' &&
					! panel.contains( event.target ) &&
					! toggle.contains( event.target )
				) {
					setOpen( false );
				}
			}

			// Progressive enhancement: reveal the toggle (hidden in markup so no-JS
			// users never see a dead control) and collapse the panel (visible in
			// markup so no-JS users get a normal inline search form).
			toggle.removeAttribute( 'hidden' );
			setOpen( false );

			toggle.addEventListener( 'click', onToggleClick );
			panel.addEventListener( 'keydown', onKeydown );
			document.addEventListener( 'click', onOutsideSearch );
			bound.push( {
				search: true,
				toggle: toggle,
				panel: panel,
				onToggleClick: onToggleClick,
				onKeydown: onKeydown,
				onOutsideSearch: onOutsideSearch,
			} );
		} );
	}

	function destroy() {
		bound.forEach( function ( entry ) {
			if ( entry.document ) {
				document.removeEventListener( 'click', entry.onOutside );
				return;
			}
			if ( entry.search ) {
				entry.toggle.removeEventListener(
					'click',
					entry.onToggleClick
				);
				entry.panel.removeEventListener( 'keydown', entry.onKeydown );
				document.removeEventListener( 'click', entry.onOutsideSearch );
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
