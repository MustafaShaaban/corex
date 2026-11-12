/**
 * The keyboard walk (spec 116, US-NFR-03; Definition of Done item 6).
 *
 * On every admin screen and theme template, at 1280px: each stop a keyboard reaches is visible
 * when it has focus, and shows a focus indicator with at least 3:1 against what is behind it, in
 * light and in dark. Then each control that says it expands (`aria-expanded="false"`) is opened,
 * Escape is pressed, and it must close and hand focus back to that control.
 *
 * Stops are taken in document order and focused by script after one real Tab press, which is
 * what makes the browser treat focus as keyboard focus. That reads 200 stops in one call instead
 * of 200 round trips; a stop that only a script can reach (`tabindex="-1"`) is not taken.
 */
const { test, expect } = require( '@playwright/test' );

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings } = require( './findings' );
const { site, DESKTOP, APPEARANCES } = require( './site' );

const STOPS = 200;
const EXPANDERS = 8;

async function open( page, path ) {
	await page.addInitScript( installQualityHelpers );
	await page.setViewportSize( DESKTOP );
	await page.goto( path, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );
}

async function focusFindings( page, id ) {
	const findings = [];

	for ( const appearance of APPEARANCES ) {
		await page.emulateMedia( { colorScheme: appearance } );
		await page.keyboard.press( 'Tab' );

		const walk = await page.evaluate(
			( limit ) => window.__corexQuality.focusWalk( limit ),
			STOPS
		);

		// A walk that reached nothing proves nothing: every screen and page has a stop.
		expect(
			walk.stops,
			`${ id }: the keyboard walk found no stop to focus`
		).toBeGreaterThan( 0 );

		findings.push(
			...walk.found.map( ( item ) => ( {
				screen: id,
				cell: `1280-${ appearance }-ltr`,
				check: 'keyboard',
				rule: item.rule,
				signature: item.signature,
				detail: `"${ item.name }"`,
			} ) )
		);
	}

	return findings;
}

/**
 * Each expanding control: opened, Escape, closed, focus back on it.
 * @param {import('@playwright/test').Page} page The page under test.
 * @param {string}                          id   A screen or template id.
 * @param {string}                          path The address to open.
 */
async function escapeFindings( page, id, path ) {
	const findings = [];
	// Taken once, as handles: the set of collapsed controls changes as each one is opened.
	const expanders = (
		await page.locator( '[aria-expanded="false"]:visible' ).elementHandles()
	).slice( 0, EXPANDERS );

	for ( const control of expanders ) {
		const href = await control.getAttribute( 'href' ).catch( () => null );

		if ( href && ! href.startsWith( '#' ) ) {
			continue;
		}

		const signature = await control
			.evaluate( ( element ) =>
				window.__corexQuality.signature( element )
			)
			.catch( () => null );

		if ( signature === null ) {
			continue;
		}

		await control.click( { timeout: 3_000 } ).catch( () => {} );
		await page.waitForTimeout( 250 );
		const opened = await control
			.getAttribute( 'aria-expanded' )
			.catch( () => null );

		if ( opened !== 'true' ) {
			if ( ! page.url().includes( path ) ) {
				await page.goto( path, { waitUntil: 'load' } );
				break;
			}
			continue;
		}

		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 250 );

		const after = await control
			.evaluate( ( element ) => ( {
				expanded: element.getAttribute( 'aria-expanded' ),
				focused: element.ownerDocument.activeElement === element,
			} ) )
			.catch( () => null );

		if ( after === null ) {
			continue;
		}
		const finding = ( rule, detail ) => ( {
			screen: id,
			cell: '1280-light-ltr',
			check: 'keyboard',
			rule,
			signature,
			detail,
		} );

		if ( after.expanded !== 'false' ) {
			findings.push(
				finding(
					'escape-does-not-close',
					'still expanded after Escape'
				)
			);
			await control.click( { timeout: 3_000 } ).catch( () => {} );
		} else if ( ! after.focused ) {
			findings.push(
				finding(
					'focus-not-returned',
					'closed by Escape, but focus did not go back to the control that opened it'
				)
			);
		}
	}

	return findings;
}

test.describe( 'admin screens', () => {
	for ( const screen of site.screens ) {
		test( `${ screen.id }: focus is visible, Escape closes and returns focus`, async ( {
			page,
		} ) => {
			await open( page, screen.path );
			const findings = await focusFindings( page, screen.id );
			await page.emulateMedia( { colorScheme: 'light' } );
			findings.push(
				...( await escapeFindings( page, screen.id, screen.path ) )
			);
			assertNoNewFindings( expect, findings );
		} );
	}
} );

test.describe( 'theme templates, as a visitor sees them', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	for ( const template of site.templates.filter( ( entry ) => entry.path ) ) {
		const id = `template:${ template.template }`;

		test( `${ id }: focus is visible, Escape closes and returns focus`, async ( {
			page,
		} ) => {
			await open( page, template.path );
			const findings = await focusFindings( page, id );
			await page.emulateMedia( { colorScheme: 'light' } );
			findings.push(
				...( await escapeFindings( page, id, template.path ) )
			);
			assertNoNewFindings( expect, findings );
		} );
	}
} );
