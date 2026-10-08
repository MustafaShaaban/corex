/**
 * What the three Notifications surfaces show while a request is on its way (spec 108, slice 1).
 *
 * Before this, each said "loading" in a sentence, and the list said it by removing itself: every
 * filter and every page turn swapped the notifications on screen for a line of text. The defects
 * held down here are the ones a person meets: a surface that looks empty when it is only waiting,
 * and a list that is taken away to say it is being fetched.
 *
 * The network is the one thing replaced. Each request is answered by the test, when the test
 * chooses, so the moment between asking and being told can be looked at.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';

import NotificationsApp from '../NotificationsApp.js';
import PreferencesPanel from '../PreferencesPanel.js';
import NotificationDrawer from '../../components/NotificationDrawer.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../../tests/Support/adminDateTimeConfig.js';

// WordPress supplies this module at run time; it is not installed here, so there is nothing
// real to load and the mock is the module.
jest.mock(
	'@wordpress/api-fetch',
	() => ( { __esModule: true, default: jest.fn() } ),
	{ virtual: true }
);

/** Every request the surface has made, each waiting for the test to answer it. */
let requests;
let container;
let root;

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( removeDateTimeConfig );

beforeEach( () => {
	requests = [];
	apiFetch.mockImplementation(
		( options ) =>
			new Promise( ( resolve, reject ) => {
				requests.push( { path: options.path, resolve, reject } );
			} )
	);
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

function mount( element ) {
	act( () => {
		root.render( element );
	} );
}

async function answer( request, data ) {
	await act( async () => {
		request.resolve( { data } );
	} );
}

async function fail( request ) {
	await act( async () => {
		request.reject( new Error( 'offline' ) );
	} );
}

function notification( id, title ) {
	return {
		id,
		category: 'submissions',
		severity: 'warning',
		source_module: 'corex-forms',
		environment: 'production',
		occurrences: 1,
		latest_occurred_at: '2026-07-27T11:30:00.000Z',
		action: null,
		can_resolve: true,
		rendered: { title, body: 'The mail server refused the message.' },
		user_state: {
			read: false,
			status: 'unread',
			view: 'action_needed',
			needs_action: true,
		},
	};
}

const state = () =>
	container.querySelector( '.corex-loadable' ).dataset.corexState;
const placeholder = () => container.querySelector( '.corex-admin-skeleton' );
const titles = () =>
	[ ...container.querySelectorAll( '.corex-notification__title' ) ]
		.map( ( title ) => title.textContent )
		.filter( Boolean );

function press( name ) {
	const button = [ ...container.querySelectorAll( 'button' ) ].find(
		( candidate ) => candidate.textContent.trim() === name
	);
	act( () => {
		button.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);
	} );
}

describe( 'the Notifications screen', () => {
	it( 'shows a placeholder, and no "nothing needs you", until the first answer', async () => {
		mount( <NotificationsApp /> );

		expect( state() ).toBe( 'loading' );
		expect( placeholder() ).not.toBeNull();
		expect( container.textContent ).not.toContain(
			'Nothing needs you right now.'
		);

		await answer( requests[ 0 ], { items: [], total: 0 } );

		expect( state() ).toBe( 'ready' );
		expect( container.textContent ).toContain(
			'Nothing needs you right now.'
		);
	} );

	it( 'keeps the notifications on screen while another view is fetched', async () => {
		mount( <NotificationsApp /> );
		await answer( requests[ 0 ], {
			items: [ notification( 1, 'A submission was not delivered' ) ],
			total: 1,
		} );

		press( 'Updates' );

		expect( state() ).toBe( 'refreshing' );
		expect( titles() ).toEqual( [ 'A submission was not delivered' ] );
		expect( placeholder() ).toBeNull();

		await answer( requests[ 1 ], {
			items: [ notification( 2, 'An export finished' ) ],
			total: 1,
		} );

		expect( state() ).toBe( 'ready' );
		expect( titles() ).toEqual( [ 'An export finished' ] );
	} );

	it( 'shows the answer to the last thing asked, whichever arrives last', async () => {
		// Two views asked for in quick succession: the first answer is slow and lands second.
		// The list that is kept on screen makes this reachable; it used to be hidden by a
		// loading line that both requests replaced.
		mount( <NotificationsApp /> );
		await answer( requests[ 0 ], { items: [], total: 0 } );

		press( 'Updates' );
		press( 'History' );
		await answer( requests[ 2 ], {
			items: [ notification( 3, 'A blocker was resolved' ) ],
			total: 1,
		} );
		await answer( requests[ 1 ], {
			items: [ notification( 2, 'An export finished' ) ],
			total: 1,
		} );

		expect( titles() ).toEqual( [ 'A blocker was resolved' ] );
		expect( state() ).toBe( 'ready' );
	} );
} );

describe( 'notification preferences', () => {
	it( 'shows a placeholder until the categories arrive', async () => {
		mount( <PreferencesPanel /> );

		expect( state() ).toBe( 'loading' );
		expect( placeholder() ).not.toBeNull();

		await answer( requests[ 0 ], {
			preferences: [
				{ category: 'security', enabled: true, mandatory: true },
				{ category: 'editorial', enabled: false, mandatory: false },
			],
		} );

		expect( state() ).toBe( 'ready' );
		expect(
			container.querySelectorAll( '.corex-notifications-prefs__row' )
		).toHaveLength( 2 );
	} );
} );

describe( 'the notification drawer', () => {
	it( 'shows a placeholder, and no "all caught up", until the answer', async () => {
		mount( <NotificationDrawer open onClose={ () => {} } /> );

		expect( state() ).toBe( 'loading' );
		expect( placeholder() ).not.toBeNull();
		expect( container.textContent ).not.toContain( 'all caught up' );

		await answer( requests[ 0 ], { items: [] } );

		expect( state() ).toBe( 'ready' );
		expect( container.textContent ).toContain( 'all caught up' );
	} );
} );

it.each( [
	[
		'the Notifications screen',
		<NotificationsApp key="screen" />,
		'Notifications could not be loaded.',
	],
	[
		'notification preferences',
		<PreferencesPanel key="preferences" />,
		'Preferences could not be loaded.',
	],
	[
		'the notification drawer',
		<NotificationDrawer key="drawer" open onClose={ () => {} } />,
		'Notifications could not be loaded.',
	],
] )(
	'%s says a load failed, and loads again when asked to',
	async ( _surface, element, sentence ) => {
		mount( element );
		await fail( requests[ 0 ] );

		expect( state() ).toBe( 'error' );
		expect( container.textContent ).toContain( sentence );

		press( 'Try again' );

		expect( requests ).toHaveLength( 2 );
		expect( state() ).toBe( 'loading' );
	}
);
