/**
 * Spec 122, US-VIS-01 and US-VIS-02: a flow that is not taking answers says so to visitors and
 * offers another way to get in touch, and a submission that arrives after it closed keeps the
 * visitor's answers.
 *
 * The flow is one this spec keeps and reuses; the page that shows it is made for the run and
 * removed after it.
 */
const { test, expect } = require( '@playwright/test' );
const { collectConsoleErrors } = require( './helpers' );

const FLOW_NAME = 'CoreX E2E closed flow';
const FLOW_SLUG = 'corex-e2e-closed-flow';

function flowsApi( page, method, path, body ) {
	return page.evaluate(
		async ( [ verb, suffix, data ] ) => {
			const url = window.corexFlows.restUrl + suffix;
			const options = { nonce: window.corexFlows.nonce };
			const result =
				verb === 'get'
					? await window.Corex.api.get( url, options )
					: await window.Corex.api[ verb ]( url, data, options );
			return result.envelope;
		},
		[ method, path, body ]
	);
}

/**
 * The flow published and open, with the owner's closed message and alternative, on a new page.
 *
 * @param {import('@playwright/test').Page} page The admin's page.
 * @return {Promise<{flowId:number, pageId:number, link:string}>} What the test works with.
 */
async function openFlowOnAPage( page ) {
	await page.goto( '/wp-admin/admin.php?page=corex-forms' );
	await expect( page.locator( '.corex-flow-list__catalog' ) ).toHaveAttribute(
		'data-status',
		'ready'
	);
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
				configuration: {
					schema: [
						{
							uuid: 'closed-email',
							key: 'email',
							label: 'Email',
							type: 'email',
							required: true,
						},
					],
					validation: { email: [ 'required', 'email' ] },
					routing: {
						rules: [],
						fallback: { type: 'flow_owner', config: {} },
					},
					email_routes: [],
					success: { type: 'inline', message: 'Received.' },
					placement_snapshot: { type: 'block' },
				},
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
	if ( flow.state === 'draft' ) {
		await flowsApi( page, 'post', `/${ flow.id }/publish`, {
			expected_version: flow.current_draft_version,
		} );
	}
	await flowsApi( page, 'patch', `/${ flow.id }/availability`, {
		availability: {
			closed_message: 'Applications reopen in March.',
			alternative: {
				type: 'email',
				label: 'Email the careers team',
				email: 'jobs@example.test',
			},
		},
	} );
	const created = await page.evaluate( async ( flowId ) => {
		const result = await window.Corex.api.post(
			'/wp-json/wp/v2/pages',
			{
				title: 'Closed flow',
				status: 'publish',
				content: `<!-- wp:corex/flow {"flowId":${ flowId }} /-->`,
			},
			{ nonce: window.corexFlows.nonce }
		);
		return result.envelope.data;
	}, flow.id );

	return { flowId: flow.id, pageId: created.id, link: created.link };
}

async function removePage( page, pageId ) {
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

test( 'a submission sent after the flow closed keeps its answers and shows the closed notice', async ( {
	page,
	browser,
} ) => {
	const errors = collectConsoleErrors( page );
	const { flowId, pageId, link } = await openFlowOnAPage( page );
	const visitorContext = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const visitor = await visitorContext.newPage();
	const visitorErrors = collectConsoleErrors( visitor );

	try {
		await visitor.goto( link );
		await visitor.getByLabel( 'Email' ).fill( 'too-late@example.test' );

		// Closed after the page was loaded.
		const detail = await flowsApi( page, 'get', `/${ flowId }` );
		await flowsApi( page, 'post', `/${ flowId }/close`, {
			expected_version: detail.data.flow.current_draft_version,
		} );

		await visitor.getByRole( 'button', { name: 'Submit' } ).click();
		const notice = visitor.locator( '.corex-flow-notice' );
		await expect( notice ).toContainText( 'Closed' );
		await expect( notice ).toContainText( 'Applications reopen in March.' );
		await expect(
			notice.getByRole( 'link', { name: 'Email the careers team' } )
		).toHaveAttribute( 'href', 'mailto:jobs@example.test' );
		await expect( visitor.getByLabel( 'Email' ) ).toHaveValue(
			'too-late@example.test'
		);

		// Loaded again, the page shows the notice where the form was, at a phone's width,
		// right to left too.
		await visitor.setViewportSize( { width: 375, height: 800 } );
		await visitor.goto( link );
		await expect( visitor.locator( 'form.corex-form' ) ).toHaveCount( 0 );
		await expect(
			visitor.getByRole( 'heading', { name: FLOW_NAME } )
		).toBeVisible();
		await expect( visitor.locator( '.corex-flow-notice' ) ).toContainText(
			'Applications reopen in March.'
		);
		await visitor
			.locator( 'html' )
			.evaluate( ( root ) => root.setAttribute( 'dir', 'rtl' ) );
		const fits = await visitor.evaluate(
			() =>
				document.documentElement.scrollWidth <=
				document.documentElement.clientWidth
		);
		expect( fits ).toBe( true );
	} finally {
		await visitorContext.close();
		await removePage( page, pageId );
	}

	expect( errors, `console errors:\n${ errors.join( '\n' ) }` ).toEqual( [] );
	// The refused submission is an answer the page expects, not a script error.
	expect(
		visitorErrors.filter( ( error ) => ! error.includes( '409' ) )
	).toEqual( [] );
} );

test( 'a scheduled flow says when it opens, and opens when the date is cleared in the Availability tab', async ( {
	page,
	browser,
} ) => {
	const { flowId, pageId, link } = await openFlowOnAPage( page );
	const visitorContext = await browser.newContext( {
		storageState: { cookies: [], origins: [] },
	} );
	const visitor = await visitorContext.newPage();

	try {
		await page.goto(
			`/wp-admin/admin.php?page=corex-forms&corex_flow=${ flowId }`
		);
		await page
			.locator( '.corex-flow-editor__stages button', {
				hasText: 'Availability',
			} )
			.click();
		await page.getByLabel( 'Opens on' ).fill( '2099-01-01T09:00' );
		await page.getByRole( 'button', { name: 'Save availability' } ).click();
		await expect( page.getByText( 'Availability saved.' ) ).toBeVisible();
		await expect(
			page.locator( '.corex-flow-editor__title' )
		).toContainText( 'Scheduled' );

		await visitor.goto( link );
		await expect( visitor.locator( '.corex-flow-notice' ) ).toContainText(
			'This form opens on'
		);
		await expect( visitor.locator( '.corex-flow-notice' ) ).toContainText(
			'2099'
		);

		await page.getByLabel( 'Opens on' ).fill( '' );
		await page.getByRole( 'button', { name: 'Save availability' } ).click();
		await expect(
			page.locator( '.corex-flow-editor__title' )
		).toContainText( 'Published' );
		await visitor.goto( link );
		await expect( visitor.locator( 'form.corex-form' ) ).toHaveCount( 1 );
	} finally {
		await visitorContext.close();
		await removePage( page, pageId );
	}
} );
