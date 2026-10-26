/**
 * The Arabic proof (spec 121, US-NFR-02; Definition of Done items 5 and 9 in real Arabic).
 *
 * Every CoreX admin screen and tab, every theme template with the Company kit, and the Contact
 * page's form submitted empty, run in Arabic through the `corex-e2e-arabic.php` fixture: the
 * request's language is Arabic and its direction right to left, and every CoreX string a PHP or
 * JavaScript call asked for and the catalogue did not answer is recorded. A screen fails naming
 * each such string; names the glossary keeps in English are not counted
 * (plugins/corex-core/languages/glossary-ar.md). A page whose catalogue did not load fails as that,
 * never as a pass on English text.
 *
 * Each target is kept as four screenshots, 375 and 1280px, light and dark, in
 * test-results/quality/arabic/, for the reader who checks them against
 * specs/121-tokens-and-type/arabic-review.md.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );

const { OUTPUT } = require( './findings' );
const { site, PHONE, DESKTOP, APPEARANCES, fileNameOf } = require( './site' );

const FOLDER = path.join( OUTPUT, 'arabic' );
const CONTACT = '/contact/';

const ADMIN = site.screens.map( ( screen ) => ( {
	id: screen.id,
	path: screen.path,
	admin: true,
} ) );

const PAGES = site.templates
	.filter( ( template ) => template.path && template.status !== 404 )
	.map( ( template ) => ( {
		id: `template:${ template.template }`,
		path: template.path,
		admin: false,
	} ) );

/**
 * Opens a target in Arabic and returns what the catalogue did not answer, from the page and from
 * every REST answer it waited for.
 *
 * @param {import('@playwright/test').Page}           page    The page.
 * @param {import('@playwright/test').BrowserContext} context Its context.
 * @param {string}                                    baseURL The site.
 * @param {string}                                    address The target.
 */
async function openInArabic( page, context, baseURL, address ) {
	const fromRest = new Set();

	await context.addCookies( [
		{ name: 'corex_e2e_locale', value: 'ar', url: baseURL },
	] );
	page.on( 'response', ( response ) => {
		const header = response.headers()[ 'x-corex-arabic-missing' ];

		if ( header ) {
			JSON.parse( decodeURIComponent( header ) ).forEach( ( text ) =>
				fromRest.add( text )
			);
		}
	} );
	await page.setViewportSize( DESKTOP );
	await page.goto( address, { waitUntil: 'load' } );
	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );

	const state = await page.evaluate( () => ( {
		catalogue: window.__corexArabicCatalogue,
		missing: window.__corexArabicMissing || [],
		lang: document.documentElement.lang,
		dir: document.documentElement.dir || document.body.dir,
	} ) );

	return {
		...state,
		missing: [ ...new Set( [ ...state.missing, ...fromRest ] ) ],
	};
}

/**
 * The face an Arabic text node in the target is drawn in, or null when it has none.
 *
 * @param {string} scope A selector for the part of the page to read.
 * @return {string|null} The computed font family.
 */
function arabicFace( scope ) {
	const root = document.querySelector( scope ) || document.body;
	const walker = document.createTreeWalker(
		root,
		window.NodeFilter.SHOW_TEXT
	);

	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		const parent = node.parentElement;

		if (
			parent &&
			/[؀-ۿ]{3,}/.test( node.nodeValue ) &&
			parent.getClientRects().length > 0 &&
			! parent.closest( 'code, pre, kbd, samp' )
		) {
			return window.getComputedStyle( parent ).fontFamily;
		}
	}

	return null;
}

async function keepScreenshots( page, id ) {
	fs.mkdirSync( FOLDER, { recursive: true } );

	for ( const viewport of [ DESKTOP, PHONE ] ) {
		await page.setViewportSize( viewport );

		for ( const appearance of APPEARANCES ) {
			await page.emulateMedia( { colorScheme: appearance } );
			await page.screenshot( {
				path: path.join(
					FOLDER,
					`${ fileNameOf( id ) }-${ viewport.width }-${ appearance }.png`
				),
				fullPage: viewport === PHONE,
			} );
		}
	}
}

function assertArabic( target, state, face ) {
	expect(
		state.catalogue,
		`${ target.id }: the Arabic catalogue is not loaded (plugins/corex-core/languages/corex-ar.mo), or the corex-e2e-arabic.php fixture is not in mu-plugins`
	).toBe( true );
	expect( state.dir, `${ target.id }: the page is not right to left` ).toBe(
		'rtl'
	);
	expect(
		state.missing,
		`${ target.id }: CoreX strings with no Arabic, add them to plugins/corex-core/languages/corex-ar.po:\n${ state.missing.join( '\n' ) }`
	).toEqual( [] );

	if ( face !== null ) {
		expect(
			face,
			`${ target.id }: Arabic text is not drawn in the Arabic face`
		).toMatch( /Arabic/ );
	}
}

for ( const target of [ ...ADMIN, ...PAGES ] ) {
	test( `${ target.id }: CoreX reads in Arabic`, async ( {
		page,
		context,
		baseURL,
	} ) => {
		if ( ! target.admin ) {
			await context.clearCookies();
		}

		const state = await openInArabic( page, context, baseURL, target.path );
		const face = await page.evaluate(
			arabicFace,
			target.admin ? '.corex-admin' : 'main'
		);

		await keepScreenshots( page, target.id );
		assertArabic( target, state, face );
	} );
}

test( 'the Contact form answers an empty submit in Arabic', async ( {
	page,
	context,
	baseURL,
} ) => {
	await context.clearCookies();
	await openInArabic( page, context, baseURL, CONTACT );

	const form = page
		.locator( 'form.corex-form, .corex-form form, form[class*="corex"]' )
		.first();
	await expect( form, `no CoreX form on ${ CONTACT }` ).toBeVisible();
	await form.locator( '[type="submit"]' ).first().click();
	await expect(
		page.locator( '[aria-invalid="true"]' ).first()
	).toBeVisible();

	const state = await page.evaluate( () => ( {
		catalogue: window.__corexArabicCatalogue,
		missing: window.__corexArabicMissing || [],
		dir: document.documentElement.dir,
		errors: [
			...document.querySelectorAll(
				'[role="alert"], .corex-form__error, .corex-form__summary'
			),
		]
			.map( ( element ) => element.textContent.trim() )
			.filter( Boolean ),
	} ) );

	await keepScreenshots( page, 'form:contact-errors' );
	assertArabic( { id: 'form:contact-errors' }, state, null );
	expect(
		state.errors.join( ' ' ),
		'the error messages are not in Arabic'
	).toMatch( /[؀-ۿ]{3,}/ );
} );
