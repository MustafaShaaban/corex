/**
 * Corex E2E — what the admin shows while it waits (spec 108).
 *
 * Each test holds a request's answer back, looks at the screen in the moment between asking and
 * being told, and then lets the answer through. That moment is the whole subject: on a local
 * site it lasts a few milliseconds and nobody ever sees it, which is how a surface comes to look
 * empty, or to throw its content away, while it is only waiting.
 *
 * The answer that is let through is written here, so that a placeholder can be measured against
 * the card that replaces it. Nothing seeds notification records on a test site.
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local), a built corex-config, and
 * `npx playwright install`. Uses the saved admin session.
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: require( './global-setup' ).STORAGE_STATE } );

const NOTIFICATIONS = '/wp-admin/admin.php?page=corex-notifications';
const OVERVIEW = '/wp-admin/admin.php?page=corex-settings';

// The list request, however the site spells its REST URLs; not an action on one item.
const isNotificationList = ( url ) =>
	/corex\/v1\/notifications(\?|&|$)/.test( decodeURIComponent( url.href ) );

function notification( id, title ) {
	return {
		id,
		category: 'submissions',
		severity: 'warning',
		source_module: 'corex-forms',
		environment: 'production',
		occurrences: 1,
		latest_occurred_at: new Date( Date.now() - 1800000 ).toISOString(),
		action: null,
		can_resolve: true,
		rendered: { title, body: 'The mail server refused the message.' },
		user_state: {
			read: false,
			status: 'unread',
			view: 'action_needed',
			needs_action: true,
		},
	};
}

const listOf = ( ...items ) => ( {
	success: true,
	data: { items, total: items.length },
} );

/**
 * Answer every list request with what the test gives it, when the test says so.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<(body: Object) => Promise<void>>} `answer( body )`: lets the oldest waiting request through.
 */
async function holdNotificationLists( page ) {
	const waiting = [];
	const arrivals = [];

	await page.route( isNotificationList, ( route ) => {
		waiting.push( route );
		arrivals.shift()?.();
	} );

	return async ( body ) => {
		if ( waiting.length === 0 ) {
			await new Promise( ( resolve ) => arrivals.push( resolve ) );
		}
		await waiting.shift().fulfill( { json: body } );
	};
}

const THEMES = [ 'dark', 'light' ];
const DIRECTIONS = [ 'ltr', 'rtl' ];

async function dress( page, theme, direction ) {
	await page.evaluate(
		( [ appearance, dir ] ) => {
			document.documentElement.setAttribute( 'dir', dir );
			document
				.querySelector( '.corex-admin' )
				.setAttribute( 'data-corex-theme', appearance );
		},
		[ theme, direction ]
	);
}

const box = ( locator ) =>
	locator.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return {
			left: Math.round( rect.left ),
			top: Math.round( rect.top ),
			width: Math.round( rect.width ),
			height: Math.round( rect.height ),
		};
	} );

for ( const theme of THEMES ) {
	for ( const direction of DIRECTIONS ) {
		test( `a placeholder stands where the first notification will (${ theme }, ${ direction })`, async ( {
			page,
		} ) => {
			const answer = await holdNotificationLists( page );
			await page.goto( NOTIFICATIONS );
			await dress( page, theme, direction );

			const surface = page.locator(
				'.corex-notifications-screen .corex-loadable'
			);
			await expect( surface ).toHaveAttribute(
				'data-corex-state',
				'loading'
			);
			// Nothing says "empty" while nothing has been said at all.
			await expect(
				page.getByText( 'Nothing needs you right now.' )
			).toHaveCount( 0 );

			const placeholderCard = surface
				.locator(
					'.corex-admin-skeleton .corex-notifications-screen__item'
				)
				.first();
			const bar = placeholderCard
				.locator( '.corex-admin-skeleton__bar' )
				.first();
			await expect( placeholderCard ).toBeVisible();

			// A placeholder that cannot be told from its card is not there.
			const colours = await bar.evaluate( ( element ) => ( {
				bar: window.getComputedStyle( element ).backgroundColor,
				card: window.getComputedStyle(
					element.closest( '.corex-notifications-screen__item' )
				).backgroundColor,
			} ) );
			expect( colours.bar ).not.toBe( colours.card );

			const before = await box( placeholderCard );

			await answer(
				listOf(
					notification( 1, 'A contact form submission was not sent' ),
					notification( 2, 'A second submission was not sent' )
				)
			);
			await expect( surface ).toHaveAttribute(
				'data-corex-state',
				'ready'
			);

			const after = await box(
				surface.locator( '.corex-notifications-screen__item' ).first()
			);

			expect( after.left ).toBe( before.left );
			expect( after.top ).toBe( before.top );
			expect( after.width ).toBe( before.width );
			// Measured: 5px short of this card left to right, 7px right to left. The pill
			// round a severity and the chrome of an action button are what a bar does not
			// have. A card with a longer body, or with no action, differs by more than
			// that from its neighbour.
			expect(
				Math.abs( after.height - before.height )
			).toBeLessThanOrEqual( 8 );
		} );
	}
}

test( 'a list that is being replaced stays on screen, dimmed, and out of reach', async ( {
	page,
} ) => {
	const answer = await holdNotificationLists( page );
	await page.goto( NOTIFICATIONS );
	await answer(
		listOf( notification( 1, 'A contact form submission was not sent' ) )
	);

	const surface = page.locator(
		'.corex-notifications-screen .corex-loadable'
	);
	const title = surface.getByText( 'A contact form submission was not sent' );
	await expect( title ).toBeVisible();

	await page.getByRole( 'button', { name: 'Updates', exact: true } ).click();

	await expect( surface ).toHaveAttribute( 'data-corex-state', 'refreshing' );
	await expect( title ).toBeVisible();
	await expect( surface.locator( '.corex-admin-skeleton' ) ).toHaveCount( 0 );

	const body = surface.locator( '.corex-loadable__body' );
	await expect( body ).toHaveAttribute( 'inert', '' );
	await expect( body ).toHaveCSS( 'opacity', '0.58' );
	// The bar that says "waiting" is drawn, and is moving.
	expect(
		await surface.evaluate(
			( element ) =>
				window.getComputedStyle( element, '::before' ).animationName
		)
	).toContain( 'corex-waiting-bar' );

	await answer( listOf( notification( 2, 'An export finished' ) ) );

	await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
	await expect( surface.getByText( 'An export finished' ) ).toBeVisible();
	await expect( body ).toHaveCSS( 'opacity', '1' );
} );

test( 'nothing moves for a person who asked for reduced motion', async ( {
	page,
} ) => {
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	const answer = await holdNotificationLists( page );
	await page.goto( NOTIFICATIONS );

	const surface = page.locator(
		'.corex-notifications-screen .corex-loadable'
	);
	const bar = surface.locator( '.corex-admin-skeleton__bar' ).first();
	await expect( bar ).toBeVisible();

	// The placeholder is still drawn: a plain block, with no band crossing it.
	expect(
		await bar.evaluate(
			( element ) =>
				window.getComputedStyle( element, '::after' ).animationName
		)
	).toBe( 'none' );

	await answer(
		listOf( notification( 1, 'A contact form submission was not sent' ) )
	);
	await page.getByRole( 'button', { name: 'Updates', exact: true } ).click();
	await expect( surface ).toHaveAttribute( 'data-corex-state', 'refreshing' );

	// The waiting bar is there and is not travelling.
	expect(
		await surface.evaluate(
			( element ) =>
				window.getComputedStyle( element, '::before' ).animationName
		)
	).toBe( 'corex-loading-appear' );
	expect(
		await page.evaluate(
			() =>
				document
					.getAnimations()
					.filter(
						( animation ) =>
							animation.effect.getComputedTiming().iterations ===
							Infinity
					).length
		)
	).toBe( 0 );

	await answer( listOf() );
} );

test( 'the drawer shows a placeholder, and never "all caught up", before its answer', async ( {
	page,
} ) => {
	const answer = await holdNotificationLists( page );
	await page.goto( OVERVIEW );
	await page.locator( '[data-corex-notification-bell]' ).click();

	const surface = page.locator(
		'.corex-notification-drawer__panel .corex-loadable'
	);
	await expect( surface ).toHaveAttribute( 'data-corex-state', 'loading' );
	await expect(
		surface.locator(
			'.corex-admin-skeleton .corex-notification-drawer__item'
		)
	).toHaveCount( 3 );
	await expect( page.getByText( 'all caught up' ) ).toHaveCount( 0 );

	await answer( listOf() );

	await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
	await expect( page.getByText( 'all caught up' ) ).toBeVisible();
} );

test( 'a working button keeps its width, shows the loader, and cannot be pressed again', async ( {
	page,
} ) => {
	// The request is answered here and never reaches the site: a test that marked every
	// notification on a developer's site as read would be one nobody ran twice.
	const sent = [];
	await page.route(
		( url ) => decodeURIComponent( url.href ).includes( 'read-all' ),
		( route ) => sent.push( route )
	);
	await page.goto( NOTIFICATIONS );
	await expect(
		page.locator( '.corex-notifications-screen .corex-loadable' )
	).toHaveAttribute( 'data-corex-state', 'ready' );

	const button = page.getByRole( 'button', { name: 'Mark all as read' } );
	const before = await box( button );

	await button.click();

	await expect( button ).toHaveAttribute( 'data-corex-working', 'true' );
	await expect( button ).toBeDisabled();
	await expect( button ).toHaveAttribute( 'aria-busy', 'true' );
	// Busy, not switched off: it is not dimmed as a disabled control is.
	await expect( button ).toHaveCSS( 'opacity', '1' );
	expect( await box( button ) ).toEqual( before );
	expect(
		await button.evaluate(
			( element ) =>
				window.getComputedStyle( element, '::after' ).animationName
		)
	).toBe( 'corex-loader-turn' );

	// A press that lands anyway sends nothing.
	await button.click( { force: true } );
	expect( sent ).toHaveLength( 1 );

	await sent[ 0 ].fulfill( { json: { success: true, data: {} } } );

	await expect( button ).not.toHaveAttribute( 'data-corex-working', 'true' );
	await expect( button ).toBeEnabled();
} );

test( 'the working state reaches a control drawn where WordPress puts a modal', async ( {
	page,
} ) => {
	// A WordPress `Modal` is drawn at the end of <body>, outside the admin's own wrapper,
	// and the first rules for a working control were scoped to that wrapper: the two
	// confirm buttons in such modals were disabled and showed nothing.
	await page.goto( NOTIFICATIONS );

	const animation = await page.evaluate( () => {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.disabled = true;
		button.setAttribute( 'data-corex-working', 'true' );
		button.textContent = 'Confirm and apply';
		document.body.appendChild( button );

		return {
			insideWrapper: Boolean( button.closest( '.corex-admin' ) ),
			name: window.getComputedStyle( button, '::after' ).animationName,
		};
	} );

	expect( animation ).toEqual( {
		insideWrapper: false,
		name: 'corex-loader-turn',
	} );
} );

test( 'in Email Studio the button that was pressed is the one that works', async ( {
	page,
} ) => {
	// The request is held and then dropped: nothing is created on the site by this test.
	const sent = [];
	await page.route(
		( url ) =>
			/email-studio\/templates(\?|$)/.test(
				decodeURIComponent( url.href )
			),
		( route ) =>
			route.request().method() === 'POST'
				? sent.push( route )
				: route.continue()
	);
	await page.goto( '/wp-admin/admin.php?page=corex-email-studio' );
	await page
		.getByRole( 'button', { name: 'Templates', exact: true } )
		.click();
	await page.getByLabel( 'New template slug' ).fill( 'held-by-a-test' );
	await page.getByLabel( 'Name', { exact: true } ).fill( 'Held by a test' );

	const create = page.getByRole( 'button', { name: 'Create', exact: true } );
	const before = await box( create );

	await create.click();

	await expect( create ).toHaveAttribute( 'data-corex-working', 'true' );
	expect( await box( create ) ).toEqual( before );
	// Every other button on the tab waits, and none of them says it is the one working.
	await expect( page.locator( '[data-corex-working]' ) ).toHaveCount( 1 );

	await sent[ 0 ].abort();

	await expect( create ).not.toHaveAttribute( 'data-corex-working', 'true' );
	await expect( create ).toBeEnabled();
} );

test( 'on Insights, a screen that is not React, "Run check" works the same way', async ( {
	page,
} ) => {
	// Held and then dropped: no check is run on the site by this test.
	const sent = [];
	await page.route(
		( url ) => /insights\/run(\?|$)/.test( decodeURIComponent( url.href ) ),
		( route ) => sent.push( route )
	);
	await page.goto( '/wp-admin/admin.php?page=corex-insights' );

	const card = page.locator( '.corex-insight-card' ).first();
	const run = card.getByRole( 'button', { name: 'Run check' } );
	const before = await box( run );

	await run.click();

	// The card is drawn again as markup, so this is a new button with the same name.
	await expect( run ).toHaveAttribute( 'data-corex-working', 'true' );
	await expect( run ).toBeDisabled();
	expect( await box( run ) ).toEqual( before );
	expect(
		await run.evaluate(
			( element ) =>
				window.getComputedStyle( element, '::after' ).animationName
		)
	).toBe( 'corex-loader-turn' );

	await sent[ 0 ].abort();

	await expect( run ).toBeEnabled();
	await expect( run ).not.toHaveAttribute( 'data-corex-working', 'true' );
} );

test( 'in the setup wizard, the step that waits for its plan says so', async ( {
	page,
} ) => {
	// "Next" used to do nothing visible until the plan arrived, and asked again on a second
	// press. The request is let through at the end: it reads, and changes nothing.
	const held = [];
	await page.route(
		( url ) => /setup\/plan(\?|&|$)/.test( decodeURIComponent( url.href ) ),
		( route ) => held.push( route )
	);
	await page.goto( '/wp-admin/admin.php?page=corex-setup' );
	await expect(
		page.locator( '#corex-setup-app .corex-setup__panel' )
	).toBeVisible();

	const next = page.locator( '#corex-setup-next' );
	// Welcome, Brand, Kit and Demo ask the server for nothing; the step after them is the plan.
	for ( let step = 0; step < 6 && held.length === 0; step++ ) {
		await next.click();
		await page.waitForTimeout( 150 );
	}

	expect( held ).toHaveLength( 1 );
	await expect( next ).toHaveAttribute( 'data-corex-working', 'true' );
	await expect( next ).toBeDisabled();
	await expect( page.locator( '#corex-setup-back' ) ).toBeDisabled();

	await held[ 0 ].continue();

	await expect( next ).not.toHaveAttribute( 'data-corex-working', 'true' );
} );

const DATA = '/wp-admin/admin.php?page=corex-data-models&tab=records';

// A request for a source's rows: not the list of sources, and not one record.
const isRecordRows = ( url ) => {
	const href = decodeURIComponent( url.href );
	return (
		/corex\/v1\/data\/[a-z0-9_-]+(\?|&)/.test( href ) &&
		! /data\/(sources|migrations)/.test( href ) &&
		/per_page=/.test( href )
	);
};

test.describe( 'the Data records list', () => {
	// Wide enough that a row is one line: a row that wraps is as tall as what it holds.
	test.use( { viewport: { width: 1900, height: 1000 } } );

	test( 'shows rows that are not there yet, the height of the rows that replace them', async ( {
		page,
	} ) => {
		const held = [];
		await page.route( isRecordRows, ( route ) => held.push( route ) );
		await page.goto( DATA );

		const surface = page.locator(
			'.corex-data__panel-body .corex-loadable'
		);
		await expect( surface ).toHaveAttribute(
			'data-corex-state',
			'loading'
		);
		// Neither "nothing here" nor a count nobody has made.
		await expect( page.getByText( 'No records yet.' ) ).toHaveCount( 0 );
		await expect(
			page.locator( '.corex-data__metric .corex-admin-skeleton' )
		).toHaveCount( 1 );

		const placeholderRow = surface
			.locator( '.corex-admin-skeleton tbody tr' )
			.first();
		const placeholderHead = surface.locator(
			'.corex-admin-skeleton thead tr'
		);
		const before = {
			row: await box( placeholderRow ),
			head: await box( placeholderHead ),
		};

		await held.shift().continue();
		await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );

		const after = {
			row: await box( surface.locator( 'tbody tr' ).first() ),
			head: await box( surface.locator( 'thead tr' ) ),
		};
		expect( after ).toEqual( before );
	} );

	test( 'keeps its rows, and holds its total, while it is sorted', async ( {
		page,
	} ) => {
		await page.goto( DATA );
		const surface = page.locator(
			'.corex-data__panel-body .corex-loadable'
		);
		await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
		const rows = await surface.locator( 'tbody tr' ).count();
		expect( rows ).toBeGreaterThan( 0 );

		const held = [];
		await page.route( isRecordRows, ( route ) => held.push( route ) );
		await surface.locator( '.corex-data__sort' ).first().click();

		await expect( surface ).toHaveAttribute(
			'data-corex-state',
			'refreshing'
		);
		await expect( surface.locator( 'tbody tr' ) ).toHaveCount( rows );
		await expect( surface.locator( '.corex-admin-skeleton' ) ).toHaveCount(
			0
		);
		await expect( page.locator( '.corex-data__metrics' ) ).toHaveAttribute(
			'data-corex-waiting',
			'true'
		);

		await held.shift().continue();

		await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
		await expect(
			page.locator( '.corex-data__metrics' )
		).not.toHaveAttribute( 'data-corex-waiting', 'true' );
	} );

	test( 'asks once for ten letters typed without a pause, and keeps the box', async ( {
		page,
	} ) => {
		await page.goto( DATA );
		await expect(
			page.locator( '.corex-data__panel-body .corex-loadable' )
		).toHaveAttribute( 'data-corex-state', 'ready' );

		let asked = 0;
		page.on( 'request', ( request ) => {
			if ( isRecordRows( new URL( request.url() ) ) ) {
				asked += 1;
			}
		} );

		const search = page.getByRole( 'textbox', { name: 'Search records' } );
		await search.pressSequentially( 'contact fo', { delay: 40 } );

		await expect(
			page.locator( '.corex-data__panel-body .corex-loadable' )
		).toHaveAttribute( 'data-corex-state', 'ready' );
		await page.waitForTimeout( 600 );

		// SC-004 allows two. Typed at this pace it is one.
		expect( asked ).toBe( 1 );
		await expect( search ).toBeFocused();
		await expect( search ).toHaveValue( 'contact fo' );
	} );

	test( 'opens a record on the press, and draws its fields as fields', async ( {
		page,
	} ) => {
		await page.goto( DATA );
		await expect(
			page.locator( '.corex-data__panel-body .corex-loadable' )
		).toHaveAttribute( 'data-corex-state', 'ready' );

		const held = [];
		await page.route(
			( url ) =>
				/corex\/v1\/data\/[a-z0-9_-]+\/\d+(\?|$)/.test(
					decodeURIComponent( url.href )
				),
			( route ) => held.push( route )
		);
		await page.getByRole( 'button', { name: 'View' } ).first().click();

		// It used to open when the record did: the press showed nothing.
		const dialog = page.getByRole( 'dialog', { name: 'Record detail' } );
		await expect( dialog ).toBeVisible();
		const surface = dialog.locator( '.corex-loadable' );
		await expect( surface ).toHaveAttribute(
			'data-corex-state',
			'loading'
		);
		await expect(
			dialog.getByText( 'This record has no readable fields.' )
		).toHaveCount( 0 );

		// The real route answers, untouched: this is the comparison between the screen and
		// the route that serves it which no test made, while the screen read the answer one
		// level too shallow and drew a record as a single field named "Record".
		await held.shift().continue();
		await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );

		const labels = await dialog
			.locator( '.corex-data__field dt' )
			.allTextContents();
		expect( labels.length ).toBeGreaterThan( 1 );
		expect( labels ).not.toContain( 'Record' );
		await expect(
			dialog.locator( '.corex-data__field dd' ).first()
		).not.toContainText( '{"' );
	} );

	test( 'does not say "No exports yet." before the export history has answered', async ( {
		page,
	} ) => {
		const held = [];
		await page.route(
			( url ) =>
				/corex\/v1\/data\/[a-z0-9_-]+\/exports(\?|$)/.test(
					decodeURIComponent( url.href )
				),
			( route ) =>
				route.request().method() === 'GET'
					? held.push( route )
					: route.continue()
		);
		await page.goto(
			'/wp-admin/admin.php?page=corex-data-models&tab=export'
		);

		const surface = page.locator(
			'.corex-data-models__workspace .corex-loadable'
		);
		await expect( surface ).toHaveAttribute(
			'data-corex-state',
			'loading'
		);
		await expect(
			surface.locator( '.corex-admin-skeleton li' )
		).toHaveCount( 3 );
		await expect( page.getByText( 'No exports yet.' ) ).toHaveCount( 0 );

		await held.shift().continue();

		await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
	} );
} );
