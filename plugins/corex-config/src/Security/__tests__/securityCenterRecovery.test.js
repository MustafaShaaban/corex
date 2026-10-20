/**
 * The Security Center's recovery panel, bookmark warning and recovery notice (spec 112, User
 * Story 4, FR-020 to FR-023): an owner with no command line can follow it, and is told while
 * recovery is switched on.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';
import SecurityCenter from '../SecurityCenter.js';

const CONSTANT_LINE = "define( 'COREX_LOGIN_UNGUARD', true );";
const COMMAND = 'wp corex security reset-login';
const NOTICE =
	'Recovery is on: wp-login.php is open to everyone. Remove COREX_LOGIN_UNGUARD from wp-config.php to hide it again.';

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
	container.remove();
} );

function render( config = {} ) {
	act( () => {
		root.render(
			<SecurityCenter
				section="login"
				config={ {
					recoveryCommand: COMMAND,
					loginPolicy: {
						enabled: true,
						blockDefaultEndpoints: true,
						loginUrl: 'https://example.test/sign-in',
					},
					...config,
				} }
			/>
		);
	} );
}

const recoveryPanel = () =>
	[ ...container.querySelectorAll( 'section' ) ].find(
		( section ) => section.querySelector( 'h2' )?.textContent === 'Recovery'
	);

const copyValues = ( element ) =>
	[ ...element.querySelectorAll( 'textarea[readonly]' ) ].map(
		( input ) => input.value
	);

it( 'gives the way back without a command line step by step, then the command line way', () => {
	render();
	const text = recoveryPanel().textContent;

	expect( text ).toContain( 'Without a command line' );
	expect( text ).toContain( 'file manager' );
	expect( text ).toContain( "That's all, stop editing" );
	expect( text ).toContain( '/wp-login.php' );
	expect( text ).toContain( 'Remove the line' );
	expect( text ).toContain( 'With a command line' );
} );

it( 'shows the line and the command as values that can be copied, left to right', () => {
	render();
	const panel = recoveryPanel();

	expect( copyValues( panel ) ).toEqual( [ CONSTANT_LINE, COMMAND ] );
	panel.querySelectorAll( 'textarea[readonly]' ).forEach( ( input ) => {
		expect( input.getAttribute( 'dir' ) ).toBe( 'ltr' );
	} );
} );

it( 'says what each way does and does not do', () => {
	render();
	const text = recoveryPanel().textContent;

	// The constant: reopens wp-login.php while set, keeps the site's own address, ends no lockout.
	expect( text ).toContain( 'wp-login.php is open to everyone' );
	expect( text ).toContain( 'own login address keeps working' );
	expect( text ).toContain( 'does not end a lockout' );
	// The command: protection off, default address back, every lockout ended.
	expect( text ).toContain( 'turns login protection off' );
	expect( text ).toContain( 'ends every lockout' );
} );

it( 'names both ways in the bookmark warning', () => {
	render();
	const warning = container.querySelector( '.corex-security__warning' ).textContent;

	expect( warning ).toContain( 'COREX_LOGIN_UNGUARD' );
	expect( warning ).toContain( COMMAND );
} );

it( 'says recovery is on while the constant is set', () => {
	render( { recoveryActive: true } );

	expect( container.textContent ).toContain( NOTICE );
} );

it( 'says nothing about recovery while the constant is not set', () => {
	render( { recoveryActive: false } );

	expect( container.textContent ).not.toContain( 'Recovery is on' );
} );
