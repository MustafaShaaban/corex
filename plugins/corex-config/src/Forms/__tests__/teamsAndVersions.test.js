/**
 * The Teams view and the Versions tab (spec 122): teams are listed, made, given members and
 * removed (FR-012); published versions are listed newest first, opened read only and copied into
 * the draft without publishing (FR-005).
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { TeamsPanel } from '../TeamsPanel.js';
import { VersionsTab } from '../tabs/VersionsTab.js';

const people = [
	{ id: 4, name: 'Sara Ahmed' },
	{ id: 9, name: 'Omar Said' },
];

let container;
let root;

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
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	container.remove();
} );

function buttonNamed( name, within = container ) {
	return Array.from( within.querySelectorAll( 'button' ) ).find(
		( button ) => button.textContent === name
	);
}

/**
 * Changes a controlled input the way a person typing does, so React sees it.
 *
 * @param {HTMLInputElement} input The field.
 * @param {string}           value What is typed.
 */
function type( input, value ) {
	const setter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	setter.call( input, value );
	input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
}

async function settle() {
	await act( async () => {
		await Promise.resolve();
	} );
}

describe( 'TeamsPanel (FR-012)', () => {
	function teamsClient( teams ) {
		const answer = ( next ) =>
			Promise.resolve( { ok: true, data: { teams: next } } );

		return {
			list: jest.fn( () => answer( teams ) ),
			create: jest.fn( ( team ) =>
				answer( [ ...teams, { key: 'support', ...team } ] )
			),
			update: jest.fn( () => answer( teams ) ),
			remove: jest.fn( () => answer( [] ) ),
		};
	}

	async function renderTeams( client, onChange = () => {} ) {
		act( () => {
			root.render(
				<TeamsPanel
					client={ client }
					people={ people }
					onChange={ onChange }
				/>
			);
		} );
		await settle();
	}

	it( 'lists each team with its members by name, and a removed person as a former user', async () => {
		await renderTeams(
			teamsClient( [
				{ key: 'sales', name: 'Sales', members: [ 4, 77 ] },
			] )
		);

		expect( container.textContent ).toContain( 'Sales' );
		expect( container.textContent ).toContain(
			'2 members: Sara Ahmed, A former user'
		);
	} );

	it( 'says when there are no teams yet', async () => {
		await renderTeams( teamsClient( [] ) );

		expect( container.textContent ).toContain( 'No teams yet.' );
	} );

	it( 'makes a team with the name and members chosen, and hands the new list to routing', async () => {
		const client = teamsClient( [] );
		const onChange = jest.fn();
		await renderTeams( client, onChange );

		act( () => buttonNamed( 'New team' ).click() );
		const save = buttonNamed( 'Save team' );
		expect( save.disabled ).toBe( true );
		act( () => {
			type( container.querySelector( '#corex-team-name' ), 'Support' );
			container.querySelector( '#corex-team-member-9' ).click();
		} );
		await act( async () => buttonNamed( 'Save team' ).click() );
		await settle();

		expect( client.create ).toHaveBeenCalledWith( {
			name: 'Support',
			members: [ 9 ],
		} );
		expect( onChange ).toHaveBeenCalledWith( [
			{ key: 'support', name: 'Support', members: [ 9 ] },
		] );
		expect( container.textContent ).toContain( 'Team saved.' );
		expect( container.querySelector( '#corex-team-name' ) ).toBeNull();
	} );

	it( 'asks before removing a team, says what happens to its submissions, and Cancel removes nothing', async () => {
		const client = teamsClient( [
			{ key: 'sales', name: 'Sales', members: [] },
		] );
		await renderTeams( client );

		act( () => buttonNamed( 'Remove' ).click() );
		const dialog = container.querySelector( 'dialog' );
		expect( dialog.textContent ).toContain(
			'Submissions already assigned to Sales stay with it'
		);
		act( () => buttonNamed( 'Cancel', dialog ).click() );
		expect( client.remove ).not.toHaveBeenCalled();

		act( () => buttonNamed( 'Remove' ).click() );
		await act( async () => buttonNamed( 'Remove team' ).click() );
		await settle();

		expect( client.remove ).toHaveBeenCalledWith( 'sales' );
		expect( container.textContent ).toContain( 'Team removed.' );
	} );

	it( 'shows the reason when the server refuses', async () => {
		const client = teamsClient( [] );
		client.create = jest.fn( () =>
			Promise.resolve( { ok: false, message: 'That name is taken.' } )
		);
		await renderTeams( client );

		act( () => buttonNamed( 'New team' ).click() );
		act( () =>
			type( container.querySelector( '#corex-team-name' ), 'Sales' )
		);
		await act( async () => buttonNamed( 'Save team' ).click() );
		await settle();

		expect( container.querySelector( '[role="alert"]' ).textContent ).toBe(
			'That name is taken.'
		);
		expect( container.querySelector( '#corex-team-name' ) ).not.toBeNull();
	} );
} );

describe( 'VersionsTab (FR-005)', () => {
	const draft = {
		flow: {
			state: 'published',
			published_version: 3,
			publications: [
				{
					version: 3,
					at: '2026-10-21T10:00:00+00:00',
					by_name: 'Sara Ahmed',
				},
				{ version: 1, at: '2026-01-01T00:00:00+00:00', by_name: '' },
			],
		},
		versions: [
			{
				version_number: 3,
				configuration: {
					schema: [
						{
							uuid: 'a',
							key: 'email',
							label: 'Work email',
							type: 'email',
						},
						{ uuid: 'b', key: 'name', label: 'Name', type: 'text' },
					],
				},
			},
			{
				version_number: 1,
				configuration: {
					schema: [
						{
							uuid: 'a',
							key: 'email',
							label: 'Email',
							type: 'email',
						},
					],
				},
			},
		],
	};

	function renderVersions( onCopy, shown = draft ) {
		act( () => {
			root.render(
				<VersionsTab
					draft={ shown }
					busy={ false }
					pending=""
					onCopy={ onCopy }
				/>
			);
		} );
	}

	it( 'lists each published version newest first, marks the live one and an unknown author', () => {
		renderVersions( () => () => {} );
		const rows = Array.from(
			container.querySelectorAll( '.corex-flow-versions__row' )
		).map( ( row ) => row.textContent );

		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ] ).toContain( 'Version 3' );
		expect( rows[ 0 ] ).toContain( 'Live' );
		expect( rows[ 0 ] ).toContain( 'Sara Ahmed' );
		expect( rows[ 1 ] ).toContain( 'Version 1' );
		expect( rows[ 1 ] ).not.toContain( 'Live' );
		expect( rows[ 1 ] ).toContain( 'Author not recorded' );
	} );

	it( 'opens a version read only, with its questions', () => {
		renderVersions( () => () => {} );
		const open = container.querySelectorAll(
			'.corex-flow-versions__row button'
		)[ 2 ];

		act( () => open.click() );

		expect( open.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		const summary = container.querySelector(
			'.corex-flow-versions__summary'
		);
		expect( summary.textContent ).toContain( '1 question' );
		expect( summary.textContent ).toContain( 'Email' );
		expect( summary.querySelector( 'input' ) ).toBeNull();
	} );

	it( 'copies the version asked for into the draft', () => {
		const copied = [];
		renderVersions( ( version ) => () => copied.push( version ) );

		act( () =>
			Array.from( container.querySelectorAll( 'button' ) )
				.filter(
					( button ) => button.textContent === 'Copy into draft'
				)[ 1 ]
				.click()
		);

		expect( copied ).toEqual( [ 1 ] );
	} );

	it( 'says a flow never published has no versions to list', () => {
		renderVersions( () => () => {}, {
			flow: { state: 'draft', published_version: 0, publications: [] },
			versions: [],
		} );

		expect( container.textContent ).toContain(
			'This flow has not been published yet.'
		);
	} );
} );
