/**
 * Jest — what the editor is told under the Title field of a `corex/modal` or `corex/drawer`: nothing
 * while the block has a title, and otherwise what a screen reader will be told in its place.
 */
import { titleHelp } from './title-help';

jest.mock( '@wordpress/i18n', () => ( { __: ( text ) => text } ), {
	virtual: true,
} );

describe( 'the help under a dialog block’s Title field', () => {
	it( 'says nothing while the block has a title', () => {
		expect(
			titleHelp( { title: 'Terms', triggerLabel: '' } )
		).toBeUndefined();
	} );

	it( 'says the trigger label stands in, when there is one and no title', () => {
		expect(
			titleHelp( { title: '', triggerLabel: 'Read the terms' } )
		).toMatch( /trigger label/ );
	} );

	it( 'asks for a title when there is no trigger label either', () => {
		expect( titleHelp( { title: '', triggerLabel: '' } ) ).toMatch(
			/Give it a title/
		);
	} );

	it( 'reads a title or a label of spaces as none, as the renderer does', () => {
		expect( titleHelp( { title: '  ', triggerLabel: ' ' } ) ).toMatch(
			/Give it a title/
		);
	} );
} );
