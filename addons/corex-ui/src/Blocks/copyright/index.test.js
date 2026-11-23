/**
 * Jest: Corex Copyright is drawn in place (spec 136, FR-018, User Story 2 scenario 2). The editor
 * draws the line CopyrightRenderer prints, the year and the site's name in the same translated
 * sentence, without asking the server to draw it, and saves nothing (a dynamic block).
 *
 * WordPress keeps the site's name with its characters escaped (`&#039;`, `&amp;`), so the editor
 * reads it from WordPress's core data store and decodes it, as WordPress's Site Title block does.
 */
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

const mockAsked = [];
const mockSite = { name: '' };

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( { useBlockProps: ( props ) => props || {} } ),
	{ virtual: true }
);
jest.mock( '@wordpress/core-data', () => ( { store: { name: 'core' } } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: ( mapSelect ) =>
			mapSelect( ( store ) => {
				if ( store !== require( '@wordpress/core-data' ).store ) {
					throw new Error( 'read from a store other than core data' );
				}

				return {
					getEntityRecord: ( kind, name ) => {
						mockAsked.push( [ kind, name ] );

						return { name: mockSite.name };
					},
				};
			} ),
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/i18n',
	() => ( {
		__: ( s ) => s,
		sprintf: ( format, ...values ) =>
			values.reduce(
				( text, value, index ) =>
					text.replace( `%${ index + 1 }$s`, value ),
				format
			),
	} ),
	{ virtual: true }
);

import './index.js';

describe( 'copyright block: drawn in place (FR-018)', () => {
	const [ name, config ] = registerBlockType.mock.calls[ 0 ];
	// The renderer's year is UTC (`gmdate( 'Y' )`).
	const year = String( new Date().getUTCFullYear() );
	const line = ( siteName ) => {
		mockSite.name = siteName;
		mockAsked.length = 0;

		return config.edit( { attributes: {} } );
	};

	test( 'registers and saves nothing (dynamic)', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-018: draws the line of the page, the year and the site name', () => {
		const element = line( 'Atlas Studio' );

		expect( element.type ).toBe( 'p' );
		expect( element.props.className ).toBe( 'corex-copyright' );
		expect( element.props.children ).toBe(
			`© ${ year } Atlas Studio. All rights reserved.`
		);
	} );

	test( "FR-018: reads the site's name from the base record anyone who edits may read", () => {
		line( 'Atlas Studio' );

		expect( mockAsked ).toEqual( [ [ 'root', '__unstableBase' ] ] );
	} );

	test( 'FR-018: a name WordPress keeps escaped is drawn with its characters', () => {
		expect( line( 'Mustafa&#039;s Studio &amp; Co' ).props.children ).toBe(
			`© ${ year } Mustafa's Studio & Co. All rights reserved.`
		);
	} );
} );
