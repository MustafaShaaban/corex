/**
 * Corex E2E — a release installed from the Releases screen, and left again (spec 107, slices 4
 * and 5; T055, T065).
 *
 * A real installation, on a real server: a package is sent, read, confirmed with its version
 * typed, unpacked, checked, and put in the place of a plugin folder that is on the site. While
 * the files are in place and the database step has not run, a visitor gets WordPress's own
 * maintenance answer; afterwards the site is served again.
 * Then the site goes back to the release before, from the same screen, and the record of both
 * is read off it. Last, it is installed again on a host that does not hand the installation's
 * key header to PHP, as the host of 2026-10-18 did not: the key in the address gets it through
 * WordPress's maintenance answer (FR-024a), and the record says so.
 *
 * The release is one made here. It owns a single plugin folder of its own, so nothing of CoreX
 * moves. The folder is put on the site first, as an earlier release would have left it, with a
 * file the new release does not hold: that file is the fault of 2026-10-11, and it has to be
 * gone afterwards.
 *
 * This spec holds the whole site in maintenance for a moment, so it runs in a project of its
 * own, after every other (see playwright.config.js).
 *
 * ENVIRONMENT-GATED: needs a site whose server can write its plugins folder, its must-use
 * plugins folder and its root. Where it cannot, the Releases screen says so and this is
 * skipped, unless COREX_E2E_MUST_INSTALL is set: then it fails, so a run that was set up for it
 * cannot pass without having done it.
 */
const { createHash } = require( 'node:crypto' );
const os = require( 'node:os' );
const path = require( 'node:path' );
const AdmZip = require( 'adm-zip' );
const { test, expect } = require( '@playwright/test' );
const { contrast, pinAppearance, wpEval } = require( './helpers' );

test.use( { storageState: require( './global-setup' ).STORAGE_STATE } );

const RELEASES = '/wp-admin/admin.php?page=corex-releases';

const VERSION = '99.1.0';
const A_PACKAGE = `corex-release-e2e-${ VERSION }-20261011-120000.zip`;
const FOLDER = 'wp-content/plugins/corex-e2e-released';

const PLUGIN_OF = ( release ) =>
	`<?php\n/**\n * Plugin Name: CoreX E2E released plugin\n * Description: ${ release }\n */\n`;

/** The admin's spacing tokens, in pixels at the admin's 16px root. */
const SPACE_SM = 12;
const SPACE_LG = 24;

/** WCAG 2.2 AA for text of this size. */
const READABLE = 4.5;

const sha256 = ( data ) =>
	createHash( 'sha256' ).update( data ).digest( 'hex' );

/**
 * What the builder writes for a folder (`scripts/release-content-hash.mjs`): its files, its
 * bytes, and a hash over one line a file.
 *
 * @param {Object<string,string>} files Each file's path in the folder, and what it holds.
 * @return {{files: number, bytes: number, hash: string}} The folder's measure.
 */
function measured( files ) {
	const lines = Object.entries( files ).map( ( [ name, holds ] ) => {
		const content = Buffer.from( holds );
		return Buffer.from(
			`${ name }\0${ content.length }\0${ sha256( content ) }\n`
		);
	} );
	lines.sort( Buffer.compare );

	return {
		files: lines.length,
		bytes: Object.values( files ).reduce(
			( sum, holds ) => sum + Buffer.byteLength( holds ),
			0
		),
		hash: sha256( Buffer.concat( lines ) ),
	};
}

/**
 * A package whose description is true of it, as the builder makes one.
 *
 * @param {string|null} client The client it is built for; null for the framework alone.
 * @return {string} The zip's path.
 */
function packageFor( client ) {
	const files = { 'corex-e2e-released.php': PLUGIN_OF( VERSION ) };
	const zip = new AdmZip();

	zip.addFile(
		'corex-release.json',
		Buffer.from(
			JSON.stringify( {
				name: 'corex-shared-host-dist',
				schema: 2,
				built_at: '2026-10-11T12:00:00.000Z',
				corex_version: VERSION,
				client,
				plugins: [ 'corex-e2e-released' ],
				themes: [],
				requires: { php: '8.0', wordpress: '6.0' },
				release_paths: [ FOLDER ],
				contents: { [ FOLDER ]: measured( files ) },
				autoload: { file: '', psr4: {} },
			} )
		)
	);
	Object.entries( files ).forEach( ( [ name, holds ] ) =>
		zip.addFile( `${ FOLDER }/${ name }`, Buffer.from( holds ) )
	);
	zip.writeZip( path.join( os.tmpdir(), A_PACKAGE ) );

	return path.join( os.tmpdir(), A_PACKAGE );
}

/** PHP that leaves the installer's classes in `$make`, and the plugin's folder in `$folder`. */
const THE_INSTALLER =
	'$make = fn ( string $class ) => \\Corex\\Boot::app()->container()->make( "Corex\\\\Config\\\\Releases\\\\$class" );' +
	"$folder = WP_PLUGIN_DIR . '/corex-e2e-released';" +
	'$remove = function ( string $path ) use ( &$remove ): void { if ( ! is_dir( $path ) ) { return; }' +
	' foreach ( array_diff( scandir( $path ), [ \'.\', \'..\' ] ) as $entry ) { is_dir( "$path/$entry" ) ? $remove( "$path/$entry" ) : unlink( "$path/$entry" ); } rmdir( $path ); };';

/**
 * Take off the site everything an installation of the fixture release leaves on it.
 *
 * @return {string|null} Null where WP-CLI cannot reach the install.
 */
function forgetTheInstallation() {
	return wpEval(
		THE_INSTALLER +
			"$store = $make( 'ReleaseStore' ); $store->clearState();" +
			"foreach ( [ 'staging', 'previous', 'discarded' ] as $area ) { $store->empty( $area ); }" +
			`$sent = $store->fileIn( 'incoming', '${ A_PACKAGE }' ); if ( is_file( $sent ) ) { unlink( $sent ); }` +
			"$make( 'ReleaseSafeguards' )->remove(); $make( 'InstalledRelease' )->forget();" +
			"$make( 'ReleaseTableCopies' )->discard(); $remove( $folder ); echo 'forgotten';"
	);
}

/**
 * Put the plugin on the site as an earlier release left it, with a file no release holds now.
 *
 * @return {string|null} Null where WP-CLI cannot reach the install.
 */
function leaveAnEarlierRelease() {
	return wpEval(
		THE_INSTALLER +
			// Whoever the web server runs as has to be able to move it: on a site made for a
			// test run that is another user than the one WP-CLI runs as.
			'mkdir( $folder ); chmod( $folder, 0777 );' +
			`file_put_contents( "$folder/corex-e2e-released.php", ${ JSON.stringify(
				PLUGIN_OF( 'the release before' )
			) } );` +
			'file_put_contents( "$folder/left-behind.php", \'<?php // shipped by the release before, and not by this one\' );' +
			"echo 'left';"
	);
}

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

	return data;
}

/**
 * Send the fixture package and go on to the confirmation.
 *
 * @param {import('@playwright/test').Page} page     The page.
 * @param {Object}                          overview What the site said of itself when the screen opened.
 * @return {Promise<string|null>} The client the package was built for.
 */
async function askToInstall( page, overview ) {
	// A site that knows whose it is takes only its own client's package.
	const client = overview.installed.known ? overview.installed.client : null;

	await page
		.locator( '#corex-releases-file' )
		.setInputFiles( packageFor( client ) );
	await page.getByRole( 'button', { name: 'Send to the site' } ).click();
	await page
		.locator( '.corex-releases__statement' )
		.getByRole( 'button', { name: 'Install this release…' } )
		.click();
	await expect(
		page.getByRole( 'heading', { name: `Install CoreX ${ VERSION }` } )
	).toBeFocused();

	return client;
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
 * Skip where this host cannot install a release, unless the run was set up for it to.
 *
 * @param {Object} overview What the site said of itself when the screen opened.
 */
function onlyWhereTheHostCanInstall( overview ) {
	if ( process.env.COREX_E2E_MUST_INSTALL ) {
		expect( overview.blockers, 'what stands in the way' ).toEqual( [] );
	}
	test.skip(
		overview.blockers.length > 0,
		'this host cannot install a release, and the Releases screen says why'
	);
}

test.describe.configure( { mode: 'serial' } );

test.beforeAll( forgetTheInstallation );
test.afterAll( forgetTheInstallation );

for ( const [ width, theme, direction ] of [
	[ 1280, 'dark', 'ltr' ],
	[ 1280, 'light', 'rtl' ],
	[ 782, 'light', 'ltr' ],
	[ 782, 'dark', 'rtl' ],
] ) {
	test( `the confirmation keeps its measure (${ width }, ${ theme }, ${ direction })`, async ( {
		page,
	} ) => {
		await page.setViewportSize( { width, height: 1100 } );
		const overview = await openReleases( page );
		onlyWhereTheHostCanInstall( overview );
		await askToInstall( page, overview );
		await pinAppearance( page, theme, direction );

		const running = await box(
			page.locator( '.corex-releases__panel' ).first()
		);
		const confirm = page.locator( '.corex-releases__confirm' );
		const panel = await box( confirm );

		// One more panel of the same column, one token under the one above it. The rest of
		// the screen is put away: an installation is the only thing in hand.
		await expect( page.locator( '.corex-releases__panel' ) ).toHaveCount(
			2
		);
		expect( panel.left ).toBe( running.left );
		expect( panel.right ).toBe( running.right );
		expect( panel.top - running.bottom ).toBe( SPACE_LG );

		// The two buttons on one line, a small token apart, whichever way the page reads.
		const install = await box(
			confirm.getByRole( 'button', {
				name: `Install CoreX ${ VERSION }`,
			} )
		);
		const decline = await box(
			confirm.getByRole( 'button', { name: 'Do not install' } )
		);
		expect( decline.top ).toBe( install.top );
		expect(
			direction === 'rtl'
				? install.left - decline.right
				: decline.left - install.right
		).toBe( SPACE_SM );

		// The recovery link and the button that saves it share a line at this width or the
		// button goes under: either way neither reaches past the panel.
		const link = await box( confirm.locator( '#corex-releases-recovery' ) );
		const save = await box(
			confirm.getByRole( 'button', { name: 'Save it as a file' } )
		);
		expect( Math.min( link.left, save.left ) ).toBeGreaterThanOrEqual(
			panel.left + SPACE_LG
		);
		expect( Math.max( link.right, save.right ) ).toBeLessThanOrEqual(
			panel.right - SPACE_LG
		);

		const overflow = await page
			.locator( '.corex-admin__content' )
			.evaluate(
				( content ) => content.scrollWidth - content.clientWidth
			);
		expect( overflow ).toBeLessThanOrEqual( 0 );

		// The words can be read on the panel they are on.
		const { ground } = await paint( confirm );
		for ( const words of [
			'.corex-releases__subheading',
			'.corex-releases__line',
			'.corex-releases__label',
		] ) {
			const { ink } = await paint( confirm.locator( words ).first() );
			expect(
				contrast( ink, ground ),
				`${ words } on its panel`
			).toBeGreaterThanOrEqual( READABLE );
		}

		// Not confirmed until the version is typed; and declining leaves the site as it was.
		await expect(
			confirm.getByRole( 'button', {
				name: `Install CoreX ${ VERSION }`,
			} )
		).toBeDisabled();
		await confirm.getByRole( 'button', { name: 'Do not install' } ).click();
		await expect( page.locator( '.corex-releases__confirm' ) ).toHaveCount(
			0
		);
		await expect(
			page.getByRole( 'button', { name: 'Send to the site' } )
		).toBeVisible();
	} );
}

test( 'a release is installed from the screen, and a visitor is held off while it is put in place', async ( {
	page,
	playwright,
	baseURL,
} ) => {
	const before = leaveAnEarlierRelease();
	const overview = await openReleases( page );
	onlyWhereTheHostCanInstall( overview );
	const client = await askToInstall( page, overview );
	const confirm = page.locator( '.corex-releases__confirm' );

	// The way back is there before anything is confirmed, and can be kept as a file.
	await expect( confirm.locator( '#corex-releases-recovery' ) ).toHaveValue(
		/corex_release_key=[0-9a-f]{64}&corex_release_recover=1$/
	);
	const saved = page.waitForEvent( 'download' );
	await confirm.getByRole( 'button', { name: 'Save it as a file' } ).click();
	expect( ( await saved ).suggestedFilename() ).toBe(
		`corex-release-recovery-${ VERSION }.txt`
	);

	// The request that finishes the installation is held on its way: the files are in place
	// by then, and what a visitor gets in that moment can be looked at.
	let letItFinish;
	const filesInPlace = new Promise( ( held ) => {
		page.route(
			( url ) => /install\/finish/.test( decodeURIComponent( url.href ) ),
			async ( route ) => {
				await new Promise( ( release ) => {
					letItFinish = release;
					held();
				} );
				await route.continue();
			}
		);
	} );

	await confirm.locator( '#corex-releases-version' ).fill( VERSION );
	if ( overview.installed.known === false && client ) {
		await confirm.locator( '#corex-releases-client' ).fill( client );
	}
	await confirm
		.getByRole( 'button', { name: `Install CoreX ${ VERSION }` } )
		.click();
	await filesInPlace;

	// The screen says where the site is, in words.
	await expect(
		page.locator( '.corex-releases__step[data-corex-step="now"]' )
	).toContainText( 'Bring the database to the release' );

	// A visitor, signed out, in a browser that knows nothing of the installation.
	const visitor = await playwright.request.newContext( { baseURL } );
	const heldOff = await visitor.get( '/' );
	expect( heldOff.status() ).toBe( 503 );
	expect( await heldOff.text() ).toContain( 'maintenance' );

	letItFinish();

	await expect(
		page.getByRole( 'heading', { name: `CoreX ${ VERSION } is installed` } )
	).toBeFocused();
	await expect( page.locator( '.corex-releases__installed' ) ).toContainText(
		'No change to the database was needed.'
	);
	expect( ( await visitor.get( '/' ) ).status() ).toBe( 200 );
	await visitor.dispose();

	// The folder is the release's, whole: what the release before left in it is not on the
	// site any more, and is with the release that was kept.
	if ( before !== null ) {
		const after = JSON.parse(
			wpEval(
				THE_INSTALLER +
					"$kept = $make( 'ReleaseStore' )->folder( 'previous' ) . '/wp-content/plugins/corex-e2e-released';" +
					'echo wp_json_encode( [' +
					"'files' => array_values( array_diff( scandir( $folder ), [ '.', '..' ] ) )," +
					"'release' => str_contains( (string) file_get_contents( \"$folder/corex-e2e-released.php\" ), '" +
					VERSION +
					"' )," +
					'\'kept\' => is_file( "$kept/left-behind.php" ),' +
					"'maintenance' => is_file( ABSPATH . '.maintenance' ) ] );"
			)
		);

		expect( after ).toEqual( {
			files: [ 'corex-e2e-released.php' ],
			release: true,
			kept: true,
			maintenance: false,
		} );
	}

	await page.getByRole( 'button', { name: 'Done' } ).click();
	await expect(
		page.locator( '.corex-releases__panel' ).first()
	).toContainText( `This site is running CoreX ${ VERSION }` );
} );

test( 'the site goes back to the release before from the screen, and what happened is on it', async ( {
	page,
} ) => {
	const overview = await openReleases( page );
	onlyWhereTheHostCanInstall( overview );
	// The test above installed the release; this one leaves it.
	expect( overview.previous, 'a release to go back to' ).not.toBeNull();

	const kept = page.locator( '.corex-releases__kept' );
	const history = page.locator( '.corex-releases__history' );

	// What is kept, and the two panels that say so, in the screen's one column.
	await expect( kept ).toContainText( `CoreX ${ VERSION } replaced it on` );
	const [ above, keptBox, historyBox ] = await Promise.all( [
		box(
			page
				.locator(
					'.corex-releases .corex-loadable__body > .corex-releases__panel'
				)
				.last()
		),
		box( kept ),
		box( history ),
	] );
	expect( keptBox.left ).toBe( above.left );
	expect( keptBox.right ).toBe( above.right );
	expect( keptBox.top - above.bottom ).toBe( SPACE_LG );
	expect( historyBox.left ).toBe( above.left );
	expect( historyBox.top - keptBox.bottom ).toBe( SPACE_LG );

	// The record says who installed it, and can be read on its panel.
	const installed = history.locator( '.corex-releases__entry' ).first();
	await expect( installed ).toContainText(
		`CoreX ${ VERSION } was installed`
	);
	const { ground } = await paint( history );
	for ( const words of [ '.corex-releases__what', '.corex-releases__who' ] ) {
		const { ink } = await paint( installed.locator( words ) );
		expect(
			contrast( ink, ground ),
			`${ words } on its panel`
		).toBeGreaterThanOrEqual( READABLE );
	}

	// Going back: what it does not undo is said before the confirmation is asked for.
	await kept.getByRole( 'button', { name: /^Go back to .*…$/ } ).click();
	await expect( kept ).toContainText(
		'It reverses no change to the database'
	);
	const goBack = kept.getByRole( 'button', { name: /^Go back to [^…]*$/ } );
	await expect( goBack ).toBeDisabled();
	await kept
		.locator( '#corex-releases-return' )
		.fill( overview.previous.phrase );
	await goBack.click();

	await expect(
		page.getByRole( 'heading', { name: /^The site is back on / } )
	).toBeFocused();
	await expect( page.locator( '.corex-releases__returned' ) ).toContainText(
		'No change to the database was reversed.'
	);
	await expect( page.locator( '.corex-releases__kept' ) ).toHaveCount( 0 );
	await expect(
		history.locator( '.corex-releases__entry' ).first()
	).toContainText( 'The site went back' );

	// The folder is as the release before left it, the file the newer one did not ship
	// included, and there is no way back left on the site because there is nothing to go
	// back to.
	const after = wpEval(
		THE_INSTALLER +
			'echo wp_json_encode( [' +
			"'files' => array_values( array_diff( scandir( $folder ), [ '.', '..' ] ) )," +
			"'recovery' => is_file( WPMU_PLUGIN_DIR . '/corex-release-recovery.php' )," +
			"'maintenance' => is_file( ABSPATH . '.maintenance' ) ] );"
	);
	if ( after !== null ) {
		expect( JSON.parse( after ) ).toEqual( {
			files: [ 'corex-e2e-released.php', 'left-behind.php' ],
			recovery: false,
			maintenance: false,
		} );
	}
} );

test( 'a release is installed where the host does not hand the key’s header to PHP', async ( {
	page,
} ) => {
	const overview = await openReleases( page );
	onlyWhereTheHostCanInstall( overview );
	const client = await askToInstall( page, overview );
	const confirm = page.locator( '.corex-releases__confirm' );

	// Every request of the installation reaches the site without the header, as a host that
	// drops it hands them to PHP. Only the address carries the key now.
	const stripped = [];
	await page.route(
		( url ) =>
			/install\/(confirm|step|finish)/.test(
				decodeURIComponent( url.href )
			),
		async ( route ) => {
			const headers = { ...route.request().headers() };
			delete headers[ 'x-corex-release-key' ];
			stripped.push(
				new URL( route.request().url() ).searchParams.has(
					'corex_release_key'
				)
			);
			await route.continue( { headers } );
		}
	);

	await confirm.locator( '#corex-releases-version' ).fill( VERSION );
	if ( overview.installed.known === false && client ) {
		await confirm.locator( '#corex-releases-client' ).fill( client );
	}
	await confirm
		.getByRole( 'button', { name: `Install CoreX ${ VERSION }` } )
		.click();

	// Through WordPress's maintenance at once, not when its ten minutes are up.
	await expect(
		page.getByRole( 'heading', { name: `CoreX ${ VERSION } is installed` } )
	).toBeFocused( { timeout: 60000 } );
	expect( stripped.length ).toBeGreaterThan( 2 );
	expect( stripped.every( Boolean ), 'the key is in every address' ).toBe(
		true
	);

	// The record says how the finishing request got through.
	const recorded = wpEval(
		THE_INSTALLER +
			"$log = $make( 'ReleaseStore' )->log(); $last = end( $log );" +
			"echo wp_json_encode( [ 'event' => $last['event'] ?? '', 'key_from' => $last['key_from'] ?? '', 'maintenance' => is_file( ABSPATH . '.maintenance' ) ] );"
	);
	if ( recorded !== null ) {
		expect( JSON.parse( recorded ) ).toEqual( {
			event: 'installed',
			key_from: 'address',
			maintenance: false,
		} );
	}
} );
