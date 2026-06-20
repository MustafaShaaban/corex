/**
 * Jest unit tests for the buildless CoreX navigation behavior (Spec 058 / M3).
 * Loads the module against jsdom and asserts the mega-menu enhancements over native
 * <details> — single-open (siblings close), Escape closes + returns focus to summary,
 * outside-click closes — the transparent-header scroll state, and listener teardown.
 * Rendered RTL/reduced-motion remain browser-gated (CSS-owned).
 */

const MODULE = '../theme/assets/js/corex-navigation.js';

function load() {
	jest.isolateModules( () => {
		require( MODULE );
	} );
	return window.Corex.navigation;
}

function setMegaDom() {
	document.body.innerHTML = `
		<header class="corex-header">
			<details class="corex-mega" id="m1"><summary>Products</summary>
				<div class="corex-mega__panel"><a href="#a">Item A</a></div>
			</details>
			<details class="corex-mega" id="m2"><summary>Solutions</summary>
				<div class="corex-mega__panel"><a href="#b">Item B</a></div>
			</details>
			<a href="#outside" id="outside">Outside</a>
		</header>`;
}

beforeEach( () => {
	delete window.Corex;
	global.requestAnimationFrame = ( cb ) => setTimeout( cb, 0 );
	Object.defineProperty( window, 'scrollY', { value: 0, configurable: true, writable: true } );
} );

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

it( 'closes sibling panels when one mega panel opens', () => {
	setMegaDom();
	load();

	const m1 = document.getElementById( 'm1' );
	const m2 = document.getElementById( 'm2' );

	m2.open = true;
	m1.open = true;
	m1.dispatchEvent( new window.Event( 'toggle' ) );

	expect( m1.open ).toBe( true );
	expect( m2.open ).toBe( false );
} );

it( 'closes the open panel on Escape and returns focus to its summary', () => {
	setMegaDom();
	load();

	const m1 = document.getElementById( 'm1' );
	m1.open = true;
	m1.dispatchEvent(
		new window.KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } )
	);

	expect( m1.open ).toBe( false );
	expect( document.activeElement ).toBe( m1.querySelector( 'summary' ) );
} );

it( 'closes the open panel on an outside click', () => {
	setMegaDom();
	load();

	const m1 = document.getElementById( 'm1' );
	m1.open = true;
	document.getElementById( 'outside' ).click();

	expect( m1.open ).toBe( false );
} );

it( 'leaves clicks inside an open panel alone', () => {
	setMegaDom();
	load();

	const m1 = document.getElementById( 'm1' );
	m1.open = true;
	m1.querySelector( 'a' ).click();

	expect( m1.open ).toBe( true );
} );

it( 'toggles the transparent header state on scroll', async () => {
	document.body.innerHTML =
		'<header class="corex-header corex-header--transparent">x</header>';
	load();

	const header = document.querySelector( '.corex-header--transparent' );
	expect( header.getAttribute( 'data-corex-header-state' ) ).toBe( 'top' );

	Object.defineProperty( window, 'scrollY', { value: 200, configurable: true, writable: true } );
	window.dispatchEvent( new window.Event( 'scroll' ) );
	await flush();

	expect( header.getAttribute( 'data-corex-header-state' ) ).toBe( 'scrolled' );
} );

it( 'removes its listeners on destroy', () => {
	setMegaDom();
	const nav = load();
	nav.destroy();

	const m1 = document.getElementById( 'm1' );
	m1.open = true;
	document.getElementById( 'outside' ).click();

	// After teardown the outside-click handler no longer closes the panel.
	expect( m1.open ).toBe( true );
} );
