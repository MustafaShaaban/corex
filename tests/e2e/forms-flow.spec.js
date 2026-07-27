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
	// Wait for the catalog to have settled, not merely for a loading string to vanish. Code forms
	// render from localised data with no request, so rows exist before the flows have arrived —
	// waiting on a row proved nothing, and waiting on the loading text passed before it appeared.
	await expect( page.locator( '.corex-flow-list__catalog' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
} );

test( 'lists code-registered forms in the catalog as read-only definitions', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );

	// `contact` is the framework's own form, registered through FormRegistry rather than stored as
	// a flow. Before spec 074 the screen named after forms did not list it at all.
	const row = page.locator( '.corex-flow-list__row.is-read-only', {
		hasText: 'contact',
	} );
	await expect( row ).toBeVisible();
	await expect( row.getByText( 'Defined in code' ) ).toBeVisible();

	const disclose = row.getByRole( 'button' );
	await expect( disclose ).toHaveAttribute( 'aria-expanded', 'false' );
	await disclose.click();
	await expect( disclose ).toHaveAttribute( 'aria-expanded', 'true' );

	// A real definition, not a stub: the fields, their rules, and a route to the submissions.
	await expect(
		row.getByRole( 'table', { name: 'Field definitions' } )
	).toBeVisible();
	await expect(
		row.getByRole( 'rowheader', { name: /Email/ } )
	).toBeVisible();
	await expect(
		row.getByText( 'the visual builder cannot change it', { exact: false } )
	).toBeVisible();

	const submissions = row.getByRole( 'link', {
		name: 'View submissions for this form',
	} );
	await expect( submissions ).toHaveAttribute(
		'href',
		/corex_form=slug%3Acontact|corex_form=slug:contact/
	);

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'creates publishes tests and submits a persisted flow without console errors', async ( {
	page,
} ) => {
	const errors = collectConsoleErrors( page );
	const existing = page.getByRole( 'button', {
		name: new RegExp( FLOW_NAME ),
	} );

	if ( ! ( await existing.isVisible().catch( () => false ) ) ) {
		await page.getByLabel( 'Flow name' ).fill( FLOW_NAME );
		await page.getByLabel( 'Slug', { exact: true } ).fill( FLOW_SLUG );
		await page
			.getByLabel( 'Description' )
			.fill( 'A repeatable browser verification fixture.' );
		await page.getByRole( 'button', { name: 'Create draft' } ).click();
		await expect(
			page.getByRole( 'heading', { name: FLOW_NAME } )
		).toBeVisible();

		// Field type is a CorexSelect now (spec 069) — an in-DOM listbox rather than a native
		// <select>, so it is opened and picked rather than driven with selectOption().
		await page.getByRole( 'combobox', { name: 'Field type' } ).click();
		await page
			.getByRole( 'option', { name: 'Email', exact: true } )
			.click();
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await page
			.getByLabel( 'Label', { exact: true } )
			.fill( 'Email address' );
		await page.getByLabel( 'Placeholder' ).fill( 'name@example.com' );
		await page.getByRole( 'button', { name: 'Save draft' } ).click();
	} else {
		await existing.click();
	}
	const publish = page.getByRole( 'button', { name: 'Publish' } );
	if ( await publish.isVisible().catch( () => false ) ) {
		await expect( publish ).toBeEnabled();
		await publish.click();
		await expect(
			page.getByRole( 'button', { name: 'Move to draft' } )
		).toBeVisible();
	}

	// The Test stage lives in the stage rail; each stage button's accessible name carries its
	// step number and status (e.g. "7 Test Ready"), so scope to the rail and match the label.
	await page
		.locator( '.corex-flow-editor__stages button', { hasText: 'Test' } )
		.click();
	await page.getByRole( 'button', { name: 'Run marked test' } ).click();
	await expect( page.getByText( 'Marked test completed.' ) ).toBeVisible();
	await expect( page.locator( '.corex-flow-test-results > li' ) ).toHaveCount(
		7
	);

	const visitor = await page.evaluate( async ( slug ) => {
		const list = await window.Corex.api.get(
			`${ window.corexFlows.restUrl }?search=${ slug }`,
			{
				nonce: window.corexFlows.nonce,
			}
		);
		const flow = list.envelope.data.flows.find(
			( candidate ) => candidate.slug === slug
		);
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
		const fits = await page.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth
		);
		expect( fits, `horizontal overflow at ${ width }px` ).toBe( true );
	}

	await page
		.locator( 'html' )
		.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
	await expect( page.locator( '.corex-flows-app' ) ).toHaveCSS(
		'direction',
		'rtl'
	);
} );
