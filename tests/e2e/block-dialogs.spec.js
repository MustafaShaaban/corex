/**
 * Corex E2E — what closes the front-end `corex/modal` and `corex/drawer` blocks, and what does not.
 *
 * Both are a native `<dialog>` opened modally, and a visitor leaves one by Escape, by its close
 * button, or by a press on the backdrop. The press is made here with a mouse, both ends of it,
 * because a click is not enough to know a press on the backdrop by: when the mouse goes down on
 * one element and comes up on another, the browser gives the click to the nearest element holding
 * both, and between the backdrop and anything in the dialog that is the dialog element. The
 * dialog element is also its own padded box, so a click whose target is the dialog can be a click
 * on the box. The specs below place the mouse in all three: on the content, on the box's own
 * padding, and on the backdrop outside the box.
 *
 * It also holds where an open dialog is: inside the window, with backdrop left beside it, and
 * scrolled by the visitor as far as its last line. Those are read off a second page whose dialogs
 * hold more than any window here fits, at a desktop's size and a phone's, in both directions.
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

/** The share of the window's width a dialog may take: `92vw` in both stylesheets. */
const WIDEST = 0.92;

const WINDOWS = [
	{ width: 1280, height: 720 },
	{ width: 375, height: 812 },
];

const DIRECTIONS = [ 'ltr', 'rtl' ];

/** A turn of the wheel longer than any dialog here: it ends where the dialog stops scrolling. */
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
	const context = await browser.newContext( { storageState: STORAGE_STATE } );
	const page = await context.newPage();
	await page.goto( '/wp-admin/' );
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	return { context, page, nonce };
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
	await page.goto( path );
	if ( direction ) {
		await page
			.locator( 'html' )
			.evaluate(
				( root, dir ) => root.setAttribute( 'dir', dir ),
				direction
			);
	}
	const trigger = page.getByRole( 'button', {
		name: block.attributes.triggerLabel,
	} );
	await trigger.click();

	const dialog = page.locator( block.dialog );
	await expect( dialog ).toHaveJSProperty( 'open', true );
	await dialog.evaluate( ( el ) =>
		Promise.all( el.getAnimations().map( ( motion ) => motion.finished ) )
	);

	return { dialog, trigger };
}

/**
 * Three places to put the mouse: on the dialog's content, on the dialog's own padding, and on the
 * backdrop outside its box.
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
	const paddingLeft = await dialog.evaluate( ( el ) =>
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
 * Publish a page holding every block of BLOCKS, each with the same content.
 *
 * @param {import('@playwright/test').Page} page  A page on the admin's session.
 * @param {string}                          nonce Its REST nonce.
 * @param {string}                          title The page's title.
 * @param {string}                          body  What each dialog holds.
 * @return {Promise<number>} The page's id.
 */
async function publish( page, nonce, title, body ) {
	const response = await page.request.post( '/wp-json/wp/v2/pages', {
		headers: { 'X-WP-Nonce': nonce },
		data: {
			title,
			status: 'publish',
			content: BLOCKS.map( ( block ) =>
				blockComment( block.name, {
					...block.attributes,
					content: body,
				} )
			).join( '\n\n' ),
		},
	} );
	expect( response.ok() ).toBe( true );

	return ( await response.json() ).id;
}

let pagePath = '';
let longPagePath = '';
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

			await dialog.getByRole( 'button', { name: 'Close' } ).click();

			await expect( dialog ).toHaveJSProperty( 'open', false );
			await expect( trigger ).toBeFocused();
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
		} );

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
						const box = await dialog.boundingBox();

						await page.mouse.move(
							box.x + box.width / 2,
							size.height / 2
						);
						await page.mouse.wheel( 0, FAR );

						await expect(
							dialog.getByRole( 'link', { name: LAST_LINE } )
						).toBeInViewport( { ratio: 1 } );
					} );
				} );
			}
		}
	} );
}
