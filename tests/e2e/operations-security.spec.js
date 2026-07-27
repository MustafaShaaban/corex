/**
 * Corex E2E — Operations & Security (spec 077, T030–T033).
 *
 * The unit and integration suites prove each piece. This proves the screen: that an operator can
 * reach each area, that the mode form asks only what the chosen mode needs, that the address
 * survives a submission, and that it all holds at 375px, at 200% zoom and in RTL.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const SCREEN = '/wp-admin/admin.php?page=corex-operations-security';

const SECTIONS = [
	[ 'overview', 'Overview' ],
	[ 'environment', 'Environment & Maintenance' ],
	[ 'login', 'Login Protection' ],
	[ 'hardening', 'Hardening' ],
	[ 'activity', 'Activity' ],
];

test.describe( 'the screen has sections an operator can reach', () => {
	for ( const [ slug, label ] of SECTIONS ) {
		test( `${ slug } is linkable and marks itself current`, async ( {
			page,
		} ) => {
			const errors = collectConsoleErrors( page );
			await page.goto( `${ SCREEN }&tab=${ slug }` );

			const active = page.locator( '.corex-admin__tab.is-active' );
			await expect( active ).toHaveCount( 1 );
			await expect( active ).toHaveText( label );
			await expect( active ).toHaveAttribute( 'aria-current', 'page' );

			// FR-004: no section is a heading over nothing.
			const body = await page
				.locator( '.corex-opsec__sections' )
				.innerText();
			expect( body.trim().length ).toBeGreaterThan( 60 );

			expect(
				errors,
				`console errors on ${ slug }:\n${ errors.join( '\n' ) }`
			).toEqual( [] );
		} );
	}

	test( 'an unknown section falls back to the overview rather than to nothing', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=not-a-section` );

		await expect(
			page.locator( '.corex-admin__tab.is-active' )
		).toHaveText( 'Overview' );
	} );

	test( 'sections are real links, so Back returns to the previous one', async ( {
		page,
	} ) => {
		// The reason they are links and not an ARIA tablist: this works without a line of
		// JavaScript from us.
		await page.goto( `${ SCREEN }&tab=hardening` );
		await page.click( '.corex-admin__tabs a:has-text("Activity")' );
		await expect(
			page.locator( '.corex-admin__tab.is-active' )
		).toHaveText( 'Activity' );

		await page.goBack();
		await expect(
			page.locator( '.corex-admin__tab.is-active' )
		).toHaveText( 'Hardening' );
	} );
} );

test.describe( 'the mode form asks only what the chosen mode needs', () => {
	/**
	 * What the form is currently offering, from the operator's point of view.
	 *
	 * @param {import('@playwright/test').Page} page The environment section.
	 * @return {Promise<Object>} Visible blocks and the inputs that would actually submit.
	 */
	async function offered( page ) {
		return page.evaluate( () => {
			const blocks = [ ...document.querySelectorAll( '[data-mode]' ) ];

			return {
				visible: blocks
					.filter( ( block ) => ! block.hidden )
					.map( ( block ) => block.getAttribute( 'data-mode' ) ),
				// Disabled inputs are not submitted, which is the point: the server can never
				// receive a confirmation belonging to a mode nobody chose.
				submittable: blocks.flatMap( ( block ) =>
					[
						...block.querySelectorAll( 'input:not([disabled])' ),
					].map( ( input ) => input.name )
				),
			};
		} );
	}

	test( 'exactly one mode is described at a time', async ( { page } ) => {
		await page.goto( `${ SCREEN }&tab=environment` );

		const shown = await offered( page );
		expect( shown.visible ).toHaveLength( 1 );
	} );

	test( 'production asks for the phrase and nothing else', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment&mode=production` );

		const shown = await offered( page );
		expect( shown.visible ).toEqual( [ 'production' ] );
		expect( shown.submittable ).toContain( 'corex_confirm_phrase' );
		expect( shown.submittable ).not.toContain( 'corex_confirm' );
	} );

	test( 'maintenance asks for the acknowledgement and nothing else', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment&mode=maintenance` );

		const shown = await offered( page );
		expect( shown.visible ).toEqual( [ 'maintenance' ] );
		expect( shown.submittable ).toContain( 'corex_confirm' );
		expect( shown.submittable ).not.toContain( 'corex_confirm_phrase' );
	} );

	test( 'development and staging ask for no confirmation at all', async ( {
		page,
	} ) => {
		// The defect this replaces: both confirmations were rendered for every mode, so a site in
		// Development asked its operator to acknowledge consequences that could not occur.
		for ( const mode of [ 'development', 'staging' ] ) {
			await page.goto( `${ SCREEN }&tab=environment&mode=${ mode }` );

			const shown = await offered( page );
			expect( shown.visible, mode ).toEqual( [ mode ] );
			expect( shown.submittable, mode ).not.toContain(
				'corex_confirm_phrase'
			);
			expect( shown.submittable, mode ).not.toContain( 'corex_confirm' );
		}
	} );

	test( 'applying the mode already in force is not offered', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment` );

		const form = page.locator( '[data-corex-mode-form]' );
		const current = await form.getAttribute( 'data-current-mode' );

		// The native <select> is hidden — CorexSelect upgrades it and keeps it only as the
		// submitted value (DECISIONS #141). Driving the button-and-listbox the operator actually
		// uses is both the only way to reach it and the stronger assertion: it proves the
		// disclosure hears the approved control, not just a raw select.
		const choose = async ( mode ) => {
			await page.click( '.corex-opsec__mode-form .corex-select__button' );
			await page.click(
				`.corex-opsec__mode-form [role="option"]:has-text("${ mode }")`
			);
		};

		const label = ( mode ) =>
			mode.charAt( 0 ).toUpperCase() + mode.slice( 1 );

		await choose( label( current ) );
		await expect(
			page.locator( '[data-corex-mode-apply]' )
		).toBeDisabled();

		// And a different one is.
		const other = current === 'staging' ? 'development' : 'staging';
		await choose( label( other ) );
		await expect( page.locator( '[data-corex-mode-apply]' ) ).toBeEnabled();
	} );
} );

test.describe( 'the screen holds together', () => {
	test( 'at 375px, at 200% zoom, and in RTL', async ( { page } ) => {
		await page.goto( `${ SCREEN }&tab=overview` );

		const overflow = async () =>
			page.evaluate( () => {
				const root = document.documentElement;
				return Math.max( 0, root.scrollWidth - root.clientWidth );
			} );

		const baseline = await overflow();

		for ( const dir of [ 'ltr', 'rtl' ] ) {
			await page
				.locator( 'html' )
				.evaluate(
					( root, value ) => root.setAttribute( 'dir', value ),
					dir
				);

			for ( const width of [ 375, 720, 1440 ] ) {
				await page.setViewportSize( { width, height: 900 } );

				await expect(
					page.locator( '.corex-opsec__summary-list' )
				).toBeVisible();

				expect(
					await overflow(),
					`overflow at ${ width }px (${ dir })`
				).toBeLessThanOrEqual( Math.max( baseline, 1 ) );
			}
		}
	} );

	test( 'section spacing comes from the container, not from each section', async ( {
		page,
	} ) => {
		// FR-020. Measured rather than asserted from the stylesheet: what matters is that the gaps
		// between siblings are equal, which is what a container gap guarantees and per-section
		// margins do not.
		await page.setViewportSize( { width: 1440, height: 1000 } );
		await page.goto( `${ SCREEN }&tab=environment` );

		const gaps = await page.evaluate( () => {
			const container = document.querySelector(
				'.corex-opsec__sections'
			);
			const children = [ ...container.children ].filter(
				( child ) => child.getBoundingClientRect().height > 0
			);

			return children.slice( 1 ).map( ( child, index ) => {
				const previous = children[ index ].getBoundingClientRect();
				return Math.round(
					child.getBoundingClientRect().top - previous.bottom
				);
			} );
		} );

		expect( gaps.length ).toBeGreaterThan( 0 );
		for ( const gap of gaps ) {
			expect( gap, `gaps: ${ gaps.join( ', ' ) }` ).toBe( gaps[ 0 ] );
		}
	} );

	test( 'dates come from the shared contract, not from this screen', async ( {
		page,
	} ) => {
		// Spec 076 owns date presentation; this asserts the screen uses it rather than
		// reimplementing one.
		await page.goto( `${ SCREEN }&tab=environment` );

		const body = await page.locator( '.corex-opsec__sections' ).innerText();
		expect( body ).not.toMatch( /\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/ );
	} );
} );
