/**
 * corex/mega-menu in the editor (spec 137 slice 3; US-NAV-02 criteria 1 and 6, FR-009, FR-013).
 *
 * The block registers under its block.json name and saves nothing (the server draws it). WordPress
 * reads the blocks a Navigation block accepts from the Navigation block's settings in the editor, so
 * the add-on's script adds the item there through `blocks.registerBlockType`: the Navigation block
 * gains it, keeps what it had, and no other block changes. The editor draws the item as the page
 * draws its trigger, with the label typed in place; that the trees match is the parity test's.
 */
// The real hooks, so the filter is read as WordPress runs it.
// eslint-disable-next-line import/no-extraneous-dependencies
const { applyFilters, removeAllFilters } = require( '@wordpress/hooks' );
const {
	installStandIns,
	registeredBlock,
} = require( '../../../../../packages/build-tools/parity' );
const {
	renderEdit,
} = require( '../../../../../packages/build-tools/parity/render' );

installStandIns( jest );
jest.mock( './style.scss', () => ( {} ), { virtual: true } );

const metadata = require( './block.json' );
const { allowInNavigation, NAVIGATION } = require( './allow-in-navigation' );

require( './index.js' );

const NAVIGATION_SETTINGS = {
	title: 'Navigation',
	allowedBlocks: [ 'core/navigation-link', 'core/navigation-submenu' ],
};

describe( 'FR-009: the item is a block the Navigation block accepts', () => {
	test( 'registers under its block.json name, as the item WordPress lists in a menu', () => {
		const settings = registeredBlock( metadata.name );

		expect( metadata.name ).toBe( 'corex/mega-menu' );
		expect( metadata.title ).toBe( 'Mega menu item' );
		expect( metadata.parent ).toEqual( [ NAVIGATION ] );
		expect( typeof settings.edit ).toBe( 'function' );
		expect( settings.save() ).toBeNull();
	} );

	test( 'adds itself to the blocks the Navigation block accepts, after the ones it had', () => {
		expect(
			allowInNavigation( NAVIGATION_SETTINGS, NAVIGATION ).allowedBlocks
		).toEqual( [
			'core/navigation-link',
			'core/navigation-submenu',
			'corex/mega-menu',
		] );
		// The settings WordPress passed in are not changed in place.
		expect( NAVIGATION_SETTINGS.allowedBlocks ).toHaveLength( 2 );
	} );

	test( 'is added once, and to no other block', () => {
		const once = allowInNavigation(
			allowInNavigation( NAVIGATION_SETTINGS, NAVIGATION ),
			NAVIGATION
		);
		const group = { allowedBlocks: [ 'core/paragraph' ] };

		expect( once.allowedBlocks ).toEqual( [
			'core/navigation-link',
			'core/navigation-submenu',
			'corex/mega-menu',
		] );
		expect( allowInNavigation( group, 'core/group' ) ).toBe( group );
	} );

	test( 'leaves a Navigation block that lists no blocks, which accepts every block, as it is', () => {
		const open = { title: 'Navigation' };

		expect( allowInNavigation( open, NAVIGATION ) ).toBe( open );
	} );

	test( 'is hooked on blocks.registerBlockType, which WordPress runs as it registers the Navigation block', () => {
		expect(
			applyFilters(
				'blocks.registerBlockType',
				NAVIGATION_SETTINGS,
				NAVIGATION
			).allowedBlocks
		).toContain( 'corex/mega-menu' );
	} );

	afterAll( () => removeAllFilters( 'blocks.registerBlockType' ) );
} );

describe( 'FR-013: the label is typed in place, in the menu', () => {
	test( 'draws a list item holding the trigger, its label as rich text and its caret', () => {
		const markup = renderEdit( registeredBlock( metadata.name ).edit, {
			metadata,
			attributes: { label: 'Services', panel: 'services' },
		} );
		document.body.innerHTML = `<ul>${ markup }</ul>`;
		const item = document.querySelector( 'li' );
		const trigger = item.querySelector( 'button' );

		expect( item.classList.contains( 'wp-block-navigation-item' ) ).toBe(
			true
		);
		expect( item.classList.contains( 'corex-mega-menu' ) ).toBe( true );
		expect( trigger.classList.contains( 'corex-nav__trigger' ) ).toBe(
			true
		);
		expect( trigger.getAttribute( 'type' ) ).toBe( 'button' );
		expect(
			trigger.querySelector( '.corex-mega-menu__label' ).textContent
		).toBe( 'Services' );
		expect(
			trigger
				.querySelector( '.corex-mega-menu__caret' )
				.getAttribute( 'aria-hidden' )
		).toBe( 'true' );
		// The panel is the page's alone: in the menu the item is its trigger.
		expect( item.querySelector( '.corex-nav__panel' ) ).toBeNull();
	} );

	test( 'takes one line: a break typed in the label would print its words run together', () => {
		const { RichText } = require( '@wordpress/block-editor' );
		const element = registeredBlock( metadata.name ).edit( {
			attributes: { label: 'Our services', panel: 'services' },
			setAttributes: () => {},
		} );
		const find = ( node ) => {
			if ( ! node || typeof node !== 'object' ) {
				return null;
			}
			if ( node.type === RichText ) {
				return node;
			}
			const children = [ node.props?.children ].flat();
			return children.map( find ).find( Boolean ) || null;
		};

		expect( find( element ).props.disableLineBreaks ).toBe( true );
	} );
} );
