/**
 * Tests for CorexErrorState (spec 083, FR-011).
 *
 * The claims worth testing are the ones that stop a shared error component becoming worse than the
 * ad-hoc markup it replaces: it never offers a control that does nothing, it announces urgently only
 * when the failure is a consequence of something just done, and it renders whatever the server said
 * as text rather than as markup.
 *
 * Rendered through `createRoot` + `act`, as corexTime.test.js does — the repo has no
 * testing-library dependency and this spec adds none.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexErrorState from '../CorexErrorState.js';

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

function render( element ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	act( () => {
		root.render( element );
	} );
	return container;
}

afterEach( () => {
	document.body.innerHTML = '';
} );

it( 'renders no actions block when there is nothing to offer', () => {
	const container = render( <CorexErrorState message="Could not load." /> );

	expect(
		container.querySelector( '.corex-error-state__actions' )
	).toBeNull();
	expect( container.querySelectorAll( 'button' ) ).toHaveLength( 0 );
} );

it( 'offers retry only when a handler was actually supplied', () => {
	// A retry button with nothing behind it costs a click to learn the same thing twice.
	const withoutHandler = render(
		<CorexErrorState message="Nope." onRetry={ null } />
	);
	expect( withoutHandler.querySelectorAll( 'button' ) ).toHaveLength( 0 );

	document.body.innerHTML = '';

	let retried = 0;
	const withHandler = render(
		<CorexErrorState message="Nope." onRetry={ () => ( retried += 1 ) } />
	);
	const button = withHandler.querySelector( 'button' );
	expect( button ).not.toBeNull();

	act( () => {
		button.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } )
		);
	} );
	expect( retried ).toBe( 1 );
} );

it( 'interrupts for a failure the reader just caused, and not otherwise', () => {
	const field = render(
		<CorexErrorState scale="field" message="Required." />
	);
	expect( field.firstChild.getAttribute( 'role' ) ).toBe( 'alert' );
	expect( field.firstChild.getAttribute( 'aria-live' ) ).toBe( 'assertive' );

	document.body.innerHTML = '';

	const panel = render(
		<CorexErrorState scale="panel" message="Could not load." />
	);
	expect( panel.firstChild.getAttribute( 'role' ) ).toBe( 'status' );
	expect( panel.firstChild.getAttribute( 'aria-live' ) ).toBe( 'polite' );
} );

it( 'falls back to panel scale rather than emitting an unstyled modifier', () => {
	const container = render(
		<CorexErrorState scale="enormous" message="Hm." />
	);

	expect( container.firstChild.className ).toContain(
		'corex-error-state--panel'
	);
	expect( container.firstChild.className ).not.toContain( 'enormous' );
} );

it( 'keeps the title out of the small scales, where it would only repeat the message', () => {
	const action = render(
		<CorexErrorState
			scale="action"
			title="Save failed"
			message="Try again in a moment."
		/>
	);
	expect( action.querySelector( '.corex-error-state__title' ) ).toBeNull();

	document.body.innerHTML = '';

	const page = render(
		<CorexErrorState
			scale="page"
			title="Save failed"
			message="Try again in a moment."
		/>
	);
	expect(
		page.querySelector( '.corex-error-state__title' ).textContent
	).toBe( 'Save failed' );
} );

it( 'renders what the server said as text, never as markup', () => {
	const container = render(
		<CorexErrorState
			message="Could not load."
			detail="<b>500</b> upstream"
		/>
	);

	const detail = container.querySelector( '.corex-error-state__detail' );
	expect( detail.textContent ).toBe( '<b>500</b> upstream' );
	expect( detail.querySelector( 'b' ) ).toBeNull();
} );

it( 'renders a link action as a link and a callback action as a button', () => {
	const container = render(
		<CorexErrorState
			message="Gone."
			actions={ [
				{ label: 'Dashboard', href: '/wp-admin/', primary: true },
				{ label: 'Dismiss', onClick: () => {} },
			] }
		/>
	);

	const link = container.querySelector( 'a.button' );
	expect( link.getAttribute( 'href' ) ).toBe( '/wp-admin/' );
	expect( link.className ).toContain( 'button-primary' );
	expect( container.querySelector( 'button.button' ).textContent ).toBe(
		'Dismiss'
	);
} );
