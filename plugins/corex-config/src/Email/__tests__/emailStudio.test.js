/**
 * Pure client-state tests for the functional Email Studio (spec 068: FR-111–FR-125).
 */

import {
	TABS,
	buildEndpoint,
	composePreviewHtml,
	emailStudioReducer,
	initialEmailStudioState,
	normalizeOverview,
	previewDocument,
	validateDraftForm,
} from '../emailStudioClient.js';

describe( 'Email Studio client state', () => {
	it( 'exposes every functional Studio section', () => {
		expect( TABS.map( ( tab ) => tab.key ) ).toEqual( [
			'overview',
			'templates',
			'layouts',
			'partials',
			'variables',
			'routing',
			'preview',
			'plain',
			'test',
			'logs',
			'health',
		] );
	} );

	it( 'moves through loading ready mutation and error states immutably', () => {
		const loading = emailStudioReducer( initialEmailStudioState, {
			type: 'load',
		} );
		const ready = emailStudioReducer( loading, {
			type: 'loaded',
			payload: { templates: [ { id: 3, slug: 'welcome' } ], counts: {} },
		} );
		const mutating = emailStudioReducer( ready, { type: 'mutating' } );
		const failed = emailStudioReducer( mutating, {
			type: 'failed',
			message: 'Nope',
		} );
		const notice = emailStudioReducer( failed, {
			type: 'notice',
			message: 'Choose a template',
		} );

		expect( initialEmailStudioState.status ).toBe( 'idle' );
		expect( loading.status ).toBe( 'loading' );
		expect( ready.status ).toBe( 'ready' );
		expect( ready.data.templates ).toHaveLength( 1 );
		expect( mutating.mutating ).toBe( true );
		expect( failed.status ).toBe( 'error' );
		expect( failed.message ).toBe( 'Nope' );
		expect( notice.status ).toBe( 'ready' );
		expect( notice.message ).toBe( 'Choose a template' );
	} );

	it( 'normalizes missing collections and counts instead of fabricating data', () => {
		expect(
			normalizeOverview( { delivery: { environment: 'development' } } )
		).toEqual(
			expect.objectContaining( {
				templates: [],
				layouts: [],
				partials: [],
				routes: [],
				captures: [],
				attempts: [],
				recentTestSends: [],
				health: [],
				variables: {},
				counts: {},
			} )
		);
	} );

	it( 'builds only declared REST endpoints', () => {
		expect(
			buildEndpoint( '/corex/v1/email-studio', 'template', 12 )
		).toBe( '/corex/v1/email-studio/templates/12' );
		expect( buildEndpoint( '/corex/v1/email-studio', 'draft', 12 ) ).toBe(
			'/corex/v1/email-studio/templates/12/draft'
		);
		expect(
			buildEndpoint( '/corex/v1/email-studio', 'resend', 'abc-123' )
		).toBe( '/corex/v1/email-studio/attempts/abc-123/resend' );
		expect( () => buildEndpoint( '/email-studio', 'invented' ) ).toThrow();
	} );
} );

describe( 'Email Studio editing helpers', () => {
	it( 'validates required draft fields before a mutation', () => {
		expect( validateDraftForm( {} ) ).toEqual(
			expect.objectContaining( {
				subject: expect.any( String ),
				from_address: expect.any( String ),
				html_body: expect.any( String ),
				layout_id: expect.any( String ),
			} )
		);
		expect(
			validateDraftForm( {
				subject: 'Welcome',
				from_address: 'hello@example.com',
				html_body: '<p>Hello</p>',
				plain_text_mode: 'auto',
				layout_id: 1,
				layout_version: 1,
			} )
		).toEqual( {} );
	} );

	it( 'builds sandbox preview markup with sample values and explicit direction', () => {
		const preview = previewDocument(
			'<p>Hello {{ user.name }}</p>',
			{ 'user.name': '<Sam>' },
			'rtl'
		);

		expect( preview ).toContain( 'dir="rtl"' );
		expect( preview ).toContain( 'Hello &lt;Sam&gt;' );
		expect( preview ).not.toContain( '<Sam>' );
	} );

	it( 'composes the selected layout revision and active partials around draft content', () => {
		const html = composePreviewHtml(
			{
				html_body: '<p>Hello</p>{{> signature }}',
				layout_id: 4,
				layout_version: 2,
			},
			[
				{
					id: 4,
					version: 2,
					regions: {
						header: '{{> masthead }}',
						accent: '#abcdef',
						body: '<main>{{ content }}</main>',
						button: '<a>Continue</a>',
						footer: '<footer>Bye</footer>',
					},
				},
			],
			[
				{
					slug: 'masthead',
					status: 'active',
					html_body: '<header>CoreX</header>',
				},
				{
					slug: 'signature',
					status: 'active',
					html_body: '<p>Team</p>',
				},
			]
		);

		expect( html ).toContain( 'border-block-start:4px solid #abcdef' );
		expect( html ).toContain( '<header>CoreX</header>' );
		expect( html ).toContain( '<main><p>Hello</p><p>Team</p></main>' );
		expect( html ).toContain( '<footer>Bye</footer>' );
	} );
} );
