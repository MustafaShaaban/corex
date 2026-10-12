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

/**
 * The subject the reply box opens with: "Re: " and the name of the form the person filled in
 * (spec 106, FR-035), which `seedSubmission()` calls "Inbox E2E flow". It is also what the
 * box goes back to when a reply has gone. Whole, and not a pattern: every subject these
 * tests type begins "Re: " too, and a box that kept one would have matched.
 */
const STARTING_SUBJECT = 'Re: Inbox E2E flow';

/** Long enough for a browser to have said everything about the last key. */
const SETTLED = 400;

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
	await expect(
		pane.getByRole( 'textbox', { name: 'Message' } )
	).toHaveAttribute( 'aria-describedby', await notice.getAttribute( 'id' ) );

	// What somebody started is not lost while delivery is switched on; it still cannot be sent.
	await pane.getByLabel( 'Subject' ).fill( 'Re: your message' );
	await pane.getByRole( 'textbox', { name: 'Message' } ).click();
	await page.keyboard.type( 'Hello' );
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
	// A subject to start from is there, and is a place to start: nothing is kept of it.
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue(
		STARTING_SUBJECT
	);
	await expect( pane.locator( '.corex-pane__reply-draft' ) ).toHaveCount( 0 );

	await pane.getByLabel( 'Subject' ).fill( 'Re: your message' );
	await pane.getByRole( 'textbox', { name: 'Message' } ).click();
	await page.keyboard.type( 'Hello Salma,' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'We will call you tomorrow.' );
	await page.keyboard.press( 'Shift+Enter' );
	await page.keyboard.type( 'Regards' );
	// Entered by a click, not the keyboard: the first line is a paragraph all the same, and
	// is drawn at the size of the one after it.
	const written = pane.getByRole( 'textbox', { name: 'Message' } );
	await expect( written.locator( 'p' ) ).toHaveCount( 2 );
	const sizes = await written.evaluate( ( box ) =>
		[ box, ...box.querySelectorAll( 'p' ) ].map(
			( drawn ) => window.getComputedStyle( drawn ).fontSize
		)
	);
	expect( new Set( sizes ).size, `one size of text, got ${ sizes }` ).toBe(
		1
	);
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
	// The reply that went is now the email the next one answers: the box starts from it.
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue(
		'Re: your message'
	);
	await expect(
		pane.getByRole( 'radio', {
			name: 'In the conversation they already have',
		} )
	).toBeChecked();
	await expect( pane.getByRole( 'textbox', { name: 'Message' } ) ).toHaveText(
		''
	);

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

/**
 * The email a reply left as, read from where a development site keeps it.
 *
 * @param {import('@playwright/test').Page} page  A page showing the inbox.
 * @param {string}                          email Who the reply was for.
 * @return {Promise<{html_body:string,plain_text:string,subject:string}>} The kept email.
 */
function keptEmailFor( page, email ) {
	return page.evaluate( async ( recipient ) => {
		const config = window.corexSubmissions;
		const studio = await window.Corex.api.get(
			config.restUrl.replace( /\/submissions$/, '/email-studio' ),
			{ nonce: config.nonce }
		);

		return studio.envelope.data.captures.find( ( capture ) =>
			capture.to.includes( recipient )
		);
	}, email );
}

/**
 * What a mail client that is a browser draws of an email: read off the page.
 *
 * @param {import('@playwright/test').Page} letter A page holding the email.
 * @return {Promise<Object>} The measurements.
 */
function measuredEmail( letter ) {
	return letter.evaluate( () => {
		const colours = ( node ) => {
			let ground = node;
			while (
				ground &&
				window.getComputedStyle( ground ).backgroundColor ===
					'rgba(0, 0, 0, 0)'
			) {
				ground = ground.parentElement;
			}
			return [
				window.getComputedStyle( node ).color,
				window.getComputedStyle( ground || document.body )
					.backgroundColor,
			];
		};
		const box = ( node ) => node.getBoundingClientRect();
		const paragraphs = Array.from( document.querySelectorAll( 'p' ) );
		// The card is two bands of one box: the header, then the message.
		const [ header, card ] = document.querySelectorAll( 'td.cx-surface' );
		const footer = document.querySelector( 'td.cx-muted' );
		const edgeOf = ( paragraph ) => {
			const range = document.createRange();
			range.selectNodeContents( paragraph );
			const text = range.getBoundingClientRect();
			return {
				fromLeft: Math.round( text.left - box( card ).left ),
				fromRight: Math.round( box( card ).right - text.right ),
			};
		};

		return {
			sideways:
				document.documentElement.scrollWidth -
				document.documentElement.clientWidth,
			cardWidth: Math.round( box( card ).width ),
			fontSize: parseFloat(
				window.getComputedStyle( paragraphs[ 0 ] ).fontSize
			),
			header:
				header.textContent.trim() || header.querySelector( 'img' )?.alt,
			footer: footer.textContent.trim(),
			order: [ header, card, footer ].map( ( part ) =>
				Math.round( box( part ).top )
			),
			// What makes the header a band of the card and not a row above it: the card's
			// own ground, which is not the canvas's, under the brand's edge.
			grounds: [ header, card, document.body ].map(
				( part ) => window.getComputedStyle( part ).backgroundColor
			),
			edge: [
				window.getComputedStyle( header ).borderTopWidth,
				window.getComputedStyle( header ).borderTopColor,
				window.getComputedStyle( card ).borderTopWidth,
			],
			sides: [ header, card ].map(
				( part ) => window.getComputedStyle( part ).borderLeftColor
			),
			message: colours( paragraphs[ 0 ] ),
			link: colours( card.querySelector( 'a' ) ),
			foot: colours( footer ),
			head: colours( header ),
			english: edgeOf( paragraphs[ 0 ] ),
			arabic: edgeOf( paragraphs[ 1 ] ),
		};
	} );
}

test( 'sends the reply as a designed email that holds on a phone, in a dark mail client, and for an Arabic line', async ( {
	page,
	context,
} ) => {
	await openPaneOn( page, 'capture' );
	// Posted to the route as the editor of the next slice will post it: a link cannot be typed
	// into today's plain box, and the design has to be seen drawing one.
	const sent = await page.evaluate( async ( recipient ) => {
		const config = window.corexSubmissions;
		const list = await window.Corex.api.get(
			`${ config.restUrl }?search=${ encodeURIComponent( recipient ) }`,
			{ nonce: config.nonce }
		);
		const answer = await window.Corex.api.post(
			`${ config.restUrl }/${ list.envelope.data.items[ 0 ].id }/reply`,
			{
				subject: 'Re: your message',
				body:
					'<p>Hello Salma, thank you for writing to us about the offer.</p>' +
					'<p>مرحبا سلمى، سنتصل بك غدا.</p>' +
					'<p>See <a href="https://example.com/offer">the offer</a>.</p>',
			},
			{ nonce: config.nonce }
		);
		return answer.envelope.data.result.state;
	}, EMAIL );
	expect( sent ).toBe( 'captured' );

	const kept = await keptEmailFor( page, EMAIL );
	expect( kept.subject ).toBe( 'Re: your message' );
	// The same words beside the design, for a client that shows no HTML.
	expect( kept.plain_text ).toContain( 'Hello Salma, thank you' );
	expect( kept.plain_text ).toContain( 'مرحبا سلمى' );
	expect( kept.plain_text ).not.toContain( '<' );

	// What a mail client shows is the document alone, with nothing of the admin around it.
	const letter = await context.newPage();
	await letter.setContent( kept.html_body );

	for ( const width of [ 320, 375, 800 ] ) {
		for ( const colorScheme of [ 'light', 'dark' ] ) {
			await letter.setViewportSize( { width, height: 700 } );
			await letter.emulateMedia( { colorScheme } );
			const measured = await measuredEmail( letter );
			const where = `${ width }px ${ colorScheme }`;

			expect( measured.sideways, `${ where }: no sideways scroll` ).toBe(
				0
			);
			expect(
				measured.cardWidth,
				`${ where }: never wider than the measure`
			).toBeLessThanOrEqual( 600 );
			expect(
				measured.fontSize,
				`${ where }: text at a size that is read`
			).toBeGreaterThanOrEqual( 16 );
			expect( measured.header, `${ where }: the site is named` ).not.toBe(
				''
			);
			expect( measured.footer, `${ where }: the footer` ).toContain(
				'This message was sent by'
			);
			expect(
				measured.order,
				`${ where }: header, message, footer, in that order`
			).toEqual( [ ...measured.order ].sort( ( a, b ) => a - b ) );
			expect(
				measured.grounds[ 0 ],
				`${ where }: the header stands on the card's ground`
			).toBe( measured.grounds[ 1 ] );
			expect(
				measured.grounds[ 0 ],
				`${ where }: which is not the canvas`
			).not.toBe( measured.grounds[ 2 ] );
			expect(
				measured.edge[ 0 ],
				`${ where }: the brand's edge is the header's`
			).toBe( '4px' );
			expect(
				measured.edge[ 2 ],
				`${ where }: and nothing is drawn between the two bands but the header's own rule`
			).toBe( '0px' );
			expect(
				measured.sides[ 0 ],
				`${ where }: one border down both bands`
			).toBe( measured.sides[ 1 ] );
			for ( const part of [ 'message', 'link', 'foot', 'head' ] ) {
				expect(
					contrast( ...measured[ part ] ),
					`${ where }: the ${ part }'s contrast`
				).toBeGreaterThanOrEqual( 4.5 );
			}
			// Each paragraph starts from its own side: the English one from the left of
			// the card, the Arabic one from the right.
			expect(
				measured.english.fromLeft,
				`${ where }: the English line starts at the left`
			).toBeLessThan( measured.english.fromRight + 1 );
			expect(
				measured.arabic.fromRight,
				`${ where }: the Arabic line starts at the right`
			).toBeLessThan( measured.arabic.fromLeft );
		}
	}

	await letter.close();
} );

/**
 * The editor's toolbar and field, read off the page.
 *
 * @param {import('@playwright/test').Locator} pane The pane.
 * @return {Promise<Object>} The measurements.
 */
function measuredEditor( pane ) {
	return pane.evaluate( ( dialog ) => {
		const box = ( node ) => node.getBoundingClientRect();
		const editor = dialog.querySelector( '.corex-richtext' );
		const tools = Array.from(
			editor.querySelectorAll( '.corex-richtext__tool' )
		);
		const subject = dialog.querySelector(
			'.corex-pane__section input[type="text"]'
		);
		const ground = ( node ) => {
			let current = node;
			while (
				current &&
				window.getComputedStyle( current ).backgroundColor ===
					'rgba(0, 0, 0, 0)'
			) {
				current = current.parentElement;
			}
			return window.getComputedStyle( current ).backgroundColor;
		};

		return {
			sizes: tools.map( ( tool ) => [
				Math.round( box( tool ).width ),
				Math.round( box( tool ).height ),
			] ),
			gaps: tools
				.slice( 1 )
				.map( ( tool, index ) =>
					Math.round(
						Math.abs(
							box( tool ).left - box( tools[ index ] ).left
						) - box( tool ).width
					)
				),
			level: new Set(
				tools.map( ( tool ) => Math.round( box( tool ).top ) )
			).size,
			// The field sits where the field above it sits, edge for edge.
			edges: [
				Math.round( box( editor ).left - box( subject ).left ),
				Math.round( box( editor ).right - box( subject ).right ),
			],
			icon: [
				window.getComputedStyle( tools[ 1 ] ).color,
				ground( tools[ 1 ] ),
			],
			text: [
				window.getComputedStyle(
					editor.querySelector( '.corex-richtext__text' )
				).color,
				ground( editor.querySelector( '.corex-richtext__text' ) ),
			],
			firstToolFromStart: Math.round(
				window.getComputedStyle( dialog ).direction === 'rtl'
					? box( editor ).right - box( tools[ 0 ] ).right
					: box( tools[ 0 ] ).left - box( editor ).left
			),
			pageOverflows:
				document.documentElement.scrollWidth -
					document.documentElement.clientWidth >
				1,
		};
	} );
}

test( 'writes a formatted reply with the keyboard alone, previews the email, and sends what was previewed', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const pane = await openPaneOn( page, 'capture' );
	const message = pane.getByRole( 'textbox', { name: 'Message' } );
	const tool = ( name ) =>
		pane
			.getByRole( 'toolbar', { name: 'Formatting' } )
			.getByRole( 'button', {
				name,
				exact: true,
			} );

	await pane.getByLabel( 'Subject' ).fill( 'Re: your message' );
	await expect(
		pane.getByRole( 'button', { name: 'Preview' } )
	).toBeDisabled();

	// From the subject: the toolbar is one stop, the text the next.
	await pane.getByLabel( 'Subject' ).focus();
	await page.keyboard.press( 'Tab' );
	await expect( tool( 'Bold' ) ).toBeFocused();
	await page.keyboard.press( 'ArrowRight' );
	await expect( tool( 'Italic' ) ).toBeFocused();
	await page.keyboard.press( 'Tab' );
	await expect( message ).toBeFocused();

	await page.keyboard.type( 'Hello Salma,' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'We will ' );
	// A person pauses before a shortcut. Without the pause the button's state was set by
	// the last letter's change of selection arriving late, and not by the command.
	await page.waitForTimeout( SETTLED );
	await page.keyboard.press( 'Control+b' );
	await expect( tool( 'Bold' ) ).toHaveAttribute( 'aria-pressed', 'true' );
	await page.keyboard.type( 'call' );
	await page.waitForTimeout( SETTLED );
	await page.keyboard.press( 'Control+b' );
	await expect( tool( 'Bold' ) ).toHaveAttribute( 'aria-pressed', 'false' );
	await page.keyboard.type( ' you tomorrow.' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Control+Shift+8' );
	await expect( tool( 'Bulleted list' ) ).toHaveAttribute(
		'aria-pressed',
		'true'
	);
	await page.keyboard.type( 'By telephone' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'In the morning' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.type( 'The details: ' );
	await page.keyboard.press( 'Control+k' );
	const address = pane.getByRole( 'textbox', { name: 'Link address' } );
	await expect( address ).toBeFocused();
	await page.keyboard.type( 'javascript:alert(1)' );
	await page.keyboard.press( 'Enter' );
	await expect( pane.getByRole( 'alert' ) ).toContainText( 'web address' );
	await address.fill( 'example.com/offer' );
	await page.keyboard.press( 'Enter' );
	await expect( address ).toHaveCount( 0 );
	await expect( message ).toBeFocused();

	// What was written is what a reply has: paragraphs, emphasis, a list, a link.
	await expect( message.locator( 'p' ).first() ).toHaveText( 'Hello Salma,' );
	await expect( message.locator( 'b, strong' ) ).toHaveText( 'call' );
	await expect( message.locator( 'ul li' ) ).toHaveText( [
		'By telephone',
		'In the morning',
	] );
	await expect( message.locator( 'a' ) ).toHaveAttribute(
		'href',
		'https://example.com/offer'
	);

	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await message.scrollIntoViewIfNeeded();
		const measured = await measuredEditor( pane );
		const where = `${ theme } ${ direction }`;

		for ( const [ width, height ] of measured.sizes ) {
			// WCAG 2.2, 2.5.8: a target of at least 24 by 24.
			expect(
				width,
				`${ where }: a tool's width`
			).toBeGreaterThanOrEqual( 24 );
			expect( height, `${ where }: a tool is square` ).toBe( width );
		}
		expect(
			new Set( measured.gaps ).size,
			`${ where }: one distance between the tools, got ${ measured.gaps }`
		).toBe( 1 );
		expect( measured.level, `${ where }: the tools on one line` ).toBe( 1 );
		expect(
			measured.edges,
			`${ where }: the editor's edges are the subject field's`
		).toEqual( [ 0, 0 ] );
		expect(
			measured.firstToolFromStart,
			`${ where }: the toolbar starts where the line starts`
		).toBeLessThan( 12 );
		expect(
			contrast( ...measured.icon ),
			`${ where }: a tool's icon`
		).toBeGreaterThanOrEqual( 3 );
		expect(
			contrast( ...measured.text ),
			`${ where }: the text being written`
		).toBeGreaterThanOrEqual( 4.5 );
		expect( measured.pageOverflows, `${ where }: no sideways scroll` ).toBe(
			false
		);
	}
	await pinAppearance( page, 'dark', 'ltr' );

	// The preview is the email, built by the server from what is written now.
	await pane.getByRole( 'button', { name: 'Preview' } ).click();
	const preview = page.getByRole( 'dialog', {
		name: 'Preview of the reply',
	} );
	await expect( preview ).toBeVisible();
	const frame = preview.locator( 'iframe' );
	await expect( frame ).toHaveAttribute( 'sandbox', '' );
	const letter = preview.frameLocator( 'iframe' );
	await expect( letter.locator( 'p' ).first() ).toHaveText( 'Hello Salma,' );
	await expect( letter.locator( 'b, strong' ) ).toHaveText( 'call' );
	await expect( letter.locator( 'ul li' ) ).toHaveCount( 2 );
	await expect(
		letter.locator( 'td a[href="https://example.com/offer"]' )
	).toHaveText( 'example.com/offer' );
	const previewed = await frame.getAttribute( 'srcdoc' );

	const wide = ( await frame.boundingBox() ).width;
	await preview.getByRole( 'button', { name: 'Phone' } ).click();
	const narrow = ( await frame.boundingBox() ).width;
	expect( narrow, "the phone width is a phone's" ).toBeLessThanOrEqual( 360 );
	expect( wide, 'the wide width is wider' ).toBeGreaterThan( narrow );
	await expect(
		preview.getByRole( 'button', { name: 'Phone' } )
	).toHaveAttribute( 'aria-pressed', 'true' );

	// Leaving the preview leaves the pane, and what was written, as they were.
	await page.keyboard.press( 'Escape' );
	await expect( preview ).toHaveCount( 0 );
	await expect( pane ).toBeVisible();
	// And the keyboard is back on the button that opened it, not on the page.
	await expect(
		pane.getByRole( 'button', { name: 'Preview' } )
	).toBeFocused();
	await expect( message.locator( 'ul li' ) ).toHaveCount( 2 );

	await pane.getByRole( 'button', { name: 'Send reply' } ).click();
	await expect( pane.locator( '.corex-pane__reply' ) ).toHaveCount( 1 );
	await expect( message ).toHaveText( '' );

	// SC-002: the email that was kept is the one that was previewed, to the byte.
	const kept = await keptEmailFor( page, EMAIL );
	expect( kept.html_body ).toBe( previewed );
	expect( kept.plain_text ).toContain( '- By telephone\n- In the morning' );
	// Its text is its address, so the plain text says it once.
	expect( kept.plain_text ).toContain( 'The details: example.com/offer\n' );

	// And it is on the submission as it was written.
	const listed = pane.locator( '.corex-pane__reply-body' );
	await expect( listed.locator( 'li' ) ).toHaveCount( 2 );
	await expect( listed.locator( 'a' ) ).toHaveAttribute(
		'href',
		'https://example.com/offer'
	);

	// A paragraph reads in the direction of what is written in it, as it will in the email:
	// an Arabic line in an English reply starts from the right.
	// After a list too: a browser leaves what is written after one inside the paragraph the
	// list was started in, where a mark on the outermost blocks alone did not reach it.
	await message.click();
	await page.keyboard.type( 'Hello Salma,' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Control+Shift+8' );
	await page.keyboard.type( 'One' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.insertText( 'مرحبا سلمى، سنتصل بك غدا' );
	const directions = await message.evaluate( ( box ) =>
		Array.from( box.querySelectorAll( 'p' ) )
			.filter( ( paragraph ) => ! paragraph.querySelector( 'p, ul' ) )
			.map( ( paragraph ) => [
				paragraph.textContent.slice( 0, 5 ),
				window.getComputedStyle( paragraph ).direction,
			] )
	);
	expect( directions ).toEqual( [
		[ 'Hello', 'ltr' ],
		[ 'مرحبا', 'rtl' ],
	] );

	// What the browser offers by its own keys and a reply does not have is not drawn: the
	// box shows what will be sent.
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Control+u' );
	await page.keyboard.type( 'plain' );
	await expect( message ).toContainText( 'plain' );
	await expect( message.locator( 'u' ) ).toHaveCount( 0 );

	expect( errors ).toEqual( [] );
} );

// Spec 106, FR-006: what somebody began and did not send is theirs to come back to.
test( 'keeps a reply that was begun as a draft, gives it back on another visit, and discards it when asked', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	let pane = await openPaneOn( page, 'capture' );
	const row = page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first();
	const reopen = async () => {
		await page.keyboard.press( 'Escape' );
		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
		await row.getByRole( 'button' ).click();
		pane = page.getByRole( 'dialog' );
		await expect(
			pane.getByRole( 'heading', { name: 'Reply' } )
		).toBeVisible();
	};
	const draftLine = () => pane.locator( '.corex-pane__reply-draft' );

	await expect( draftLine() ).toHaveCount( 0 );
	// A subject is kept as it was typed, angle bracket and all.
	await pane.getByLabel( 'Subject' ).fill( 'Re: the offer <500 EGP' );
	await pane.getByRole( 'textbox', { name: 'Message' } ).click();
	await page.keyboard.type( 'Hello Salma,' );
	await page.keyboard.press( 'Enter' );
	await page.keyboard.press( 'Control+b' );
	await page.keyboard.type( 'tomorrow' );
	// Written down once writing pauses, and said in the page.
	await expect( draftLine() ).toContainText( 'Draft saved.' );

	// Left and come back to: where it was, formatting and all.
	await reopen();
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue(
		'Re: the offer <500 EGP'
	);
	const message = pane.getByRole( 'textbox', { name: 'Message' } );
	await expect( message.locator( 'p' ).first() ).toHaveText( 'Hello Salma,' );
	await expect( message.locator( 'b, strong' ) ).toHaveText( 'tomorrow' );
	await expect( draftLine() ).toContainText(
		'the reply you began earlier and did not send'
	);
	await expect(
		pane.getByRole( 'button', { name: 'Send reply' } )
	).toBeEnabled();

	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await draftLine().scrollIntoViewIfNeeded();
		const measured = await draftLine().evaluate( ( line ) => {
			const box = ( element ) => element.getBoundingClientRect();
			const actions = line.previousElementSibling;
			const said = line.querySelector( 'span' );
			const button = line.querySelector( 'button' );
			const colour = ( element ) =>
				window.getComputedStyle( element ).color;
			const ground = ( element ) => {
				let at = element;
				while ( at ) {
					const fill = window.getComputedStyle( at ).backgroundColor;
					if (
						fill &&
						! /rgba\(0, 0, 0, 0\)|transparent/.test( fill )
					) {
						return fill;
					}
					at = at.parentElement;
				}
				return 'rgb(255, 255, 255)';
			};

			return {
				within:
					box( line ).left >= box( actions ).left - 1 &&
					box( line ).right <= box( actions ).right + 1,
				below: box( line ).top - box( actions ).bottom,
				oneLine:
					Math.abs( box( said ).bottom - box( button ).bottom ) < 6,
				buttonHeight: box( button ).height,
				text: [ colour( said ), ground( said ) ],
				overflows:
					document.documentElement.scrollWidth >
					document.documentElement.clientWidth,
			};
		} );
		const where = `${ theme } ${ direction }`;

		expect( measured.within, `${ where }: inside the section` ).toBe(
			true
		);
		expect(
			measured.below,
			`${ where }: clear of the buttons above it`
		).toBeGreaterThanOrEqual( 8 );
		expect(
			measured.oneLine,
			`${ where }: its two parts on one line`
		).toBe( true );
		// WCAG 2.2, 2.5.8: a target of at least 24 pixels.
		expect(
			measured.buttonHeight,
			`${ where }: the discard button's height`
		).toBeGreaterThanOrEqual( 24 );
		expect(
			contrast( ...measured.text ),
			`${ where }: what the line says`
		).toBeGreaterThanOrEqual( 4.5 );
		expect( measured.overflows, `${ where }: no sideways scroll` ).toBe(
			false
		);
	}
	await pinAppearance( page, 'dark', 'ltr' );

	await pane.getByRole( 'button', { name: 'Discard draft' } ).click();
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue(
		STARTING_SUBJECT
	);
	await expect( message ).toHaveText( '' );
	await expect( draftLine() ).toHaveCount( 0 );
	await expect( pane.getByLabel( 'Subject' ) ).toBeFocused();

	// And it is gone for good, not only from this visit.
	await reopen();
	await expect( pane.getByLabel( 'Subject' ) ).toHaveValue(
		STARTING_SUBJECT
	);
	await expect( draftLine() ).toHaveCount( 0 );

	expect( errors ).toEqual( [] );
} );

// Spec 106, FR-032 and FR-033: on the first live site a square logo was drawn 180 by 180 on
// the canvas above the message. The suite's own site has no logo, so these two are asked for.
for ( const [ logo, what ] of [
	[ 'mark', 'a square mark with the site named beside it' ],
	[ 'name', 'a logo that is the name, alone' ],
] ) {
	test( `opens the email with ${ what }, held to its box at every width`, async ( {
		page,
		context,
	} ) => {
		await openPaneOn( page, 'capture' );
		await page.setExtraHTTPHeaders( {
			'X-Corex-E2E-Mail': 'capture',
			'X-Corex-E2E-Mail-Logo': logo,
		} );
		const subject = `Re: the header with ${ logo } ${ Date.now() }`;
		const html = await page.evaluate(
			async ( [ recipient, title ] ) => {
				const config = window.corexSubmissions;
				const list = await window.Corex.api.get(
					`${ config.restUrl }?search=${ encodeURIComponent( recipient ) }`,
					{ nonce: config.nonce }
				);
				await window.Corex.api.post(
					`${ config.restUrl }/${ list.envelope.data.items[ 0 ].id }/reply`,
					{ subject: title, body: '<p>Hello Salma.</p>' },
					{ nonce: config.nonce }
				);
				const studio = await window.Corex.api.get(
					config.restUrl.replace( /\/submissions$/, '/email-studio' ),
					{ nonce: config.nonce }
				);

				return studio.envelope.data.captures.find(
					( capture ) => capture.subject === title
				)?.html_body;
			},
			[ EMAIL, subject ]
		);
		expect( html, 'the reply was kept' ).toBeTruthy();

		const letter = await context.newPage();
		await letter.setContent( html, { waitUntil: 'load' } );
		// A right-to-left site writes its header from the other side: that is the markup's,
		// and is held where the markup is written, in the unit test.
		for ( const width of [ 320, 375, 800 ] ) {
			await letter.setViewportSize( { width, height: 600 } );
			const measured = await letter.evaluate( () => {
				const band = document.querySelector( 'td.cx-band' );
				const image = band.querySelector( 'img' );
				const box = ( node ) => node.getBoundingClientRect();
				const named = band.querySelector( 'td.cx-text' );

				return {
					loaded: image.naturalWidth > 0,
					logo: [ box( image ).width, box( image ).height ].map(
						Math.round
					),
					said: [
						image.getAttribute( 'width' ),
						image.getAttribute( 'height' ),
					].map( Number ),
					alt: image.alt,
					words: band.textContent.trim(),
					band: Math.round( box( band ).height ),
					// The name beside a mark: on its line, and clear of it.
					beside: named
						? [
								Math.round(
									Math.abs(
										( box( named ).top +
											box( named ).bottom ) /
											2 -
											( box( image ).top +
												box( image ).bottom ) /
												2
									)
								),
								Math.round(
									Math.min(
										Math.abs(
											box( named ).left -
												box( image ).right
										),
										Math.abs(
											box( image ).left -
												box( named ).right
										)
									)
								),
							]
						: null,
					inside:
						box( image ).left >= box( band ).left &&
						box( image ).right <= box( band ).right,
					sideways:
						document.documentElement.scrollWidth -
						document.documentElement.clientWidth,
				};
			} );
			const where = `${ logo } at ${ width }px`;

			expect( measured.loaded, `${ where }: the logo is drawn` ).toBe(
				true
			);
			expect(
				measured.logo[ 0 ],
				`${ where }: no wider than its box`
			).toBeLessThanOrEqual( 180 );
			expect(
				measured.logo[ 1 ],
				`${ where }: no taller than its box`
			).toBeLessThanOrEqual( 48 );
			// What a client that reads no style is given is what this one drew.
			expect(
				measured.said,
				`${ where }: its own size says the same`
			).toEqual( measured.logo );
			expect( measured.inside, `${ where }: inside the header` ).toBe(
				true
			);
			// The edge, the padding above and below, the box, the rule under it.
			expect(
				measured.band,
				`${ where }: the header is no taller than the box allows`
			).toBeLessThanOrEqual( 4 + 16 + 48 + 16 + 1 );
			expect( measured.sideways, `${ where }: no sideways scroll` ).toBe(
				0
			);
			if ( logo === 'mark' ) {
				// The name is text: there when images are not shown, and said once.
				expect(
					measured.words,
					`${ where }: the site is named`
				).not.toBe( '' );
				expect( measured.alt, `${ where }: and named once` ).toBe( '' );
				expect(
					measured.beside[ 0 ],
					`${ where }: the name is level with the mark`
				).toBeLessThanOrEqual( 2 );
				expect(
					measured.beside[ 1 ],
					`${ where }: and clear of it`
				).toBe( 12 );
			} else {
				expect(
					measured.words,
					`${ where }: the logo is the name`
				).toBe( '' );
				expect(
					measured.alt,
					`${ where }: which stands in for it`
				).not.toBe( '' );
			}
		}
	} );
}

// Spec 106, story 10: a reply joins the emails the person already has, or stands alone, and
// whoever writes it chooses. The first reply here is the only email this person has been
// sent, so it is a new email; the second is offered its conversation.
test( 'sends a second reply in the conversation the first began, or as a new email, as chosen', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const pane = await openPaneOn( page, 'capture' );
	const subject = pane.getByLabel( 'Subject' );
	const message = pane.getByRole( 'textbox', { name: 'Message' } );
	const choice = pane.getByRole( 'group', { name: 'How it is sent' } );
	const stamp = Date.now();
	const write = async ( words ) => {
		await message.click();
		await page.keyboard.type( words );
		await pane.getByRole( 'button', { name: 'Send reply' } ).click();
		await expect( message ).toHaveText( '' );
	};
	const headersOf = ( title ) =>
		page.evaluate( async ( wanted ) => {
			const config = window.corexSubmissions;
			const studio = await window.Corex.api.get(
				config.restUrl.replace( /\/submissions$/, '/email-studio' ),
				{ nonce: config.nonce }
			);

			return studio.envelope.data.captures.find(
				( capture ) => capture.subject === wanted
			)?.headers;
		}, title );

	// Nothing was sent to this person before: no choice, and the reason.
	await expect( choice ).toHaveCount( 0 );
	await expect( pane.locator( '.corex-pane__reply-way' ) ).toContainText(
		'This will be a new email'
	);
	const first = `About your enquiry ${ stamp }`;
	await subject.fill( first );
	await write( 'Hello Salma.' );

	// Now there is an email to answer: the choice is offered, the conversation chosen, and
	// the subject is the one that answers it.
	await expect( choice ).toBeVisible();
	await expect(
		choice.getByRole( 'radio', {
			name: 'In the conversation they already have',
		} )
	).toBeChecked();
	await expect( subject ).toHaveValue( `Re: ${ first }` );

	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await choice.scrollIntoViewIfNeeded();
		const measured = await choice.evaluate( ( group ) => {
			const box = ( node ) => node.getBoundingClientRect();
			const rows = Array.from( group.querySelectorAll( 'label' ) );
			const field = group.previousElementSibling.querySelector( 'input' );
			const rtl = window.getComputedStyle( group ).direction === 'rtl';
			const start = ( node ) =>
				rtl ? box( node ).right : box( node ).left;

			return {
				heights: rows.map( ( row ) => Math.round( box( row ).height ) ),
				// Each choice starts where the subject field does.
				starts: rows.map( ( row ) =>
					Math.round( Math.abs( start( row ) - start( field ) ) )
				),
				between: Math.round(
					box( rows[ 1 ] ).top - box( rows[ 0 ] ).bottom
				),
				radioToWords: rows.map( ( row ) => {
					const radio = row.querySelector( 'input' );
					const words = row.querySelector( 'span' );
					return Math.round(
						rtl
							? box( radio ).left - box( words ).right
							: box( words ).left - box( radio ).right
					);
				} ),
				level: rows.map( ( row ) => {
					const radio = row.querySelector( 'input' );
					const words = row.querySelector( 'span' );
					return Math.round(
						Math.abs(
							( box( radio ).top + box( radio ).bottom ) / 2 -
								( box( words ).top + box( words ).bottom ) / 2
						)
					);
				} ),
				border: window.getComputedStyle( group ).borderTopWidth,
				overflows:
					document.documentElement.scrollWidth >
					document.documentElement.clientWidth,
			};
		} );
		const where = `${ theme } ${ direction }`;

		for ( const height of measured.heights ) {
			// WCAG 2.2, 2.5.8: a target of at least 24 pixels.
			expect(
				height,
				`${ where }: a choice's height`
			).toBeGreaterThanOrEqual( 24 );
		}
		expect( measured.starts, `${ where }: in line with the field` ).toEqual(
			[ 0, 0 ]
		);
		expect(
			measured.between,
			`${ where }: the same distance between the choices as in each`
		).toBe( measured.radioToWords[ 0 ] );
		expect(
			new Set( measured.radioToWords ).size,
			`${ where }: one distance from a radio to its words`
		).toBe( 1 );
		for ( const offset of measured.level ) {
			expect(
				offset,
				`${ where }: a radio level with its words`
			).toBeLessThanOrEqual( 2 );
		}
		expect( measured.border, `${ where }: no box of the browser's` ).toBe(
			'0px'
		);
		expect( measured.overflows, `${ where }: no sideways scroll` ).toBe(
			false
		);
	}
	await pinAppearance( page, 'dark', 'ltr' );

	await write( 'And one more thing.' );
	// A new email, chosen though there is a conversation: the subject follows the choice.
	await choice.getByRole( 'radio', { name: 'As a new email' } ).check();
	await expect( subject ).toHaveValue( STARTING_SUBJECT );
	const third = `Something else ${ stamp }`;
	await subject.fill( third );
	await write( 'A separate matter.' );

	const [ began, joined, alone ] = [
		await headersOf( first ),
		await headersOf( `Re: ${ first }` ),
		await headersOf( third ),
	];
	// Each left under an id of its own.
	expect( began[ 'Message-ID' ] ).toMatch( /^<[0-9a-f]{20}@[^>\s]+>$/ );
	expect(
		new Set(
			[ began, joined, alone ].map( ( kept ) => kept[ 'Message-ID' ] )
		).size
	).toBe( 3 );
	expect( began[ 'In-Reply-To' ] ).toBeUndefined();
	// The second answers the first.
	expect( joined[ 'In-Reply-To' ] ).toBe( began[ 'Message-ID' ] );
	expect( joined.References ).toBe( began[ 'Message-ID' ] );
	// The third stands alone.
	expect( alone[ 'In-Reply-To' ] ).toBeUndefined();
	expect( alone.References ).toBeUndefined();

	// And each is listed with the way it went.
	await expect( pane.locator( '.corex-pane__reply' ) ).toHaveCount( 3 );
	const listed = await pane
		.locator( '.corex-pane__reply' )
		.evaluateAll( ( replies ) =>
			replies.map( ( reply ) =>
				reply.textContent.includes( 'In the conversation' )
					? 'conversation'
					: 'new'
			)
		);
	expect( listed ).toEqual( [ 'new', 'conversation', 'new' ] );

	expect( errors ).toEqual( [] );
} );

// Spec 106, FR-022. A reply that did not go is on the submission as one that did not go, and
// whoever opens it next can send it again as it was written.
test( 'sends a reply that did not go again, from where it is listed, and adds the sending to it', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	// A site that delivers, whose mail server takes nothing: nothing reaches a mailer.
	const pane = await openPaneOn( page, 'fail' );
	await pane.getByLabel( 'Subject' ).fill( 'Re: your kitchen' );
	await pane.getByRole( 'textbox', { name: 'Message' } ).click();
	await page.keyboard.type( 'We can come on Tuesday.' );
	await pane.getByRole( 'button', { name: 'Send reply' } ).click();

	await expect( pane ).toContainText( 'The reply was not sent.' );
	const reply = pane.locator( '.corex-pane__reply' );
	await expect( reply ).toHaveCount( 1 );
	await expect( reply ).toContainText( 'Not sent' );
	const again = reply.getByRole( 'button', { name: 'Send again' } );
	await expect( again ).toBeVisible();

	// Somebody else, later, on a site that now keeps its email. What was left in the box
	// is their colleague's draft, not theirs; they open the submission and find the reply.
	await page.setExtraHTTPHeaders( { 'X-Corex-E2E-Mail': 'capture' } );
	await page.reload();
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	await page.getByLabel( 'Search' ).fill( EMAIL );
	await page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first()
		.getByRole( 'button' )
		.click();
	const later = page.getByRole( 'dialog' );
	const listed = later.locator( '.corex-pane__reply' );
	const button = listed.getByRole( 'button', { name: 'Send again' } );
	await expect( listed ).toContainText( 'Not sent' );
	await expect( listed ).not.toContainText( 'Tried 2 times' );

	// Where it stands, before it is pressed: in the reply's own card, apart from its words,
	// at the end of its row, tall enough to press.
	for ( const [ theme, direction ] of APPEARANCES ) {
		await pinAppearance( page, theme, direction );
		await button.scrollIntoViewIfNeeded();
		const measured = await listed.evaluate( ( card ) => {
			const box = ( node ) => node.getBoundingClientRect();
			const pressed = card.querySelector( '.corex-pane__actions button' );
			const row = pressed.parentElement;
			const before = row.previousElementSibling;
			const inside = window.getComputedStyle( card );
			const rtl = window.getComputedStyle( card ).direction === 'rtl';
			const edge = rtl
				? box( pressed ).left -
					box( card ).left -
					parseFloat( inside.paddingLeft ) -
					parseFloat( inside.borderLeftWidth )
				: box( card ).right -
					parseFloat( inside.paddingRight ) -
					parseFloat( inside.borderRightWidth ) -
					box( pressed ).right;

			return {
				above: Math.round( box( pressed ).top - box( before ).bottom ),
				fromTheEnd: Math.round( edge ),
				height: Math.round( box( pressed ).height ),
				below: Math.round(
					box( card ).bottom -
						parseFloat( inside.paddingBottom ) -
						parseFloat( inside.borderBottomWidth ) -
						box( pressed ).bottom
				),
				pastTheCard:
					box( pressed ).left < box( card ).left ||
					box( pressed ).right > box( card ).right,
			};
		} );
		const where = `${ theme } ${ direction }`;

		expect(
			measured.above,
			`${ where }: apart from the reply's words`
		).toBeGreaterThanOrEqual( 8 );
		expect( measured.fromTheEnd, `${ where }: at the end of its row` ).toBe(
			0
		);
		expect( measured.below, `${ where }: on the card's own padding` ).toBe(
			0
		);
		expect(
			measured.height,
			`${ where }: tall enough to press`
		).toBeGreaterThanOrEqual( 30 );
		expect( measured.pastTheCard, `${ where }: inside the card` ).toBe(
			false
		);
	}
	await pinAppearance( page, 'dark', 'ltr' );

	// By the keyboard: it is a button like any other.
	await button.focus();
	await page.keyboard.press( 'Enter' );

	await expect( later.getByRole( 'status' ) ).toContainText(
		`Reply kept on this site, not delivered to ${ EMAIL }.`
	);
	// One reply still, tried twice, and nothing left to send again.
	await expect( listed ).toHaveCount( 1 );
	await expect( listed ).toContainText( 'Kept, not delivered' );
	await expect( listed ).toContainText( 'Tried 2 times' );
	await expect( listed ).toContainText( 'We can come on Tuesday.' );
	await expect( button ).toHaveCount( 0 );
	// Its button went with it: the keyboard is on the subject, not on the page.
	await expect( later.getByLabel( 'Subject' ) ).toBeFocused();

	// What went is what was kept the first time.
	const kept = await keptEmailFor( page, EMAIL );
	expect( kept.subject ).toBe( 'Re: your kitchen' );
	expect( kept.html_body ).toContain( 'We can come on Tuesday.' );

	await later.locator( 'summary', { hasText: 'History' } ).click();
	await expect( later ).toContainText( 'Reply could not be sent' );

	expect( errors ).toEqual( [] );
} );
