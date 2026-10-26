/**
 * Email Studio's preview and test send are the email that is sent (spec 120, story 1), its plain
 * text is what goes out (story 2), and a kept email shows its plain-text version (story 7).
 *
 * Runs against a site that keeps its mail (operations mode Development), so a test send is
 * captured and nobody receives it.
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

/**
 * Opens Email Studio and one of the templates CoreX ships, by its name.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string}                          name The template's name.
 */
async function openTemplate( page, name ) {
	await page.goto( '/wp-admin/admin.php?page=corex-email-studio' );
	await expect(
		page.locator( '.corex-email-app > .corex-loadable' )
	).toHaveAttribute( 'data-corex-state', 'ready' );
	await page
		.getByRole( 'button', { name: 'Templates', exact: true } )
		.click();
	const chosen = page.locator( '.corex-email-app__list button', {
		hasText: name,
	} );
	test.skip(
		( await chosen.count() ) === 0,
		`This site has no "${ name }" template: CoreX installs it on a new site.`
	);
	await chosen.first().click();
	await expect( page.getByRole( 'heading', { name, level: 2 } ) ).toBeVisible();
}

/** The email in the preview, once the server has drawn it. */
function previewFrame( page ) {
	return page.frameLocator( 'iframe[title="The email as it is sent"]' );
}

test( 'shows the email the server draws, framed, with sample values that can be changed', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	await openTemplate( page, 'Admin notification' );
	await page.getByRole( 'button', { name: 'Preview', exact: true } ).click();

	const email = previewFrame( page );
	// The frame's footer, which the browser never drew before (spec 120, story 3).
	await expect( email.getByText( 'This message was sent by' ) ).toBeVisible();
	await expect( email.getByText( 'Contact form' ).first() ).toBeVisible();
	await expect(
		page.getByRole( 'complementary', { name: 'Sample values' } )
	).toBeVisible();

	await page.getByLabel( 'Form title' ).fill( 'Quote request' );
	await expect( email.getByText( 'Quote request' ).first() ).toBeVisible();

	await page.getByRole( 'button', { name: 'Mobile', exact: true } ).click();
	await expect( page.locator( '.corex-email-app__preview-frame' ) ).toHaveCSS(
		'max-width',
		'375px'
	);
	await page
		.getByRole( 'button', { name: 'Right to left', exact: true } )
		.click();
	await expect( email.locator( 'html' ) ).toHaveAttribute( 'dir', 'rtl' );

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'shows the plain text that is sent, and names a variable that cannot be drawn', async ( {
	page,
} ) => {
	await openTemplate( page, 'Contact confirmation' );
	await page
		.getByRole( 'button', { name: 'Plain text', exact: true } )
		.click();
	await expect(
		page.getByRole( 'heading', { name: 'Sent as the plain-text version' } )
	).toBeVisible();
	await expect( page.locator( '.corex-email-app__plain' ) ).toContainText(
		'This message was sent by'
	);

	// An unknown variable, typed and not saved: the preview names it in place of the email.
	await page
		.getByRole( 'button', { name: 'Templates', exact: true } )
		.click();
	const body = page.getByLabel( 'HTML body' );
	await body.fill( `${ await body.inputValue() }{{ nonsense.key }}` );
	await page.getByRole( 'button', { name: 'Test send', exact: true } ).click();
	await expect(
		page.getByText( 'Unknown variable: nonsense.key.' )
	).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Send test', exact: true } )
	).toHaveCount( 0 );
} );

test( 'sends the previewed email as a test, refusing an address that is not one', async ( {
	page,
} ) => {
	await openTemplate( page, 'Contact confirmation' );
	await page.getByRole( 'button', { name: 'Test send', exact: true } ).click();

	const to = page.getByLabel( 'Send the test to' );
	await to.fill( 'not-an-address' );
	await page.getByRole( 'button', { name: 'Send test', exact: true } ).click();
	await expect(
		page.getByText(
			'Enter a valid email address: not-an-address is not one.'
		)
	).toBeVisible();

	await to.fill( 'me@example.test' );
	await page.getByRole( 'button', { name: 'Send test', exact: true } ).click();
	await expect(
		page.getByText( 'Captured: nothing left this site' )
	).toBeVisible();
	const toTheLog = page.getByRole( 'link', {
		name: 'Open it in the delivery log',
	} );
	await expect( toTheLog ).toHaveAttribute( 'href', /tab=logs#corex-attempt-/ );

	// The kept email holds both versions, as it would have arrived.
	await toTheLog.click();
	const kept = page
		.locator( '.corex-email-app__captures li', {
			hasText: '[Test] Thanks, we have your message',
		} )
		.first();
	await kept.getByText( 'Show the email', { exact: true } ).click();
	await expect( kept.locator( '.corex-email-app__plain' ) ).toContainText(
		'Thanks, we have your message.'
	);
} );

test( 'keeps the preview and the test send inside a phone\'s width', async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 375, height: 812 } );
	await openTemplate( page, 'Admin notification' );
	await page.getByRole( 'button', { name: 'Preview', exact: true } ).click();
	await expect(
		previewFrame( page ).getByText( 'This message was sent by' )
	).toBeVisible();

	const fits = await page.evaluate(
		() =>
			document.documentElement.scrollWidth <=
			document.documentElement.clientWidth
	);
	expect( fits ).toBe( true );
} );
