/**
 * Corex E2E — what closes the front-end `corex/modal` and `corex/drawer` blocks, and what does not.
 *
 * Both are a native `<dialog>` opened modally, and a visitor leaves one by Escape, by its close
 * button, or by a press on the backdrop. The press is made here with a mouse, both ends of it,
 * because a click is not enough to know a press on the backdrop by: when the mouse goes down on
 * one element and comes up on another, the browser gives the click to the nearest element holding
 * both, and between the backdrop and anything in the dialog that is the dialog element. The
 * dialog element is also a box of its own, so a click whose target is the dialog can be a click on
 * the box. The specs below place the mouse in all three: on the content, on the padding the box
 * keeps round it, and on the backdrop outside the box.
 *
 * It also holds where an open dialog is: inside the window, with backdrop left beside it, and
 * scrolled by the visitor as far as its last line. Those are read off a second page whose dialogs
 * hold more than any window here fits, at a desktop's size and a phone's, in both directions.
 *
 * And it holds what a visitor is given on opening one, and keeps while reading it: the dialog at
 * its first line, focus on its close button, and its title and that button in view as far as the
 * last line, by the wheel or by the keyboard. A browser puts focus on the first thing in a dialog
 * that takes it and scrolls to it, so two long pages are read: one whose dialogs end in a link,
 * and one whose dialogs begin with one as well. A drawer is also watched coming in, from the edge
 * it is on, in both directions.
 *
 * And it holds the name a dialog is announced by: its title, or, on a fourth page whose blocks
 * were given no title, the label of the button that opened it. The body of a dialog takes the same
 * name while it is a stop for Tab. The name is read twice. Playwright works one out from the page
 * by the ARIA rules, in every browser. That is not the browser's own accessibility tree, which is
 * what a screen reader is given, and the two can differ: a dialog labelled by the button that
 * opened it has a name by the rules and none in Chromium's tree, where that button is inert while
 * the dialog is open (DECISIONS #316). So in Chromium the tree itself is read as well.
 *
 * Last, the short page is read with scripts off, where nothing answers a button: each dialog is
 * then in the page, its title and what it holds, and the buttons that would do nothing are not.
 *
 * The pages are provisioned through the REST API (admin session) and read as a visitor.
 *
 * ENVIRONMENT-GATED: needs the site at COREX_BASE_URL + `npx playwright install`.
 */
const { test, expect } = require( '@playwright/test' );

const { STORAGE_STATE } = require( './global-setup' );

const BODY =
	'<p>Read this before you agree.</p><p><button type="button">Agree</button></p>';

/** The words a long dialog ends on, as a link: what a visitor scrolls to, or reaches with Tab. */
const LAST_LINE = 'The last line';

/**
 * More than a window fits, as the block's Content box takes it: text with no element around it,
 * so nothing but the dialog's own padding comes after the last line.
 */
const LONG_BODY =
	Array.from(
		{ length: 80 },
		( _, index ) => `Sentence ${ index + 1 } of what a visitor reads.`
	).join( ' ' ) + ` <a href="#last-line">${ LAST_LINE }</a>`;

/** The words the third page's dialogs begin with, as a link: the first thing in them to take focus. */
const FIRST_LINE = 'The first line';

const LONG_BODY_LINK_FIRST = `<a href="#first-line">${ FIRST_LINE }</a> ${ LONG_BODY }`;

/** The smallest side, in CSS pixels, of something meant to be pressed (WCAG 2.2, 2.5.8). */
const SMALLEST_TARGET = 24;

/** The share of the window's width a dialog may take: `92vw` in both stylesheets. */
const WIDEST = 0.92;

const WINDOWS = [
	{ width: 1280, height: 720 },
	{ width: 375, height: 812 },
];

const DIRECTIONS = [ 'ltr', 'rtl' ];

/** A turn of the wheel longer than any dialog here. */
const FAR = 100000;

const BLOCKS = [
	{
		label: 'modal',
		name: 'corex/modal',
		attributes: { title: 'Terms', triggerLabel: 'Open the modal' },
		dialog: 'dialog.corex-modal',
	},
	{
		label: 'drawer at the end',
		name: 'corex/drawer',
		attributes: { title: 'Filters', triggerLabel: 'Open the end drawer' },
		dialog: 'dialog.corex-drawer--end',
	},
	{
		label: 'drawer at the start',
		name: 'corex/drawer',
		attributes: {
			title: 'Menu',
			triggerLabel: 'Open the start drawer',
			side: 'start',
		},
		dialog: 'dialog.corex-drawer--start',
	},
];

/**
 * Blocks given no title, each with the name its dialog is to have: the label of its trigger, which
 * for the drawer is the one a trigger gets when the editor writes none.
 */
const UNTITLED = [
	{
		label: 'modal with no title',
		name: 'corex/modal',
		attributes: { triggerLabel: 'Read the terms' },
		dialog: 'dialog.corex-modal',
		named: 'Read the terms',
	},
	{
		label: 'drawer with no title and no trigger label',
		name: 'corex/drawer',
		attributes: {},
		dialog: 'dialog.corex-drawer',
		named: 'Open',
	},
];

/**
 * The presses that leave a dialog open, as `[ scenario, where the press begins, where it is let
 * go ]`. The places are those `places()` measures.
 */
const KEPT_OPEN = [
	[
		'a press begun on its content is let go on the backdrop',
		'content',
		'backdrop',
	],
	[
		'a press begun on the backdrop is let go on its content',
		'backdrop',
		'content',
	],
	[ 'its own padding is clicked', 'padding', 'padding' ],
	[
		'a press begun on its own padding is let go on the backdrop',
		'padding',
		'backdrop',
	],
];

/**
 * A block as the editor would save it: its attributes as JSON in a comment, with the characters
 * that would end the comment or start markup written as escapes.
 *
 * @param {string} name       The block's name.
 * @param {Object} attributes Its attributes.
 * @return {string} The block comment.
 */
function blockComment( name, attributes ) {
	const json = JSON.stringify( attributes )
		.replace( /--/g, '\\u002d\\u002d' )
		.replace( /</g, '\\u003c' )
		.replace( />/g, '\\u003e' )
		.replace( /&/g, '\\u0026' );

	return `<!-- wp:${ name } ${ json } /-->`;
}

/**
 * Open a page on the admin's session and read the REST nonce it carries.
 *
 * @param {import('@playwright/test').Browser} browser The browser.
 * @return {Promise<{context: import('@playwright/test').BrowserContext, page: import('@playwright/test').Page, nonce: string}>} The session.
 */
async function adminSession( browser ) {
	// Scripts on, said here: a context made in a hook takes the options of the test the hook runs
	// beside, some of these tests turn scripts off, and the nonce is read from a script.
	const context = await browser.newContext( {
		storageState: STORAGE_STATE,
		javaScriptEnabled: true,
	} );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
}

/**
 * Go to a page of dialogs and find the button that opens one of them.
 *
 * @param {import('@playwright/test').Page} page              The page.
 * @param {Object}                          block             One of BLOCKS.
 * @param {Object}                          where             Where it is.
 * @param {string}                          where.path        The page's path.
 * @param {string}                          [where.direction] `rtl` to read the page right to left.
 * @return {Promise<import('@playwright/test').Locator>} The button.
 */
async function visit( page, block, { path, direction } ) {
	await page.goto( path );
	if ( direction ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);
	}

	return page.getByRole( 'button', {
		name: block.attributes.triggerLabel ?? block.named,
		exact: true,
	} );
}

/**
 * The button a dialog is closed by.
 *
 * @param {import('@playwright/test').Locator} dialog The dialog.
 * @return {import('@playwright/test').Locator} Its close button.
 */
function closeButton( dialog ) {
	return dialog.getByRole( 'button', { name: 'Close' } );
}

/** Why a spec that reads the browser's own accessibility tree is skipped outside Chromium. */
const CHROMIUM_ONLY =
	'The accessibility tree is read over the DevTools protocol, which only Chromium speaks.';

/**
 * The names Chromium itself gives the elements of one role that it presents, read from its own
 * accessibility tree. An element it does not present (a closed dialog, anything outside an open
 * modal one) is left out.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {string}                          role The role: `dialog`, `group`.
 * @return {Promise<string[]>} Their names. An element with no name gives an empty one.
 */
async function namesInChromiumsTree( page, role ) {
	const session = await page.context().newCDPSession( page );
	await session.send( 'Accessibility.enable' );
	const { nodes } = await session.send( 'Accessibility.getFullAXTree' );
	await session.detach();

	return nodes
		.filter( ( node ) => ! node.ignored && node.role?.value === role )
		.map( ( node ) => node.name?.value ?? '' );
}

/**
 * The parts of a dialog that are scrolled away from their start, the dialog itself among them.
 *
 * @param {import('@playwright/test').Locator} dialog The dialog.
 * @return {Promise<string[]>} Their class names. Empty when the dialog is at its first line.
 */
function scrolledParts( dialog ) {
	return dialog.evaluate( ( el ) =>
		[ el, ...el.querySelectorAll( '*' ) ]
			.filter( ( part ) => part.scrollTop !== 0 )
			.map( ( part ) => part.className )
	);
}

/**
 * Open one block's dialog and wait until it has stopped moving, so its box can be measured.
 *
 * @param {import('@playwright/test').Page} page              The page.
 * @param {Object}                          block             One of BLOCKS.
 * @param {Object}                          [where]           Where to open it.
 * @param {string}                          [where.path]      The page's path. The short page's when left out.
 * @param {string}                          [where.direction] `rtl` to read the page right to left.
 * @return {Promise<{dialog: import('@playwright/test').Locator, trigger: import('@playwright/test').Locator}>} The dialog and the button that opened it.
 */
async function open( page, block, { path = pagePath, direction } = {} ) {
	const trigger = await visit( page, block, { path, direction } );
	await trigger.click();

	const dialog = page.locator( block.dialog );
	await expect( dialog ).toHaveJSProperty( 'open', true );
	await dialog.evaluate( ( el ) =>
		Promise.all( el.getAnimations().map( ( motion ) => motion.finished ) )
	);

	return { dialog, trigger };
}

/**
 * Three places to put the mouse: on the dialog's content, on the padding the dialog keeps round
 * what it holds, and on the backdrop outside its box.
 *
 * @param {import('@playwright/test').Page}    page   The page.
 * @param {import('@playwright/test').Locator} dialog The open dialog.
 * @return {Promise<{content: {x: number, y: number}, padding: {x: number, y: number}, backdrop: {x: number, y: number}}>} The points, in viewport pixels.
 */
async function places( page, dialog ) {
	const box = await dialog.boundingBox();
	const button = await dialog
		.getByRole( 'button', { name: 'Agree' } )
		.boundingBox();
	const paddingLeft = await dialog
		.locator( '[class$="__body"]' )
		.evaluate( ( el ) =>
			parseFloat( window.getComputedStyle( el ).paddingLeft )
		);
	const middle = box.y + box.height / 2;
	const spaceBefore = box.x;
	const backdropX =
		spaceBefore > 1
			? spaceBefore / 2
			: ( box.x + box.width + page.viewportSize().width ) / 2;

	return {
		content: {
			x: button.x + button.width / 2,
			y: button.y + button.height / 2,
		},
		padding: { x: box.x + paddingLeft / 2, y: middle },
		backdrop: { x: backdropX, y: middle },
	};
}

/**
 * Turn the wheel over a dialog until its last line is in view, all of it.
 *
 * Turned again until it is: one turn takes Firefox no further than a page of what scrolls, however
 * long the turn, and Chromium to the end.
 *
 * @param {import('@playwright/test').Page}    page   The page.
 * @param {import('@playwright/test').Locator} dialog The open dialog.
 * @return {Promise<void>} Resolves at the last line, and fails if the wheel does not reach it.
 */
async function wheelToLastLine( page, dialog ) {
	const box = await dialog.boundingBox();
	await page.mouse.move(
		box.x + box.width / 2,
		page.viewportSize().height / 2
	);

	await expect( async () => {
		await page.mouse.wheel( 0, FAR );
		await expect(
			dialog.getByRole( 'link', { name: LAST_LINE } )
		).toBeInViewport( { ratio: 1, timeout: 1000 } );
	} ).toPass( { timeout: 15000 } );
}

/**
 * Press the mouse down at one place and let it go at another.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @param {{x: number, y: number}}          from Where the press begins.
 * @param {{x: number, y: number}}          to   Where it is let go.
 */
async function press( page, from, to ) {
	await page.mouse.move( from.x, from.y );
	await page.mouse.down();
	await page.mouse.move( to.x, to.y, { steps: 4 } );
	await page.mouse.up();
}

/**
 * Publish a page of blocks, each with the same content.
 *
 * @param {import('@playwright/test').Page} page     A page on the admin's session.
 * @param {string}                          nonce    Its REST nonce.
 * @param {string}                          title    The page's title.
 * @param {string}                          body     What each dialog holds.
 * @param {Object[]}                        [blocks] The blocks. BLOCKS when left out.
 * @return {Promise<number>} The page's id.
 */
async function publish( page, nonce, title, body, blocks = BLOCKS ) {
	const response = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title,
			status: 'publish',
			content: blocks
				.map( ( block ) =>
					blockComment( block.name, {
						...block.attributes,
						content: body,
					} )
				)
				.join( '\n\n' ),
		},
	} );
	expect( response.ok() ).toBe( true );

	return ( await response.json() ).id;
}

let pagePath = '';
let longPagePath = '';
let linkFirstPagePath = '';
let untitledPagePath = '';

/** The long pages, by what their dialogs hold. Read when a test runs: the paths are made first. */
const LONG_PAGES = [
	[ 'ends in a link', () => longPagePath ],
	[ 'begins with a link', () => linkFirstPagePath ],
];
const pageIds = [];

// The visitor's view. The config's admin session is inherited unless it is emptied here.
test.use( { storageState: { cookies: [], origins: [] } } );

test.beforeAll( async ( { browser } ) => {
	const { context, page, nonce } = await adminSession( browser );
	pageIds.push( await publish( page, nonce, 'Dialog blocks E2E', BODY ) );
	pagePath = `/?page_id=${ pageIds[ 0 ] }`;
	pageIds.push(
		await publish( page, nonce, 'Dialog blocks E2E, long', LONG_BODY )
	);
	longPagePath = `/?page_id=${ pageIds[ 1 ] }`;
	pageIds.push(
		await publish(
			page,
			nonce,
			'Dialog blocks E2E, long, a link first',
			LONG_BODY_LINK_FIRST
		)
	);
	linkFirstPagePath = `/?page_id=${ pageIds[ 2 ] }`;
	pageIds.push(
		await publish(
			page,
			nonce,
			'Dialog blocks E2E, long, no titles',
			LONG_BODY,
			UNTITLED
		)
	);
	untitledPagePath = `/?page_id=${ pageIds[ 3 ] }`;
	await context.close();
} );

test.afterAll( async ( { browser } ) => {
	if ( ! pageIds.length ) {
		return;
	}
	const { context, page, nonce } = await adminSession( browser );
	for ( const id of pageIds ) {
		await page.request
			.delete( `/wp-json/wp/v2/pages/${ id }?force=true`, {
				headers: { 'X-WP-Nonce': nonce },
			} )
			.catch( () => {} );
	}
	await context.close();
} );

for ( const block of BLOCKS ) {
	test.describe( `the ${ block.label }`, () => {
		for ( const [ scenario, from, to ] of KEPT_OPEN ) {
			test( `stays open when ${ scenario }`, async ( { page } ) => {
				const { dialog } = await open( page, block );
				const at = await places( page, dialog );

				await press( page, at[ from ], at[ to ] );

				await expect( dialog ).toHaveJSProperty( 'open', true );
			} );
		}

		test( 'closes on a click on the backdrop, and focus returns to its trigger', async ( {
			page,
		} ) => {
			const { dialog, trigger } = await open( page, block );
			const { backdrop } = await places( page, dialog );

			await page.mouse.click( backdrop.x, backdrop.y );

			await expect( dialog ).toHaveJSProperty( 'open', false );
			await expect( trigger ).toBeFocused();
		} );

		test( 'closes on Escape, and focus returns to its trigger', async ( {
			page,
		} ) => {
			const { dialog, trigger } = await open( page, block );

			await page.keyboard.press( 'Escape' );

			await expect( dialog ).toHaveJSProperty( 'open', false );
			await expect( trigger ).toBeFocused();
		} );

		test( 'closes on its close button, and focus returns to its trigger', async ( {
			page,
		} ) => {
			const { dialog, trigger } = await open( page, block );

			await closeButton( dialog ).click();

			await expect( dialog ).toHaveJSProperty( 'open', false );
			await expect( trigger ).toBeFocused();
		} );

		test( 'is named by its title', async ( { page } ) => {
			const { dialog } = await open( page, block );

			await expect( dialog ).toHaveAccessibleName(
				block.attributes.title
			);
		} );

		test( 'is given its title as its name by Chromium’s own accessibility tree', async ( {
			page,
			browserName,
		} ) => {
			test.skip( browserName !== 'chromium', CHROMIUM_ONLY );
			await open( page, block );

			expect( await namesInChromiumsTree( page, 'dialog' ) ).toEqual( [
				block.attributes.title,
			] );
		} );

		test( 'opens on its close button, and Tab goes on to what it holds', async ( {
			page,
		} ) => {
			const { dialog } = await open( page, block );

			await expect( closeButton( dialog ) ).toBeFocused();

			// All of it fits, so nothing stands between the button and the first thing in it.
			await page.keyboard.press( 'Tab' );

			await expect(
				dialog.getByRole( 'button', { name: 'Agree' } )
			).toBeFocused();
		} );

		test.describe( 'on a phone', () => {
			// A tap has no press to read but the mouse events the browser makes of it.
			test.use( {
				hasTouch: true,
				viewport: { width: 375, height: 812 },
			} );

			test( 'closes on a tap on the backdrop', async ( { page } ) => {
				const { dialog } = await open( page, block );
				const { backdrop } = await places( page, dialog );

				await page.touchscreen.tap( backdrop.x, backdrop.y );

				await expect( dialog ).toHaveJSProperty( 'open', false );
			} );

			test( 'has a close button big enough to press', async ( {
				page,
			} ) => {
				const { dialog } = await open( page, block );

				const box = await closeButton( dialog ).boundingBox();

				expect( box.width ).toBeGreaterThanOrEqual( SMALLEST_TARGET );
				expect( box.height ).toBeGreaterThanOrEqual( SMALLEST_TARGET );
			} );
		} );

		test.describe( 'with scripts off', () => {
			test.use( { javaScriptEnabled: false } );

			test( 'is in the page, its title and what it holds, without the buttons nothing answers', async ( {
				page,
			} ) => {
				await page.goto( pagePath );
				const dialog = page.locator( block.dialog );

				await expect(
					dialog.getByRole( 'heading', {
						name: block.attributes.title,
					} )
				).toBeVisible();
				await expect(
					dialog.getByRole( 'button', { name: 'Agree' } )
				).toBeVisible();
				await expect(
					page.getByText( block.attributes.triggerLabel )
				).toBeHidden();
				await expect( dialog.getByText( '×' ) ).toBeHidden();
			} );
		} );

		if ( block.name === 'corex/drawer' ) {
			for ( const direction of DIRECTIONS ) {
				test( `comes in from the edge it is on, ${ direction }`, async ( {
					page,
				} ) => {
					// The end is the right of a page read left to right, and the left of one
					// read right to left; the start is the other.
					const onTheRight =
						( block.attributes.side === 'start' ) ===
						( direction === 'rtl' );
					const trigger = await visit( page, block, {
						path: pagePath,
						direction,
					} );
					// Held at its first frame, where it begins.
					await page.addStyleTag( {
						content: `${ block.dialog } { animation-play-state: paused !important; }`,
					} );
					await trigger.click();
					const dialog = page.locator( block.dialog );
					await expect( dialog ).toHaveJSProperty( 'open', true );

					const begins = await dialog.boundingBox();
					await dialog.evaluate( ( el ) =>
						el
							.getAnimations()
							.forEach( ( motion ) => motion.finish() )
					);
					const rests = await dialog.boundingBox();

					const towardsItsEdge = onTheRight ? 1 : -1;
					expect(
						( begins.x - rests.x ) * towardsItsEdge
					).toBeGreaterThan( 0 );
				} );
			}
		}

		for ( const size of WINDOWS ) {
			test.describe( `holding more than fits a ${ size.width }x${ size.height } window`, () => {
				test.use( { viewport: size } );

				for ( const [ what, path ] of LONG_PAGES ) {
					test( `opens at its first line, on its close button, when what it holds ${ what }`, async ( {
						page,
					} ) => {
						const { dialog } = await open( page, block, {
							path: path(),
						} );

						await expect( closeButton( dialog ) ).toBeFocused();
						expect( await scrolledParts( dialog ) ).toEqual( [] );
						await expect(
							dialog.getByRole( 'heading' )
						).toBeInViewport( { ratio: 1 } );
						await expect( closeButton( dialog ) ).toBeInViewport( {
							ratio: 1,
						} );
					} );
				}

				test( 'keeps its title and its close button in view at its last line, and is closed by that button', async ( {
					page,
				} ) => {
					const { dialog } = await open( page, block, {
						path: longPagePath,
					} );

					await wheelToLastLine( page, dialog );

					await expect(
						dialog.getByRole( 'heading' )
					).toBeInViewport( { ratio: 1 } );
					await expect( closeButton( dialog ) ).toBeInViewport( {
						ratio: 1,
					} );

					await closeButton( dialog ).click();

					await expect( dialog ).toHaveJSProperty( 'open', false );
				} );

				test( 'is scrolled by the keyboard from its close button as far as its last line', async ( {
					page,
				} ) => {
					const { dialog } = await open( page, block, {
						path: longPagePath,
					} );

					// To what the dialog holds, which takes focus while it holds more than fits.
					await page.keyboard.press( 'Tab' );
					await page.keyboard.press( 'End' );

					await expect(
						dialog.getByRole( 'link', { name: LAST_LINE } )
					).toBeInViewport( { ratio: 1 } );
					await expect( closeButton( dialog ) ).toBeInViewport( {
						ratio: 1,
					} );
				} );
			} );
		}

		for ( const size of WINDOWS ) {
			for ( const direction of DIRECTIONS ) {
				test.describe( `holding more than fits a ${ size.width }x${ size.height } window, ${ direction }`, () => {
					test.use( { viewport: size } );

					test( 'is inside the window and leaves backdrop beside it', async ( {
						page,
					} ) => {
						const { dialog } = await open( page, block, {
							path: longPagePath,
							direction,
						} );

						const box = await dialog.boundingBox();

						expect( box.x ).toBeGreaterThanOrEqual( 0 );
						expect( box.y ).toBeGreaterThanOrEqual( 0 );
						expect( box.x + box.width ).toBeLessThanOrEqual(
							size.width
						);
						expect( box.y + box.height ).toBeLessThanOrEqual(
							size.height
						);
						expect( box.width ).toBeLessThanOrEqual(
							Math.ceil( WIDEST * size.width )
						);
					} );

					test( 'is scrolled as far as its last line', async ( {
						page,
					} ) => {
						const { dialog } = await open( page, block, {
							path: longPagePath,
							direction,
						} );

						await wheelToLastLine( page, dialog );

						await expect(
							dialog.getByRole( 'link', { name: LAST_LINE } )
						).toBeInViewport( { ratio: 1 } );
					} );
				} );
			}
		}
	} );
}

for ( const block of UNTITLED ) {
	test.describe( `the ${ block.label }`, () => {
		test( 'is named by the label of the button that opened it', async ( {
			page,
		} ) => {
			const { dialog } = await open( page, block, {
				path: untitledPagePath,
			} );

			await expect( dialog ).toHaveAccessibleName( block.named );
		} );

		test( 'is given that name, and its body the same, by Chromium’s own accessibility tree', async ( {
			page,
			browserName,
		} ) => {
			test.skip( browserName !== 'chromium', CHROMIUM_ONLY );
			await open( page, block, { path: untitledPagePath } );

			expect( await namesInChromiumsTree( page, 'dialog' ) ).toEqual( [
				block.named,
			] );
			expect( await namesInChromiumsTree( page, 'group' ) ).toEqual( [
				block.named,
			] );
		} );

		test( 'gives its body the same name while that is a stop for Tab', async ( {
			page,
		} ) => {
			const { dialog } = await open( page, block, {
				path: untitledPagePath,
			} );

			// To what the dialog holds, which takes focus while it holds more than fits.
			await page.keyboard.press( 'Tab' );

			await expect(
				dialog.getByRole( 'group', { name: block.named, exact: true } )
			).toBeFocused();
		} );
	} );
}

test.describe( 'the page of dialogs with scripts off', () => {
	test.use( { javaScriptEnabled: false } );

	for ( const size of WINDOWS ) {
		test.describe( `in a ${ size.width }x${ size.height } window`, () => {
			test.use( { viewport: size } );

			test( 'has each dialog under the one before it, and none wider than the window', async ( {
				page,
			} ) => {
				await page.goto( pagePath );

				const boxes = [];
				for ( const block of BLOCKS ) {
					boxes.push(
						await page.locator( block.dialog ).boundingBox()
					);
				}

				boxes.forEach( ( box ) => {
					expect( box.x ).toBeGreaterThanOrEqual( 0 );
					expect( box.x + box.width ).toBeLessThanOrEqual(
						size.width
					);
				} );
				boxes.slice( 1 ).forEach( ( box, index ) => {
					const before = boxes[ index ];
					expect( box.y ).toBeGreaterThanOrEqual(
						before.y + before.height
					);
				} );
			} );
		} );
	}
} );
