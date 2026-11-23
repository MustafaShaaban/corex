/**
 * Jest: Social Share is drawn in place (spec 136, FR-018, User Story 2 scenario 2). The editor
 * draws the bar SocialShareRenderer prints, one link per chosen network in the renderer's order,
 * without asking the server to draw it; a link in the editor leads nowhere.
 */
import { registerBlockType } from '@wordpress/blocks';
// React comes from the root's devDependencies; the Node build, as the parity helper uses.
// eslint-disable-next-line import/no-extraneous-dependencies
import { renderToStaticMarkup } from 'react-dom/server.node';
// eslint-disable-next-line import/no-extraneous-dependencies
import { createElement } from 'react';
import metadata from './block.json';
import { ICON_PATHS } from './icons';

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( {
		useBlockProps: ( props ) => props || {},
		InspectorControls: () => null,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( { PanelBody: () => null, CheckboxControl: () => null } ),
	{ virtual: true }
);
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s } ), { virtual: true } );

import './index.js';

const draw = ( attributes ) => {
	const [ , config ] = registerBlockType.mock.calls[ 0 ];
	const holder = document.createElement( 'div' );
	holder.innerHTML = renderToStaticMarkup(
		createElement( config.edit, { attributes, setAttributes: () => {} } )
	);

	return holder;
};

describe( 'social share block: drawn in place (FR-018)', () => {
	test( 'registers and saves nothing (dynamic)', () => {
		const [ name, config ] = registerBlockType.mock.calls[ 0 ];

		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-018: draws a link for each chosen network, in the order of the page', () => {
		const bar = draw( { networks: [ 'email', 'x', 'unknown' ] } );
		const links = [
			...bar.querySelectorAll( '.corex-social-share__link' ),
		];

		expect( links.map( ( link ) => link.className ) ).toEqual( [
			'corex-social-share__link corex-social-share__link--x',
			'corex-social-share__link corex-social-share__link--email',
		] );
		expect( links.map( ( link ) => link.textContent ) ).toEqual( [
			'Share on X',
			'Share by email',
		] );
		expect( links.every( ( link ) => ! link.hasAttribute( 'href' ) ) ).toBe(
			true
		);
	} );

	test( 'with no network chosen it draws them all, as the page does', () => {
		const bar = draw( { networks: [] } );

		expect(
			bar.querySelectorAll( '.corex-social-share__link' ).length
		).toBe( 5 );
	} );

	test( 'with no networks attribute at all it draws them all, as the page does', () => {
		const bar = draw( {} );

		expect(
			bar.querySelectorAll( '.corex-social-share__link' ).length
		).toBe( 5 );
	} );

	test( 'with only networks it does not know it draws none, as the page does', () => {
		const bar = draw( { networks: [ 'mastodon' ] } );

		expect(
			bar.querySelectorAll( '.corex-social-share__link' ).length
		).toBe( 0 );
	} );

	test( 'the copy button shows, as a browser with a clipboard shows it; the device button stays hidden', () => {
		const bar = draw( { networks: [ 'x' ] } );

		expect( bar.querySelector( '.corex-social-share__copy' ).hidden ).toBe(
			false
		);
		expect(
			bar.querySelector( '.corex-social-share__native' ).hidden
		).toBe( true );
	} );
} );

test( "the glyphs are the renderer's, path for path", () => {
	const fs = require( 'fs' );
	const path = require( 'path' );
	const renderer = fs.readFileSync(
		path.resolve( __dirname, '../../SocialShareRenderer.php' ),
		'utf8'
	);
	const printed = Object.fromEntries(
		Array.from(
			renderer.matchAll( /'([a-z]+)'\s*=>\s*'<path d="([^"]+)"\/>'/g ),
			( [ , name, d ] ) => [ name, d ]
		)
	);

	expect( Object.keys( printed ) ).toHaveLength( 7 );
	expect( ICON_PATHS ).toEqual( printed );
} );
