/**
 * The controls Blog Pro never had (spec 075, FR-2/FR-3).
 *
 * `POST /blog/editorial/{id}/transition` and `POST /blog/comments/{id}/moderate` are the point of an
 * editorial workspace and had no caller in the product; `buildTransitionPayload` shaped a request
 * nobody sent. These drive both through the rendered components, so they cannot pass unless the
 * wiring exists.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import EditorialPanel from '../EditorialPanel.js';
import ModerationPanel from '../ModerationPanel.js';

const EDITORIAL = {
	post_id: 7,
	editorial_state: 'ready_for_review',
	native_status: 'pending',
	editorial_state_label: 'Ready for review',
	native_status_label: 'Pending review',
	transitions: [
		{ key: 'approved', label: 'Approved', requires_schedule: false },
		{ key: 'scheduled', label: 'Scheduled', requires_schedule: true },
	],
	can_transition: true,
};

const COMMENT = {
	comment_id: 31,
	post_id: 7,
	author: 'Ada',
	content: 'This helped, thank you.',
	submitted_at: '2026-07-26T09:00:00+00:00',
	state: 'pending',
	state_label: 'Awaiting review',
	first_comment: true,
	likely_spam: false,
	can_moderate: true,
};

let container;
let root;

const find = ( selector ) => container.querySelector( selector );
const all = ( selector ) => [ ...container.querySelectorAll( selector ) ];

function render( element ) {
	act( () => root.render( element ) );
}

function click( node ) {
	act( () =>
		node.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		)
	);
}

/**
 * Pick an option by its visible text.
 *
 * CorexSelect commits on `mousedown`, not `click` — it has to, or the button's own blur closes the
 * menu before the click lands. Driving the real control rather than the component's state is the
 * point: it is what proves the panel is wired to the approved control (DECISIONS #141).
 *
 * @param {string} label The option's visible text.
 */
function choose( label ) {
	click( find( '.corex-select__button' ) );
	const option = all( '[role="option"]' ).find( ( node ) =>
		node.textContent.includes( label )
	);
	act( () =>
		option.dispatchEvent(
			new window.MouseEvent( 'mousedown', { bubbles: true } )
		)
	);
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

describe( 'EditorialPanel', () => {
	it( 'shows the CoreX state and the WordPress status in words', () => {
		// Both were printed as raw slugs on an otherwise translated screen.
		render(
			<EditorialPanel
				editorial={ EDITORIAL }
				onTransition={ jest.fn() }
			/>
		);

		expect( container.textContent ).toContain( 'Ready for review' );
		expect( container.textContent ).toContain( 'Pending review' );
		expect( container.textContent ).not.toContain( 'ready_for_review' );
	} );

	it( 'offers no way to move a post the actor may not move', () => {
		// Hidden, not shown and refused (DECISIONS #159).
		render(
			<EditorialPanel
				editorial={ { ...EDITORIAL, can_transition: false } }
				onTransition={ jest.fn() }
			/>
		);

		expect( find( '[data-corex-blog-transition]' ) ).toBeNull();
		expect( container.textContent ).toContain( 'do not have permission' );
	} );

	it( 'will not submit until a destination is chosen', () => {
		render(
			<EditorialPanel
				editorial={ EDITORIAL }
				onTransition={ jest.fn() }
			/>
		);

		expect( find( '[data-corex-blog-transition]' ).disabled ).toBe( true );
	} );

	it( 'sends the chosen state through the payload builder', () => {
		const onTransition = jest.fn();
		render(
			<EditorialPanel
				editorial={ EDITORIAL }
				onTransition={ onTransition }
			/>
		);

		// Drive the real CorexSelect rather than reaching into state.
		choose( 'Approved' );
		click( find( '[data-corex-blog-transition]' ) );

		expect( onTransition ).toHaveBeenCalledWith(
			expect.objectContaining( { state: 'approved' } )
		);
	} );

	it( 'requires a date before it will schedule, rather than letting the server throw', () => {
		// `EditorialWorkflowService::scheduledStatus()` throws without a timestamp. Failing there
		// gives the person an exception; failing here gives them a field to fill in.
		const onTransition = jest.fn();
		render(
			<EditorialPanel
				editorial={ EDITORIAL }
				onTransition={ onTransition }
			/>
		);

		choose( 'Scheduled' );

		expect( find( 'input[type="datetime-local"]' ) ).not.toBeNull();
		expect( find( '[data-corex-blog-transition]' ).disabled ).toBe( true );

		click( find( '[data-corex-blog-transition]' ) );
		expect( onTransition ).not.toHaveBeenCalled();
	} );

	it( 'says so when the post has no editorial record at all', () => {
		render(
			<EditorialPanel editorial={ null } onTransition={ jest.fn() } />
		);

		expect( container.textContent ).toContain( 'no editorial record' );
	} );
} );

describe( 'ModerationPanel', () => {
	it( 'shows what the comment actually says, not just who wrote it', () => {
		// A queue of author + state cannot be moderated from — you would open each comment elsewhere.
		render(
			<ModerationPanel
				comments={ [ COMMENT ] }
				onModerate={ jest.fn() }
			/>
		);

		expect( container.textContent ).toContain( 'This helped, thank you.' );
		expect( container.textContent ).toContain( 'Ada' );
		expect( container.textContent ).toContain( 'Awaiting review' );
		expect( find( 'time' ).getAttribute( 'datetime' ) ).toBe(
			COMMENT.submitted_at
		);
	} );

	it( 'offers approve, spam, and trash — and nothing else', () => {
		// Three, not five: the queue holds only comments awaiting review, so "unapprove" has nothing
		// to act on, and edit/reply are excluded by spec 075 §10.
		render(
			<ModerationPanel
				comments={ [ COMMENT ] }
				onModerate={ jest.fn() }
			/>
		);

		expect(
			all( '[data-corex-blog-moderate]' ).map(
				( b ) => b.dataset.corexBlogModerate
			)
		).toEqual( [ 'approve', 'spam', 'trash' ] );
	} );

	it( 'reports the action against the comment it belongs to', () => {
		const onModerate = jest.fn();
		render(
			<ModerationPanel
				comments={ [ COMMENT ] }
				onModerate={ onModerate }
			/>
		);

		click( find( '[data-corex-blog-moderate="spam"]' ) );

		expect( onModerate ).toHaveBeenCalledWith( 31, 'spam' );
	} );

	it( 'hides the actions from someone who may not moderate', () => {
		render(
			<ModerationPanel
				comments={ [ { ...COMMENT, can_moderate: false } ] }
				onModerate={ jest.fn() }
			/>
		);

		expect( all( '[data-corex-blog-moderate]' ) ).toHaveLength( 0 );
		// The comment is still readable — the queue is not hidden, only the controls.
		expect( container.textContent ).toContain( 'This helped, thank you.' );
	} );

	it( 'flags what a moderator would want to notice first', () => {
		render(
			<ModerationPanel
				comments={ [ { ...COMMENT, likely_spam: true } ] }
				onModerate={ jest.fn() }
			/>
		);

		expect( container.textContent ).toContain( 'Looks like spam' );
		expect( container.textContent ).toContain(
			'First comment from this person'
		);
	} );

	it( 'treats an empty queue as the good outcome, not an absence', () => {
		render( <ModerationPanel comments={ [] } onModerate={ jest.fn() } /> );

		expect( container.textContent ).toContain(
			'Nothing is waiting for you here.'
		);
	} );
} );
