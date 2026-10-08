/**
 * Tests for workingProps (spec 108, story 3): what a control is while its request is out.
 *
 * The defect behind it is a button that can be pressed twice. Every action in the admin either
 * showed WordPress's striped button, changed its label, was only disabled, or showed nothing and
 * sent its request again on the second press.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { usePending, workingProps } from '../working.js';

let container;
let root;

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
} );

function showButton( props ) {
	act( () => {
		root.render(
			<button type="button" { ...props }>
				Save the template
			</button>
		);
	} );
	return container.querySelector( 'button' );
}

function press( button ) {
	act( () => {
		button.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);
	} );
}

it( 'sends nothing on a second press while the first is on its way', () => {
	let sent = 0;
	const send = () => ( sent += 1 );

	press( showButton( { onClick: send, ...workingProps( false ) } ) );
	press( showButton( { onClick: send, ...workingProps( true ) } ) );

	expect( sent ).toBe( 1 );
} );

it( 'keeps its name and says it is busy', () => {
	const button = showButton( workingProps( true ) );

	expect( button.textContent.trim() ).toBe( 'Save the template' );
	expect( button.getAttribute( 'aria-busy' ) ).toBe( 'true' );
	expect( button.dataset.corexWorking ).toBe( 'true' );
} );

it( 'leaves alone a control that is disabled for a reason of its own', () => {
	const button = showButton( { disabled: true, ...workingProps( false ) } );

	expect( button.disabled ).toBe( true );
	expect( button.hasAttribute( 'aria-busy' ) ).toBe( false );
	expect( button.hasAttribute( 'data-corex-working' ) ).toBe( false );
} );

describe( 'usePending', () => {
	let pending;
	let during;

	function Probe() {
		[ pending, during ] = usePending();
		return null;
	}

	beforeEach( () => {
		act( () => {
			root.render( <Probe /> );
		} );
	} );

	it( 'names the control for the whole of its task, and no longer', async () => {
		let finish;
		let outcome;

		act( () => {
			outcome = during(
				'commit',
				() => new Promise( ( resolve ) => ( finish = resolve ) )
			);
		} );
		expect( pending ).toBe( 'commit' );

		await act( async () => {
			finish( 'queued' );
		} );

		expect( pending ).toBe( '' );
		await expect( outcome ).resolves.toBe( 'queued' );
	} );

	it( 'lets go of the control when its task fails, and does not hide the failure', async () => {
		let outcome;

		await act( async () => {
			outcome = during( 'commit', () =>
				Promise.reject( new Error( 'refused' ) )
			);
			await outcome.catch( () => {} );
		} );

		expect( pending ).toBe( '' );
		await expect( outcome ).rejects.toThrow( 'refused' );
	} );
} );
