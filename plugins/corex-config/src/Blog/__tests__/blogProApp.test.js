/**
 * BlogProApp — the screen that used to be a poster of its own data (spec 075, FR-1/FR-6).
 *
 * The defect these hold down: `BlogProApp` called `useReducer` and threw the dispatch away, so the
 * whole of `blogProState.js` — the reducer's four outcome cases, both payload builders, the endpoint
 * helper — was unreachable from the running app while being fully covered by `blogPro.test.js`. A
 * green suite over code no user could run. Every assertion here goes through the rendered component,
 * so it cannot pass unless the wiring exists.
 *
 * No @testing-library in this repo, so the component is driven through a real jsdom root — the same
 * approach as admin/__tests__/corexSelect.test.js.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import BlogProApp from '../BlogProApp.js';

const POSTS = [
	{
		id: 7,
		title: 'Launch week',
		status: 'publish',
		permalink: 'https://acme.test/launch',
	},
	{
		id: 9,
		title: 'Field notes',
		status: 'draft',
		permalink: 'https://acme.test/notes',
	},
];

function config( overrides = {} ) {
	return {
		restUrl: 'https://acme.test/wp-json/corex/v1',
		nonce: 'test-nonce',
		posts: POSTS,
		selectedPostId: 7,
		selectedPost: { ...POSTS[ 0 ], status_label: 'Published' },
		periodDays: 30,
		analytics: {
			views: 120,
			reads: 44,
			share_clicks: 6,
			unique_visitors: 90,
			average_read_seconds: 51,
			has_data: true,
		},
		editorial: {
			post_id: 7,
			editorial_state: 'ready_for_review',
			native_status: 'pending',
			editorial_state_label: 'Ready for review',
			native_status_label: 'Pending review',
			transitions: [
				{
					key: 'approved',
					label: 'Approved',
					requires_schedule: false,
				},
				{
					key: 'scheduled',
					label: 'Scheduled',
					requires_schedule: true,
				},
			],
			can_transition: true,
			assignee_id: 0,
			due_at: null,
		},
		comments: [],
		authors: [ { name: 'Ada', post_count: 3 } ],
		shareControls: [
			{ target: 'facebook', label: 'Facebook', url: 'https://x.test' },
		],
		can: { moderate: true, publish: true },
		...overrides,
	};
}

let container;
let root;

function mount( props = {} ) {
	act( () => {
		root.render( <BlogProApp config={ config() } { ...props } /> );
	} );
}

const text = () => container.textContent;
const find = ( selector ) => container.querySelector( selector );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	global.fetch = jest.fn( () =>
		Promise.resolve( {
			ok: true,
			json: () => Promise.resolve( { ok: true, data: {} } ),
		} )
	);
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
	jest.restoreAllMocks();
	delete global.fetch;
} );

describe( 'BlogProApp', () => {
	describe( 'it names the post it is talking about', () => {
		it( 'states the selected post rather than implying a site-wide total', () => {
			// The defect: every panel was computed for whichever post sorted first, and nothing said
			// so, under a heading ("First-party reading signals") that reads as the whole site.
			mount();

			expect( find( '.corex-blog-pro__subject' ) ).not.toBeNull();
			expect( text() ).toContain( 'Launch week' );
		} );

		it( 'names the period the figures cover', () => {
			// "120 views" means nothing without "in the last 30 days".
			mount();

			expect( text() ).toContain( '30' );
		} );

		it( 'offers every listed post as a choice', () => {
			mount();
			const selector = find( '[data-corex-blog-post-selector]' );

			expect( selector ).not.toBeNull();
			expect( selector.textContent ).toContain( 'Launch week' );
		} );

		it( 'says so plainly when there are no posts at all', () => {
			mount( {
				config: config( {
					posts: [],
					selectedPostId: 0,
					selectedPost: null,
					analytics: null,
					editorial: null,
					shareControls: [],
				} ),
			} );

			expect( text() ).toContain( 'Create a WordPress post' );
		} );
	} );

	describe( 'it speaks in words, not slugs', () => {
		it( 'renders the editorial state and native status as labels', () => {
			mount();

			expect( text() ).toContain( 'Ready for review' );
			expect( text() ).toContain( 'Pending review' );
			expect( text() ).not.toContain( 'ready_for_review' );
		} );

		it( 'does not print an author line assembled by concatenation', () => {
			// `${ author.name } · ${ author.post_count }` was built outside the i18n system and could
			// not be translated or pluralized.
			mount();

			expect( text() ).toContain( 'Ada' );
			expect( text() ).not.toContain( 'Ada · 3' );
		} );
	} );

	describe( 'it tells you what happened', () => {
		it( 'renders a notice in a live region when an action fails', async () => {
			// The reducer's `error` case had no caller at all; neither did the notice it builds.
			global.fetch = jest.fn( () =>
				Promise.reject( new Error( 'Network is down' ) )
			);
			mount();

			await act( async () => {
				find( '[data-corex-blog-refresh]' ).dispatchEvent(
					new window.MouseEvent( 'click', { bubbles: true } )
				);
			} );

			const notice = find( '.corex-blog-pro__notice' );
			expect( notice ).not.toBeNull();
			expect( notice.getAttribute( 'role' ) ).toBe( 'alert' );
		} );

		it( 'reaches the REST routes with the localized nonce', async () => {
			mount();

			await act( async () => {
				find( '[data-corex-blog-refresh]' ).dispatchEvent(
					new window.MouseEvent( 'click', { bubbles: true } )
				);
			} );

			expect( global.fetch ).toHaveBeenCalled();
			const [ url, init ] = global.fetch.mock.calls[ 0 ];
			expect( String( url ) ).toContain( '/corex/v1/blog/' );
			expect( init.headers[ 'X-WP-Nonce' ] ).toBe( 'test-nonce' );
		} );
	} );

	describe( 'analytics admit what they do not know', () => {
		it( 'distinguishes a post nobody has opened from one never seen', () => {
			mount( {
				config: config( {
					analytics: {
						views: 0,
						reads: 0,
						share_clicks: 0,
						unique_visitors: 0,
						average_read_seconds: 0,
						has_data: false,
					},
				} ),
			} );

			expect( text() ).toContain( 'No reading data yet' );
		} );

		it( 'shows a real zero when analytics has seen the post', () => {
			mount( {
				config: config( {
					analytics: {
						views: 0,
						reads: 0,
						share_clicks: 1,
						unique_visitors: 1,
						average_read_seconds: 0,
						has_data: true,
					},
				} ),
			} );

			expect( text() ).not.toContain( 'No reading data yet' );
		} );
	} );
} );
