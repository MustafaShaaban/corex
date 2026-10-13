/**
 * Insights, Setup Wizard, and Settings surfaces (spec 068: T204). Verifies the full designed
 * Insights widget set, the nine-step wizard flow, and the Settings sections (incl. the Advanced
 * diagnostics read-out) render from real state with no console errors and no horizontal overflow.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

test( 'Insights renders the full designed widget set from real state', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect(
		page.getByRole( 'heading', { name: 'Readiness & Insights' } )
	).toBeVisible();

	// Two runnable providers (run-cards) + the five informational widgets.
	await expect( page.locator( '.corex-insight-card' ) ).toHaveCount( 2 );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );
	await expect(
		page.getByRole( 'heading', { name: 'Security events' } )
	).toBeVisible();
	await expect(
		page.getByRole( 'heading', { name: 'Forms & Flows analytics' } )
	).toBeVisible();
	// No stale "Planned" widget.
	await expect( page.getByText( 'Planned', { exact: true } ) ).toHaveCount(
		0
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'Insights lays its cards and widgets out on one shared grid', async ( {
	page,
} ) => {
	// This spec used to assert element counts only, which is exactly why the broken layout passed
	// CI for so long: the widgets were appended into a nested container that the outer grid then
	// treated as ONE cell, so all five collapsed into a single narrow column beside the cards.
	// Counts were still 2 and 5. Geometry is what catches it.
	await page.setViewportSize( { width: 1440, height: 900 } );
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );

	// The nested container is gone and every tile is a direct child of the one grid.
	await expect( page.locator( '.corex-insights__widgets' ) ).toHaveCount( 0 );
	await expect( page.locator( '#corex-insights-app > *' ) ).toHaveCount( 7 );

	// Two columns above the 48rem breakpoint: every tile starts on one of exactly two x offsets.
	const columns = await page
		.locator( '#corex-insights-app > *' )
		.evaluateAll( ( tiles ) => [
			...new Set(
				tiles.map( ( tile ) =>
					Math.round( tile.getBoundingClientRect().x )
				)
			),
		] );
	expect( columns, `tile x offsets: ${ columns.join( ', ' ) }` ).toHaveLength(
		2
	);

	// And they are real columns, not a stack: the widest tile cannot span the whole row.
	const grid = await page.locator( '#corex-insights-app' ).boundingBox();
	const tile = await page
		.locator( '.corex-insight-widget' )
		.first()
		.boundingBox();
	expect( tile.width ).toBeLessThan( grid.width * 0.75 );
} );

test( 'Insights orders its widgets by urgency, with nothing-to-show last', async ( {
	page,
} ) => {
	// FR-027. The chip text carries the state, so DOM order plus chip text is the whole contract.
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );
	await expect( page.locator( '.corex-insight-widget' ) ).toHaveCount( 5 );

	const chips = await page
		.locator( '.corex-insight-widget .corex-badge' )
		.allInnerTexts();
	const idle = chips.map( ( chip, index ) =>
		/Not connected|No data yet/.test( chip ) ? index : -1
	);
	const busy = chips.map( ( chip, index ) =>
		/Not connected|No data yet/.test( chip ) ? -1 : index
	);

	const lastBusy = Math.max( ...busy );
	const firstIdle = Math.min( ...idle.filter( ( index ) => index >= 0 ) );

	expect(
		firstIdle === Infinity || lastBusy < firstIdle,
		`chips in DOM order: ${ chips.join( ' | ' ) }`
	).toBe( true );
} );

test( 'Setup Wizard walks the nine-step flow with real kit and demo levels', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Setup Wizard' } )
	).toBeVisible();

	// The nine-step stepper mounted (JS took over from the server fallback).
	await expect( page.locator( '.corex-setup__step' ) ).toHaveCount( 9 );
	await expect(
		page.locator( '#corex-setup-app .corex-setup__panel' )
	).toBeVisible();

	// Welcome → Brand → Kit: a real kit option is offered.
	await page.locator( '#corex-setup-next' ).click();
	await page.locator( '#corex-setup-next' ).click();
	await expect(
		page.locator( 'input[name="corex-kit"]' ).first()
	).toBeVisible();

	// Kit → Demo: the three real demo levels.
	await page.locator( '#corex-setup-next' ).click();
	await expect( page.locator( 'input[name="corex-level"]' ) ).toHaveCount(
		3
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'Settings renders the real sections including the Advanced diagnostics read-out', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await page.goto(
		'/wp-admin/admin.php?page=corex-settings-config&corex_tab=advanced'
	);
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Settings' } )
	).toBeVisible();

	// The section tabs, including the new Advanced tab.
	await expect( page.locator( '[data-corex-tab="advanced"]' ) ).toBeVisible();
	await expect( page.locator( '[data-corex-tab="media"]' ) ).toBeVisible();
	// The Advanced diagnostics show a real PHP version — never a fabricated value.
	await expect( page.getByText( 'PHP version' ) ).toBeVisible();
	await expect( page.getByText( /8\.\d+\.\d+/ ).first() ).toBeVisible();

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'the three surfaces have no horizontal overflow at mobile width', async ( {
	page,
} ) => {
	for ( const slug of [
		'corex-insights',
		'corex-setup',
		'corex-settings-config',
	] ) {
		await page.setViewportSize( { width: 375, height: 800 } );
		await page.goto( `/wp-admin/admin.php?page=${ slug }` );
		await page.waitForTimeout( 400 );
		const fits = await page.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth + 1
		);
		expect( fits, `horizontal overflow on ${ slug }` ).toBe( true );
	}
} );

// Spec 106, story 5: the three settings of a reply's sender, and a save that says what it did
// not take. An address typed wrongly was dropped in silence under "Settings saved".
test( 'Settings refuses a reply sender address that is not one, says so until dismissed, and saves the rest', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const open = async () => {
		await page.goto(
			'/wp-admin/admin.php?page=corex-settings-config&corex_tab=mail'
		);
		await expect(
			page.getByRole( 'tab', { name: 'Mail' } )
		).toHaveAttribute( 'aria-selected', 'true' );
	};
	const field = ( name ) => page.locator( `[name="${ name }"]` );
	const save = () =>
		page.getByRole( 'button', { name: 'Save settings' } ).click();
	await page.setViewportSize( { width: 1280, height: 900 } );
	await open();
	const before = {
		name: await field( 'mail_reply_from_name' ).inputValue(),
		address: await field( 'mail_reply_from_address' ).inputValue(),
	};

	try {
		// An address a browser lets through and WordPress does not: no dot after the @.
		// One with nothing after the @ is stopped by the browser, with its own words.
		await field( 'mail_reply_from_name' ).fill( 'Acme Sales' );
		await field( 'mail_reply_from_address' ).fill( 'answers@acme' );
		await save();

		const refused = page.locator( '.corex-toast' );
		await expect( refused ).toHaveAttribute( 'role', 'alert' );
		await expect( refused ).toContainText(
			'Reply sender address was not changed: "answers@acme" is not an email address.'
		);
		await expect( refused ).toContainText( 'Everything else was saved.' );
		await expect( refused ).not.toContainText( 'Settings saved.' );
		// It is the last thing in the page and was there when the page arrived, which a
		// screen reader does not reliably announce: it has focus, which reads it. And the
		// field it is about says it was not taken, and is described by its line.
		await expect( refused ).toBeFocused();
		await expect( field( 'mail_reply_from_address' ) ).toHaveAttribute(
			'aria-invalid',
			'true'
		);
		const itsLine = refused.locator(
			'[data-corex-refused="mail_reply_from_address"]'
		);
		await expect( field( 'mail_reply_from_address' ) ).toHaveAttribute(
			'aria-describedby',
			new RegExp( `(^| )${ await itsLine.getAttribute( 'id' ) }( |$)` )
		);
		await expect( field( 'mail_reply_from_name' ) ).not.toHaveAttribute(
			'aria-invalid',
			'true'
		);
		// The rest was saved, the Mail tab is still the one open, and the field that was
		// refused is as it was.
		await expect( field( 'mail_reply_from_name' ) ).toHaveValue(
			'Acme Sales'
		);
		await expect( field( 'mail_reply_from_address' ) ).toHaveValue(
			before.address
		);

		// Where it stands: inside the window, its lines one under another, the button to
		// dismiss it beside them and inside it.
		const measured = await refused.evaluate( ( toast ) => {
			const box = ( node ) => node.getBoundingClientRect();
			const lines = [ ...toast.querySelectorAll( 'p' ) ].map( box );
			const dismiss = box( toast.querySelector( 'button' ) );
			const whole = box( toast );

			return {
				lines: lines.length,
				stacked: lines.every(
					( line, at ) =>
						at === 0 || line.top >= lines[ at - 1 ].bottom
				),
				gaps: lines
					.slice( 1 )
					.map( ( line, at ) =>
						Math.round( line.top - lines[ at ].bottom )
					),
				dismissInside:
					dismiss.left >= whole.left &&
					dismiss.right <= whole.right &&
					dismiss.top >= whole.top &&
					dismiss.bottom <= whole.bottom,
				dismissBesideTheLines: lines.every(
					( line ) =>
						line.right <= dismiss.left || line.left >= dismiss.right
				),
				inWindow:
					whole.left >= 0 &&
					whole.right <= window.innerWidth &&
					whole.bottom <= window.innerHeight,
			};
		} );
		expect( measured.lines ).toBe( 2 );
		expect( measured.stacked, 'one line under another' ).toBe( true );
		expect( measured.gaps[ 0 ], 'apart' ).toBeGreaterThanOrEqual( 2 );
		expect( measured.dismissInside, 'the button inside it' ).toBe( true );
		expect( measured.dismissBesideTheLines, 'beside the words' ).toBe(
			true
		);
		expect( measured.inWindow, 'inside the window' ).toBe( true );

		// It names what to type again: it does not go away by itself, as "saved" does.
		await page.waitForTimeout( 6000 );
		await expect( refused ).toBeVisible();
		// Dismissed by the keyboard, the field to type again is where the keyboard is:
		// the button that was pressed went with the message.
		await refused.getByRole( 'button', { name: 'Dismiss' } ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( refused ).toHaveCount( 0 );
		await expect( field( 'mail_reply_from_address' ) ).toBeFocused();

		// Typed as an address, it is saved, and that is all that is said.
		await field( 'mail_reply_from_address' ).fill( 'answers@example.com' );
		await save();
		const saved = page.locator( '.corex-toast' );
		await expect( saved ).toHaveAttribute( 'role', 'status' );
		await expect( saved ).toContainText( 'Settings saved.' );
		await expect( field( 'mail_reply_from_address' ) ).toHaveValue(
			'answers@example.com'
		);
	} finally {
		// As they were: a reply sent by another test leaves as this site's replies do. What
		// goes wrong here is said and not thrown, so that it does not stand in for whatever
		// failed above.
		try {
			await open();
			await field( 'mail_reply_from_name' ).fill( before.name );
			await field( 'mail_reply_from_address' ).fill( before.address );
			await save();
			await expect( page.locator( '.corex-toast' ) ).toContainText(
				'Settings saved.'
			);
		} catch ( notRestored ) {
			// eslint-disable-next-line no-console
			console.warn(
				`The reply sender settings were not put back: ${ notRestored.message }`
			);
		}
	}

	expect( errors ).toEqual( [] );
} );
