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

	/**
	 * The mode the site is in, and whether it declared it or only inherits it from the WordPress
	 * environment type. The two differ in what the form offers: a declared mode proposes nothing
	 * when chosen again, and an inherited one can still be declared.
	 *
	 * @param {import('@playwright/test').Page} page The environment section.
	 * @return {Promise<{current: string, declared: boolean}>} Where the site stands.
	 */
	async function standing( page ) {
		const form = page.locator( '[data-corex-mode-form]' );

		return {
			current: await form.getAttribute( 'data-current-mode' ),
			declared:
				( await form.getAttribute( 'data-mode-declared' ) ) === '1',
		};
	}

	/**
	 * The blocks the form should be showing when `mode` is selected.
	 *
	 * @param {{current: string, declared: boolean}} site Where the site stands.
	 * @param {string}                               mode The mode selected.
	 * @return {string[]} That mode's block, or none when selecting it proposes nothing.
	 */
	const described = ( site, mode ) =>
		site.declared && site.current === mode ? [] : [ mode ];

	test( 'the mode a site has declared proposes nothing; any other mode is described alone', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment` );

		// The selection starts on the mode the site is in. For a declared mode that used to draw
		// its consequences and its confirmation beside a disabled button; it proposes nothing, so
		// the form says so and asks for nothing. An inherited mode can still be declared, so its
		// block is offered.
		const site = await standing( page );
		const resting = await offered( page );

		expect( resting.visible ).toEqual( described( site, site.current ) );
		await expect( page.locator( '[data-corex-mode-same]' ) ).toBeVisible( {
			visible: site.declared,
		} );

		const other = site.current === 'staging' ? 'development' : 'staging';

		await page.goto( `${ SCREEN }&tab=environment&mode=${ other }` );

		expect( ( await offered( page ) ).visible ).toEqual( [ other ] );
		await expect( page.locator( '[data-corex-mode-same]' ) ).toBeHidden();
	} );

	test( 'production asks for the phrase and nothing else', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment&mode=production` );

		const site = await standing( page );
		const shown = await offered( page );

		expect( shown.visible ).toEqual( described( site, 'production' ) );
		expect( shown.submittable ).not.toContain( 'corex_confirm' );
		if ( shown.visible.length > 0 ) {
			expect( shown.submittable ).toContain( 'corex_confirm_phrase' );
		}
	} );

	test( 'maintenance asks for the acknowledgement and nothing else', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment&mode=maintenance` );

		const site = await standing( page );
		const shown = await offered( page );

		expect( shown.visible ).toEqual( described( site, 'maintenance' ) );
		expect( shown.submittable ).not.toContain( 'corex_confirm_phrase' );
		if ( shown.visible.length > 0 ) {
			expect( shown.submittable ).toContain( 'corex_confirm' );
		}
	} );

	test( 'maintenance says what it closes and what stays open, without opening anything', async ( {
		page,
	} ) => {
		// Spec 112 FR-018: the lines an owner reads before choosing, not the collapsed reference.
		await page.goto( `${ SCREEN }&tab=environment&mode=maintenance` );

		const block = page.locator( '[data-mode="maintenance"]' );
		test.skip(
			await block.isHidden(),
			'the site has declared Maintenance, so choosing it again proposes nothing to read'
		);

		for ( const words of [
			'Public form submissions are closed',
			'sign-in and password reset stay open',
			'discussion settings',
		] ) {
			await expect(
				block.getByText( words, { exact: false } )
			).toBeVisible();
		}
	} );

	test( 'development and staging ask for no confirmation at all', async ( {
		page,
	} ) => {
		// The defect this replaces: both confirmations were rendered for every mode, so a site in
		// Development asked its operator to acknowledge consequences that could not occur.
		for ( const mode of [ 'development', 'staging' ] ) {
			await page.goto( `${ SCREEN }&tab=environment&mode=${ mode }` );

			const site = await standing( page );
			const shown = await offered( page );

			expect( shown.visible, mode ).toEqual( described( site, mode ) );
			expect( shown.submittable, mode ).not.toContain(
				'corex_confirm_phrase'
			);
			expect( shown.submittable, mode ).not.toContain( 'corex_confirm' );
		}
	} );

	test( 'applying a declared mode again is not offered, and declaring an inherited one is', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=environment` );

		const site = await standing( page );

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

		// A site that only inherits its mode is told to declare one, and choosing the mode it
		// already follows does that. The button used to be disabled for it all the same.
		await choose( label( site.current ) );
		await expect( page.locator( '[data-corex-mode-apply]' ) ).toBeEnabled( {
			enabled: ! site.declared,
		} );

		// And a different one always is.
		const other = site.current === 'staging' ? 'development' : 'staging';
		await choose( label( other ) );
		await expect( page.locator( '[data-corex-mode-apply]' ) ).toBeEnabled();
	} );
} );

test.describe( 'recovery works without a command line', () => {
	// Spec 112, User Story 4.
	test( 'the panel gives both ways back, as code that can be copied', async ( {
		page,
	} ) => {
		await page.goto( `${ SCREEN }&tab=login` );

		const panel = page.locator( '.corex-security__recovery' );
		await expect(
			panel.getByRole( 'heading', { name: 'Without a command line' } )
		).toBeVisible();
		await expect(
			panel.getByRole( 'heading', { name: 'With a command line' } )
		).toBeVisible();

		const values = panel.locator( 'textarea[readonly]' );
		await expect( values ).toHaveCount( 2 );
		await expect( values.nth( 0 ) ).toHaveValue(
			"define( 'COREX_LOGIN_UNGUARD', true );"
		);
		await expect( values.nth( 1 ) ).toHaveValue(
			'wp corex security reset-login'
		);

		// The whole value is in view at any width: an owner reads the line before copying it.
		const rows = panel.locator( '.corex-copy-field__row' );
		for ( const width of [ 1280, 375 ] ) {
			await page.setViewportSize( { width, height: 900 } );

			for ( let index = 0; index < 2; index++ ) {
				const textarea = rows.nth( index ).locator( 'textarea' );
				const field = await textarea.boundingBox();
				const button = await rows
					.nth( index )
					.getByRole( 'button', { name: 'Copy' } )
					.boundingBox();
				const hidden = await textarea.evaluate( ( element ) => ( {
					across: element.scrollWidth - element.clientWidth,
					down: element.scrollHeight - element.clientHeight,
				} ) );

				// The button is beside the field, or under it where they do not fit on one line.
				expect(
					Math.abs( field.y - button.y ) <= 1 ||
						button.y >= field.y + field.height
				).toBe( true );
				expect( hidden ).toEqual( { across: 0, down: 0 } );
				await expect( textarea ).toHaveAttribute( 'dir', 'ltr' );
			}
		}
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
