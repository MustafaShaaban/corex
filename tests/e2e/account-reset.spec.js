/**
 * A member who forgot their password gets back in without leaving the site (spec 068,
 * FR-158a to FR-158c).
 *
 * Measured on 2026-10-13: the account block's reset email carried WordPress's own link, the
 * member chose the new password on `wp-login.php` and was sent on to `wp-admin`, and the
 * account page had no form that takes a reset key. Where login protection hides the login,
 * the link was the hidden address (DECISIONS #340).
 *
 * This is the walk a member makes, in a browser because three things in it exist nowhere
 * else: the form's script sends the page mark, the key is taken out of the address before
 * the page is drawn, and the page says what happened once it is loaded again.
 *
 * No email leaves: the fixture `fixtures/corex-e2e-reset-mail.php` keeps the one this
 * browser's request would have sent, and it is read through WP-CLI. Without WP-CLI the walk
 * cannot be made and is skipped.
 */
const { test, expect } = require( '@playwright/test' );
const { wpEval } = require( './helpers' );

const STAMP = Date.now();
const TAG = `walk${ STAMP }`;
const LOGIN = `corex-reset-e2e-${ STAMP }`;
const FIRST_PASSWORD = `first-${ STAMP }-passphrase`;
const NEW_PASSWORD = `new-${ STAMP }-passphrase`;
const KEY_IN_ADDRESS = /corex_reset_(key|login)=/;

let pageId = 0;
let accountUrl = '';
let usedLink = '';

/**
 * The email this browser's reset request would have sent.
 *
 * @return {string} Its text, or an empty string when none was kept.
 */
function keptEmail() {
	return (
		wpEval( `echo get_transient( 'corex_e2e_reset_mail_${ TAG }' );` ) || ''
	);
}

test.use( {
	// A visitor, whose requests have their email kept instead of sent.
	storageState: { cookies: [], origins: [] },
	extraHTTPHeaders: { 'X-Corex-E2E-Reset-Mail': TAG },
} );

test.beforeAll( () => {
	const made = wpEval(
		`$user = wp_insert_user( [
			'user_login' => '${ LOGIN }',
			'user_email' => '${ LOGIN }@example.com',
			'user_pass'  => '${ FIRST_PASSWORD }',
			'role'       => 'subscriber',
		] );
		$page = wp_insert_post( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Account reset walk ${ STAMP }',
			'post_content' => '<!-- wp:corex/account /-->',
		] );
		echo wp_json_encode( [ 'page' => $page, 'url' => get_permalink( $page ), 'user' => is_int( $user ) ] );`
	);
	test.skip( made === null, 'WP-CLI is not available on this machine.' );

	const answer = JSON.parse( made );
	expect( answer.user, 'the member was created' ).toBe( true );
	pageId = answer.page;
	accountUrl = answer.url;
} );

test.afterAll( () => {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/user.php';
		$user = get_user_by( 'login', '${ LOGIN }' );
		if ( $user ) { wp_delete_user( $user->ID ); }
		wp_delete_post( ${ pageId }, true );
		delete_transient( 'corex_e2e_reset_mail_${ TAG }' );`
	);
} );

// In order: the second is about the link the first has used.
test.describe.configure( { mode: 'serial' } );

test( 'a forgotten password is reset on the account page', async ( {
	page,
} ) => {
	await page.goto( accountUrl );

	const forgot = page.locator( '[data-corex-account-form="reset-request"]' );
	// By name: the block's forms share field names, and a label is not the test here.
	await forgot.locator( 'input[name="login"]' ).fill( LOGIN );
	await forgot.getByRole( 'button', { name: 'Email a reset link' } ).click();
	await expect( page.getByText( 'If an account matches' ) ).toBeVisible();

	// The email names the page the reset was asked from, and no screen of WordPress's.
	const email = keptEmail();
	const [ link ] = email.match( /\S*key=\S*/ ) || [ '' ];
	expect( link.startsWith( accountUrl ), `the link in: ${ email }` ).toBe(
		true
	);
	expect( email ).not.toContain( 'wp-login.php' );

	// The key is out of the address before the page is drawn, and out of the page's links.
	await page.goto( link );
	expect( page.url() ).not.toMatch( KEY_IN_ADDRESS );
	expect( await page.content() ).not.toMatch( KEY_IN_ADDRESS );

	// The new-password form, and no other.
	const reset = page.locator( '[data-corex-account-form="reset"]' );
	await expect(
		reset.getByRole( 'heading', { name: 'Choose a new password' } )
	).toBeVisible();
	await expect( page.locator( '[data-corex-account] form' ) ).toHaveCount(
		1
	);

	await reset
		.getByLabel( 'New password', { exact: true } )
		.fill( NEW_PASSWORD );
	await reset.getByLabel( 'Confirm new password' ).fill( NEW_PASSWORD );
	await reset.getByRole( 'button', { name: 'Save password' } ).click();

	// The page is loaded again: it says so, and offers the sign-in form.
	const signIn = page.locator( '[data-corex-account-form="login"]' );
	await expect( signIn ).toBeVisible();
	await expect(
		page.getByText( 'Your password has been updated. You can now sign in.' )
	).toBeVisible();

	await signIn.locator( 'input[name="login"]' ).fill( LOGIN );
	await signIn.locator( 'input[name="password"]' ).fill( NEW_PASSWORD );
	await signIn.getByRole( 'button', { name: 'Sign in' } ).click();
	await expect(
		page.getByRole( 'heading', { name: 'Your account' } )
	).toBeVisible();

	usedLink = link;
} );

test( 'a link whose key was used says so, and a new one can be asked for', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 375, height: 800 } );
	await page.goto( usedLink );

	// Above the forms and in the window when the page opens, on a phone too: the form
	// that asks for a new link is the third, and nobody pressed its button.
	const note = page.locator(
		'[data-corex-account] > .corex-account__status:first-child'
	);
	await expect( note ).toHaveText(
		'This password reset link is invalid or has expired. Please ask for a new one.'
	);
	await expect( note ).toBeInViewport( { ratio: 1 } );

	// It stands off the forms by the block's own gap, as the block's parts do.
	const gaps = await page
		.locator( '[data-corex-account]' )
		.evaluate( ( block ) => {
			const forms = block.querySelector( '.corex-account__grid' );
			return {
				noteToForms: Math.round(
					forms.getBoundingClientRect().top -
						block.firstElementChild.getBoundingClientRect().bottom
				),
				block: Math.round(
					parseFloat( window.getComputedStyle( block ).rowGap )
				),
			};
		} );
	expect( gaps.noteToForms ).toBe( gaps.block );

	const forgot = page.locator( '[data-corex-account-form="reset-request"]' );
	await expect( forgot ).toBeVisible();
	await expect(
		page.locator( '[data-corex-account-form="reset"]' )
	).toHaveCount( 0 );

	// The visitor's next answer takes the note's place.
	await forgot.locator( 'input[name="login"]' ).fill( LOGIN );
	await forgot.getByRole( 'button', { name: 'Email a reset link' } ).click();
	await expect( forgot.getByText( 'If an account matches' ) ).toBeVisible();
	await expect( note ).toBeEmpty();
} );

test( 'the new-password form is laid out as the other account forms are', async ( {
	page,
} ) => {
	const link = wpEval(
		`$user = get_user_by( 'login', '${ LOGIN }' );
		echo ( new \\Corex\\Profile\\Reset\\ResetLink() )->emailed( ${ pageId }, $user->user_login, get_password_reset_key( $user ) );`
	);
	test.skip( ! link, 'WP-CLI is not available on this machine.' );

	for ( const width of [ 1280, 375 ] ) {
		await page.setViewportSize( { width, height: 800 } );
		await page.goto( link );

		const form = page.locator( '[data-corex-account-form="reset"]' );
		await expect( form ).toBeVisible();

		const boxes = await form.evaluate( ( el ) => {
			const box = ( node ) => {
				const { left, right, width: w } = node.getBoundingClientRect();
				return { left, right, width: w };
			};
			return {
				form: box( el ),
				padding: parseFloat(
					window.getComputedStyle( el ).paddingInlineEnd
				),
				fields: Array.from(
					el.querySelectorAll( 'input[type="password"]' ),
					box
				),
				button: box( el.querySelector( 'button' ) ),
				sideways:
					document.documentElement.scrollWidth >
					document.documentElement.clientWidth,
			};
		} );

		expect( boxes.sideways, `no sideways scroll at ${ width }` ).toBe(
			false
		);
		expect( boxes.fields ).toHaveLength( 2 );
		// Both fields and the button start on one line, inside the form.
		for ( const field of boxes.fields ) {
			expect( field.left ).toBe( boxes.fields[ 0 ].left );
			expect( field.width ).toBe( boxes.fields[ 0 ].width );
			// Inside the form's padding, not only inside its border.
			expect( field.right ).toBeLessThanOrEqual(
				boxes.form.right - boxes.padding
			);
		}
		expect( boxes.button.left ).toBe( boxes.fields[ 0 ].left );
	}
} );
