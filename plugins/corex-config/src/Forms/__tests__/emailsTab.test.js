/**
 * EmailsTab: a flow's emails named in words, and a template that uses what the form does not
 * supply flagged before it is bound (spec 120, story 5).
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { EmailsTab, eventLabel, missingVariables } from '../tabs/EmailsTab.js';

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function mount( props ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( <EmailsTab { ...props } /> );
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

const schema = [
	{ key: 'name', label: 'Name' },
	{ key: 'email', label: 'Email' },
];

it( 'names each of a flow’s emails in words, never by its key', () => {
	expect( eventLabel( 'team_notification' ) ).toBe(
		'Notification to your team'
	);
	expect( eventLabel( 'submitter_confirmation' ) ).toBe(
		'Confirmation to the person who filled it in'
	);
	expect( eventLabel( 'admin_failure' ) ).toBe( 'Alert when an email fails' );
} );

it( 'finds the variables a template uses that the form does not supply', () => {
	expect(
		missingVariables(
			[
				'submission.name',
				'submission.phone',
				'form.answers',
				'user.name',
				'failure.message',
			],
			schema,
			'team_notification'
		)
	).toEqual( [ 'submission.phone', 'user.name', 'failure.message' ] );
	expect(
		missingVariables( [ 'failure.message' ], schema, 'admin_failure' )
	).toEqual( [] );
} );

it( 'warns of a bound template’s missing variables and records the confirmation to send without them', () => {
	const changes = [];
	const { container, cleanup } = mount( {
		routes: [
			{ event: 'team_notification', enabled: true, template_id: 4 },
		],
		templates: [
			{ id: 4, name: 'Welcome', variables: [ 'user.name', 'site.name' ] },
		],
		schema,
		onChange: ( value ) => changes.push( value ),
	} );

	const legends = [ ...container.querySelectorAll( 'legend' ) ].map(
		( legend ) => legend.textContent
	);
	expect( legends ).toContain( 'Notification to your team' );
	expect( container.textContent ).toContain(
		'This template uses user.name, which this form does not supply.'
	);

	const accept = container.querySelector(
		'#corex-flow-emails-team_notification-accept-missing'
	);
	act( () => {
		accept.click();
	} );

	expect( changes[ 0 ] ).toEqual( [
		{
			event: 'team_notification',
			enabled: true,
			template_id: 4,
			accept_missing: true,
		},
	] );
	cleanup();
} );

it( 'says nothing for a template that uses only what the form supplies', () => {
	const { container, cleanup } = mount( {
		routes: [
			{ event: 'team_notification', enabled: true, template_id: 4 },
		],
		templates: [
			{
				id: 4,
				name: 'Admin notification',
				variables: [ 'form.title', 'submission.name' ],
			},
		],
		schema,
		onChange: () => {},
	} );

	expect(
		container.querySelector( '.corex-flow-editor__warning' )
	).toBeNull();
	cleanup();
} );
