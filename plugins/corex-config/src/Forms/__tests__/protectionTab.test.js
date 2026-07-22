/**
 * ProtectionTab — the per-form spam-protection panel (spec 071 US4).
 *
 * Verifies the panel uses the approved CorexSelect control (never a native <select>), and that its
 * onChange keeps the stored shape sparse — an all-inherit form emits `{}`, so it stays
 * checksum-neutral on the server.
 *
 * No @testing-library in this repo; the component is driven through a real jsdom root, matching
 * corexSelect.test.js.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { ProtectionTab } from '../tabs/ProtectionTab.js';

beforeAll( () => {
	// React 18 only treats act() as authoritative when the environment opts in.
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function mount( props ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( <ProtectionTab { ...props } /> );
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

it( 'renders the approved CorexSelect for the mode, not a native select', () => {
	const { container, cleanup } = mount( {
		protection: {},
		onChange: () => {},
	} );

	expect( container.querySelector( '.corex-select' ) ).not.toBeNull();
	expect( container.querySelector( 'select' ) ).toBeNull();
	cleanup();
} );

it( 'keeps the stored shape sparse — a threshold override adds only that key', () => {
	let latest = null;
	const { container, cleanup } = mount( {
		protection: {},
		onChange: ( value ) => {
			latest = value;
		},
	} );

	const threshold = container.querySelector( 'input[type="number"]' );
	act( () => {
		const setter = Object.getOwnPropertyDescriptor(
			window.HTMLInputElement.prototype,
			'value'
		).set;
		setter.call( threshold, '0.7' );
		threshold.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );

	expect( latest ).toEqual( { threshold: 0.7 } ); // captcha stays inherited → absent
	cleanup();
} );

it( 'drops a key when its override is cleared, never persisting an empty string', () => {
	let latest = null;
	const { container, cleanup } = mount( {
		protection: { action: 'contact_form' },
		onChange: ( value ) => {
			latest = value;
		},
	} );

	const action = container.querySelector( 'input[type="text"], input:not([type])' );
	act( () => {
		const setter = Object.getOwnPropertyDescriptor(
			window.HTMLInputElement.prototype,
			'value'
		).set;
		setter.call( action, '' );
		action.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );

	expect( latest ).toEqual( {} ); // the action key is removed, not set to ''
	cleanup();
} );
