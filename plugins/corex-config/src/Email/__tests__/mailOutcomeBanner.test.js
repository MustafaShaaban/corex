/**
 * What Email Studio says happens to mail (spec 113, US4): the banner on every tab, and the test
 * send's note, both from the outcome the server sends.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { MailOutcomeBanner } from '../components/MailOutcomeBanner.js';
import { testSendNote } from '../components/PreviewPanels.js';
import { normalizeOverview } from '../emailStudioClient.js';

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function mount( outcome ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( <MailOutcomeBanner outcome={ outcome } /> );
	} );
	return {
		container,
		cleanup: () =>
			act( () => {
				root.unmount();
				container.remove();
			} ),
	};
}

const BLOCKED = {
	state: 'blocked',
	title: 'Blocked: Email Studio sends nothing',
	detail: 'Still sent for real: form notifications.',
	facts: [ 'Live delivery: off', 'Provider: none set' ],
};

it( 'states the outcome, what still goes out, and the facts behind it', () => {
	const { container, cleanup } = mount( BLOCKED );
	const banner = container.querySelector( '.corex-email-app__outcome' );

	expect( banner.classList.contains( 'is-warning' ) ).toBe( true );
	expect( banner.querySelector( 'h2' ).textContent ).toBe(
		'Blocked: Email Studio sends nothing'
	);
	expect( banner.textContent ).toContain( 'Still sent for real' );
	expect(
		[ ...banner.querySelectorAll( 'li' ) ].map( ( li ) => li.textContent )
	).toEqual( [ 'Live delivery: off', 'Provider: none set' ] );
	cleanup();
} );

it( 'draws nothing before the studio is read', () => {
	const { container, cleanup } = mount( null );

	expect( container.innerHTML ).toBe( '' );
	cleanup();
} );

it( 'keeps the outcome the server sent when the studio is read', () => {
	expect( normalizeOverview( { outcome: BLOCKED } ).outcome ).toBe( BLOCKED );
	expect( normalizeOverview( {} ).outcome ).toBeNull();
} );

it( 'tells a test send what it will meet', () => {
	expect( testSendNote( { state: 'captured' } ) ).toContain( 'captured' );
	expect( testSendNote( { state: 'delivered' } ) ).toContain(
		'sent for real'
	);
	expect( testSendNote( { state: 'blocked' } ) ).toContain( 'refused' );
} );
