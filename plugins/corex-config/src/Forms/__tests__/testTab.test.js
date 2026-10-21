/**
 * TestTab — a marked test holds its emails (spec 113, US3): the tab says so before the button is
 * pressed, and lists what a run held, each linked to Email Studio's log.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { TestTab, heldEmailLink } from '../tabs/TestTab.js';

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function mount( props ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( <TestTab busy={ false } onRun={ () => {} } { ...props } /> );
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

it( 'says before a run that emails are held, not sent', () => {
	const { container, cleanup } = mount( { result: null } );

	expect( container.textContent ).toContain( 'Emails are held, not sent' );
	expect( container.querySelector( '.corex-flow-test-held' ) ).toBeNull();
	cleanup();
} );

it( 'lists each held email with a link to it in the log', () => {
	const { container, cleanup } = mount( {
		result: {
			stages: [ { key: 'email', label: 'email', state: 'success', message: 'Held.' } ],
			held_emails: [
				{
					to: [ 'team@example.com' ],
					subject: 'New lead',
					capture_id: '0b6f1c1e-1a2b-4c3d-8e9f-0a1b2c3d4e5f',
					path: 'Form notification',
				},
			],
		},
	} );
	const link = container.querySelector( '.corex-flow-test-held a' );

	expect( container.textContent ).toContain( 'New lead, to team@example.com' );
	expect( link.getAttribute( 'href' ) ).toBe(
		'admin.php?page=corex-email-studio&tab=logs#corex-capture-0b6f1c1e-1a2b-4c3d-8e9f-0a1b2c3d4e5f'
	);
	cleanup();
} );

it( 'says so when a run had no email to send', () => {
	const { container, cleanup } = mount( {
		result: { stages: [], held_emails: [] },
	} );

	expect( container.textContent ).toContain( 'This run had no email to send.' );
	cleanup();
} );

it( 'keeps an odd capture id inside the link', () => {
	expect( heldEmailLink( 'a b#c' ) ).toBe(
		'admin.php?page=corex-email-studio&tab=logs#corex-capture-a%20b%23c'
	);
} );
