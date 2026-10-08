/**
 * Corex E2E — the Releases screen (spec 107, slice 3).
 *
 * What a person sees before anything on a site changes: the release that is running, a package
 * sent to the site and read back as a statement, and a file that is not a package refused in
 * words. The packages are made here, small, in the shape the builder gives one; nothing on the
 * site is installed, because this screen cannot install yet.
 *
 * The layout is measured, in both themes and both directions and at two widths, and not only
 * looked at: the distances are the admin's spacing tokens, and a distance that is one of them
 * by eye and not by number is how two screens come to look nearly alike.
 *
 * ENVIRONMENT-GATED: needs Apache up (http://corex.local), a built corex-config, and
 * `npx playwright install`. Uses the saved admin session.
 */
const os = require( 'node:os' );
const path = require( 'node:path' );
const AdmZip = require( 'adm-zip' );
const { test, expect } = require( '@playwright/test' );
const { contrast, pinAppearance, wpEval } = require( './helpers' );

test.use( { storageState: require( './global-setup' ).STORAGE_STATE } );

const RELEASES = '/wp-admin/admin.php?page=corex-releases';

const A_PACKAGE = 'corex-release-e2e-99.0.0-20261008-180000.zip';
const NOT_A_PACKAGE = 'corex-release-e2e-not-a-package.zip';

/** The folders the fixture release says it owns, and holds a file in. */
const RELEASE_PATHS = [
	'wp-content/plugins/corex-core',
	'wp-content/themes/corex',
	'wp-content/vendor',
];

/** The admin's spacing tokens, in pixels at the admin's 16px root. */
const SPACE_SM = 12;
const SPACE_LG = 24;
const BORDER = 1;

/** WCAG 2.2 AA for text of this size. */
const READABLE = 4.5;

const fileInTemp = ( name ) => path.join( os.tmpdir(), name );

/**
 * A package as the builder makes one: its description at the top, and a file in every folder
 * it says it holds. Newer than any release, so it is never "an older release".
 *
 * @param {string|null} client The client it is built for; null for the framework alone.
 * @return {string} The zip's path.
 */
function packageFor( client ) {
	const zip = new AdmZip();
	const held = { files: 1, bytes: 40, hash: 'a'.repeat( 64 ) };

	zip.addFile(
		'corex-release.json',
		Buffer.from(
			JSON.stringify( {
				name: 'corex-shared-host-dist',
				schema: 2,
				built_at: '2026-10-08T18:00:00.000Z',
				corex_version: '99.0.0',
				client,
				plugins: [ 'corex-core' ],
				themes: [ 'corex' ],
				requires: { php: '8.0', wordpress: '6.0' },
				release_paths: RELEASE_PATHS,
				contents: Object.fromEntries(
					RELEASE_PATHS.map( ( folder ) => [ folder, held ] )
				),
				autoload: {
					file: 'wp-content/vendor/autoload.php',
					psr4: { 'Corex\\': 'wp-content/plugins/corex-core/src/' },
				},
			} )
		)
	);
	RELEASE_PATHS.forEach( ( folder ) =>
		zip.addFile( `${ folder }/index.php`, Buffer.from( '<?php' ) )
	);
	zip.writeZip( fileInTemp( A_PACKAGE ) );

	return fileInTemp( A_PACKAGE );
}

/** A zip, named as a package is, that is not one. */
function zipThatIsNotAPackage() {
	const zip = new AdmZip();
	zip.addFile( 'readme.txt', Buffer.from( 'Not a release.' ) );
	zip.writeZip( fileInTemp( NOT_A_PACKAGE ) );

	return fileInTemp( NOT_A_PACKAGE );
}

/**
 * Open the screen and wait for the site's answer.
 *
 * @param {import('@playwright/test').Page} page The page.
 * @return {Promise<Object>} What the site says of itself: `{ known, version, client }`.
 */
async function openReleases( page ) {
	const answered = page.waitForResponse(
		( response ) =>
			/corex\/v1\/releases$/.test(
				decodeURIComponent( response.url() )
			) && response.request().method() === 'GET'
	);
	await page.goto( RELEASES );
	const { data } = await ( await answered ).json();

	await expect(
		page.locator( '.corex-releases .corex-loadable' )
	).toHaveAttribute( 'data-corex-state', 'ready' );

	return data.installed;
}

const box = ( locator ) =>
	locator.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		return {
			left: Math.round( rect.left ),
			right: Math.round( rect.right ),
			top: Math.round( rect.top ),
			bottom: Math.round( rect.bottom ),
		};
	} );

const paint = ( locator ) =>
	locator.evaluate( ( element ) => {
		const style = window.getComputedStyle( element );
		return { ink: style.color, ground: style.backgroundColor };
	} );

/**
 * How far in from a box's leading edge another starts, whichever way the page reads.
 *
 * @param {Object} outer     The box around.
 * @param {Object} inner     The box inside it.
 * @param {string} direction 'ltr' or 'rtl'.
 * @return {number} The distance in pixels.
 */
const inset = ( outer, inner, direction ) =>
	direction === 'rtl' ? outer.right - inner.right : inner.left - outer.left;

test.afterAll( () => {
	// What the tests sent is left in the site's own folder for releases: taken out again where
	// WP-CLI can reach the install. Where it cannot, the site is one made for the run.
	wpEval(
		'$store = \\Corex\\Boot::app()->container()->make( \\Corex\\Config\\Releases\\ReleaseStore::class );' +
			`foreach ( [ '${ A_PACKAGE }', '${ NOT_A_PACKAGE }' ] as $name ) {` +
			"$file = $store->fileIn( 'incoming', $name ); if ( is_file( $file ) ) { unlink( $file ); } }"
	);
} );

for ( const width of [ 1280, 782 ] ) {
	for ( const theme of [ 'dark', 'light' ] ) {
		for ( const direction of [ 'ltr', 'rtl' ] ) {
			test( `the screen keeps its measure (${ width }, ${ theme }, ${ direction })`, async ( {
				page,
			} ) => {
				await page.setViewportSize( { width, height: 900 } );
				await openReleases( page );
				await pinAppearance( page, theme, direction );

				const panels = page.locator(
					'.corex-releases .corex-loadable__body > .corex-releases__panel'
				);
				await expect( panels ).toHaveCount( 3 );
				const [ running, send, packages ] = await Promise.all(
					[ 0, 1, 2 ].map( ( nth ) => box( panels.nth( nth ) ) )
				);

				// One column: every panel between the same two edges, one token apart.
				expect( send.left ).toBe( running.left );
				expect( send.right ).toBe( running.right );
				expect( packages.left ).toBe( running.left );
				expect( packages.right ).toBe( running.right );
				expect( send.top - running.bottom ).toBe( SPACE_LG );
				expect( packages.top - send.bottom ).toBe( SPACE_LG );

				// Inside a panel: the heading one token in from the leading edge and the top,
				// and the first line one smaller token under it.
				const heading = await box(
					panels.nth( 0 ).locator( '.corex-releases__heading' )
				);
				const line = await box(
					panels.nth( 0 ).locator( '.corex-releases__line' ).first()
				);
				expect( inset( running, heading, direction ) ).toBe(
					SPACE_LG + BORDER
				);
				expect( heading.top - running.top ).toBe( SPACE_LG + BORDER );
				expect( line.top - heading.bottom ).toBe( SPACE_SM );

				// Nothing reaches past the screen's own width.
				const overflow = await page
					.locator( '.corex-admin__content' )
					.evaluate(
						( content ) => content.scrollWidth - content.clientWidth
					);
				expect( overflow ).toBeLessThanOrEqual( 0 );

				// The host is one of two things, and the panel says which: nothing stands in
				// the way, or a list of what does. A site whose server cannot write its own
				// folders, as the one these tests meet in CI cannot, shows the list.
				await expect(
					panels
						.nth( 0 )
						.locator(
							'.corex-releases__line--quiet, .corex-releases__cautions'
						)
				).toHaveCount( 1 );

				// The words can be read on the panel they are on, the quiet ones too.
				const ground = ( await paint( panels.nth( 0 ) ) ).ground;
				for ( const words of [
					'.corex-releases__heading',
					'.corex-releases__line',
					'.corex-releases__line--quiet',
					'.corex-releases__label',
				] ) {
					const written = page.locator(
						`.corex-releases ${ words }`
					);
					if ( ( await written.count() ) === 0 ) {
						continue;
					}
					const { ink } = await paint( written.first() );
					expect(
						contrast( ink, ground ),
						`${ words } on its panel`
					).toBeGreaterThanOrEqual( READABLE );
				}

				// With no file chosen there is nothing to send, and the control says so.
				await expect(
					page.getByRole( 'button', { name: 'Send to the site' } )
				).toBeDisabled();
			} );
		}
	}
}

test( 'a package sent to the site is read back as a statement', async ( {
	page,
} ) => {
	const installed = await openReleases( page );
	// A site that knows whose it is takes only its own client's package.
	const client = installed.known ? installed.client : 'acme';

	// The part is held on its way, so that the moment of sending can be looked at.
	let letThePartThrough;
	const partHeld = new Promise( ( held ) => {
		page.route(
			( url ) => /[?&]offset=\d+/.test( url.href ),
			async ( route ) => {
				await new Promise( ( release ) => {
					letThePartThrough = release;
					held();
				} );
				await route.continue();
			}
		);
	} );

	await page
		.locator( '#corex-releases-file' )
		.setInputFiles( packageFor( client ) );
	const send = page.getByRole( 'button', { name: 'Send to the site' } );
	// Brought into view first: a click on a control below the fold scrolls the page, and the
	// control would then be measured in two places.
	await send.scrollIntoViewIfNeeded();
	const atRest = await box( send );
	await send.click();
	await partHeld;

	// On its way: the control works and keeps its size, and there is a bar to say how far.
	await expect( send ).toHaveAttribute( 'data-corex-working', 'true' );
	expect( await box( send ) ).toEqual( atRest );
	await expect( page.locator( '.corex-releases progress' ) ).toBeVisible();

	letThePartThrough();

	const statement = page.locator( '.corex-releases__statement' );
	await expect( statement ).toBeVisible();
	await expect( statement ).toContainText( A_PACKAGE );
	await expect( statement ).toContainText( 'CoreX 99.0.0' );
	await expect( statement ).toContainText( client ?? 'The framework alone' );
	// The statement is the answer to a button above it: the focus goes to it.
	await expect(
		statement.getByRole( 'heading', { name: 'What this package is' } )
	).toBeFocused();

	// It is on the site now, and the screen is at rest again.
	await expect(
		page.locator( '.corex-releases__held', { hasText: A_PACKAGE } )
	).toBeVisible();
	await expect( send ).not.toHaveAttribute( 'data-corex-working', 'true' );
	await expect( page.locator( '.corex-releases progress' ) ).toHaveCount( 0 );

	// The statement is one more panel of the same column.
	const last = await box(
		page
			.locator(
				'.corex-releases .corex-loadable__body > .corex-releases__panel'
			)
			.last()
	);
	const stated = await box( statement );
	expect( stated.left ).toBe( last.left );
	expect( stated.right ).toBe( last.right );
	expect( stated.top - last.bottom ).toBe( SPACE_LG );
} );

test( 'a zip that is not a package is refused in words, and not kept', async ( {
	page,
} ) => {
	await openReleases( page );

	await page
		.locator( '#corex-releases-file' )
		.setInputFiles( zipThatIsNotAPackage() );
	await page.getByRole( 'button', { name: 'Send to the site' } ).click();

	const refusal = page.locator( '.corex-releases .corex-error-state--panel' );
	await expect( refusal ).toContainText(
		'The site did not take this package.'
	);
	await expect( refusal ).toContainText(
		'This is not a CoreX release package'
	);
	await expect( refusal ).toContainText( 'removed from the site' );
	await expect( page.locator( '.corex-releases__statement' ) ).toHaveCount(
		0
	);

	// It is gone from the site, and the list says so once it has been asked for again.
	const surface = page.locator( '.corex-releases .corex-loadable' );
	await expect( surface ).toHaveAttribute( 'data-corex-state', 'ready' );
	await expect(
		page.locator( '.corex-releases__held', { hasText: NOT_A_PACKAGE } )
	).toHaveCount( 0 );

	// The refusal stands where a statement would: one more block of the same column.
	const last = await box(
		surface.locator( '.corex-releases__panel' ).last()
	);
	const refused = await box( refusal );
	expect( refused.left ).toBe( last.left );
	expect( refused.right ).toBe( last.right );
	expect( refused.top - last.bottom ).toBe( SPACE_LG );
} );
