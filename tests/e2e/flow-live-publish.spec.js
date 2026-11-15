/**
 * Spec 122, US-FLW-01: a change to a live flow is published in one step, and the form never leaves
 * the page while it is.
 *
 * The flow is one this spec keeps and reuses, as the forms specs do with theirs. The page that
 * shows it is made for the run and removed after it.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_NAME = 'CoreX E2E live publish';
const FLOW_SLUG = 'corex-e2e-live-publish';

/**
 * Calls the flows API from the Forms & Flows screen, as its own script does.
 *
 * @param {import('@playwright/test').Page} page   The admin's page, on Forms & Flows.
 * @param {string}                          method get, post, patch or delete.
 * @param {string}                          path   After the flows route: '' or '/7/publish'.
 * @param {Object}                          [body] The request's body.
 * @return {Promise<Object>} The envelope.
 */
function flowsApi( page, method, path, body ) {
	return page.evaluate(
		async ( [ verb, suffix, data ] ) => {
			const url = window.corexFlows.restUrl + suffix;
			const options = { nonce: window.corexFlows.nonce };
			const result =
				verb === 'get' || verb === 'delete'
					? await window.Corex.api[ verb ]( url, options )
					: await window.Corex.api[ verb ]( url, data, options );
			return result.envelope;
		},
		[ method, path, body ]
	);
}

/**
 * The flow, published with one email question labelled "Email", and a page that shows it.
 *
 * @param {import('@playwright/test').Page} page The admin's page.
 * @return {Promise<{flowId:number, pageId:number, link:string}>} What the test works with.
 */
async function liveFlowOnAPage( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect( page.locator( '.corex-flow-list__catalog' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
	const configuration = {
		schema: [
			{
				uuid: 'live-email',
				key: 'email',
				label: 'Email',
				type: 'email',
				required: true,
			},
		],
		validation: { email: [ 'required', 'email' ] },
		routing: { rules: [], fallback: { type: 'flow_owner', config: {} } },
		email_routes: [],
		success: { type: 'inline', message: 'Received live.' },
		placement_snapshot: { type: 'block' },
	};
	const listed = await flowsApi( page, 'get', `?search=${ FLOW_SLUG }` );
	let flow = listed.data.flows.find( ( row ) => row.slug === FLOW_SLUG );
	if ( ! flow ) {
		flow = (
			await flowsApi( page, 'post', '', {
				slug: FLOW_SLUG,
				name: FLOW_NAME,
				owner_id: await page.evaluate(
					() => window.corexFlows.ownerId
				),
				configuration,
			} )
		).data.flow;
	} else {
		// Every run starts from the same saved draft.
		const detail = await flowsApi( page, 'get', `/${ flow.id }` );
		const current = detail.data.versions.find(
			( version ) => version.version_number === flow.current_draft_version
		);
		flow = (
			await flowsApi( page, 'patch', `/${ flow.id }`, {
				expected_version: current.version_number,
				expected_checksum: current.checksum,
				configuration,
			} )
		).data.flow;
	}
	if ( flow.state !== 'draft' && flow.state !== 'published' ) {
		flow = (
			await flowsApi( page, 'post', `/${ flow.id }/unpublish`, {
				expected_version: flow.current_draft_version,
			} )
		).data.flow;
	}
	await flowsApi( page, 'post', `/${ flow.id }/publish`, {
		expected_version: flow.current_draft_version,
	} );

	const created = await page.evaluate( async ( flowId ) => {
		const result = await window.Corex.api.post(
			'/wp-json/wp/v2/pages',
			{
				title: 'Live publish',
				status: 'publish',
				content: `<!-- wp:corex/flow {"flowId":${ flowId }} /-->`,
			},
			{ nonce: window.corexFlows.nonce }
		);
		return result.envelope.data;
	}, flow.id );

	return { flowId: flow.id, pageId: created.id, link: created.link };
}

test( 'publishes changes in one step while the page keeps showing the form, and accepts a page loaded before', async ( {
	page,
	browser,
} ) => {
	const errors = collectConsoleErrors( page );
	const { flowId, pageId, link } = await liveFlowOnAPage( page );
	const visitorContext = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const visitor = await visitorContext.newPage();
	const watcher = await visitorContext.newPage();

	try {
		// A visitor loads the page before the change.
		await visitor.goto( link );
		await expect( visitor.getByLabel( 'Email' ) ).toBeVisible();

		await page.goto(
			`/wp-admin/admin.php?page=corex-forms&corex_flow=${ flowId }`
		);
		await expect(
			page.getByRole( 'heading', { name: FLOW_NAME } )
		).toBeVisible();
		await expect(
			page.getByText( 'Live version is up to date' )
		).toBeVisible();
		await page
			.locator( '.corex-flow-editor__workspace ol > li > button' )
			.first()
			.click();
		await page
			.getByLabel( 'Label', { exact: true } )
			.first()
			.fill( 'Work email' );
		await page.getByRole( 'button', { name: 'Save draft' } ).click();
		await expect(
			page.getByText( 'All changes saved', { exact: true } )
		).toBeVisible();

		// The page is read again and again while the change is published.
		let reading = true;
		const readings = ( async () => {
			const seen = [];
			while ( reading ) {
				await watcher.goto( link );
				seen.push( await watcher.locator( 'form.corex-form' ).count() );
			}
			return seen;
		} )();
		await page.getByRole( 'button', { name: 'Publish changes' } ).click();
		await expect(
			page.getByText( 'Live version is up to date' )
		).toBeVisible();
		reading = false;
		const seen = await readings;

		expect( seen.length ).toBeGreaterThan( 0 );
		expect( seen.every( ( count ) => count === 1 ) ).toBe( true );
		await watcher.goto( link );
		await expect( watcher.getByLabel( 'Work email' ) ).toBeVisible();
		await expect(
			page.locator( '.corex-flow-editor__live' )
		).toContainText( 'Live: version' );

		// The page loaded before is accepted.
		await visitor.getByLabel( 'Email' ).fill( 'live-publish@example.test' );
		await visitor.getByRole( 'button', { name: 'Submit' } ).click();
		await expect( visitor.getByText( 'Received live.' ) ).toBeVisible();

		// The Versions tab lists what was published, newest first, live first.
		await page
			.locator( '.corex-flow-editor__stages button', {
				hasText: 'Versions',
			} )
			.click();
		const rows = page.locator( '.corex-flow-versions__list > li' );
		await expect( rows.first() ).toContainText( 'Live' );
	} finally {
		await visitorContext.close();
		await page.evaluate(
			( id ) =>
				window.Corex.api.delete(
					`/wp-json/wp/v2/pages/${ id }?force=true`,
					{
						nonce: window.corexFlows.nonce,
					}
				),
			pageId
		);
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
} );

test( 'asks before it unpublishes, and Cancel keeps the flow live', async ( {
	page,
} ) => {
	const { flowId, pageId } = await liveFlowOnAPage( page );

	try {
		await page.goto(
			`/wp-admin/admin.php?page=corex-forms&corex_flow=${ flowId }`
		);
		const unpublish = page.getByRole( 'button', { name: 'Unpublish' } );
		await unpublish.click();
		const question = page.getByRole( 'dialog', {
			name: 'Unpublish this flow?',
		} );
		await expect( question ).toContainText(
			'disappear from every page that shows it'
		);
		await question.getByRole( 'button', { name: 'Cancel' } ).click();

		await expect( question ).toBeHidden();
		await expect( unpublish ).toBeFocused();
		await expect(
			page.locator( '.corex-flow-editor__title' )
		).toContainText( 'Published' );
	} finally {
		await page.evaluate(
			( id ) =>
				window.Corex.api.delete(
					`/wp-json/wp/v2/pages/${ id }?force=true`,
					{
						nonce: window.corexFlows.nonce,
					}
				),
			pageId
		);
	}
} );
