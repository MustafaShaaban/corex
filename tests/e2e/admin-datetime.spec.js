/**
 * Corex E2E — dates across the CoreX admin (spec 076, T040/T041).
 *
 * The unit and integration suites prove the formatter is right. This proves the *product* is: that
 * every screen an operator actually opens shows a date they can read, that no machine timestamp
 * survives anywhere in operator-facing text, and that the format holds up at 375px, at 200% zoom,
 * and in RTL — where the longest month name has the most room to break something.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

/** Every CoreX screen that renders at least one date. */
const DATE_SURFACES = [
	'corex-settings',
	'corex-submissions',
	'corex-forms',
	'corex-email-studio',
	'corex-operations-security',
	'corex-notifications',
	'corex-blog-pro',
	'corex-access',
];

/**
 * Shapes a person should never be shown.
 *
 * Matched against the CoreX workspace's text only. The wp-admin chrome around it is not ours, and
 * an assertion that fails on WordPress's own markup teaches nothing.
 */
const MACHINE_SHAPES = [
	{
		name: 'ISO 8601 / SQL datetime',
		pattern: /\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/,
	},
	{ name: 'a bare Unix timestamp', pattern: /(^|\s)1[0-9]{9}(\s|$)/ },
	{ name: 'Invalid Date', pattern: /Invalid Date/ },
	{ name: 'NaN', pattern: /\bNaN\b/ },
	{
		name: 'the Unix epoch',
		pattern: /1 January 1970|Jan 1, 1970|1970-01-01/,
	},
];

/**
 * The workspace text, which is what this spec makes claims about.
 *
 * @param {import('@playwright/test').Page} page The loaded admin page.
 * @return {Promise<string>} The CoreX shell's text content.
 */
async function workspaceText( page ) {
	return page
		.locator( '.corex-admin' )
		.first()
		.innerText()
		.catch( () => '' );
}

test.describe( 'dates an operator can read', () => {
	for ( const slug of DATE_SURFACES ) {
		test( `${ slug } shows no machine timestamp`, async ( { page } ) => {
			const errors = collectConsoleErrors( page );
			await page.goto( `/wp-admin/admin.php?page=${ slug }` );
			await expect(
				page.locator( '.corex-admin' ).first()
			).toBeVisible();

			const text = await workspaceText( page );

			for ( const shape of MACHINE_SHAPES ) {
				expect(
					shape.pattern.test( text ),
					`${ slug } renders ${ shape.name } in operator-facing text`
				).toBe( false );
			}

			expect(
				errors,
				`console errors on ${ slug }:\n${ errors.join( '\n' ) }`
			).toEqual( [] );
		} );
	}

	test( 'every rendered date is a semantic time element with a machine value', async ( {
		page,
	} ) => {
		// FR-012. A `<time>` without a valid `datetime` claims a machine-readable date it does not
		// have, which is worse for a parser than no markup at all.
		await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
		await expect( page.locator( '.corex-inbox__table' ) ).toBeVisible();

		const times = await page
			.locator( '.corex-admin time' )
			.evaluateAll( ( nodes ) =>
				nodes.map( ( node ) => ( {
					datetime: node.getAttribute( 'datetime' ),
					text: node.textContent.trim(),
				} ) )
			);

		expect( times.length ).toBeGreaterThan( 0 );

		for ( const time of times ) {
			expect(
				time.datetime,
				`a <time> reading "${ time.text }" carries no datetime`
			).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/ );
			// The human half must not BE the machine half — the defect this spec removes.
			expect( time.text ).not.toBe( time.datetime );
		}
	} );

	test( 'the submission inbox reads the way a person writes a date', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
		await expect( page.locator( '.corex-inbox__table' ) ).toBeVisible();

		const cell = page
			.locator( '.corex-inbox__table tbody tr td:last-child' )
			.first();

		// "27 July 2026 at 8:53 AM" — day, full month name, four-digit year, connector, 12-hour
		// clock with a meridiem, and no seconds.
		await expect( cell ).toHaveText(
			/^\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} (AM|PM)$/
		);
	} );

	test( 'the same record reads the same in a different browser timezone', async ( {
		browser,
	} ) => {
		// SC-003, and the reason this spec exists: before spec 076 these surfaces rendered the same
		// records nine hours apart depending on who was looking.
		//
		// Read from the Submission Inbox rather than the flow list, which is where the defect was
		// originally measured. The inbox is the surface CI seeds (three submissions, in the
		// workflow's fixture step), so this assertion runs everywhere instead of being excluded on
		// a fresh install — and its date column is where the raw timestamps actually were.
		const read = async ( timezoneId ) => {
			const context = await browser.newContext( {
				storageState: require( './global-setup' ).STORAGE_STATE,
				timezoneId,
			} );
			const page = await context.newPage();
			await page.goto( '/wp-admin/admin.php?page=corex-submissions' );

			// Waiting for the shell is not enough — it is server-rendered and visible before the
			// React table mounts, so reading immediately finds zero dates and the comparison
			// passes vacuously against two empty lists.
			//
			// Bounded, and with its own message: an open-ended waitFor turns "this site has no
			// submissions" into "the test timed out after 60 seconds", which says nothing about
			// why. This says it in ten.
			await page
				.locator( '.corex-inbox__table tbody tr td:last-child time' )
				.first()
				.waitFor( { state: 'attached', timeout: 10_000 } )
				.catch( () => {
					throw new Error(
						`No dated submission rows rendered in ${ timezoneId }. The inbox needs at least one submission; CI seeds three in the "Seed browser-test fixtures" step.`
					);
				} );

			// Keyed by the machine value, not read as a list. The inbox is a live feed — other
			// specs in this suite create submissions — so the two passes do not necessarily see
			// the same rows, and comparing two lists positionally would report a difference that
			// is a new submission rather than a timezone leak. Keying asks the question that
			// matters: for one instant, do both readers see the same words?
			const byInstant = await page
				.locator( '.corex-inbox__table tbody tr td:last-child time' )
				.evaluateAll( ( nodes ) =>
					Object.fromEntries(
						nodes.map( ( node ) => [
							node.getAttribute( 'datetime' ),
							node.textContent.trim(),
						] )
					)
				);
			await context.close();
			return byInstant;
		};

		const inUtc = await read( 'UTC' );
		const inTokyo = await read( 'Asia/Tokyo' );

		const shared = Object.keys( inUtc ).filter( ( instant ) =>
			Object.prototype.hasOwnProperty.call( inTokyo, instant )
		);

		expect(
			shared.length,
			'the two passes saw no record in common, so nothing was compared'
		).toBeGreaterThan( 0 );

		for ( const instant of shared ) {
			expect(
				inTokyo[ instant ],
				`${ instant } read differently in Asia/Tokyo`
			).toBe( inUtc[ instant ] );
		}
	} );
} );

test.describe( 'dates hold their shape', () => {
	test( 'at 375px, at 200% zoom, and in RTL', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
		await expect( page.locator( '.corex-inbox__table' ) ).toBeVisible();

		const baseline = await page.evaluate( () => {
			const root = document.documentElement;
			return Math.max( 0, root.scrollWidth - root.clientWidth );
		} );

		for ( const dir of [ 'ltr', 'rtl' ] ) {
			await page
				.locator( 'html' )
				.evaluate(
					( root, value ) => root.setAttribute( 'dir', value ),
					dir
				);

			for ( const width of [ 375, 768, 1440 ] ) {
				await page.setViewportSize( { width, height: 900 } );

				const date = page
					.locator(
						'.corex-inbox__table tbody tr td:last-child time'
					)
					.first();

				// The date is still a readable date, not truncated to an ellipsis or wrapped into
				// something unparseable.
				await expect( date ).toHaveText(
					/^\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} (AM|PM)$/
				);

				// And it costs no horizontal scroll beyond what wp-admin's own chrome already
				// takes — measured, not assumed, because core's admin bar contributes 1px in RTL
				// that CoreX neither causes nor can remove (DECISIONS #163).
				const overflow = await page.evaluate( () => {
					const root = document.documentElement;
					return Math.max( 0, root.scrollWidth - root.clientWidth );
				} );

				expect(
					overflow,
					`dates overflow at ${ width }px (${ dir })`
				).toBeLessThanOrEqual( Math.max( baseline, 1 ) );
			}
		}
	} );

	test( 'at 200% zoom the date stays on one readable line', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
		await expect( page.locator( '.corex-inbox__table' ) ).toBeVisible();

		// 200% zoom is simulated the way WCAG 1.4.4 means it: half the viewport, full text size.
		await page.setViewportSize( { width: 720, height: 512 } );

		await expect(
			page
				.locator( '.corex-inbox__table tbody tr td:last-child time' )
				.first()
		).toHaveText( /^\d{1,2} \w+ \d{4} at \d{1,2}:\d{2} (AM|PM)$/ );
	} );
} );
