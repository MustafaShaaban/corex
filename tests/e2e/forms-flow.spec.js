/**
 * Forms & Flows admin and visitor pipeline proof (spec 068: T089).
 */

const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_NAME = 'CoreX E2E flow';
const FLOW_SLUG = 'corex-e2e-flow';

test.beforeEach( async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect(
		page.getByRole( 'heading', { name: 'CoreX Forms & Flows' } )
	).toBeVisible();
	await expect( page.getByText( 'Loading flows…' ) ).toBeHidden();
} );

test( 'creates publishes tests and submits a persisted flow without console errors', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const existing = page.getByRole( 'button', { name: new RegExp( FLOW_NAME ) } );

	if ( ! ( await existing.isVisible().catch( () => false ) ) ) {
		await page.getByLabel( 'Flow name' ).fill( FLOW_NAME );
		await page.getByLabel( 'Slug', { exact: true } ).fill( FLOW_SLUG );
		await page.getByLabel( 'Description' ).fill( 'A repeatable browser verification fixture.' );
		await page.getByRole( 'button', { name: 'Create draft' } ).click();
		await expect( page.getByRole( 'heading', { name: FLOW_NAME } ) ).toBeVisible();

		await page.getByLabel( 'Field type' ).selectOption( 'email' );
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page.getByLabel( 'Label', { exact: true } ).fill( 'Email address' );
		await page.getByLabel( 'Placeholder' ).fill( 'name@example.com' );
		await page.getByRole( 'button', { name: 'Save draft' } ).click();
	} else {
		await existing.click();
	}
	const publish = page.getByRole( 'button', { name: 'Publish' } );
	if ( await publish.isVisible().catch( () => false ) ) {
		await expect( publish ).toBeEnabled();
		await publish.click();
		await expect( page.getByRole( 'button', { name: 'Move to draft' } ) ).toBeVisible();
	}

	// The Test stage lives in the stage rail; each stage button's accessible name carries its
	// step number and status (e.g. "7 Test Ready"), so scope to the rail and match the label.
	await page.locator( '.corex-flow-editor__stages button', { hasText: 'Test' } ).click();
	await page.getByRole( 'button', { name: 'Run marked test' } ).click();
	await expect( page.getByText( 'Marked test completed.' ) ).toBeVisible();
	await expect( page.locator( '.corex-flow-test-results > li' ) ).toHaveCount( 7 );

	const visitor = await page.evaluate( async ( slug ) => {
		const list = await window.Corex.api.get( `${ window.corexFlows.restUrl }?search=${ slug }`, {
			nonce: window.corexFlows.nonce,
		} );
		const flow = list.envelope.data.flows.find( ( candidate ) => candidate.slug === slug );
		return window.Corex.api.post(
			`${ window.corexFlows.restUrl }/${ flow.id }/submit`,
			{ email: 'visitor@example.com' },
			{ nonce: window.corexFlows.nonce }
		);
	}, FLOW_SLUG );

	expect( visitor.envelope.ok ).toBe( true );
	expect( visitor.envelope.data.completed ).toBe( true );
	expect( visitor.envelope.data.stages ).toHaveLength( 7 );
	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'contains the builder at mobile tablet desktop and wide viewports with RTL', async ( {
	page,
} ) => {
	for ( const width of [ 375, 768, 1024, 1440 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		const fits = await page.evaluate( () => document.documentElement.scrollWidth <= document.documentElement.clientWidth );
		expect( fits, `horizontal overflow at ${ width }px` ).toBe( true );
	}

	await page.locator( 'html' ).evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( page.locator( '.corex-flows-app' ) ).toHaveCSS( 'direction', 'rtl' );
} );
