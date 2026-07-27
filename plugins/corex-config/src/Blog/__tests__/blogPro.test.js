import {
	blogEndpoint,
	blogReducer,
	buildTransitionPayload,
	initialBlogState,
	normalizeAnalytics,
} from '../blogProState.js';

const analyticsPayload = {
	views: '12',
	reads: '6',
	share_clicks: '3',
	unique_visitors: '4',
	average_read_seconds: '45',
};

describe( 'Blog Pro client state', () => {
	it( 'builds stable Blog REST endpoints', () => {
		expect( blogEndpoint( '/corex/v1', 'blog/analytics' ) ).toBe(
			'/corex/v1/blog/analytics'
		);
		expect(
			blogEndpoint( '/corex/v1/', '/blog/comments/12/moderate' )
		).toBe( '/corex/v1/blog/comments/12/moderate' );
	} );

	it( 'normalizes the aggregate into the cards the panel renders', () => {
		// `chart` and `topPosts` used to be shaped here too. Nothing on the server has ever sent
		// either, so both were permanently empty (spec 075, T052).
		const analytics = normalizeAnalytics( analyticsPayload );

		expect( analytics.cards ).toMatchObject( {
			views: 12,
			reads: 6,
			shareClicks: 3,
			uniqueVisitors: 4,
			averageReadSeconds: 45,
			engagement: 50,
		} );
		expect( analytics ).not.toHaveProperty( 'chart' );
		expect( analytics ).not.toHaveProperty( 'topPosts' );
	} );

	it( 'serializes an editorial transition without raw network fields', () => {
		expect(
			buildTransitionPayload( {
				state: 'ready_for_review',
				assigneeId: '7',
				dueAt: '2026-07-11T17:00:00+00:00',
				note: ' Ready for review. ',
			} )
		).toEqual( {
			state: 'ready_for_review',
			assignee_id: 7,
			due_at: '2026-07-11T17:00:00+00:00',
			scheduled_at: '',
			note: 'Ready for review.',
		} );
	} );

	it( 'tracks analytics, editorial, comments, authors, and recoverable errors', () => {
		let state = blogReducer( initialBlogState(), {
			type: 'loaded',
			payload: {
				analytics: analyticsPayload,
				editorial: {
					post_id: 42,
					editorial_state: 'draft',
					native_status: 'draft',
				},
				comments: [ { comment_id: 9, state: 'pending' } ],
				authors: [ { name: 'Mina Author', post_count: 2 } ],
				shareControls: [
					{
						target: 'copy_link',
						label: 'Copy link',
						url: 'https://example.test/post',
					},
				],
			},
		} );

		expect( state.analytics.cards.views ).toBe( 12 );
		expect( state.comments[ 0 ].state ).toBe( 'pending' );

		state = blogReducer( state, {
			type: 'transitioned',
			editorial: {
				post_id: 42,
				editorial_state: 'ready_for_review',
				native_status: 'pending',
			},
		} );
		state = blogReducer( state, {
			type: 'commentModerated',
			commentId: 9,
			state: 'approved',
		} );

		expect( state.editorial.editorial_state ).toBe( 'ready_for_review' );
		expect( state.comments[ 0 ].state ).toBe( 'approved' );
		expect( state.notice ).toEqual( {
			tone: 'success',
			message: 'Comment updated.',
		} );

		state = blogReducer( state, {
			type: 'error',
			message: 'Blog update failed.',
		} );
		expect( state.notice ).toEqual( {
			tone: 'error',
			message: 'Blog update failed.',
		} );
	} );
} );
