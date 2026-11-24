/**
 * Spec 136, slice 5 (User Story 4, FR-030 to FR-032, FR-039): the accordion as a browser draws it,
 * TC-BLK-02-01 to 06 as far as one site reaches (06 on Perego's database is the owner's).
 *
 * A published page holds two accordions: the first with "Open one at a time" on and its questions
 * as headings 3, its first answer holding a paragraph, a list, an image and a button; the second
 * with the defaults. A second page holds an accordion saved before this slice (its questions and
 * answers in `items`, a self-closing block). A draft holds the same old accordion for the editor.
 *
 * - TC-BLK-02-01: the list, the image and the button render inside the answer, and show when it opens;
 * - TC-BLK-02-02 and 03: with the setting on, opening a question closes the open one, with scripts on
 *   and off (the `name` of `details` does it); with it off, two stay open;
 * - TC-BLK-02-04: the chevron points forward closed and down open, the browser's triangle is not
 *   drawn, and in Arabic, right to left, the closed chevron points the other way and sits at the
 *   other end; under reduced motion it turns without a transition;
 * - TC-BLK-02-05: with scripts off, Tab reaches a question, Enter opens it and Space closes it, and
 *   Chromium's own accessibility tree (read over CDP, not Playwright's computed one) reports the
 *   question expanded, then collapsed, with a heading of level 3 inside it (FR-032);
 * - TC-BLK-02-06: the old accordion keeps its questions and answers on the page; in the editor it
 *   is migrated to accordion items holding paragraphs, with no warning, and a question is typed in
 *   place.
 *
 * The spec makes the pages and the draft and removes them. ENVIRONMENT-GATED: needs the site at
 * COREX_BASE_URL.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

const VISITOR = { cookies: [], origins: [] };
const DESKTOP = { width: 1280, height: 900 };

const comment = ( name, attributes ) =>
	`wp:${ name }${ attributes ? ` ${ JSON.stringify( attributes ) }` : '' }`;
const block = ( name, attributes, inner ) =>
	`<!-- ${ comment( name, attributes ) } -->${ inner }<!-- /wp:${ name } -->`;
const paragraph = ( text ) => block( 'paragraph', null, `<p>${ text }</p>` );
const item = ( question, answer ) =>
	block( 'corex/accordion-item', { question }, answer );

const RICH_ANSWER = [
	paragraph( 'Three ways in.' ),
	block(
		'list',
		null,
		`<ul class="wp-block-list">${ block(
			'list-item',
			null,
			'<li>Read the guide</li>'
		) }</ul>`
	),
	block(
		'image',
		null,
		'<figure class="wp-block-image"><img src="/wp-includes/images/w-logo-blue.png" alt="The WordPress mark"/></figure>'
	),
	block(
		'buttons',
		null,
		`<div class="wp-block-buttons">${ block(
			'button',
			null,
			'<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/">Start here</a></div>'
		) }</div>`
	),
].join( '' );

const PAGE = [
	block(
		'corex/accordion',
		{ oneOpen: true, headingLevel: 3, className: 'cx136-one' },
		item( 'How do I start?', RICH_ANSWER ) +
			item( 'Is it free?', paragraph( 'Yes, it is free.' ) ) +
			item( 'Where is the code?', paragraph( 'In one repository.' ) )
	),
	block(
		'corex/accordion',
		{ className: 'cx136-many' },
		item( 'First of many?', paragraph( 'The first answer.' ) ) +
			item( 'Second of many?', paragraph( 'The second answer.' ) )
	),
].join( '' );

/** An accordion as a page saved before this slice holds it. */
const OLD = `<!-- wp:corex/accordion ${ JSON.stringify( {
	items: [
		{ title: 'What is Corex?', content: 'A WordPress framework.' },
		{ title: 'Does it need a script?', content: 'No, it never does.' },
	],
} ) } /-->`;

const made = { page: null, old: null, draft: null };

async function adminSession( browser ) {
	// The test beside it may run with scripts off; this session reads the REST nonce, so it keeps them.
	const context = await browser.newContext( {
		storageState: STORAGE_STATE,
		javaScriptEnabled: true,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const { nonce, root } = await page.evaluate( () => window.wpApiSettings );
	// WordPress's own address for a route: `/wp-json/` or, under plain permalinks, `?rest_route=`.
	const route = ( path, query = '' ) =>
		`${ root }${ path }${ query && ( root.includes( '?' ) ? '&' : '?' ) }${ query }`;

	return { context, page, nonce, route };
}

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce, route } = await adminSession( browser );
	const create = async ( type, data ) =>
		(
			await page.request.post( route( `wp/v2/${ type }` ), {
				headers: { 'X-WP-Nonce': nonce },
				data,
			} )
		).json();
	made.page = await create( 'pages', {
		title: 'cx136-accordion',
		status: 'publish',
		content: PAGE,
	} );
	made.old = await create( 'pages', {
		title: 'cx136-accordion-old',
		status: 'publish',
		content: OLD,
	} );
	made.draft = await create( 'posts', {
		title: 'cx136 old accordion in the editor',
		status: 'draft',
		content: OLD,
	} );
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	const { context, page, nonce, route } = await adminSession( browser );
	for ( const [ type, entry ] of [
		[ 'pages', made.page ],
		[ 'pages', made.old ],
		[ 'posts', made.draft ],
	] ) {
		if ( entry?.id ) {
			await page.request.delete(
				route( `wp/v2/${ type }/${ entry.id }`, 'force=true' ),
				{ headers: { 'X-WP-Nonce': nonce } }
			);
		}
	}
	await context.close();
} );

/**
 * A visitor's page, in English or in Arabic (right to left).
 *
 * @param {import('@playwright/test').Browser} browser           The browser.
 * @param {Object}                             options
 * @param {string}                             options.url       The page.
 * @param {boolean}                            [options.scripts] Whether scripts run.
 * @param {boolean}                            [options.arabic]  Whether the request is in Arabic.
 * @param {string}                             [options.baseURL] The site, for the locale cookie.
 * @return {Promise<{context: import('@playwright/test').BrowserContext, page: import('@playwright/test').Page}>} The page, loaded.
 */
async function visit(
	browser,
	{ url, scripts = true, arabic = false, baseURL }
) {
	const context = await browser.newContext( {
		storageState: VISITOR,
		javaScriptEnabled: scripts,
		viewport: DESKTOP,
	} );
	if ( arabic ) {
		await context.addCookies( [
			{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
		] );
	}
	const page = await context.newPage();
	await page.goto( url );

	return { context, page };
}

/**
 * @param {import('@playwright/test').Locator} items The accordion's items.
 * @return {Promise<Array<boolean>>} Whether each is open.
 */
const openStates = ( items ) =>
	items.evaluateAll( ( all ) => all.map( ( details ) => details.open ) );

/**
 * The chevron's turn, from its computed transform: the sine of its angle (-1 a quarter turn back,
 * 1 a quarter turn on, 0 none), and its transition.
 *
 * @param {import('@playwright/test').Locator} icon The chevron.
 * @return {Promise<{sine: number, transition: string}>} Its turn.
 */
function turnOf( icon ) {
	return icon.evaluate( ( svg ) => {
		const style = window.getComputedStyle( svg );
		const matrix =
			style.transform === 'none'
				? [ 1, 0 ]
				: style.transform
						.replace( /^matrix\(|\)$/g, '' )
						.split( ',' )
						.map( Number );

		return {
			sine: Math.round( matrix[ 1 ] ),
			transition: style.transitionDuration,
		};
	} );
}

/**
 * Chromium's own accessibility tree for the page: every node, the ignored ones too, since a node's
 * parent may be one (a `div` the tree leaves out).
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Array<Object>>} Its nodes.
 */
async function accessibilityTree( page ) {
	const cdp = await page.context().newCDPSession( page );
	await cdp.send( 'Accessibility.enable' );
	const { nodes } = await cdp.send( 'Accessibility.getFullAXTree' );
	await cdp.detach();

	return nodes;
}

const propertyOf = ( node, name ) =>
	node.properties?.find( ( property ) => property.name === name )?.value
		?.value;

for ( const scripts of [ true, false ] ) {
	const state = scripts ? 'scripts on' : 'scripts off';

	test( `TC-BLK-02-01: an answer holds a list, an image and a button, ${ state }`, async ( {
		browser,
	} ) => {
		const { context, page } = await visit( browser, {
			url: made.page.link,
			scripts,
		} );
		const first = page
			.locator( '.cx136-one .corex-accordion__item' )
			.first();
		const answer = first.locator( '.corex-accordion__content' );

		await expect( answer.locator( 'li' ) ).toHaveText( 'Read the guide' );
		await expect( answer.locator( 'img' ) ).toHaveAttribute(
			'alt',
			'The WordPress mark'
		);
		await expect( answer.locator( 'a.wp-block-button__link' ) ).toHaveText(
			'Start here'
		);
		await expect( answer.locator( 'li' ) ).toBeHidden();

		await first.locator( 'summary' ).click();
		await expect( answer.locator( 'li' ) ).toBeVisible();
		await expect( answer.locator( 'img' ) ).toBeVisible();
		await expect(
			answer.locator( 'a.wp-block-button__link' )
		).toBeVisible();
		await context.close();
	} );

	test( `TC-BLK-02-02, TC-BLK-02-03: one at a time when it is on, several when it is off, ${ state }`, async ( {
		browser,
	} ) => {
		const { context, page } = await visit( browser, {
			url: made.page.link,
			scripts,
		} );
		const one = page.locator( '.cx136-one .corex-accordion__item' );
		const many = page.locator( '.cx136-many .corex-accordion__item' );

		expect( await openStates( one ) ).toEqual( [ false, false, false ] );
		await one.nth( 0 ).locator( 'summary' ).click();
		expect( await openStates( one ) ).toEqual( [ true, false, false ] );
		await one.nth( 1 ).locator( 'summary' ).click();
		expect( await openStates( one ) ).toEqual( [ false, true, false ] );

		await many.nth( 0 ).locator( 'summary' ).click();
		await many.nth( 1 ).locator( 'summary' ).click();
		expect( await openStates( many ) ).toEqual( [ true, true ] );
		// The first accordion's open item is not closed by the second accordion.
		expect( await openStates( one ) ).toEqual( [ false, true, false ] );
		await context.close();
	} );
}

for ( const arabic of [ false, true ] ) {
	const language = arabic ? 'in Arabic, right to left' : 'in English';

	test( `TC-BLK-02-04: the chevron turns when open, with no browser triangle, ${ language }`, async ( {
		browser,
		baseURL,
	} ) => {
		const { context, page } = await visit( browser, {
			url: made.page.link,
			arabic,
			baseURL,
		} );
		expect(
			await page
				.locator( 'html' )
				.evaluate(
					( html ) => window.getComputedStyle( html ).direction
				)
		).toBe( arabic ? 'rtl' : 'ltr' );
		const first = page
			.locator( '.cx136-many .corex-accordion__item' )
			.first();
		const summary = first.locator( 'summary' );
		const icon = first.locator( '.corex-accordion__icon' );
		const question = first.locator( '.corex-accordion__question' );

		// No triangle: the summary is no list item and has no list marker.
		expect(
			await summary.evaluate( ( element ) => {
				const style = window.getComputedStyle( element );

				return [ style.display, style.listStyleType ];
			} )
		).toEqual( [ 'flex', 'none' ] );

		// Closed, the chevron points forward: a quarter turn back from down in English (to the
		// right), a quarter turn on in Arabic (to the left); it sits at the end of the line.
		expect( ( await turnOf( icon ) ).sine ).toBe( arabic ? 1 : -1 );
		const iconBox = await icon.boundingBox();
		const questionBox = await question.boundingBox();
		expect(
			arabic
				? iconBox.x < questionBox.x
				: iconBox.x > questionBox.x + questionBox.width
		).toBe( true );

		// Open, it points down in both, after its transition.
		await summary.click();
		await expect
			.poll( async () => ( await turnOf( icon ) ).sine )
			.toBe( 0 );
		await context.close();
	} );
}

test( 'FR-032: under reduced motion the chevron turns without a transition', async ( {
	browser,
} ) => {
	const { context, page } = await visit( browser, { url: made.page.link } );
	const icon = page.locator( '.cx136-many .corex-accordion__icon' ).first();

	expect( ( await turnOf( icon ) ).transition ).not.toBe( '0s' );
	await page.emulateMedia( { reducedMotion: 'reduce' } );
	expect( ( await turnOf( icon ) ).transition ).toBe( '0s' );
	await context.close();
} );

test( 'TC-BLK-02-05, FR-032: scripts off, the keyboard opens and closes a question, a heading the tree reports expanded then collapsed', async ( {
	browser,
	browserName,
} ) => {
	test.skip(
		browserName !== 'chromium',
		"Chromium's own accessibility tree is read over CDP"
	);
	const { context, page } = await visit( browser, {
		url: made.page.link,
		scripts: false,
	} );
	const first = page.locator( '.cx136-one .corex-accordion__item' ).first();
	const summary = first.locator( 'summary' );

	// Tab, from the top of the page, until the first question has the focus.
	let presses = 0;
	while (
		( await page
			.locator( '.cx136-one summary.corex-accordion__summary:focus' )
			.count() ) === 0 &&
		presses < 120
	) {
		await page.keyboard.press( 'Tab' );
		presses++;
	}
	await expect( summary ).toBeFocused();

	/**
	 * The question in Chromium's tree: the control the summary is, and the heading inside it.
	 *
	 * @return {Promise<{control: Object, heading: Object|undefined}>} The two nodes.
	 */
	const question = async () => {
		const nodes = await accessibilityTree( page );
		const byId = new Map( nodes.map( ( node ) => [ node.nodeId, node ] ) );
		const heading = nodes.find(
			( node ) =>
				! node.ignored &&
				node.role?.value === 'heading' &&
				node.name?.value === 'How do I start?'
		);
		// The summary's role: `DisclosureTriangle`, or `DisclosureTriangleGrouped` for an item of
		// a named group (one open at a time), as this accordion's are.
		let control = heading;
		while (
			control &&
			! /^DisclosureTriangle/.test( control.role?.value ?? '' )
		) {
			control = byId.get( control.parentId );
		}

		return { control, heading };
	};

	const closed = await question();
	expect( closed.heading, 'a heading inside the question' ).toBeDefined();
	expect( propertyOf( closed.heading, 'level' ) ).toBe( 3 );
	expect(
		closed.control,
		'the heading sits in the question control'
	).toBeDefined();
	expect( closed.control.name?.value ).toBe( 'How do I start?' );
	expect( propertyOf( closed.control, 'expanded' ) ).toBe( false );

	await page.keyboard.press( 'Enter' );
	await expect( first ).toHaveAttribute( 'open', '' );
	expect( propertyOf( ( await question() ).control, 'expanded' ) ).toBe(
		true
	);

	await page.keyboard.press( 'Space' );
	await expect( first ).not.toHaveAttribute( 'open', '' );
	expect( propertyOf( ( await question() ).control, 'expanded' ) ).toBe(
		false
	);
	await context.close();
} );

test( 'TC-BLK-02-06: an accordion saved before the change keeps its questions and answers', async ( {
	browser,
} ) => {
	const { context, page } = await visit( browser, { url: made.old.link } );
	const items = page.locator( '.corex-accordion .corex-accordion__item' );

	await expect( items.locator( '.corex-accordion__question' ) ).toHaveText( [
		'What is Corex?',
		'Does it need a script?',
	] );
	await items.nth( 1 ).locator( 'summary' ).click();
	await expect(
		items.nth( 1 ).locator( '.corex-accordion__content' )
	).toHaveText( 'No, it never does.' );
	await context.close();
} );

test( 'FR-039: opened in the editor, an old accordion becomes accordion items, and a question is typed in place', async ( {
	page,
} ) => {
	await page.setViewportSize( DESKTOP );
	await page.goto( `/wp-admin/post.php?post=${ made.draft.id }&action=edit` );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const accordion = canvas.locator( '.wp-block-corex-accordion' );
	await expect( accordion ).toBeVisible( { timeout: 45_000 } );
	await page.evaluate( () =>
		window.wp.data
			.dispatch( 'core/preferences' )
			.set( 'core/edit-post', 'welcomeGuide', false )
	);

	const shape = () =>
		page.evaluate( () => {
			const [ root ] = window.wp.data
				.select( 'core/block-editor' )
				.getBlocks();

			return {
				name: root.name,
				items: root.attributes.items,
				inner: root.innerBlocks.map( ( child ) => ( {
					name: child.name,
					question: String( child.attributes.question ),
					answer: child.innerBlocks.map(
						( answer ) =>
							`${ answer.name }: ${ String(
								answer.attributes.content
							) }`
					),
				} ) ),
			};
		} );

	expect( await shape() ).toEqual( {
		name: 'corex/accordion',
		items: [],
		inner: [
			{
				name: 'corex/accordion-item',
				question: 'What is Corex?',
				answer: [ 'core/paragraph: A WordPress framework.' ],
			},
			{
				name: 'corex/accordion-item',
				question: 'Does it need a script?',
				answer: [ 'core/paragraph: No, it never does.' ],
			},
		],
	} );
	await expect( canvas.locator( '.block-editor-warning' ) ).toHaveCount( 0 );

	// The question is typed where it stands; a space typed in it does not close the item.
	const first = accordion.locator( '.corex-accordion__item' ).first();
	await first.locator( '.corex-accordion__question' ).click();
	await page.keyboard.press( 'End' );
	await page.keyboard.type( ' today' );
	await expect( first ).toHaveAttribute( 'open', '' );
	expect( ( await shape() ).inner[ 0 ].question ).toBe(
		'What is Corex? today'
	);
	await expect(
		first.locator( '.corex-accordion__content p.wp-block-paragraph' )
	).toHaveText( 'A WordPress framework.' );
} );
