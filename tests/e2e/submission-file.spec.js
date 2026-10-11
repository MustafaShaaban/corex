/**
 * A file sent with a form is opened from the Submissions inbox (spec 081, SC-002).
 *
 * Reported on 2026-10-11 from a client site, with the words "where is the attachment": the
 * inbox showed `224` under "Answers", the number the file is stored under, and nothing to
 * press. This is the walk a client makes: a visitor sends a form with a file, and whoever
 * reads the submission opens that file from it.
 *
 * It is the only test that uploads. WordPress stores a file only when the web server received
 * it, so the upload cannot be made from the PHP suites. The form is the fixture
 * `fixtures/corex-e2e-file-form.php`.
 */
const { test, expect, request } = require( '@playwright/test' );
const { wpEval } = require( './helpers' );

const STAMP = Date.now();
const EMAIL = `corex-file-e2e-${ STAMP }@example.com`;
const FORM = 'corex-e2e-file-form';
const FILE_NAME = `corex-e2e-cv-${ STAMP }.pdf`;

// The smallest file a PDF reader opens: WordPress reads the content, not the name.
const PDF = Buffer.from(
	[
		'%PDF-1.4',
		'1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
		'2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj',
		'3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj',
		'trailer<</Root 1 0 R>>',
		'%%EOF',
		'',
	].join( '\n' )
);

let pageId = 0;

/**
 * Ask WordPress's own REST API as the signed-in administrator.
 *
 * @param {import('@playwright/test').Page} page   A page that is in the admin.
 * @param {string}                          method The request's method.
 * @param {string}                          route  The route, from the REST root.
 * @param {Object}                          [data] What to send.
 * @return {Promise<Object>} What it answered.
 */
async function wordpress( page, method, route, data ) {
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );
	const response = await page.request.fetch( `/?rest_route=${ route }`, {
		method,
		headers: { 'X-WP-Nonce': nonce },
		data,
	} );
	expect(
		response.ok(),
		`${ method } ${ route }: ${ response.status() }`
	).toBe( true );

	return response.json();
}

test.afterEach( () => {
	// The page, the submission and its file. Left on the install when WP-CLI does not answer.
	wpEval(
		`wp_delete_post( ${ pageId }, true ); foreach ( get_posts( [ 'post_type' => 'corex_submission', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'corex_field_email', 'meta_value' => '${ EMAIL }' ] ) as $id ) {` +
			` wp_delete_attachment( (int) get_post_meta( $id, 'corex_field_cv', true ), true ); wp_delete_post( $id, true ); }`
	);
} );

test( 'opens a file sent with a form from its submission, by name, and gives it to nobody signed out', async ( {
	page,
	baseURL,
} ) => {
	await page.goto( '/wp-admin/' );
	const made = await wordpress( page, 'POST', '/wp/v2/pages', {
		title: `File form ${ STAMP }`,
		status: 'publish',
		content: `<!-- wp:corex/form {"formSlug":"${ FORM }"} /-->`,
	} );
	pageId = made.id;

	// A visitor sends the form with a file.
	await page.goto( made.link );
	const form = page.locator( 'form[data-corex-schema]' );
	await form.locator( 'input[name="name"]' ).fill( 'Salma Fixture' );
	await form.locator( 'input[name="email"]' ).fill( EMAIL );
	await form.locator( 'input[type="file"][name="cv"]' ).setInputFiles( {
		name: FILE_NAME,
		mimeType: 'application/pdf',
		buffer: PDF,
	} );
	await form.getByRole( 'button', { name: /send|submit/i } ).click();
	// Accepted, and not only answered: a file the site could not keep is answered too.
	await expect( form.locator( '.corex-form__status' ) ).toHaveClass(
		/is-success/
	);

	// Whoever reads the submission finds the file under the question, by its name.
	await page.goto( '/wp-admin/admin.php?page=corex-submissions' );
	// By its form: the search does not look in the answers of a form defined in code.
	await page.getByLabel( 'Search' ).fill( FORM );
	const row = page
		.locator( '.corex-inbox__table tbody tr' )
		.filter( { hasText: EMAIL } )
		.first();
	await expect( row ).toBeVisible();
	// The list is drawn again for the search, and is not pressed until it has settled.
	await expect( page.locator( '.corex-inbox' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	await row.getByRole( 'button' ).focus();
	await page.keyboard.press( 'Enter' );
	const pane = page.getByRole( 'dialog' );
	const answer = pane
		.locator( '.corex-pane__fields div' )
		.filter( { has: page.locator( 'dt', { hasText: /^Your CV$/ } ) } )
		.locator( 'dd' );
	const link = answer.getByRole( 'link' );

	await expect( link ).toContainText( `corex-e2e-cv-${ STAMP }` );
	await expect( link ).toContainText( '.pdf' );
	await expect( answer ).not.toHaveText( /^\d+$/ );

	// Pressing it gives the file that was sent, as a download.
	const address = await link.getAttribute( 'href' );
	const opened = await page.request.get( address );

	expect( opened.status() ).toBe( 200 );
	expect( opened.headers()[ 'content-disposition' ] ).toContain(
		'attachment'
	);
	expect( opened.headers()[ 'content-type' ] ).toContain( 'application/pdf' );
	expect( ( await opened.body() ).equals( PDF ) ).toBe( true );

	// The same address, from a browser that is not signed in: no file.
	// With no cookies of its own, a new context is given the suite's signed-in ones.
	const signedOut = await request.newContext( {
		baseURL,
		storageState: { cookies: [], origins: [] },
	} );
	const refused = await signedOut.get( address );

	expect( refused.status() ).toBe( 403 );
	expect( ( await refused.body() ).includes( '%PDF' ) ).toBe( false );
	await signedOut.dispose();
} );
