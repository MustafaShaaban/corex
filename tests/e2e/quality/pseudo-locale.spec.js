/**
 * The pseudo-locale run (spec 116, US-NFR-06; Definition of Done item 9).
 *
 * With the cookie the `corex-e2e-pseudo-locale.php` fixture reads, every CoreX string comes back
 * as `⟦…⟧`. Each admin screen is opened that way in both directions, and every visible text
 * inside the CoreX shell that has words but no mark is a string that never went through a
 * translation function. Not counted: numbers, dates, addresses, and what the site holds (its
 * name, the signed-in user's name), which no catalogue translates.
 *
 * Front-end pages are left out: what a visitor reads there is the site's content, written by its
 * owner, not CoreX's strings.
 */
const { test, expect } = require( '@playwright/test' );

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings } = require( './findings' );
const { site, DESKTOP, DIRECTIONS } = require( './site' );

/** Not words a catalogue would hold. */
const NOT_WORDS =
	/^[\d\s.,:;%+\-–—/()#×·|•…]*$|^\S+@\S+$|^https?:\/\/|^\/\S*$|^v?\d+(\.\d+)+/;

function unmarkedTexts() {
	const { isRendered } = window.__corexQuality;
	const shell = document.querySelector( '.corex-admin' );
	const found = [];

	if ( ! shell ) {
		return found;
	}

	const walker = document.createTreeWalker(
		shell,
		window.NodeFilter.SHOW_TEXT
	);

	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		const text = node.nodeValue.replace( /\s+/g, ' ' ).trim();
		const parent = node.parentElement;

		if (
			! /\p{L}{2,}/u.test( text ) ||
			! parent ||
			parent.closest( 'code, kbd, samp, pre, script, style, textarea' )
		) {
			continue;
		}

		if ( ! isRendered( parent ) || /[⟦⟧]/.test( node.nodeValue ) ) {
			continue;
		}

		// A translated string with markup in it ("Read <a>the guide</a> first") reaches the page
		// as several text nodes, and only the outer ones carry a mark. A node that sits between an
		// opening and a closing mark in an ancestor's text is part of a translation.
		let inside = false;
		for (
			let ancestor = parent, level = 0;
			ancestor && level < 4 && ! inside;
			ancestor = ancestor.parentElement, level++
		) {
			const all = ancestor.textContent;
			const at = all.indexOf( node.nodeValue );
			inside =
				at >= 0 &&
				all.lastIndexOf( '⟦', at ) > all.lastIndexOf( '⟧', at - 1 ) &&
				all.indexOf( '⟧', at ) >= 0;
		}

		if ( inside ) {
			continue;
		}

		found.push( {
			text,
			element: window.__corexQuality.signature( parent ),
		} );
	}

	return found;
}

for ( const screen of site.screens ) {
	test( `${ screen.id }: every CoreX word on screen went through a translation`, async ( {
		page,
		context,
		baseURL,
	} ) => {
		await context.addCookies( [
			{ name: 'corex_pseudo_locale', value: '1', url: baseURL },
		] );
		await page.addInitScript( installQualityHelpers );
		await page.setViewportSize( DESKTOP );
		await page.goto( screen.path, { waitUntil: 'load' } );
		await page
			.waitForLoadState( 'networkidle', { timeout: 10_000 } )
			.catch( () => {} );

		const marked = await page.evaluate( () =>
			/⟦/.test( document.body.innerText )
		);
		expect(
			marked,
			'the pseudo-locale fixture is not loaded: copy tests/e2e/fixtures/corex-e2e-pseudo-locale.php into mu-plugins'
		).toBe( true );

		const ignore = await page.evaluate( () => [
			document
				.querySelector( '#wp-admin-bar-site-name > a' )
				?.textContent.trim(),
			document
				.querySelector( '#wp-admin-bar-my-account .display-name' )
				?.textContent.trim(),
		] );
		const findings = [];

		for ( const direction of DIRECTIONS ) {
			await page.evaluate(
				( dir ) => document.documentElement.setAttribute( 'dir', dir ),
				direction
			);

			for ( const item of await page.evaluate( unmarkedTexts ) ) {
				if (
					NOT_WORDS.test( item.text ) ||
					ignore.includes( item.text )
				) {
					continue;
				}

				findings.push( {
					screen: screen.id,
					cell: `1280-light-${ direction }-pseudo`,
					check: 'words',
					rule: 'not-translated',
					signature: item.text.replace( /\d+/g, '#' ).slice( 0, 120 ),
					detail: `"${ item.text.slice( 0, 100 ) }" in ${ item.element }`,
				} );
			}
		}

		assertNoNewFindings( expect, findings );
	} );
}
