/**
 * Asking for a record to be deleted, from its detail (spec 108, story 3).
 *
 * Delete does not delete: it asks the server what deleting would do, and a confirmation follows.
 * The detail used to close on the press, which left nothing on the screen between the press and
 * the confirmation, and nothing at all if the confirmation never came.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import RecordDetail from '../RecordDetail.js';

// WordPress's component library does not load under Jest here (its rich-text package refuses
// at import), so its button is stood in for by the element it draws. What is under test is
// what this screen gives it, which passes straight through.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { variant, isDestructive, children, ...rest } ) => (
		<button type="button" { ...rest }>
			{ children }
		</button>
	),
} ) );

let root;
let closed;
let preview;

const button = ( name ) =>
	[ ...document.body.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	closed = 0;
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );

	const explorer = {
		source: { fields: [ { key: 'email', label: 'Email', type: 'text' } ] },
		can: () => true,
		previewMutation: () =>
			new Promise( ( resolve ) => {
				preview = resolve;
			} ),
	};
	act( () => {
		root.render(
			<RecordDetail
				explorer={ explorer }
				record={ { id: 5, email: 'ada@example.test' } }
				close={ () => ( closed += 1 ) }
				edit={ () => {} }
			/>
		);
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

it( 'stays, with Delete working and the rest held, until there is something to confirm', async () => {
	act( () => {
		button( 'Delete' ).click();
	} );

	expect( closed ).toBe( 0 );
	expect( button( 'Delete' ).dataset.corexWorking ).toBe( 'true' );
	expect( button( 'Edit' ).disabled ).toBe( true );
	expect( button( 'Close' ).disabled ).toBe( true );

	await act( async () => {
		preview();
	} );

	expect( closed ).toBe( 1 );
} );

it( 'opens before its record has arrived, with nothing to act on yet', () => {
	act( () => {
		root.render(
			<RecordDetail
				explorer={ {
					source: { fields: [] },
					can: () => true,
					previewMutation: () => {},
				} }
				record={ null }
				close={ () => {} }
				edit={ () => {} }
			/>
		);
	} );

	expect(
		document.body.querySelector( '.corex-loadable' ).dataset.corexState
	).toBe( 'loading' );
	expect( document.body.textContent ).not.toContain(
		'This record has no readable fields.'
	);
	expect( button( 'Edit' ).disabled ).toBe( true );
	expect( button( 'Delete' ).disabled ).toBe( true );
	expect( button( 'Close' ).disabled ).toBe( false );
} );
