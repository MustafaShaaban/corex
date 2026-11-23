/**
 * Jest: Corex Copyright is drawn in place (spec 136, FR-018, User Story 2 scenario 2). The editor
 * draws the line CopyrightRenderer prints, the year and the site's name in the same translated
 * sentence, without asking the server to draw it, and saves nothing (a dynamic block).
 */
import { registerBlockType } from '@wordpress/blocks';
// React comes from the root's devDependencies; the Node build, as the parity helper uses.
// eslint-disable-next-line import/no-extraneous-dependencies
import { renderToStaticMarkup } from 'react-dom/server.node';
// eslint-disable-next-line import/no-extraneous-dependencies
import { createElement } from 'react';
import metadata from './block.json';

jest.mock( './style.scss', () => ( {} ), { virtual: true } );
jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/block-editor',
	() => ( { useBlockProps: ( props ) => props || {} } ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/data',
	() => ( {
		useSelect: ( mapSelect ) =>
			mapSelect( () => ( {
				getEntityRecord: () => ( { name: 'Atlas Studio' } ),
			} ) ),
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

	test( 'registers and saves nothing (dynamic)', () => {
		expect( name ).toBe( metadata.name );
		expect( config.save() ).toBeNull();
	} );

	test( 'FR-018: draws the line of the page, the year and the site name', () => {
		const markup = renderToStaticMarkup(
			createElement( config.edit, { attributes: {} } )
		);
		// The renderer's year is UTC (`gmdate( 'Y' )`).
		const year = String( new Date().getUTCFullYear() );

		expect( markup ).toBe(
			`<p class="corex-copyright">© ${ year } Atlas Studio. All rights reserved.</p>`
		);
	} );
} );
