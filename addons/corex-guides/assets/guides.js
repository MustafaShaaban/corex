/**
 * Client-side search for the Guides screen (spec 084, FR-012).
 *
 * Filters markup that is already in the page rather than fetching anything: the guides were
 * server-rendered, so every word being searched is already here, and a request would only add a
 * failure mode to a screen somebody opened because something else already went wrong.
 *
 * Plain enqueued JavaScript with no build step, matching `plugins/corex-core/assets/js/corex-runtime.js`.
 * Progressive: with JavaScript off the field does nothing and every guide is visible, which is the
 * correct fallback for a page whose whole content is text.
 */
( function () {
	'use strict';

	const input = document.getElementById( 'corex-guides-search' );
	if ( ! input ) {
		return;
	}

	const guides = Array.prototype.slice.call(
		document.querySelectorAll( '[data-corex-guide]' )
	);
	const sections = Array.prototype.slice.call(
		document.querySelectorAll( '[data-corex-guide-section]' )
	);
	const noResults = document.querySelector( '.corex-guides__no-results' );

	/**
	 * Show the guides matching a term, and hide any section left with nothing in it.
	 *
	 * @param {string} term What was typed, already lower-cased.
	 */
	function filter( term ) {
		let matches = 0;

		guides.forEach( function ( guide ) {
			const haystack = guide.getAttribute( 'data-corex-search' ) || '';
			const hit = term === '' || haystack.indexOf( term ) !== -1;

			guide.hidden = ! hit;
			if ( hit ) {
				matches++;
			}
		} );

		// A section heading above nothing reads as a section whose guides failed to load.
		sections.forEach( function ( section ) {
			const visible = section.querySelector(
				'[data-corex-guide]:not([hidden])'
			);
			section.hidden = ! visible;
		} );

		if ( noResults ) {
			noResults.hidden = matches !== 0;
		}
	}

	input.addEventListener( 'input', function () {
		filter( input.value.trim().toLowerCase() );
	} );

	// A guide arrived at by its anchor is opened and scrolled to, so the link lands on the content
	// rather than on a collapsed <details> the reader then has to find and expand. Every guide
	// renders `id="guide-<id>"`, which makes those anchors a public address anybody can link to —
	// a bookmark, a documentation page, a client plugin pointing its users at the right guide.
	// Spec 084's contextual help tab was the first such caller and spec 097 removed it; the anchors
	// it linked to are still there and still worth landing on properly.
	if ( window.location.hash.indexOf( '#guide-' ) === 0 ) {
		const target = document.getElementById(
			window.location.hash.slice( 1 )
		);
		if ( target ) {
			Array.prototype.forEach.call(
				target.querySelectorAll( 'details' ),
				function ( details ) {
					details.open = true;
				}
			);
			target.scrollIntoView();
		}
	}
} )();
