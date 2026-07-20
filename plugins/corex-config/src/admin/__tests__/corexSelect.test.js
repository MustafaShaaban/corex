/**
 * CorexSelect — the approved admin selection control.
 *
 * These assert the behaviour a native <select> gives for free and which a custom control has to
 * re-earn by hand: keyboard operation, type-ahead, focus return, and an honest empty state. The
 * reason for replacing the native control at all is that its open popup is drawn by the OS, so
 * the dark-mode option highlight is unreachable from CSS — that highlight is a rendered style and
 * is verified in a real browser (tests/e2e/select-control.spec.js), not here.
 *
 * No @testing-library in this repo, so the component is driven through a real jsdom root.
 */
import { createRoot } from '@wordpress/element';
// React is supplied by wp-scripts as a peer of @wordpress/element and is deliberately not a
// direct dependency; act() is only re-exported from 'react' (react-dom/test-utils is deprecated
// in 18 and warns, which @wordpress/jest-console turns into a failure).
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexSelect from '../components/CorexSelect.js';

const OPTIONS = [
	{ value: 'contact', label: 'Contact us' },
	{ value: 'careers', label: 'Careers application' },
	{ value: 'support', label: 'Support request' },
];

let container;
let root;

function mount( props = {} ) {
	act( () => {
		root.render(
			<CorexSelect
				label="Form"
				value="contact"
				options={ OPTIONS }
				onChange={ () => {} }
				{ ...props }
			/>
		);
	} );
}

const button = () => container.querySelector( '.corex-select__button' );
const optionEls = () => [ ...container.querySelectorAll( '[role="option"]' ) ];

function press( key ) {
	act( () => {
		const event = new window.KeyboardEvent( 'keydown', {
			key,
			bubbles: true,
			cancelable: true,
		} );
		button().dispatchEvent( event );
	} );
}

function click( el ) {
	act( () => {
		el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
	} );
}

beforeAll( () => {
	// React 18 only treats act() as authoritative when the environment opts in; without this
	// every update warns and @wordpress/jest-console fails the test on the warning.
	global.IS_REACT_ACT_ENVIRONMENT = true;
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

describe( 'CorexSelect', () => {
	it( 'renders a collapsed combobox naming its label and current value', () => {
		mount();

		expect( button().getAttribute( 'aria-haspopup' ) ).toBe( 'listbox' );
		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( button().getAttribute( 'aria-label' ) ).toBe( 'Form' );
		expect( button().textContent ).toContain( 'Contact us' );
		// Closed means closed: no listbox in the DOM at all.
		expect( container.querySelector( '[role="listbox"]' ) ).toBeNull();
	} );

	it( 'opens on click and marks the current value selected', () => {
		mount();
		click( button() );

		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( optionEls() ).toHaveLength( 3 );
		expect( optionEls()[ 0 ].getAttribute( 'aria-selected' ) ).toBe(
			'true'
		);
		expect( optionEls()[ 0 ].className ).toContain( 'is-selected' );
	} );

	it( 'opens with the keyboard and reports the active option without moving focus', () => {
		mount();
		press( 'ArrowDown' );

		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		// aria-activedescendant, not DOM focus — the button keeps focus while the menu is open.
		expect( button().getAttribute( 'aria-activedescendant' ) ).toBe(
			optionEls()[ 0 ].id
		);

		press( 'ArrowDown' );
		expect( button().getAttribute( 'aria-activedescendant' ) ).toBe(
			optionEls()[ 1 ].id
		);
		expect( optionEls()[ 1 ].className ).toContain( 'is-active' );
	} );

	it( 'clamps arrow movement at both ends and jumps with Home/End', () => {
		mount();
		press( 'ArrowDown' );

		press( 'ArrowUp' );
		expect( optionEls()[ 0 ].className ).toContain( 'is-active' );

		press( 'End' );
		expect( optionEls()[ 2 ].className ).toContain( 'is-active' );
		press( 'ArrowDown' );
		expect( optionEls()[ 2 ].className ).toContain( 'is-active' );

		press( 'Home' );
		expect( optionEls()[ 0 ].className ).toContain( 'is-active' );
	} );

	it( 'commits the active option on Enter and returns focus to the button', () => {
		const onChange = jest.fn();
		mount( { onChange } );

		button().focus();
		press( 'ArrowDown' );
		press( 'ArrowDown' );
		press( 'Enter' );

		expect( onChange ).toHaveBeenCalledWith( 'careers' );
		expect( container.querySelector( '[role="listbox"]' ) ).toBeNull();
		// Without this the menu closes under a focused element that no longer exists and the tab
		// order restarts at the top of the page.
		expect( document.activeElement ).toBe( button() );
	} );

	it( 'commits a clicked option', () => {
		const onChange = jest.fn();
		mount( { onChange } );
		click( button() );

		act( () => {
			optionEls()[ 2 ].dispatchEvent(
				new window.MouseEvent( 'mousedown', {
					bubbles: true,
					cancelable: true,
				} )
			);
		} );

		expect( onChange ).toHaveBeenCalledWith( 'support' );
	} );

	it( 'does not fire onChange when the current value is re-selected', () => {
		const onChange = jest.fn();
		mount( { onChange } );
		press( 'ArrowDown' );
		press( 'Enter' );

		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'closes on Escape without changing the value', () => {
		const onChange = jest.fn();
		mount( { onChange } );
		press( 'ArrowDown' );
		press( 'ArrowDown' );
		press( 'Escape' );

		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'commits by typing while closed, as a native select does', () => {
		const onChange = jest.fn();
		mount( { onChange } );

		press( 's' );

		expect( onChange ).toHaveBeenCalledWith( 'support' );
	} );

	it( 'moves the active option by typing while open, without committing', () => {
		const onChange = jest.fn();
		mount( { onChange } );

		press( 'ArrowDown' );
		press( 'c' );

		// "Careers application" — the search starts AFTER the current option, so repeating a
		// letter cycles through the matches rather than sticking on the first.
		expect( optionEls()[ 1 ].className ).toContain( 'is-active' );
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'accumulates consecutive keystrokes into one search term', () => {
		const onChange = jest.fn();
		mount( { onChange } );

		// "ca" reaches Careers; "c" alone would too, but a second letter must refine rather than
		// restart, or every multi-word list becomes unnavigable.
		press( 'ArrowDown' );
		press( 'c' );
		press( 'a' );

		expect( optionEls()[ 1 ].className ).toContain( 'is-active' );
	} );

	it( 'closes when a click lands outside it', () => {
		mount();
		click( button() );
		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		act( () => {
			document.body.dispatchEvent(
				new window.MouseEvent( 'mousedown', { bubbles: true } )
			);
		} );

		expect( button().getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'states plainly that there is nothing to choose, and will not open', () => {
		// An optional dependency that is not installed yields an empty list (Principle IX). An
		// empty popup reads as a broken control; a disabled button saying so reads as an absent
		// one, which is the truth.
		mount( { options: [], emptyLabel: 'No forms yet' } );

		expect( button().textContent ).toContain( 'No forms yet' );
		expect( button().disabled ).toBe( true );

		click( button() );
		press( 'ArrowDown' );
		expect( container.querySelector( '[role="listbox"]' ) ).toBeNull();
	} );

	it( 'will not open when disabled', () => {
		mount( { disabled: true } );

		expect( button().disabled ).toBe( true );
		press( 'ArrowDown' );
		expect( container.querySelector( '[role="listbox"]' ) ).toBeNull();
	} );

	it( 'falls back to the first option when the value matches nothing', () => {
		// A stale filter value must not blank the control.
		mount( { value: 'deleted-form' } );

		expect( button().textContent ).toContain( 'Contact us' );
	} );

	it( 'takes the full row only when asked', () => {
		mount();
		expect(
			container.querySelector( '.corex-select' ).className
		).not.toContain( 'corex-select--block' );

		mount( { block: true } );
		expect(
			container.querySelector( '.corex-select' ).className
		).toContain( 'corex-select--block' );
	} );
} );
