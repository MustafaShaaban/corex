/**
 * Replying from the submissions inbox (spec 106, slice 1; T015).
 *
 * What a person at the reply box is told: before typing, whether a reply from this site reaches
 * anybody and where that is changed; after sending, what happened to theirs; and, for whoever
 * opens the submission next, what was already answered.
 *
 * The site's mail settings are answered for these requests by the fixture
 * `corex-e2e-mail-delivery.php`, so nothing here is delivered to a mailbox and neither the CI
 * site nor a development install decides what is asserted.
 */

const { test, expect } = require( '@playwright/test' );
const {
	collectConsoleErrors,
	contrast,
	pinAppearance,
	seedSubmission,
} = require( './helpers' );

// A flow of this spec's own. Another spec's slug is another spec's to create: two files run at
// once, each found the inbox spec's flow missing on a fresh site, and the second to create it
// was refused.
const FLOW_SLUG = 'corex-reply-e2e';
// Unique per test, so the seeded submission is one row on a site where earlier runs, and the
// test before this one, left theirs.
let EMAIL = '';
test.beforeEach( () => {
	EMAIL = `corex-reply-e2e-${ Date.now() }@example.com`;
} );

const APPEARANCES = [
	[ 'dark', 'ltr' ],
	[ 'light', 'ltr' ],
	[ 'dark', 'rtl' ],
	[ 'light', 'rtl' ],
];

/**
 * Opens the seeded submission's pane on a site that treats email as `mode` says.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string}                          mode `capture` or `refuse`.
 * @return {Promise<import('@playwright/test').Locator>} The pane.
 */
async function openPaneOn( page, mode ) {
	const seeded = await seedSubmission( page, FLOW_SLUG, EMAIL );
	expect( seeded.real.envelope.ok ).toBe( true );
	await page.setExtraHTTPHeaders( { 'X-Corex-E2E-Mail': mode } );
	await page.setViewportSize( { width: 1280, height: 900 } );
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	await page.getByLabel( 'Search' ).fill( EMAIL );
	const row = page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first();
	await expect( row ).toBeVisible();
	await row.getByRole( 'button' ).click();
	const pane = page.getByRole( 'dialog' );
	await expect(
		pane.getByRole( 'heading', { name: 'Reply' } )
	).toBeVisible();

	return pane;
}

/**
 * The Reply section's parts, read off the page: the distance between each and the next, whether
 * any reaches past the section, and the notice's edge.
 *
 * @param {import('@playwright/test').Locator} pane The pane.
 * @return {Promise<Object>} The measurements.
 */
function measuredReply( pane ) {
	return pane.evaluate( ( dialog ) => {
		const box = ( node ) => node.getBoundingClientRect();
		const section = dialog
			.querySelector( '.corex-pane__reply-notice, .corex-pane__replies' )
			.closest( '.corex-pane__section' );
		// The parts that are drawn in the section's flow: the region a result is announced
		// from is read, not seen, and takes no place among them.
		const parts = Array.from( section.children ).filter(
			( part ) => window.getComputedStyle( part ).position !== 'absolute'
		);
		const notice = section.querySelector( '.corex-pane__reply-notice' );
		const edge = notice ? window.getComputedStyle( notice ) : null;
		const reply = section.querySelector( '.corex-pane__reply' );
		const colours = ( node ) => {
			const style = window.getComputedStyle( node );
			let ground = node;
			while (
				ground &&
				window.getComputedStyle( ground ).backgroundColor ===
					'rgba(0, 0, 0, 0)'
			) {
				ground = ground.parentElement;
			}
			return [
				style.color,
				window.getComputedStyle( ground ).backgroundColor,
			];
		};

		return {
			gaps: parts
				.slice( 1 )
				.map( ( part, index ) =>
					Math.round( box( part ).top - box( parts[ index ] ).bottom )
				),
			escapes: parts.filter(
				( part ) =>
					box( part ).left < box( section ).left - 1 ||
					box( part ).right > box( section ).right + 1
			).length,
			noticeStart: edge
				? parseFloat( edge.borderInlineStartWidth )
				: null,
			noticeEnd: edge ? parseFloat( edge.borderInlineEndWidth ) : null,
			noticeColours: notice
				? colours( notice.querySelector( 'p' ) )
				: null,
			linkColours:
				notice && notice.querySelector( 'a' )
					? colours( notice.querySelector( 'a' ) )
					: null,
			replyInner: reply
				? Array.from( reply.children )
						.slice( 1 )
						.map( ( part, index ) =>
							Math.round(
								box( part ).top -
									box( reply.children[ index ] ).bottom
							)
						)
				: [],
			replyColours: reply
				? colours( reply.querySelector( '.corex-pane__reply-body p' ) )
				: null,
			stateColours: reply
				? colours(
						reply.querySelector(
							'.corex-inbox__delivery span + span'
						)
					)
				: null,
			metaColours: reply
				? colours( reply.querySelector( '.corex-pane__reply-meta' ) )
				: null,
			paragraphs: reply
				? reply.querySelectorAll( '.corex-pane__reply-body p' ).length
				: 0,
			pageOverflows:
				document.documentElement.scrollWidth -
					document.documentElement.clientWidth >
				1,
		};
	} );
}

test( 'says before anything is typed that replies are not being delivered, and where that is changed', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const pane = await openPaneOn( page, 'refuse' );
	const notice = pane.locator( '.corex-pane__reply-notice' );
	const send = pane.getByRole( 'button', { name: 'Send reply' } );

	await expect( notice ).toContainText(
		'Replies are not being delivered: live delivery is switched off'
	);
	await expect(
		notice.getByRole( 'link', { name: 'Open Mail settings' } )
	).toHaveAttribute( 'href', /page=corex-settings-config&corex_tab=mail/ );
	await expect( send ).toBeDisabled();
	// A button that is off is not reached by keyboard, so the fields carry the reason.
	await expect( pane.getByLabel( 'Message' ) ).toHaveAttribute(
		'aria-describedby',
		await notice.getAttribute( 'id' )
	);

	// What somebody started is not lost while delivery is switched on; it still cannot be sent.
	await pane.getByLabel( 'Subject' ).fill( 'Re: your message' );
	await pane.getByLabel( 'Message' ).fill( 'Hello' );
	await expect( send ).toBeDisabled();

	// The server gives the same answer to anything that posts to the route anyway.
	const refused = await page.evaluate( async () => {
		const config = window.corexSubmissions;
		const list = await window.Corex.api.get(
			`${ config.restUrl }?search=${ encodeURIComponent(
				document.querySelector( '.corex-pane__contact a' ).textContent
			) }`,
			{ nonce: config.nonce }
		);
		const id = list.envelope.data.items[ 0 ].id;
		const answer = await window.Corex.api.post(
			`${ config.restUrl }/${ id }/reply`,
			{ subject: 'Re: your message', body: 'Hello' },
			{ nonce: config.nonce }
		);
		const after = await window.Corex.api.get(
			`${ config.restUrl }/${ id }`,
			{ nonce: config.nonce }
		);
		return {
			status: answer.status,
			message: answer.envelope.message,
			kept: after.envelope.data.submission.replies.length,
		};
	} );
	expect( refused.status ).toBe( 422 );
	expect( refused.message ).toContain( 'live delivery is switched off' );
	expect( refused.kept, 'a refused reply is not on record as sent' ).toBe(
		0
	);

	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await notice.scrollIntoViewIfNeeded();
		const measured = await measuredReply( pane );
		const where = `${ theme } ${ direction }`;

		// The section's own distance between its parts, with none of it a paragraph's margin.
		expect(
			new Set( measured.gaps ).size,
			`${ where }: one distance between the section's parts, got ${ measured.gaps }`
		).toBe( 1 );
		expect( measured.escapes, `${ where }: nothing past the section` ).toBe(
			0
		);
		expect(
			measured.noticeStart,
			`${ where }: the notice's mark is on its starting edge`
		).toBeGreaterThan( measured.noticeEnd );
		expect(
			contrast( ...measured.noticeColours ),
			`${ where }: the notice's text`
		).toBeGreaterThanOrEqual( 4.5 );
		expect(
			contrast( ...measured.linkColours ),
			`${ where }: the notice's link`
		).toBeGreaterThanOrEqual( 4.5 );
		expect( measured.pageOverflows, `${ where }: no sideways scroll` ).toBe(
			false
		);
	}

	// The browser reports the refusal this test asked the route for; nothing else is expected.
	expect(
		errors.filter( ( error ) => ! /status of 422.*\/reply/.test( error ) )
	).toEqual( [] );
} );

test( 'sends a reply on a site that keeps its email, says what happened, and lists it on the submission', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const pane = await openPaneOn( page, 'capture' );
	const notice = pane.locator( '.corex-pane__reply-notice' );

	await expect( notice ).toContainText( 'This is a development site.' );
	await expect( notice ).toContainText( 'is not delivered' );
	await expect( pane.locator( '.corex-pane__reply' ) ).toHaveCount( 0 );

	await pane.getByLabel( 'Subject' ).fill( 'Re: your message' );
	await pane
		.getByLabel( 'Message' )
		.fill( 'Hello Salma,\n\nWe will call you tomorrow.\nRegards' );
	await pane.getByRole( 'button', { name: 'Send reply' } ).click();

	// What happened, in words, where the button was pressed.
	const said = pane.locator( '.corex-pane__reply-outcome' );
	await expect( said ).toContainText(
		`Reply kept on this site, not delivered to ${ EMAIL }.`
	);
	await expect( pane.getByRole( 'status' ) ).toContainText(
		'Reply kept on this site, not delivered'
	);
	await expect( pane.getByLabel( 'Subject' ) ).toBeFocused();
	await expect( pane ).not.toContainText( 'Reply processed by Email Studio' );
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue( '' );
	await expect( pane.getByLabel( 'Message' ) ).toHaveValue( '' );

	// And what was said, on the submission.
	const reply = pane.locator( '.corex-pane__reply' );
	await expect( reply ).toHaveCount( 1 );
	await expect( reply ).toContainText( 'Re: your message' );
	await expect( reply ).toContainText( 'Kept, not delivered' );
	await expect( reply.locator( '.corex-pane__reply-body' ) ).toContainText(
		'We will call you tomorrow.'
	);
	await expect(
		reply.locator( '.corex-pane__reply-meta time' )
	).toBeVisible();

	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await reply.scrollIntoViewIfNeeded();
		const measured = await measuredReply( pane );
		const where = `${ theme } ${ direction }`;

		expect(
			measured.paragraphs,
			`${ where }: two typed paragraphs are two`
		).toBe( 2 );
		expect(
			new Set( measured.gaps ).size,
			`${ where }: one distance between the section's parts, got ${ measured.gaps }`
		).toBe( 1 );
		expect( measured.escapes, `${ where }: nothing past the section` ).toBe(
			0
		);
		for ( const [ what, colours ] of [
			[ 'the reply', measured.replyColours ],
			[ 'its state', measured.stateColours ],
			[ 'who and when', measured.metaColours ],
		] ) {
			expect(
				contrast( ...colours ),
				`${ where }: ${ what }`
			).toBeGreaterThanOrEqual( 4.5 );
		}
		expect( measured.pageOverflows, `${ where }: no sideways scroll` ).toBe(
			false
		);
	}

	// Somebody opening the submission afterwards reads it too.
	await pinAppearance( page, 'dark', 'ltr' );
	await page.keyboard.press( 'Escape' );
	await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
	await page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first()
		.getByRole( 'button' )
		.click();
	const again = page.getByRole( 'dialog' );
	await expect( again.locator( '.corex-pane__reply' ) ).toContainText(
		'Re: your message'
	);
	await again.locator( 'summary', { hasText: 'History' } ).click();
	await expect( again ).toContainText(
		'Reply kept on this site, not delivered'
	);

	expect( errors ).toEqual( [] );
} );
