/**
 * Approving a request through its preview, and the grants it makes (spec 112, User Story 1,
 * FR-002, FR-004, FR-005, FR-007). The whole workspace is rendered; the network is the one thing
 * replaced.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import AccessWorkspace from '../AccessWorkspace.js';

const HASH = 'b'.repeat( 64 );

const PREVIEW = {
	request_id: 41,
	requester: { id: 12, name: 'Sara Ahmed' },
	reason: 'I run the exports.',
	area: 'data',
	abilities: [
		{ key: 'corex_manage_data', label: 'Manage data', risk: 'dangerous' },
		{
			key: 'corex_export_personal_data',
			label: 'Export personal data',
			risk: 'sensitive',
		},
	],
	blockers: [],
	requires_typed_name: true,
	typed_name: 'corex_manage_data',
	expiry_choices: [ 7, 30, 90 ],
	expiry_default: 30,
	target_hash: HASH,
};

const GRANT = {
	id: 41,
	person: { id: 12, name: 'Sara Ahmed' },
	area: 'data',
	abilities: [
		{ key: 'corex_manage_data', label: 'Manage data', risk: 'dangerous' },
	],
	approver: { id: 7, name: 'Admin' },
	approved_at: '2026-10-21T10:00:00+00:00',
	note: 'Covering exports this month.',
	expires_at: '2026-11-20T10:00:00+00:00',
	in_effect: true,
	state_note: null,
};

const config = ( extra = {} ) => ( {
	restUrl: '/corex/v1/access',
	nonce: 'nonce',
	actorId: 7,
	requests: [
		{
			id: 41,
			requester: 'Sara Ahmed',
			ability: '',
			area: 'data',
			reason: 'I run the exports.',
			requested_at: '2026-10-21T09:00:00+00:00',
		},
	],
	grants: [],
	audit: [],
	matrix: { roles: [], rows: [], conflicts: [] },
	...extra,
} );

const answered = ( data ) => ( { ok: true, json: async () => ( { data } ) } );

let container;
let root;
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

async function type( label, value ) {
	const field = [ ...container.querySelectorAll( 'label' ) ].find(
		( candidate ) => candidate.textContent.includes( label )
	).control;
	const setter = Object.getOwnPropertyDescriptor(
		Object.getPrototypeOf( field ),
		'value'
	).set;
	await act( async () => {
		setter.call( field, value );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function render( extra ) {
	await act( async () => {
		root.render( <AccessWorkspace config={ config( extra ) } /> );
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.HTMLDialogElement.prototype.showModal = function () {
		this.setAttribute( 'open', '' );
	};
	window.HTMLDialogElement.prototype.close = function () {
		this.removeAttribute( 'open' );
	};
} );

beforeEach( () => {
	sent = [];
	answers = [];
	window.fetch = async ( url, request ) => {
		sent.push( {
			url,
			method: request.method || 'GET',
			body: request.body ? JSON.parse( request.body ) : null,
		} );
		return answers.shift();
	};
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

it( 'shows what an approval grants, with each risk and the reason, before anything is sent', async () => {
	await render();
	answers.push( answered( { preview: PREVIEW } ) );

	await press( 'Approve' );
	const dialog = container.querySelector( 'dialog' );

	expect( sent.map( ( call ) => call.url ) ).toEqual( [
		'/corex/v1/access/requests/41/preview',
	] );
	expect( dialog.textContent ).toContain( 'Manage data' );
	expect( dialog.textContent ).toContain( 'Dangerous' );
	expect( dialog.textContent ).toContain( 'Export personal data' );
	expect( dialog.textContent ).toContain( 'Sensitive' );
	expect( dialog.textContent ).toContain( 'I run the exports.' );
	expect(
		dialog.querySelector( 'input[type="radio"][value="30"]' ).checked
	).toBe( true );
} );

it( 'approves only once a note is written and the name is typed, and sends both with the expiry', async () => {
	await render();
	answers.push( answered( { preview: PREVIEW } ) );
	await press( 'Approve' );

	expect( button( 'Approve access' ).disabled ).toBe( true );
	await type( 'Note', 'Covering exports this month.' );
	expect( button( 'Approve access' ).disabled ).toBe( true );
	await type( 'Type corex_manage_data', 'corex_manage_data' );
	expect( button( 'Approve access' ).disabled ).toBe( false );

	answers.push(
		answered( { result: { state: 'completed', errors: [] } } ),
		answered( { grants: [ GRANT ] } )
	);
	await press( 'Approve access' );

	const decision = sent[ 1 ];
	expect( decision.url ).toBe( '/corex/v1/access/requests/41/decision' );
	expect( decision.body ).toMatchObject( {
		approved: true,
		note: 'Covering exports this month.',
		expiry_days: 30,
		confirmation: {
			operation_kind: 'access.request.approve',
			target_hash: HASH,
			actor_id: 7,
			required_phrase: 'corex_manage_data',
		},
	} );
	expect( container.querySelector( 'dialog' ) ).toBeNull();
	expect( container.textContent ).toContain( 'No one is waiting for access' );
	expect( sent[ 2 ].url ).toBe( '/corex/v1/access/grants' );
	expect( container.textContent ).toContain( 'Covering exports this month.' );
} );

it( 'keeps the dialog and says why when the server refuses the approval', async () => {
	await render();
	answers.push(
		answered( {
			preview: {
				...PREVIEW,
				requires_typed_name: false,
				typed_name: null,
			},
		} )
	);
	await press( 'Approve' );
	await type( 'Note', 'Covering.' );
	answers.push(
		answered( {
			result: {
				state: 'blocked',
				errors: [
					{
						code: 'confirmation_invalid',
						message:
							'The approval confirmation is invalid or expired.',
					},
				],
			},
		} )
	);

	await press( 'Approve access' );

	expect( container.querySelector( 'dialog' ) ).not.toBeNull();
	expect( container.textContent ).toContain(
		'The approval confirmation is invalid or expired.'
	);
	expect( container.textContent ).toContain( 'I run the exports.' );
} );

it( 'lists each grant with who approved it, why, and when it ends, and revokes it', async () => {
	await render( {
		requests: [],
		grants: [
			GRANT,
			{
				...GRANT,
				id: 9,
				note: 'Approved before expiries.',
				expires_at: null,
				in_effect: false,
				state_note: 'removed_outside_corex',
			},
		],
	} );
	const list = container.querySelector( '.corex-access__grants' );

	expect( list.textContent ).toContain( 'Sara Ahmed' );
	expect( list.textContent ).toContain( 'Admin' );
	expect( list.textContent ).toContain( 'Covering exports this month.' );
	expect( list.textContent ).toContain(
		'No expiry (granted before expiries existed)'
	);
	expect( list.textContent ).toContain(
		'No longer in effect: the ability was removed outside CoreX'
	);

	answers.push( answered( { result: { state: 'completed', errors: [] } } ) );
	await act( async () => {
		list.querySelector( 'button' ).click();
	} );

	expect( sent[ 0 ] ).toMatchObject( {
		url: '/corex/v1/access/grants/41/revoke',
		method: 'POST',
	} );
	expect( list.textContent ).not.toContain( 'Covering exports this month.' );
} );
