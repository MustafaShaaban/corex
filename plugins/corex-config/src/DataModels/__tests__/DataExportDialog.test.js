/**
 * The Data export dialog and personal data (spec 109, slice 1).
 *
 * Ticking a box was the only question the dialog asked, of anybody who could export the model.
 * A column that holds personal data is offered only to a person allowed to take personal data
 * out, and the confirmation says what it is confirming.
 *
 * No @testing-library in this repo; the dialog is driven through a real jsdom root, as
 * protectionTab.test.js is.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import DataExportDialog from '../DataExportDialog.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { variant, isDestructive, children, ...rest } ) => (
		<button type="button" { ...rest }>
			{ children }
		</button>
	),
} ) );

jest.mock( '../dataModelsApi.js', () => ( {
	downloadArtifact: jest.fn(),
	dataExportRequests: () => ( {
		preview: () =>
			Promise.resolve( {
				counts: { selected: 0, filtered: 3, all: 3 },
				formats: {
					available: [ 'xlsx', 'csv' ],
					pdf_most_records: 500,
				},
			} ),
	} ),
} ) );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

const SUBSCRIBERS = {
	key: 'subscribers',
	label: 'Newsletter subscribers',
	actions: { export_csv: { visible: true }, export_xlsx: { visible: true } },
	fields: [
		{ key: 'email', label: 'Email', personal_data_class: 'contact' },
		{ key: 'status', label: 'Status', personal_data_class: 'none' },
		{ key: 'topics', label: 'Topics', personal_data_class: 'behavioral' },
	],
};

/**
 * Opens the dialog over a source and waits for its counts.
 *
 * @param {Object} source The source, with `permissions`.
 * @return {Promise<{dialog:Element,cleanup:Function}>} The open dialog.
 */
async function open( source ) {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	await act( async () => {
		root.render(
			<DataExportDialog
				config={ { restUrl: '/corex/v1/data', nonce: 'n' } }
				source={ source }
				close={ () => {} }
			/>
		);
	} );

	return {
		// The dialog is drawn in a layer of its own, outside the root it was rendered into.
		dialog: document.body,
		cleanup: () =>
			act( () => {
				root.unmount();
				container.remove();
			} ),
	};
}

const columnNames = ( dialog ) =>
	[ ...dialog.querySelectorAll( '.corex-export__column-name' ) ].map(
		( name ) => name.textContent
	);

const startButton = ( dialog ) =>
	[ ...dialog.querySelectorAll( 'button' ) ].find( ( button ) =>
		button.textContent.startsWith( 'Export' )
	);

it( 'does not offer a column that holds personal data to a person who may not export it, and says how many it withholds', async () => {
	const { dialog, cleanup } = await open( {
		...SUBSCRIBERS,
		permissions: { personal_data: false },
	} );

	expect( columnNames( dialog ) ).toEqual( [ 'Status' ] );
	expect( dialog.textContent ).toContain(
		'2 columns that hold personal data are not offered: your role cannot export it.'
	);
	// Nothing personal is left to confirm.
	expect( dialog.querySelector( '.corex-export__notice' ) ).toBeNull();
	expect( startButton( dialog ).disabled ).toBe( false );
	await cleanup();
} );

it( 'names the kinds of personal data it asks a person who may export it to confirm, and waits for the tick', async () => {
	const { dialog, cleanup } = await open( {
		...SUBSCRIBERS,
		permissions: { personal_data: true },
	} );
	const notice = dialog.querySelector( '.corex-export__notice' );

	expect( columnNames( dialog ) ).toEqual( [ 'Email', 'Status', 'Topics' ] );
	expect( notice.textContent ).toContain(
		'personal data (contact details, interests and behaviour)'
	);
	expect( dialog.textContent ).not.toContain( 'are not offered' );
	expect( startButton( dialog ).disabled ).toBe( true );

	await act( async () => {
		notice.querySelector( 'input' ).click();
	} );

	expect( startButton( dialog ).disabled ).toBe( false );
	await cleanup();
} );

it( 'asks nothing about personal data of a source that holds none', async () => {
	const { dialog, cleanup } = await open( {
		...SUBSCRIBERS,
		fields: [ { key: 'kind', label: 'Kind', personal_data_class: 'none' } ],
		permissions: { personal_data: false },
	} );

	expect( columnNames( dialog ) ).toEqual( [ 'Kind' ] );
	expect( dialog.querySelector( '.corex-export__notice' ) ).toBeNull();
	expect( dialog.textContent ).not.toContain( 'not offered' );
	await cleanup();
} );
