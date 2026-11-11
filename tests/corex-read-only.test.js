/**
 * Jest tests for the read-only screen script (spec 117, US-SHL-06, FR-013): what a reader of a
 * CoreX screen can still use (links, tabs, filters, pagers, a GET form, the browser's own keys)
 * and what does nothing (a control that changes something, a key that changes a value, a submit).
 */

const SCRIPT = '../plugins/corex-core/assets/js/corex-read-only.js';

function load( html ) {
	document.body.innerHTML = `<p id="corex-readonly-note">Read only.</p>
		<div class="corex-admin__content" data-corex-readonly="corex-readonly-note">${ html }</div>`;
	jest.isolateModules( () => {
		require( SCRIPT );
	} );
}

function key( target, name, init = {} ) {
	const event = new window.KeyboardEvent( 'keydown', {
		key: name,
		bubbles: true,
		cancelable: true,
		...init,
	} );
	target.dispatchEvent( event );

	return event.defaultPrevented;
}

describe( 'corex-read-only (FR-013)', () => {
	it( 'marks a control that changes something unavailable and describes it by the sentence', () => {
		load(
			'<form method="post"><input id="name" type="text" /><button id="save">Save</button></form>'
		);
		const input = document.getElementById( 'name' );

		expect( input.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( input.readOnly ).toBe( true );
		expect( input.getAttribute( 'aria-describedby' ) ).toBe(
			'corex-readonly-note'
		);
		expect(
			document.getElementById( 'save' ).getAttribute( 'aria-disabled' )
		).toBe( 'true' );
	} );

	it( 'leaves filters, pagers and navigation marked to be skipped usable', () => {
		load(
			'<div class="corex-filters" data-corex-readonly-skip><select id="period"><option>7</option></select>' +
				'<button id="next" type="button">Next page</button></div>'
		);

		expect(
			document.getElementById( 'period' ).hasAttribute( 'aria-disabled' )
		).toBe( false );
		expect(
			document.getElementById( 'next' ).hasAttribute( 'aria-disabled' )
		).toBe( false );
		expect( key( document.getElementById( 'period' ), 'ArrowDown' ) ).toBe(
			false
		);
	} );

	it( 'leaves a form that only asks for a page (GET) usable', () => {
		load(
			'<form id="filter" method="get"><input id="search" type="search" /><button id="go">Filter</button></form>'
		);
		const submit = new window.Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		document.getElementById( 'filter' ).dispatchEvent( submit );

		expect(
			document.getElementById( 'search' ).hasAttribute( 'aria-disabled' )
		).toBe( false );
		expect(
			document.getElementById( 'go' ).hasAttribute( 'aria-disabled' )
		).toBe( false );
		expect( submit.defaultPrevented ).toBe( false );
	} );

	it( 'stops the keys that change or press a control', () => {
		load(
			'<form method="post"><select id="mode"><option>a</option><option>b</option></select>' +
				'<input id="on" type="checkbox" /></form>'
		);

		expect( key( document.getElementById( 'mode' ), 'ArrowDown' ) ).toBe(
			true
		);
		expect( key( document.getElementById( 'on' ), ' ' ) ).toBe( true );
		expect( key( document.getElementById( 'mode' ), 'Enter' ) ).toBe(
			true
		);
		expect( key( document.getElementById( 'mode' ), 'b' ) ).toBe( true );
	} );

	it( 'lets the browser keep its own keys: reload, copy, moving focus and the caret in a text box', () => {
		load( '<form method="post"><input id="name" type="text" value="Acme" /></form>' );
		const input = document.getElementById( 'name' );

		expect( key( input, 'F5' ) ).toBe( false );
		expect( key( input, 'c', { ctrlKey: true } ) ).toBe( false );
		expect( key( input, 'c', { metaKey: true } ) ).toBe( false );
		expect( key( input, 'Tab' ) ).toBe( false );
		expect( key( input, 'ArrowLeft' ) ).toBe( false );
		expect( key( input, 'End' ) ).toBe( false );
	} );

	it( 'stops a submit and a cut', () => {
		load(
			'<form id="form" method="post"><div id="rich" contenteditable="true">Text</div></form>'
		);
		const submit = new window.Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		const cut = new window.Event( 'cut', { bubbles: true, cancelable: true } );
		document.getElementById( 'form' ).dispatchEvent( submit );
		document.getElementById( 'rich' ).dispatchEvent( cut );

		expect( submit.defaultPrevented ).toBe( true );
		expect( cut.defaultPrevented ).toBe( true );
	} );
} );
