/**
 * A staged change to a role is previewed, read and applied (#313).
 *
 * "Preview changes" was a primary button with no handler: the matrix could be edited and nothing
 * could save it. The whole workspace is rendered here and the network is the one thing replaced.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import AccessWorkspace from '../AccessWorkspace.js';

const HASH = 'a'.repeat( 64 );

const config = {
	restUrl: '/corex/v1/access',
	nonce: 'nonce',
	actorId: 7,
	requests: [],
	audit: [],
	matrix: {
		roles: [ { key: 'editor', name: 'Editor' } ],
		rows: [
			{
				key: 'corex_manage_forms',
				label: 'Manage forms',
				cells: {
					editor: { effect: 'inherit', editable: true, reason: null },
				},
			},
		],
		conflicts: [],
	},
};

const answered = ( data ) => ( { ok: true, json: async () => ( { data } ) } );

let container;
let root;
/** What the access routes were sent, and what they answer next. */
let sent;
let answers;

const button = ( name ) =>
	[ ...container.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);

async function press( name ) {
	await act( async () => {
		button( name ).click();
	} );
}

// The select takes a choice on mouse-down, before the press can blur its button.
async function stageAllow() {
	await act( async () => {
		container
			.querySelector( '.corex-access__matrix .corex-select__button' )
			.click();
	} );
	await act( async () => {
		[ ...container.querySelectorAll( '[role="option"]' ) ]
			.find( ( option ) => option.textContent.trim() === 'Allow' )
			.dispatchEvent(
				new window.MouseEvent( 'mousedown', { bubbles: true } )
			);
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	sent = [];
	answers = [];
	window.fetch = async ( url, request ) => {
		sent.push( { url, body: JSON.parse( request.body ) } );
		return answers.shift();
	};
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => {
		root.render( <AccessWorkspace config={ config } /> );
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	delete window.fetch;
} );

it( 'applies what was previewed, confirmed by the person who read it, and shows the role as it is now', async () => {
	await stageAllow();
	answers.push(
		answered( {
			allowed: true,
			blockers: [],
			changes: { corex_manage_forms: 'allow' },
			target_hash: HASH,
		} ),
		answered( {
			result: {
				state: 'completed',
				message: 'The role abilities were updated.',
				errors: [],
			},
		} )
	);

	await press( 'Preview changes' );

	expect( sent[ 0 ].url ).toBe( '/corex/v1/access/roles/editor/preview' );
	expect( container.textContent ).toContain( 'Changes to Editor' );
	expect( container.textContent ).toContain( 'Inherit → Allow' );

	await press( 'Apply changes' );

	expect( sent[ 1 ].url ).toBe( '/corex/v1/access/roles/editor/apply' );
	expect( sent[ 1 ].body.changes ).toEqual( {
		corex_manage_forms: 'allow',
	} );
	expect( sent[ 1 ].body.confirmation ).toMatchObject( {
		operation_kind: 'access.role.change',
		target_hash: HASH,
		actor_id: 7,
	} );
	expect(
		Date.parse( sent[ 1 ].body.confirmation.expires_at )
	).toBeGreaterThan( Date.now() );

	expect( container.textContent ).not.toContain( 'Changes to Editor' );
	expect( container.querySelector( '[role="status"]' ).textContent ).toBe(
		'The role abilities were updated.'
	);
	// Nothing is staged any more: the role has what was applied.
	expect( button( 'Preview changes' ).disabled ).toBe( true );
	expect(
		container.querySelector( '.corex-access__matrix .corex-select__button' )
			.textContent
	).toContain( 'Allow' );
} );

it( 'says why the safety policy refuses a change, and does not offer to apply it', async () => {
	await stageAllow();
	answers.push(
		answered( {
			allowed: false,
			blockers: [
				{ code: 'ability_locked', ability: 'corex_manage_forms' },
			],
			changes: { corex_manage_forms: 'allow' },
			target_hash: HASH,
		} )
	);

	await press( 'Preview changes' );

	expect( container.querySelector( '[role="alert"]' ).textContent ).toContain(
		'Manage forms: This ability is locked and cannot be changed.'
	);
	expect( button( 'Apply changes' ) ).toBeUndefined();
} );

it( 'keeps the preview open and says so when the server does not apply it', async () => {
	await stageAllow();
	answers.push(
		answered( {
			allowed: true,
			blockers: [],
			changes: { corex_manage_forms: 'allow' },
			target_hash: HASH,
		} ),
		answered( {
			result: {
				state: 'blocked',
				message: 'The role ability confirmation is invalid or expired.',
				errors: [],
			},
		} )
	);

	await press( 'Preview changes' );
	await press( 'Apply changes' );

	expect( container.textContent ).toContain( 'Changes to Editor' );
	expect( container.querySelector( '[role="alert"]' ).textContent ).toBe(
		'The role ability confirmation is invalid or expired.'
	);
	expect( button( 'Preview changes' ).disabled ).toBe( false );
} );

it( 'says so above the matrix when the preview cannot be asked for', async () => {
	await stageAllow();
	answers.push( { ok: false, json: async () => ( {} ) } );

	await press( 'Preview changes' );

	expect( container.querySelector( '[role="alert"]' ).textContent ).toContain(
		'Nothing changed'
	);
	expect( container.textContent ).not.toContain( 'Changes to Editor' );
} );
