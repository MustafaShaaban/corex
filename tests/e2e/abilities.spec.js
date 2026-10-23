/**
 * Every CoreX screen opens for the people given its ability (spec 117), in a browser.
 *
 * Each test signs in as a person of its own, made here and removed afterwards, with a role of its
 * own, so the abilities it is given touch no fixture user another spec relies on.
 */
const { test, expect } = require( '@playwright/test' );
const { signInAs, wpEval } = require( './helpers' );

const PASSWORD = 'CorexE2E!abilities1';

/**
 * A person with a role of their own, given CoreX abilities by the role matrix or on their account.
 *
 * @param {string}   login        The login name.
 * @param {string[]} roleAllows   Abilities allowed for their role in the matrix.
 * @param {string[]} accountCaps  Abilities given to their account, as an approved request does.
 * @return {boolean} Whether WP-CLI made them.
 */
function makePerson( login, roleAllows, accountCaps = [] ) {
	const role = `${ login }-role`.replace( /-/g, '_' );
	const answer = wpEval(
		`add_role( '${ role }', 'E2E ${ login }', [ 'read' => true ] );` +
			` $id = wp_insert_user( [ 'user_login' => '${ login }', 'user_pass' => '${ PASSWORD }', 'user_email' => '${ login }@example.test', 'role' => '${ role }' ] );` +
			` foreach ( ${ JSON.stringify(
				accountCaps
			) } as $cap ) { ( new WP_User( $id ) )->add_cap( $cap ); }` +
			` $store = \\Corex\\Boot::app()->container()->make( \\Corex\\Access\\RoleAbilityStore::class );` +
			` $store->apply( '${ role }', array_fill_keys( ${ JSON.stringify(
				roleAllows
			) }, 'allow' ), 1, new DateTimeImmutable() );` +
			' echo is_int( $id ) ? "made" : "failed";'
	);
	return answer === 'made';
}

function removePerson( login ) {
	const role = `${ login }-role`.replace( /-/g, '_' );
	wpEval(
		"require_once ABSPATH . 'wp-admin/includes/user.php';" +
			` $user = get_user_by( 'login', '${ login }' ); if ( $user ) { wp_delete_user( $user->ID ); }` +
			` remove_role( '${ role }' );` +
			' global $wpdb; $wpdb->delete( ( new \\Corex\\Database\\Schema\\Migrator() )->fullName( \\Corex\\Config\\Access\\AccessTables::ROLE_GRANTS ),' +
			` [ 'role_key' => '${ role }' ] );`
	);
}

test.describe( 'someone allowed one CoreX ability', () => {
	const login = 'corex-e2e-forms-only';
	let made = false;

	test.beforeAll( () => {
		removePerson( login );
		made = makePerson( login, [ 'corex_manage_forms' ] );
	} );
	test.afterAll( () => removePerson( login ) );

	test( 'opens that screen, sees it and Guides in the CoreX menu, and is refused the others with 403', async ( {
		browser,
		baseURL,
	} ) => {
		test.skip( ! made, 'WP-CLI is needed to make the person.' );
		const page = await signInAs( browser, baseURL, login, PASSWORD );

		await page.goto( '/wp-admin/admin.php?page=corex-forms' );
		await expect(
			page.locator( '.corex-admin__header h1' )
		).toContainText( 'Forms' );

		const menu = await page
			.locator( '.corex-admin__rail .corex-admin__nav-item' )
			.allInnerTexts();
		expect( menu.map( ( item ) => item.trim() ) ).toEqual( [
			'Forms & Flows',
			'Guides',
		] );

		const settings = await page.goto(
			'/wp-admin/admin.php?page=corex-settings-config'
		);
		expect( settings.status() ).toBe( 403 );
		await expect( page.locator( '.corex-denied' ) ).toContainText(
			'“Manage settings”'
		);
		await expect( page.locator( '.corex-denied' ) ).not.toContainText(
			'corex_'
		);

		await page.context().close();
	} );
} );

test.describe( 'someone allowed to look but not to change', () => {
	const login = 'corex-e2e-viewer';
	let made = false;

	test.beforeAll( () => {
		removePerson( login );
		made = makePerson( login, [], [ 'corex_view_screens' ] );
	} );
	test.afterAll( () => removePerson( login ) );

	test( 'opens Settings read-only: a chip, a sentence, controls that say why, and no way to save', async ( {
		browser,
		baseURL,
	} ) => {
		test.skip( ! made, 'WP-CLI is needed to make the person.' );
		const page = await signInAs( browser, baseURL, login, PASSWORD );

		const response = await page.goto(
			'/wp-admin/admin.php?page=corex-settings-config'
		);
		expect( response.status() ).toBe( 200 );
		await expect(
			page.locator( '.corex-admin__chip--readonly' )
		).toHaveText( 'Read only' );
		await expect( page.locator( '#corex-readonly-note' ) ).toContainText(
			'“Manage settings”'
		);

		const field = page
			.locator( '.corex-settings-form input[type="text"]' )
			.first();
		await expect( field ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( field ).toHaveAttribute(
			'aria-describedby',
			/corex-readonly-note/
		);
		await field.focus();
		await expect( field ).toBeFocused();
		await page.keyboard.type( 'changed' );
		await expect( field ).not.toHaveValue( /changed/ );
		await expect(
			page.locator( 'input[name="corex_settings_nonce"]' )
		).toHaveCount( 0 );

		const forms = await page.goto(
			'/wp-admin/admin.php?page=corex-forms'
		);
		expect( forms.status() ).toBe( 403 );

		await page.context().close();
	} );
} );

test.describe( 'someone allowed to manage operations only', () => {
	const login = 'corex-e2e-operations';
	let made = false;

	test.beforeAll( () => {
		removePerson( login );
		made = makePerson( login, [ 'corex_manage_operations' ] );
	} );
	test.afterAll( () => removePerson( login ) );

	test( 'is refused switching to Maintenance, which also needs "Run dangerous actions"', async ( {
		browser,
		baseURL,
	} ) => {
		test.skip( ! made, 'WP-CLI is needed to make the person.' );
		const page = await signInAs( browser, baseURL, login, PASSWORD );

		await page.goto(
			'/wp-admin/admin.php?page=corex-operations-security&tab=environment'
		);
		const nonce = await page
			.locator( 'input[name="corex_ops_mode_nonce"]' )
			.getAttribute( 'value' );
		const refused = await page.request.post( '/wp-admin/admin-post.php', {
			form: {
				action: 'corex_ops_mode',
				corex_ops_mode_nonce: nonce,
				corex_mode: 'maintenance',
				corex_confirm: '1',
			},
			maxRedirects: 0,
		} );

		expect( refused.status() ).toBe( 403 );
		expect( await refused.text() ).toContain( 'Run dangerous actions' );

		await page.context().close();
	} );
} );

test.describe( 'the role matrix', () => {
	test( 'is a grid of states by role that scrolls in its own frame at 400px', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 400, height: 800 } );
		await page.goto( '/wp-admin/admin.php?page=corex-access&tab=matrix' );

		const grid = page.locator( '.corex-access__grid' );
		await expect( grid ).toBeVisible();
		await expect(
			grid.getByRole( 'columnheader', { name: /Administrator/ } )
		).toBeVisible();
		await expect(
			page.locator( '.corex-access__cell.is-allowed_admin' ).first()
		).toContainText( 'Allowed through site administration' );

		const sideways = await page.evaluate(
			() => document.documentElement.scrollWidth > window.innerWidth
		);
		expect( sideways ).toBe( false );

		await page.getByRole( 'button', { name: 'Edit Editor' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Editing Editor' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Back to the grid' } )
		).toBeVisible();
	} );

	test( 'has one access history, filtered by kind, with no second list on the matrix tab', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=corex-access&tab=matrix' );
		await expect(
			page.locator( '.corex-access__panels' ).getByRole( 'heading', {
				name: 'Audit',
			} )
		).toHaveCount( 0 );

		await page.goto(
			'/wp-admin/admin.php?page=corex-access&tab=audit&kind=refused'
		);
		await expect(
			page.getByRole( 'heading', { name: 'Access audit log' } )
		).toBeVisible();
		const kinds = await page
			.locator( '.corex-access__audit .corex-access__chip' )
			.allInnerTexts();
		expect( new Set( kinds ).size ).toBeLessThanOrEqual( 1 );
		expect(
			await page.locator( '.corex-access__audit li' ).count()
		).toBeLessThanOrEqual( 25 );
	} );
} );
