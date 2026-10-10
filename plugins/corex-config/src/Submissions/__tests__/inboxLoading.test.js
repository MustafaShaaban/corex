/**
 * What the Submissions inbox shows while it waits (spec 108, slice 6; T061).
 *
 * The whole screen is rendered and the network is the one thing replaced: every request waits
 * for the test to answer it, so the moment between asking and being told can be looked at. On a
 * local site that moment is a few milliseconds, which is how a list comes to say "no matching
 * submissions" when it has only not heard yet.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { InboxApp } from '../index.js';
import { trashConfirmation } from '../inbox.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { variant, isDestructive, children, ...rest } ) => (
		<button type="button" { ...rest }>
			{ children }
		</button>
	),
} ) );

const row = ( id, name ) => ( {
	id,
	submitter_name: name,
	submitter_email: `${ name.toLowerCase() }@example.com`,
	flow: 'Lead form',
	status: 'new',
	owner_type: 'none',
	created_at: '2026-10-01T10:00:00+00:00',
	read_at: '2026-10-01T11:00:00+00:00',
} );

const page = ( ...items ) => ( { items, total: 60, page: 1, per_page: 25 } );

// Already read, so opening it sends nothing but the request for it.
const submission = {
	...row( 41, 'Salma' ),
	form: 'lead',
	updated_at: '2026-10-01T11:00:00+00:00',
	retention_state: 'active',
	questions: [ { key: 'need', label: 'What do you need?', type: 'text' } ],
	values: { need: 'Branding' },
	owners: [],
	notes: [],
};

const ok = ( data ) => ( {
	ok: true,
	status: 200,
	envelope: { ok: true, data },
} );
const failed = ( message ) => ( {
	ok: false,
	status: 500,
	envelope: { ok: false, message },
} );

// Which request is which. The screen's addresses start where its configured base ends, and
// here that base is empty.
const theList = ( call ) => call.method === 'get' && call.url.startsWith( '?' );
const theSubmission = ( call ) =>
	call.method === 'get' && /^\/\d+$/.test( call.url );
const aChange = ( call ) => call.method !== 'get';

let container;
let root;
/** Every request the screen has made, each waiting for the test. */
let calls;

function holdTheNetwork() {
	const held =
		( method ) =>
		( url, ...rest ) =>
			new Promise( ( resolve ) => {
				calls.push( { method, url, body: rest[ 0 ], resolve } );
			} );

	window.Corex = {
		api: {
			get: held( 'get' ),
			post: held( 'post' ),
			patch: held( 'patch' ),
			delete: held( 'delete' ),
		},
	};
}

// Answers the request of that kind that has waited longest.
async function answer( which, result ) {
	const call = calls.find(
		( candidate ) => ! candidate.done && which( candidate )
	);
	call.done = true;
	await act( async () => {
		call.resolve( result );
	} );
}

const asked = ( which ) => calls.filter( which ).length;

const button = ( name ) =>
	[ ...document.body.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);

function press( name ) {
	act( () => {
		button( name ).click();
	} );
}

function type( field, text ) {
	const { set } = Object.getOwnPropertyDescriptor(
		Object.getPrototypeOf( field ),
		'value'
	);
	act( () => {
		set.call( field, text );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

const list = () => container.querySelector( '.corex-inbox .corex-loadable' );
const count = () => container.querySelector( '.corex-inbox__count' );

async function openSalma() {
	await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );
	act( () => {
		container.querySelector( '.corex-inbox__row-button' ).click();
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( removeDateTimeConfig );

beforeEach( () => {
	calls = [];
	holdTheNetwork();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
	act( () => {
		root.render( <InboxApp /> );
	} );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	delete window.Corex;
} );

describe( 'the list', () => {
	it( 'shows what is coming before the first answer, and does not call the inbox empty', () => {
		expect( list().dataset.corexState ).toBe( 'loading' );
		expect(
			list().querySelector( '.corex-admin-skeleton' )
		).not.toBeNull();
		expect( container.textContent ).not.toContain(
			'No matching submissions'
		);
		// A count of nothing is a statement about the inbox, and nothing is known of it yet.
		expect( container.textContent ).not.toContain(
			'0 accessible submissions'
		);
	} );

	it( 'keeps its rows while the next page is on its way, and the count waits with them', async () => {
		await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );

		expect( list().dataset.corexState ).toBe( 'ready' );
		expect( count().textContent ).toBe( '60 accessible submissions' );

		press( 'Next' );

		expect( list().dataset.corexState ).toBe( 'refreshing' );
		expect( container.textContent ).toContain( 'Salma' );
		expect( count().hasAttribute( 'data-corex-waiting' ) ).toBe( true );

		await answer( theList, ok( page( row( 42, 'Omar' ) ) ) );

		expect( list().dataset.corexState ).toBe( 'ready' );
		expect( container.textContent ).toContain( 'Omar' );
		expect( container.textContent ).not.toContain( 'Salma' );
		expect( count().hasAttribute( 'data-corex-waiting' ) ).toBe( false );
	} );

	it( 'says the first load failed where the rows would be, once, and asks again', async () => {
		await answer( theList, failed( 'The inbox is not reachable.' ) );

		expect( list().dataset.corexState ).toBe( 'error' );
		expect( container.textContent ).toContain(
			'The inbox is not reachable.'
		);
		expect( container.textContent ).not.toContain(
			'No matching submissions'
		);
		expect(
			[ ...container.querySelectorAll( 'button' ) ].filter(
				( candidate ) => candidate.textContent.trim() === 'Try again'
			)
		).toHaveLength( 1 );

		press( 'Try again' );

		expect( asked( theList ) ).toBe( 2 );

		// The answer is the rows, and nothing is announced: asking again is not a success
		// with something to say. (The press itself used to be taken for the message.)
		await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );

		expect( list().dataset.corexState ).toBe( 'ready' );
		expect( container.textContent ).toContain( 'Salma' );
		expect( container.querySelector( '.corex-inbox__notice' ) ).toBeNull();
	} );

	it( 'shows what is coming for the trash, not the inbox’s rows under the trash’s headings', async () => {
		await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );

		press( 'Trash' );

		expect( list().dataset.corexState ).toBe( 'loading' );
		expect( container.textContent ).not.toContain( 'Salma' );
	} );
} );

describe( 'the pane', () => {
	const pane = () => document.body.querySelector( '.corex-pane' );

	it( 'shows what is coming while the submission is read', async () => {
		await openSalma();

		expect(
			pane().querySelector( '.corex-admin-skeleton' )
		).not.toBeNull();

		await answer( theSubmission, ok( { submission } ) );

		expect( pane().querySelector( '.corex-admin-skeleton' ) ).toBeNull();
		expect( pane().textContent ).toContain( 'What do you need?' );
	} );

	it( 'marks the control that was pressed, and sends nothing else until the pane is current again', async () => {
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );

		type( pane().querySelector( 'textarea[id$="-note"]' ), 'Called her.' );
		press( 'Add note' );

		expect( button( 'Add note' ).dataset.corexWorking ).toBe( 'true' );
		expect( button( 'Mark unread' ).disabled ).toBe( true );
		expect( button( 'Move to trash' ).disabled ).toBe( true );

		press( 'Add note' );
		expect( asked( aChange ) ).toBe( 1 );

		// The note is in; the list and the submission are read again behind the button.
		await answer( aChange, ok( {} ) );
		expect( button( 'Add note' ).dataset.corexWorking ).toBe( 'true' );

		await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );
		await answer( theSubmission, ok( { submission } ) );

		expect(
			button( 'Add note' ).hasAttribute( 'data-corex-working' )
		).toBe( false );
		expect( button( 'Mark unread' ).disabled ).toBe( false );
	} );

	it( 'leaves the rows in reach while they are read again behind a change', async () => {
		// Opening a submission, or changing one, reads the list again. Nobody asked for
		// other rows, and a row pressed in that moment has to open: taken out of reach, the
		// press did nothing (found by the browser tests on a slower machine, PR #326).
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );
		press( 'Mark unread' );
		await answer( aChange, ok( {} ) );

		expect( asked( theList ) ).toBe( 2 );
		expect( list().dataset.corexState ).toBe( 'ready' );
		expect(
			list()
				.querySelector( '.corex-loadable__body' )
				.hasAttribute( 'inert' )
		).toBe( false );
	} );

	it( 'asks once when a confirmation is pressed twice', async () => {
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );

		press( 'Move to trash' );
		const confirm = trashConfirmation( 'trash', 1 ).confirm;
		const confirming = () =>
			[ ...document.body.querySelectorAll( 'button' ) ]
				.filter(
					( candidate ) => candidate.textContent.trim() === confirm
				)
				.at( -1 );

		act( () => confirming().click() );

		expect( confirming().dataset.corexWorking ).toBe( 'true' );

		act( () => confirming().click() );

		expect( asked( aChange ) ).toBe( 1 );
	} );
} );

describe( 'an action in the pane that fails', () => {
	const pane = () => document.body.querySelector( '.corex-pane' );
	const reasons = () =>
		[ ...container.querySelectorAll( '[role="alert"]' ) ].filter(
			( alert ) =>
				alert.textContent.includes( 'Somebody else changed it.' )
		);
	// The pane is a dialog written inside the inbox, so "above the list" is whatever is not in it.
	const inThePane = () =>
		reasons().filter( ( alert ) => alert.closest( '.corex-pane' ) ).length;
	const aboveTheList = () => reasons().length - inThePane();

	// jsdom lays nothing out, so it has nothing to scroll.
	beforeAll( () => {
		window.Element.prototype.scrollIntoView = () => {};
	} );
	afterAll( () => {
		delete window.Element.prototype.scrollIntoView;
	} );

	// The reason was drawn in the inbox's notice area, which the open pane covers: whoever
	// pressed the button saw it stop working and nothing else (#306).
	it( 'says why in the pane, where the button was pressed, and once', async () => {
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );

		press( 'Mark unread' );
		await answer( aChange, failed( 'Somebody else changed it.' ) );

		expect( inThePane() ).toBe( 1 );
		expect( aboveTheList() ).toBe( 0 );
		expect( button( 'Mark unread' ).disabled ).toBe( false );
	} );

	it( 'still says why above the list once the pane is closed', async () => {
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );
		press( 'Mark unread' );
		await answer( aChange, failed( 'Somebody else changed it.' ) );

		act( () => {
			pane().querySelector( '.corex-dialog__close' ).click();
		} );

		expect( pane() ).toBeNull();
		expect( aboveTheList() ).toBe( 1 );
	} );
} );

describe( 'an action above the list', () => {
	it( 'marks Undo while it puts a submission back', async () => {
		await openSalma();
		await answer( theSubmission, ok( { submission } ) );
		press( 'Move to trash' );
		act( () =>
			[ ...document.body.querySelectorAll( 'button' ) ]
				.filter(
					( candidate ) =>
						candidate.textContent.trim() ===
						trashConfirmation( 'trash', 1 ).confirm
				)
				.at( -1 )
				.click()
		);
		await answer( aChange, ok( {} ) );
		await answer( theList, ok( page() ) );

		press( 'Undo' );

		expect( button( 'Undo' ).dataset.corexWorking ).toBe( 'true' );

		press( 'Undo' );

		// The trash, and one restore.
		expect( asked( aChange ) ).toBe( 2 );
	} );
} );

describe( 'the export dialog', () => {
	const theCounts = ( call ) => call.url === '/exports/preview';
	const thePastExports = ( call ) =>
		call.method === 'get' && call.url === '/exports';
	const dialog = () => document.body.querySelector( '.corex-export' );

	beforeEach( async () => {
		await answer( theList, ok( page( row( 41, 'Salma' ) ) ) );
		press( 'Export' );
	} );

	it( 'shows a bar where each count will be, and the number when it is known', async () => {
		expect(
			dialog().querySelectorAll(
				'.corex-export__count .corex-admin-skeleton'
			).length
		).toBeGreaterThan( 0 );

		await answer(
			theCounts,
			ok( { counts: { selected: 0, filtered: 60, accessible: 904 } } )
		);

		expect(
			dialog().querySelector(
				'.corex-export__count .corex-admin-skeleton'
			)
		).toBeNull();
		expect( dialog().textContent ).toContain( '904' );
	} );

	it( 'shows past exports as coming until it knows, and nothing for somebody who has made none', async () => {
		expect(
			dialog().querySelector(
				'.corex-admin-skeleton .corex-export__recent'
			)
		).not.toBeNull();

		await answer( thePastExports, ok( { exports: [] } ) );

		expect( dialog().querySelector( '.corex-export__recent' ) ).toBeNull();
	} );
} );
