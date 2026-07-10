import { registerBlockType } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import flowMetadata from '../corex-flow/block.json';
import successMetadata from '../success-message/block.json';
import subscribeMetadata from '../subscribe/block.json';
import surveyMetadata from '../survey/block.json';
import ctaMetadata from '../cta-flow/block.json';

jest.mock( '@wordpress/blocks', () => ( { registerBlockType: jest.fn() } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: ( props ) => props || {},
	InspectorControls: ( { children } ) => children,
} ), { virtual: true } );
jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => children,
	SelectControl: () => null,
	TextControl: () => null,
	TextareaControl: () => null,
} ), { virtual: true } );
jest.mock( '@wordpress/server-side-render', () => () => null, { virtual: true } );
jest.mock( '@wordpress/element', () => ( {
	useState: ( initial ) => [ initial, jest.fn() ],
	useEffect: ( callback ) => callback(),
} ), { virtual: true } );
jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve( { data: { flows: [] } } ) ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( value ) => value } ), { virtual: true } );

import { flowOptions, normalizePublishedFlows, registerFlowBlock } from '../flowBlockEditor.js';

describe( 'persisted flow blocks', () => {
	beforeEach( () => registerBlockType.mockClear() );

	test( 'normalizes only published flow records from the REST envelope', () => {
		expect( normalizePublishedFlows( { data: { flows: [
			{ id: 1, name: 'Draft', state: 'draft' },
			{ id: 2, name: 'Live', state: 'published' },
		] } } ) ).toEqual( [ { id: 2, name: 'Live', state: 'published' } ] );
		expect( flowOptions( [ { id: 2, name: 'Live' } ] )[ 1 ] ).toEqual( { label: 'Live', value: '2' } );
	} );

	test.each( [
		[ flowMetadata, 'flow' ],
		[ successMetadata, 'success-message' ],
		[ subscribeMetadata, 'subscribe' ],
		[ surveyMetadata, 'survey' ],
		[ ctaMetadata, 'cta-flow' ],
	] )( 'registers %s as a dynamic persisted-flow block', ( metadata, variant ) => {
		registerFlowBlock( metadata, variant );
		const [ name, settings ] = registerBlockType.mock.calls[ 0 ];

		expect( name ).toBe( metadata.name );
		expect( settings.save() ).toBeNull();
		settings.edit( { attributes: {}, setAttributes: jest.fn() } );
		expect( apiFetch ).toHaveBeenCalledWith( { path: '/corex/v1/flows?state=published' } );
	} );
} );
