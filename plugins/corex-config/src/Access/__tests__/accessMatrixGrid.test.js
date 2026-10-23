/**
 * The role matrix grid (spec 117, US-ACC-02): every cell says its state in words, a cell that cannot
 * be set says why, "Edit" opens the role's editor, and an applied change shows in the grid.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import AccessWorkspace from '../AccessWorkspace.js';
import { accessReducer, initialAccessState } from '../accessState.js';

const cell = ( state, extra = {} ) => ( {
	effect: 'inherit',
	editable: true,
	reason: null,
	state,
	ignoredDeny: false,
	...extra,
} );

const matrix = {
	roles: [
		{ key: 'administrator', name: 'Administrator', administers: true },
		{ key: 'editor', name: 'Editor', administers: false },
	],
	rows: [
		{
			key: 'corex_manage_admin',
			label: 'Access CoreX admin',
			description: 'Access every CoreX product area.',
			group: 'corex-admin',
			groupLabel: 'CoreX admin',
			locked: true,
			cells: {
				administrator: cell( 'allowed_admin', {
					editable: false,
					reason: 'site_administrator',
				} ),
				editor: cell( 'locked', {
					editable: false,
					reason: 'locked_definition',
				} ),
			},
		},
		{
			key: 'corex_manage_email',
			label: 'Manage Email Studio',
			description: 'Configure, test, and inspect CoreX email.',
			group: 'email-studio',
			groupLabel: 'Email Studio',
			locked: false,
			cells: {
				administrator: cell( 'allowed_admin', {
					editable: false,
					reason: 'site_administrator',
					effect: 'deny',
					ignoredDeny: true,
				} ),
				editor: cell( 'allowed_set', { effect: 'allow' } ),
			},
		},
	],
	conflicts: [],
};

let container;
let root;

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( async () => {
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	await act( async () => {
		root.render(
			<AccessWorkspace
				config={ {
					restUrl: '/corex/v1/access',
					nonce: 'n',
					matrix,
					requests: [],
					grants: [],
				} }
			/>
		);
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

const rowOf = ( label ) =>
	[ ...container.querySelectorAll( '.corex-access__grid tbody tr' ) ].find(
		( row ) => row.querySelector( 'th' )?.textContent.includes( label )
	);

it( 'opens on a grid of abilities by area and roles, each cell in words', () => {
	const areas = [
		...container.querySelectorAll( '.corex-access__grid-area th' ),
	].map( ( th ) => th.textContent );
	const email = rowOf( 'Manage Email Studio' );

	expect( areas ).toEqual( [ 'CoreX admin', 'Email Studio' ] );
	expect(
		[ ...email.querySelectorAll( '.corex-access__state' ) ].map(
			( state ) => state.textContent
		)
	).toEqual( [
		'Allowed through site administration (inherited)',
		'Allowed (set here)',
	] );
	// The key is kept for developers, out of the text.
	expect( email.querySelector( 'th' ).getAttribute( 'title' ) ).toBe(
		'corex_manage_email'
	);
	expect( email.textContent ).not.toContain( 'corex_' );
} );

it( 'explains a locked cell and an ignored Deny to whoever focuses or points at it', () => {
	const locked = rowOf( 'Access CoreX admin' ).querySelectorAll( 'td' )[ 1 ];
	const admin = rowOf( 'Manage Email Studio' ).querySelector( 'td' );
	const why = locked.querySelector( '.corex-access__why' );

	expect( why.getAttribute( 'tabindex' ) ).toBe( '0' );
	expect(
		document.getElementById( why.getAttribute( 'aria-describedby' ) )
			.textContent
	).toContain( 'comes only with site administration' );
	expect( admin.querySelector( '[role="tooltip"]' ).textContent ).toContain(
		'A Deny stored here before is kept and ignored.'
	);
} );

it( 'offers Edit on an ordinary role only, and opens its editor', async () => {
	const edits = [ ...container.querySelectorAll( 'thead button' ) ];

	expect(
		edits.map( ( edit ) => edit.getAttribute( 'aria-label' ) )
	).toEqual( [ 'Edit Editor' ] );

	await act( async () => edits[ 0 ].click() );

	expect( container.querySelector( 'h2' ).textContent ).toBe(
		'Editing Editor'
	);
	expect( container.querySelector( '.corex-access__grid' ) ).toBeNull();
	expect( container.querySelector( '.corex-access__matrix' ) ).not.toBeNull();
} );

it( 'shows an applied change as the state it resolves to', () => {
	let state = accessReducer( initialAccessState(), {
		type: 'loaded',
		payload: matrix,
	} );
	state = accessReducer( state, {
		type: 'applied',
		role: 'editor',
		changes: { corex_manage_email: 'deny' },
		message: 'Done',
	} );

	expect( state.rows[ 1 ].cells.editor ).toMatchObject( {
		effect: 'deny',
		state: 'denied_set',
	} );
} );
