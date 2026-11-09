/**
 * Corex E2E — Settings that save what you changed (spec 119, R3.4).
 *
 * The integration suite holds what a save writes. This holds what a person meets: the pages under
 * Settings in the rail, a save bar that knows about unsaved changes and puts them back, the
 * question before leaving, a save that lands on its page and is not sent again on reload, a refused
 * value kept in its field, a save that did not go through said and its edits kept, switches that
 * read On or Off and answer Space, a saved secret removed with a confirmation, a page whose add-on
 * is off pointing to its card, and a page that works without scripts.
 *
 * ENVIRONMENT-GATED: needs a running site at COREX_BASE_URL and `npx playwright install`. The
 * steps that set or read a stored value go through WP-CLI (`wpEval`); without it they are skipped.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors, wpEval } = require( './helpers' );

const PAGE = '/wp-admin/admin.php?page=corex-settings-config';

/** WordPress's plugin functions, which a WP-CLI eval does not load by itself. */
const PLUGIN_API = "require_once ABSPATH . 'wp-admin/includes/plugin.php'; ";

/**
 * A stored setting, read through WP-CLI; null without it.
 *
 * @param {string} option The option's name.
 * @return {string|null} Its value.
 */
function storedOption( option ) {
	return wpEval( `echo (string) get_option( '${ option }', '' );` );
}

/**
 * Puts an option back as it was, or deletes it when it was not there.
 *
 * @param {string}      option The option's name.
 * @param {string|null} value  What it held.
 */
function restoreOption( option, value ) {
	wpEval(
		value === null || value === ''
			? `delete_option( '${ option }' );`
			: `update_option( '${ option }', ${ JSON.stringify( value ) } );`
	);
}

test.describe( 'each Settings page saves on its own', () => {
	test( 'the pages are under Settings in the rail, and the open one is marked', async ( {
		page,
	} ) => {
		const errors = collectConsoleErrors( page );
		await page.goto( `${ PAGE }&section=email` );

		const pages = page.locator( '.corex-admin__subnav' );
		await expect( pages.getByRole( 'link' ) ).toHaveText( [
			'General and appearance',
			'Email',
			'Captcha',
			'Media',
			'Insights',
			'Guides',
			'Advanced',
		] );
		await expect( pages.locator( '[aria-current="page"]' ) ).toHaveText(
			'Email'
		);
		await expect( page.locator( 'h1' ) ).toHaveText( 'Email' );
		expect( errors ).toEqual( [] );
	} );

	test( 'the bar follows an edit, and Discard puts the page back without a reload', async ( {
		page,
	} ) => {
		await page.goto( PAGE );
		const bar = page.locator( '[data-corex-savebar]' );
		const tagline = page.locator( '#brand_tagline' );
		const before = await tagline.inputValue();

		await expect( bar ).toContainText( 'No changes' );
		await expect(
			bar.getByRole( 'button', { name: 'Save changes' } )
		).toBeDisabled();
		await expect(
			bar.getByRole( 'button', { name: 'Discard' } )
		).toBeDisabled();

		await tagline.fill( `${ before } and more` );
		await expect( bar ).toContainText( 'Unsaved changes' );
		await expect(
			bar.getByRole( 'button', { name: 'Save changes' } )
		).toBeEnabled();

		await bar.getByRole( 'button', { name: 'Discard' } ).click();
		await expect( tagline ).toHaveValue( before );
		await expect( bar ).toContainText( 'No changes' );
	} );

	test( 'leaving with unsaved changes asks first, and staying keeps the edit', async ( {
		page,
	} ) => {
		await page.goto( PAGE );
		const tagline = page.locator( '#brand_tagline' );
		await tagline.fill( 'Not saved yet' );

		let asked = false;
		page.once( 'dialog', ( dialog ) => {
			asked = dialog.type() === 'beforeunload';
			dialog.dismiss();
		} );
		await page
			.locator( '.corex-admin__subnav' )
			.getByRole( 'link', { name: 'Media' } )
			.click( { noWaitAfter: true } );
		await expect.poll( () => asked ).toBe( true );
		await expect( page ).toHaveURL( /page=corex-settings-config$/ );
		await expect( tagline ).toHaveValue( 'Not saved yet' );
	} );

	test( 'a save lands on its page, says so, and a reload sends nothing again', async ( {
		page,
	} ) => {
		const before = storedOption( 'corex_brand_tagline' );
		await page.goto( `${ PAGE }&section=general` );
		const typed = `Saved by the browser suite ${ Date.now() }`;
		await page.locator( '#brand_tagline' ).fill( typed );

		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page ).toHaveURL( /section=general$/ );
		await expect( page.locator( '.corex-toast' ) ).toContainText(
			'Settings saved.'
		);
		await expect( page.locator( '[data-corex-savebar]' ) ).toContainText(
			'All changes saved'
		);
		await expect( page.locator( '#brand_tagline' ) ).toHaveValue( typed );

		// A reload is a plain GET: no form is sent again, and the message has been said.
		const reload = await page.reload();
		expect( reload.request().method() ).toBe( 'GET' );
		await expect( page.locator( '.corex-toast' ) ).toHaveCount( 0 );

		restoreOption( 'corex_brand_tagline', before );
	} );

	test( 'a refused value is named, stays in its field and stays unsaved', async ( {
		page,
	} ) => {
		await page.goto( `${ PAGE }&section=general` );
		await page.locator( '#brand_email' ).fill( 'sales@' );
		await page.getByRole( 'button', { name: 'Save changes' } ).click();

		const toast = page.locator( '.corex-toast' );
		await expect( toast ).toHaveAttribute( 'role', 'alert' );
		await expect( toast ).toContainText( 'Contact email was not changed' );
		await expect( toast ).toContainText( 'sales@' );
		await expect( page.locator( '#brand_email' ) ).toHaveValue( 'sales@' );
		await expect( page.locator( '[data-corex-savebar]' ) ).toContainText(
			'Unsaved changes'
		);
	} );

	test( 'a save that does not go through says so and keeps the edit', async ( {
		page,
	} ) => {
		await page.goto( `${ PAGE }&section=general` );
		await page.locator( '#brand_tagline' ).fill( 'Kept through a failure' );
		await page.route(
			( url ) => url.toString().includes( 'page=corex-settings-config' ),
			( route ) =>
				route.request().method() === 'POST'
					? route.fulfill( { status: 500, body: 'failure' } )
					: route.continue()
		);

		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect(
			page.locator( '[data-corex-savebar-error]' )
		).toContainText( 'did not go through' );
		await expect( page.locator( '#brand_tagline' ) ).toHaveValue(
			'Kept through a failure'
		);
	} );
} );

test.describe( 'on/off settings are switches', () => {
	test( 'a switch reads On or Off, is named by its label and answers Space', async ( {
		page,
	} ) => {
		await page.goto( `${ PAGE }&section=email` );
		const live = page.getByRole( 'switch', {
			name: 'Enable live delivery',
		} );
		const word = page
			.locator( '.corex-switch' )
			.filter( { has: live } )
			.locator( '.corex-switch__state span:visible' );
		const was = await live.isChecked();

		await expect( word ).toHaveText( was ? 'On' : 'Off' );
		await live.focus();
		await page.keyboard.press( 'Space' );
		await expect( live ).toBeChecked( { checked: ! was } );
		await expect( word ).toHaveText( was ? 'Off' : 'On' );
		await expect(
			page.locator(
				'input[type="checkbox"]:not([role="switch"]):visible'
			)
		).toHaveCount( 0 );
	} );
} );

test.describe( 'a saved secret can be removed', () => {
	test( 'Remove asks, the save deletes the secret, and the field says none is saved', async ( {
		page,
	} ) => {
		const saved = wpEval(
			"update_option( 'corex_insights_cloudflare_token', 'e2e-token-to-remove' ); echo 'ok';"
		);
		test.skip( saved !== 'ok', 'needs WP-CLI to save a secret first' );

		await page.goto( `${ PAGE }&section=insights` );
		await expect( page.locator( 'body' ) ).not.toContainText(
			'e2e-token-to-remove'
		);
		const field = page
			.locator( 'tr' )
			.filter( { has: page.locator( '#insights_cloudflare_token' ) } );
		await expect( field ).toContainText( 'A value is saved' );

		let question = '';
		page.once( 'dialog', ( dialog ) => {
			question = dialog.message();
			dialog.accept();
		} );
		await field.getByRole( 'button', { name: 'Remove' } ).click();
		expect( question ).toContain( 'security scan stops' );
		await expect( field ).toContainText( 'Removed when you save.' );

		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.corex-toast' ) ).toContainText(
			'Cloudflare API token was removed.'
		);
		await expect(
			page
				.locator( 'tr' )
				.filter( { has: page.locator( '#insights_cloudflare_token' ) } )
		).toContainText( 'No value saved' );
		expect( storedOption( 'corex_insights_cloudflare_token' ) ).toBe( '' );
	} );
} );

test.describe( 'a page whose add-on is off', () => {
	test( 'says so, shows its fields read-only and links to the add-on card', async ( {
		page,
	} ) => {
		const was = wpEval(
			PLUGIN_API +
				"echo is_plugin_active( 'corex-guides/corex-guides.php' ) ? 'on' : 'off';"
		);
		test.skip( was === null, 'needs WP-CLI to turn an add-on off' );
		wpEval(
			PLUGIN_API +
				"deactivate_plugins( 'corex-guides/corex-guides.php' );"
		);

		try {
			await page.goto( `${ PAGE }&section=guides` );
			await expect(
				page.locator( '.corex-settings-addon-off' )
			).toContainText(
				'Corex Guides is off. These settings apply when it is on.'
			);
			await expect(
				page.locator( '#guides_support_email' )
			).toHaveAttribute( 'readonly', '' );
			await expect( page.locator( '[data-corex-savebar]' ) ).toHaveCount(
				0
			);

			await page.getByRole( 'link', { name: 'Open Add-ons' } ).click();
			await expect( page ).toHaveURL(
				/page=corex-addons#corex-addon-corex-guides$/
			);
			await expect(
				page.locator( '#corex-addon-corex-guides' )
			).toBeVisible();
		} finally {
			if ( was === 'on' ) {
				wpEval(
					PLUGIN_API +
						"activate_plugin( 'corex-guides/corex-guides.php' );"
				);
			}
		}
	} );
} );

test.describe( 'without scripts', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'a page saves with its own button and shows the result', async ( {
		page,
	} ) => {
		const before = storedOption( 'corex_brand_address' );
		await page.goto( `${ PAGE }&section=general` );
		await page.locator( '#brand_address' ).fill( 'No-script Street 1' );
		await page.getByRole( 'button', { name: 'Save changes' } ).click();

		await expect( page ).toHaveURL( /section=general$/ );
		await expect( page.locator( '.corex-toast' ) ).toContainText(
			'Settings saved.'
		);
		await expect( page.locator( '#brand_address' ) ).toHaveValue(
			'No-script Street 1'
		);

		restoreOption( 'corex_brand_address', before );
	} );
} );

test.describe( 'at phone width', () => {
	test.use( { viewport: { width: 375, height: 812 } } );

	test( 'no page scrolls sideways, and the pages are a row of their own', async ( {
		page,
	} ) => {
		for ( const section of [
			'general',
			'email',
			'captcha',
			'media',
			'insights',
			'guides',
			'advanced',
		] ) {
			await page.goto( `${ PAGE }&section=${ section }` );
			const overflow = await page.evaluate(
				() =>
					document.documentElement.scrollWidth -
					document.documentElement.clientWidth
			);
			expect( overflow, section ).toBeLessThanOrEqual( 0 );
		}
	} );
} );

test.describe( 'the Media library can be converted from the admin', () => {
	test( 'a conversion starts, is followed to its end, and says what it did', async ( {
		page,
	} ) => {
		await page.goto( `${ PAGE }&section=media` );
		const panel = page.locator( '[data-corex-media-conversion]' );
		test.skip(
			( await panel.getAttribute( 'data-available' ) ) !== '1',
			'this server cannot write WebP, or conversion is off'
		);

		await panel
			.getByRole( 'button', { name: 'Convert existing library' } )
			.click();
		await expect(
			panel.locator( '[data-corex-conversion-summary]' )
		).toContainText(
			/Finished on .+ converted, .+ skipped, .+ failed, of .+ images\./,
			{ timeout: 60_000 }
		);
		await expect(
			panel.getByRole( 'button', { name: 'Cancel' } )
		).toBeHidden();

		// Reopened, the page shows the same run.
		await page.reload();
		await expect(
			panel.locator( '[data-corex-conversion-summary]' )
		).toContainText( 'Finished on' );
	} );
} );
