/**
 * Pure client-state tests for the functional Email Studio (spec 068: FR-111–FR-125).
 */

import {
	TABS,
	buildEndpoint,
	drawingErrors,
	emailStudioReducer,
	initialEmailStudioState,
	isEmailAddress,
	normalizeOverview,
	previewRequest,
	testSendRequest,
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
				variables: [],
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
		expect(
			buildEndpoint( '/corex/v1/email-studio', 'preview', 12 )
		).toBe( '/corex/v1/email-studio/templates/12/preview' );
		expect( buildEndpoint( '/corex/v1/email-studio', 'reset', 12 ) ).toBe(
			'/corex/v1/email-studio/templates/12/reset'
		);
		expect( () => buildEndpoint( '/email-studio', 'invented' ) ).toThrow();
	} );
} );

describe( 'Email Studio editing helpers', () => {
	it( 'validates required draft fields before a mutation, the From address optional', () => {
		expect( validateDraftForm( {} ) ).toEqual(
			expect.objectContaining( {
				subject: expect.any( String ),
				html_body: expect.any( String ),
				layout_id: expect.any( String ),
			} )
		);
		expect( validateDraftForm( {} ) ).not.toHaveProperty( 'from_address' );
		expect(
			validateDraftForm( {
				subject: 'Welcome',
				html_body: '<p>Hello</p>',
				plain_text_mode: 'auto',
				layout_id: 1,
				layout_version: 1,
			} )
		).toEqual( {} );
	} );

	it( 'refuses a From address that is not one and a From name with a line break', () => {
		const errors = validateDraftForm( {
			from_address: 'not-an-address',
			from_name: 'Sales\nBcc: x@example.com',
		} );

		expect( errors.from_address ).toBe(
			'The From address is not a valid email address.'
		);
		expect( errors.from_name ).toBe(
			'The From name cannot contain a line break.'
		);
		expect( isEmailAddress( ' jane@example.com ' ) ).toBe( true );
		expect( isEmailAddress( 'not-an-address' ) ).toBe( false );
	} );

	it( 'asks the server to draw the draft with the samples typed and the direction chosen', () => {
		const draft = {
			subject: 'Hi',
			html_body: '<p>Hi</p>',
			plain_text_mode: 'auto',
			layout_id: 4,
			layout_version: 2,
			// Server-owned fields never travel back.
			id: 99,
			checksum: 'x',
		};

		const asked = previewRequest( draft, { 'site.name': 'Acme' }, 'rtl' );

		expect( asked ).toEqual( {
			subject: 'Hi',
			from_name: '',
			from_address: '',
			html_body: '<p>Hi</p>',
			plain_text: '',
			plain_text_mode: 'auto',
			layout_id: 4,
			layout_version: 2,
			samples: { 'site.name': 'Acme' },
			direction: 'rtl',
		} );
		expect( previewRequest( draft ).direction ).toBe( '' );
	} );

	it( 'sends the previewed email to one address, with the same samples', () => {
		const sent = testSendRequest(
			7,
			{ subject: 'Hi', html_body: '<p>Hi</p>' },
			{ 'form.title': 'Quote' },
			' me@example.test '
		);

		expect( sent ).toEqual(
			expect.objectContaining( {
				template_id: 7,
				to: [ 'me@example.test' ],
				samples: { 'form.title': 'Quote' },
				subject: 'Hi',
			} )
		);
		expect( sent ).not.toHaveProperty( 'direction' );
	} );

	it( 'says why a template cannot be drawn in the words the server gave, naming what is missing', () => {
		expect(
			drawingErrors( {
				message: 'This template cannot be drawn yet.',
				details: {
					fields: {
						variables: 'Unknown variable: nonsense.key.',
						partials: 'The partial "promo" is not available.',
					},
				},
			} )
		).toEqual( [
			'Unknown variable: nonsense.key.',
			'The partial "promo" is not available.',
		] );
		expect( drawingErrors( { message: 'Nope' } ) ).toEqual( [ 'Nope' ] );
	} );
} );
