/**
 * FieldValue — what an operator may click, and what must stay inert (#149 item 2).
 *
 * The refusals matter more than the link. These values come from visitors, and the person reading
 * them is the one user on the site whose session is worth stealing, so every test below that
 * asserts something is NOT an anchor is guarding a stored-XSS path into the admin.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import FieldValue from '../components/FieldValue.js';

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

function render( value ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	act( () => {
		createRoot( container ).render( <FieldValue value={ value } /> );
	} );
	return container;
}

it( 'links an absolute http(s) url so a stored file can be opened', () => {
	const container = render( 'https://example.test/cv.pdf' );
	const link = container.querySelector( 'a' );

	expect( link.getAttribute( 'href' ) ).toBe( 'https://example.test/cv.pdf' );
	expect( link.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
	expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
} );

it.each( [
	[ 'a javascript: url', 'javascript:alert(1)' ],
	[ 'a data: url', 'data:text/html,<script>alert(1)</script>' ],
	[ 'a protocol-relative url', '//example.test/cv.pdf' ],
	[ 'a bare domain', 'example.test/cv.pdf' ],
	[ 'a file path', '/wp-content/uploads/cv.pdf' ],
	[ 'prose that mentions a url', 'see https://example.test for details' ],
	// The anchoring case: a value whose *prefix* is a valid URL must not match on it.
	[
		'a url followed by a second scheme',
		'https://example.test javascript:alert(1)',
	],
] )( 'leaves %s as inert text', ( _label, value ) => {
	const container = render( value );

	expect( container.querySelector( 'a' ) ).toBeNull();
	expect( container.textContent ).toBe( value );
} );

it.each( [
	[ 'null', null ],
	[ 'undefined', undefined ],
	[ 'an empty string', '' ],
	[ 'whitespace', '   ' ],
] )(
	'renders %s as an em dash rather than an empty cell',
	( _label, value ) => {
		expect( render( value ).textContent ).toBe( '—' );
	}
);

it( 'renders a number as text without trying to link it', () => {
	const container = render( 42 );

	expect( container.textContent ).toBe( '42' );
	expect( container.querySelector( 'a' ) ).toBeNull();
} );
