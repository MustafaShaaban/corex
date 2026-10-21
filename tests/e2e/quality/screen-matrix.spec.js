/**
 * The screen matrix (spec 116; US-NFR-01, US-NFR-03, US-NFR-04, US-NFR-06, US-NFR-07).
 *
 * Every CoreX admin screen and tab WordPress's menu lists, and every theme template, in eight
 * cells: 375 and 1280px, light and dark, left to right and right to left. The page is loaded once
 * per width and the four cells of that width are switched in place: the appearance through the
 * browser's colour scheme (the admin's `system` appearance follows it), the direction through
 * `dir` on the document, as the right-to-left check before it did.
 *
 * In each cell: the page answered as expected and drew CoreX's markup, it does not scroll
 * sideways, and axe finds no WCAG 2.0, 2.1 or 2.2 A or AA violation (contrast and target size
 * included). Once per load: no raw developer keys in the visible text, and no animation repeating
 * at rest. At 375px: no table wider than the screen, primary controls of 44px, at most one rail
 * control above the heading, and text at 200% neither cut off nor laid over other text.
 *
 * A failure names the screen, the cell, the check, the rule and the element. Known failures are
 * in known-failures.json. Four screenshots per screen are kept in test-results/quality/screens.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const { installQualityHelpers } = require( './in-page' );
const { assertNoNewFindings, OUTPUT } = require( './findings' );
const {
	site,
	PHONE,
	DESKTOP,
	APPEARANCES,
	DIRECTIONS,
	SCREENSHOT_CELLS,
	fileNameOf,
} = require( './site' );

const WCAG_TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ];

const ADMIN = site.screens.map( ( screen ) => ( {
	kind: 'admin',
	id: screen.id,
	path: screen.path,
	status: 200,
} ) );

const TEMPLATES = site.templates.map( ( template ) => ( {
	kind: 'template',
	id: `template:${ template.template }`,
	path: template.path,
	status: template.status,
} ) );

async function setDirection( page, direction ) {
	await page.evaluate( ( dir ) => {
		document.documentElement.setAttribute( 'dir', dir );
		document.body.setAttribute( 'dir', dir );
	}, direction );
}

/**
 * axe's violations in one cell, each node signed in the page.
 * @param {import('@playwright/test').Page} page    The page under test.
 * @param {Object}                          target  The screen or template, with its address and the status it answers.
 * @param {(fields: Object) => Object}      finding Makes a finding of the fields it is given.
 */
async function axeFindings( page, target, finding ) {
	const builder = new AxeBuilder( { page } ).withTags( WCAG_TAGS );

	if ( target.kind === 'admin' ) {
		builder.include( '.corex-admin' );
	}

	const { violations } = await builder.analyze();
	const nodes = violations.flatMap( ( violation ) =>
		violation.nodes.map( ( node ) => ( { violation, node } ) )
	);
	const signatures = await page.evaluate(
		( selectors ) =>
			selectors.map( ( selector ) => {
				try {
					return window.__corexQuality.signature(
						document.querySelector( selector )
					);
				} catch {
					return 'unknown';
				}
			} ),
		nodes.map( ( { node } ) => String( node.target.at( -1 ) ) )
	);

	return nodes.map( ( { violation, node }, index ) =>
		finding( {
			check: 'accessibility',
			rule: violation.id,
			signature: signatures[ index ],
			detail: `${ violation.help }: ${ node.target.join( ' ' ) }`,
		} )
	);
}

async function overflowFindings( page, finding ) {
	const result = await page.evaluate( () =>
		window.__corexQuality.overflow()
	);

	if ( result.by <= 0 ) {
		return [];
	}

	const culprits =
		result.culprits.length > 0
			? result.culprits
			: [ { signature: 'document', left: 0, right: result.scrollWidth } ];

	return culprits.map( ( culprit ) =>
		finding( {
			check: 'layout',
			rule: 'page-scrolls-sideways',
			signature: culprit.signature,
			detail: `the page is ${ result.scrollWidth }px wide in a ${ result.width }px window; this element spans ${ culprit.left } to ${ culprit.right }`,
		} )
	);
}

async function phoneFindings( page, finding ) {
	const result = await page.evaluate( () => window.__corexQuality.phone() );

	return [
		...result.tables.map( ( table ) =>
			finding( {
				check: 'phone',
				rule: 'table-wider-than-screen',
				signature: table.signature,
				detail: `${ table.width }px wide at 375px`,
			} )
		),
		...result.targets.map( ( control ) =>
			finding( {
				check: 'phone',
				rule: 'touch-target-under-44',
				signature: control.signature,
				detail: `"${ control.name }" is ${ control.size }`,
			} )
		),
		...( result.railBeforeHeading > 1
			? [
					finding( {
						check: 'phone',
						rule: 'rail-not-folded',
						signature: '.corex-admin__nav',
						detail: `${ result.railBeforeHeading } rail controls before the heading`,
					} ),
				]
			: [] ),
	];
}

async function textFindings( page, finding ) {
	const keys = await page.evaluate( () => window.__corexQuality.rawKeys() );

	return keys.map( ( key ) =>
		finding( {
			check: 'words',
			rule: key.rule,
			signature: key.signature,
			detail: `"${ key.text }" in ${ key.element }: "${ key.context }"`,
		} )
	);
}

async function zoomFindings( page, finding ) {
	const result = await page.evaluate( () =>
		window.__corexQuality.textAt200()
	);

	return [
		...result.clipped.map( ( item ) =>
			finding( {
				check: 'phone',
				rule: 'text-cut-off-at-200',
				signature: item.signature,
				detail: `"${ item.text }"`,
			} )
		),
		...result.overlaps.map( ( item ) =>
			finding( {
				check: 'phone',
				rule: 'text-overlaps-at-200',
				signature: item.signature,
				detail: item.text,
			} )
		),
	];
}

async function restingFindings( page, finding ) {
	const running = await page.evaluate( () =>
		window.__corexQuality.restingAnimations()
	);

	return running.map( ( animation ) =>
		finding( {
			check: 'motion',
			rule: 'animation-repeats-at-rest',
			signature: animation.signature,
			detail: 'an animation with no end runs while nothing is loading',
		} )
	);
}

async function screenshot( page, target, cell ) {
	const folder = path.join( OUTPUT, 'screens', fileNameOf( target.id ) );
	fs.mkdirSync( folder, { recursive: true } );
	await page.screenshot( {
		path: path.join( folder, `${ cell }.jpg` ),
		fullPage: true,
		type: 'jpeg',
		quality: 60,
	} );
}

/**
 * Opens one target at one width and checks its four cells.
 * @param {import('@playwright/test').Page} page     The page under test.
 * @param {Object}                          target   The screen or template, with its address and the status it answers.
 * @param {{width: number, height: number}} viewport The window size.
 */
async function checkWidth( page, target, viewport ) {
	const findings = [];
	const at = ( cell ) => ( fields ) => ( {
		screen: target.id,
		cell,
		...fields,
	} );

	if ( target.path === null ) {
		assertNoNewFindings( expect, [
			at( 'all' )( {
				check: 'coverage',
				rule: 'template-without-address',
				signature: target.id,
				detail: 'no address in tests/e2e/quality/templates.json',
			} ),
		] );
		return;
	}

	await page.addInitScript( installQualityHelpers );
	await page.setViewportSize( viewport );
	await page.emulateMedia( {
		colorScheme: 'light',
		reducedMotion: 'no-preference',
	} );

	const response = await page.goto( target.path, { waitUntil: 'load' } );
	const status = response ? response.status() : 0;
	const drawn =
		target.kind === 'admin'
			? await page
					.locator( '.corex-admin' )
					.first()
					.waitFor( { state: 'attached', timeout: 15_000 } )
					.then(
						() => true,
						() => false
					)
			: true;

	if ( status !== target.status || ! drawn ) {
		assertNoNewFindings( expect, [
			at( `${ viewport.width }-all` )( {
				check: 'load',
				rule: drawn ? 'unexpected-status' : 'no-corex-shell',
				signature: String( status ),
				detail: `${ target.path } answered ${ status }, expected ${ target.status }${ drawn ? '' : ', and drew no .corex-admin' }`,
			} ),
		] );
		return;
	}

	await page
		.waitForLoadState( 'networkidle', { timeout: 10_000 } )
		.catch( () => {} );

	const first = at( `${ viewport.width }-light-ltr` );
	findings.push( ...( await textFindings( page, first ) ) );
	findings.push( ...( await restingFindings( page, first ) ) );

	for ( const appearance of APPEARANCES ) {
		await page.emulateMedia( { colorScheme: appearance } );

		for ( const direction of DIRECTIONS ) {
			await setDirection( page, direction );
			const cell = `${ viewport.width }-${ appearance }-${ direction }`;
			const finding = at( cell );

			findings.push( ...( await overflowFindings( page, finding ) ) );
			findings.push( ...( await axeFindings( page, target, finding ) ) );

			if ( viewport.width === PHONE.width && appearance === 'light' ) {
				findings.push( ...( await phoneFindings( page, finding ) ) );
				findings.push( ...( await zoomFindings( page, finding ) ) );
			}

			if ( SCREENSHOT_CELLS.includes( cell ) ) {
				await screenshot( page, target, cell );
			}
		}
	}

	assertNoNewFindings( expect, findings );
}

test.describe( 'admin screens', () => {
	for ( const target of ADMIN ) {
		for ( const viewport of [ PHONE, DESKTOP ] ) {
			test( `${ target.id } at ${ viewport.width }px`, async ( {
				page,
			} ) => {
				await checkWidth( page, target, viewport );
			} );
		}
	}
} );

test.describe( 'theme templates, as a visitor sees them', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	for ( const target of TEMPLATES ) {
		for ( const viewport of [ PHONE, DESKTOP ] ) {
			test( `${ target.id } at ${ viewport.width }px`, async ( {
				page,
			} ) => {
				await checkWidth( page, target, viewport );
			} );
		}
	}
} );
