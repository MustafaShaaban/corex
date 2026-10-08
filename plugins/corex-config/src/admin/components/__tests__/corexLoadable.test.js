/**
 * Tests for CorexLoadable and CorexSkeleton (spec 108, stories 1 and 2).
 *
 * What a shared loading wrapper has to get right is the difference between four things a person
 * otherwise cannot tell apart: nothing has arrived yet, what is here is about to be replaced, this
 * is all there is, and it failed. Each test below is one of those, or the part of one that a
 * person who cannot see the screen depends on.
 *
 * Rendered through `createRoot` + `act`, as corexErrorState.test.js does.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexLoadable from '../CorexLoadable.js';
import CorexSkeleton, { SkeletonBar, SkeletonBox } from '../CorexSkeleton.js';

let container;
let root;

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	jest.useFakeTimers();
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	jest.useRealTimers();
	document.body.innerHTML = '';
} );

function show( props ) {
	act( () => {
		root.render(
			<CorexLoadable
				skeleton={
					<CorexSkeleton>
						<SkeletonBox />
						<SkeletonBar width="long" />
					</CorexSkeleton>
				}
				loadingLabel="Loading records…"
				errorMessage="Records could not be loaded."
				{ ...props }
			>
				<button type="button" data-test="content">
					Open the first record
				</button>
			</CorexLoadable>
		);
	} );
}

const surface = () => container.querySelector( '.corex-loadable' );
const body = () => container.querySelector( '.corex-loadable__body' );
const content = () => container.querySelector( '[data-test="content"]' );
const placeholder = () => container.querySelector( '.corex-admin-skeleton' );
const announced = () =>
	container.querySelector( '[role="status"].screen-reader-text' ).textContent;

it( 'shows a placeholder, and none of the content, until the first answer', () => {
	show( { status: 'loading' } );

	expect( placeholder() ).not.toBeNull();
	expect( content() ).toBeNull();
	expect( surface().dataset.corexState ).toBe( 'loading' );
	expect( body().getAttribute( 'aria-busy' ) ).toBe( 'true' );
} );

it( 'hides the placeholder from assistive technology and gives it nothing to read', () => {
	show( { status: 'loading' } );

	expect( placeholder().getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	expect( placeholder().textContent ).toBe( '' );
} );

it( 'shows the content alone once it is ready', () => {
	show( { status: 'ready' } );

	expect( content() ).not.toBeNull();
	expect( placeholder() ).toBeNull();
	expect( surface().dataset.corexState ).toBe( 'ready' );
	expect( body().hasAttribute( 'aria-busy' ) ).toBe( false );
	expect( body().hasAttribute( 'inert' ) ).toBe( false );
} );

it( 'keeps the content on screen while it is replaced, and out of reach', () => {
	show( { status: 'ready' } );
	show( { status: 'refreshing' } );

	expect( content() ).not.toBeNull();
	expect( placeholder() ).toBeNull();
	expect( surface().dataset.corexState ).toBe( 'refreshing' );
	expect( body().getAttribute( 'aria-busy' ) ).toBe( 'true' );
	expect( body().hasAttribute( 'inert' ) ).toBe( true );
} );

it( 'moves focus to the surface when the content holding it goes out of reach', () => {
	// Content that turns inert drops the focus it held, and the browser's answer is the top of
	// the page: a keyboard user who pressed a button in row twelve starts again from the menu.
	show( { status: 'ready' } );
	content().focus();

	show( { status: 'refreshing' } );

	expect( document.activeElement ).toBe( surface() );
} );

it( 'leaves focus alone when it was somewhere else', () => {
	const outside = document.createElement( 'button' );
	document.body.appendChild( outside );
	show( { status: 'ready' } );
	outside.focus();

	show( { status: 'refreshing' } );

	expect( document.activeElement ).toBe( outside );
} );

it( 'replaces everything with the failure, and offers the retry it was given', () => {
	let retried = 0;
	show( { status: 'error', onRetry: () => ( retried += 1 ) } );

	expect( surface().dataset.corexState ).toBe( 'error' );
	expect( placeholder() ).toBeNull();
	expect( content() ).toBeNull();
	expect(
		container.querySelector( '.corex-error-state__message' ).textContent
	).toBe( 'Records could not be loaded.' );

	act( () => {
		container
			.querySelector( '.corex-error-state button' )
			.dispatchEvent(
				new window.MouseEvent( 'click', { bubbles: true } )
			);
	} );
	expect( retried ).toBe( 1 );
} );

describe( 'what a screen reader is told', () => {
	it( 'says a load has started once it has lasted, and that it ended', () => {
		show( { status: 'loading' } );
		expect( announced() ).toBe( '' );

		act( () => jest.advanceTimersByTime( 1000 ) );
		expect( announced() ).toBe( 'Loading records…' );

		show( { status: 'ready' } );
		expect( announced() ).toBe( 'Loaded.' );
	} );

	it( 'says nothing about a load that was over before it was worth saying', () => {
		show( { status: 'loading' } );
		act( () => jest.advanceTimersByTime( 400 ) );
		show( { status: 'ready' } );
		act( () => jest.advanceTimersByTime( 2000 ) );

		expect( announced() ).toBe( '' );
	} );

	it( 'says nothing about a refresh', () => {
		show( { status: 'ready' } );
		show( { status: 'refreshing' } );
		act( () => jest.advanceTimersByTime( 2000 ) );
		show( { status: 'ready' } );

		expect( announced() ).toBe( '' );
	} );

	it( 'leaves a failure to the error state, which announces itself', () => {
		show( { status: 'loading' } );
		act( () => jest.advanceTimersByTime( 1000 ) );
		show( { status: 'error' } );

		expect( announced() ).toBe( '' );
	} );
} );
