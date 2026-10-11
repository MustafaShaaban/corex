/**
 * The Export tab, drawn (spec 109, slice 2).
 *
 * The owner, on a live site: "the import/export/migrate model is using one by one, i can't do a
 * bulk or multiselect". The tab had one dropdown named "Model". It is a list to tick, a dialog
 * for one model or for several, and a history that says what became of each file.
 *
 * The repository has no Testing Library; the tab is driven through a real jsdom root, and the
 * network is the one thing replaced.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import ExportPanel from '../ExportPanel.js';
import {
	installDateTimeConfig,
	removeDateTimeConfig,
} from '../../../../../tests/Support/adminDateTimeConfig.js';

// `@wordpress/components` does not load under Jest here; its Button is a button.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { variant, isDestructive, children, ...rest } ) => (
		<button type="button" { ...rest }>
			{ children }
		</button>
	),
} ) );

const mockRequests = {
	preview: jest.fn(),
	history: jest.fn(),
	remove: jest.fn(),
	advance: jest.fn(),
	download: jest.fn(),
	create: jest.fn(),
};

jest.mock( '../dataModelsApi.js', () => ( {
	downloadArtifact: jest.fn(),
	dataExportSetRequests: () => mockRequests,
	// One model's dialog asks its own source for its counts.
	dataExportRequests: () => ( {
		preview: () =>
			Promise.resolve( {
				counts: { selected: 0, filtered: 40, all: 40 },
				formats: { available: [ 'xlsx', 'csv' ] },
			} ),
	} ),
} ) );

const model = ( key, label, category, visible = true ) => ( {
	key,
	label,
	category,
	actions: { export_csv: { visible }, export_xlsx: { visible } },
	permissions: { personal_data: true },
	fields: [ { key: 'code', label: 'Code', personal_data_class: 'none' } ],
} );

const SOURCES = [
	model( 'submissions', 'Form submissions', 'records' ),
	model( 'table-subscribers', 'Newsletter subscribers', 'records' ),
	model( 'table-bounded-jobs', 'Bounded jobs', 'log' ),
	model( 'table-secrets', 'Secrets', 'log', false ),
];

const PAST = {
	id: 31,
	parts: [
		{ source_key: 'submissions', left_out: false },
		{ source_key: 'table-bounded-jobs', left_out: false },
	],
	format: 'xlsx',
	record_count: 43,
	exported_rows: 43,
	file_bytes: 49152,
	state: 'completed',
	file_state: 'ready',
	actor_name: 'Salma Adel',
	created_at: '2026-10-11T09:00:00+00:00',
	expires_at: '2026-11-10T09:00:00+00:00',
};

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	installDateTimeConfig();
} );

afterAll( () => {
	removeDateTimeConfig();
} );

beforeEach( () => {
	jest.clearAllMocks();
	mockRequests.preview.mockResolvedValue( {
		counts: {
			submissions: 40,
			'table-subscribers': 12,
			'table-bounded-jobs': 3,
		},
		formats: { available: [ 'xlsx', 'csv' ] },
	} );
	mockRequests.history.mockResolvedValue( { exports: [ PAST ] } );
	mockRequests.remove.mockResolvedValue( { deleted: true } );
	mockRequests.advance.mockResolvedValue( {
		progress: { state: 'completed', processed: 43, total: 43 },
	} );
} );

/**
 * Opens the tab and waits for its counts and its history.
 *
 * @return {Promise<{panel:Element,cleanup:() => Promise<void>}>} The drawn tab.
 */
async function open() {
	const container = document.createElement( 'div' );
	document.body.appendChild( container );
	const root = createRoot( container );
	await act( async () => {
		root.render(
			<ExportPanel
				config={ { restUrl: '/data', exportsUrl: '/data-exports' } }
				sources={ SOURCES }
			/>
		);
	} );

	return {
		panel: container,
		cleanup: () =>
			act( async () => {
				root.unmount();
				container.remove();
			} ),
	};
}

const byText = ( scope, selector, text ) =>
	[ ...scope.querySelectorAll( selector ) ].find(
		( element ) => element.textContent.trim() === text
	);

const tick = ( panel, label ) =>
	act( async () => {
		byText( panel, 'label', label ).querySelector( 'input' ).click();
	} );

const press = ( scope, text ) =>
	act( async () => {
		byText( scope, 'button', text ).click();
	} );

const opener = ( panel ) =>
	panel.querySelector( '.corex-data-models__export-action button' );

it( 'lists the models this person may export in two groups, each with its count, and ticks none', async () => {
	const { panel, cleanup } = await open();
	const groups = [
		...panel.querySelectorAll( '.corex-data-models__checklist-group' ),
	].map( ( group ) => [
		group.querySelector( 'legend' ).textContent,
		[ ...group.querySelectorAll( 'li' ) ].map( ( row ) =>
			row.textContent.trim()
		),
	] );

	expect( groups ).toEqual( [
		[
			'Records',
			[
				'Form submissions40 records',
				'Newsletter subscribers12 records',
			],
		],
		[ 'Logs', [ 'Bounded jobs3 records' ] ],
	] );
	expect( panel.querySelectorAll( 'input:checked' ) ).toHaveLength( 0 );
	expect( opener( panel ).disabled ).toBe( true );
	expect( panel.textContent ).toContain( 'Tick at least one model.' );
	expect( panel.textContent ).toContain( '1 model is not listed' );
	await cleanup();
} );

it( 'ticks a whole group at once, and says on its button how many models it will export', async () => {
	const { panel, cleanup } = await open();

	await tick( panel, 'All records' );

	expect( opener( panel ).textContent ).toBe( 'Export 2 models…' );
	expect( opener( panel ).disabled ).toBe( false );
	await cleanup();
} );

it( 'opens one dialog for several ticked models, with each model and what it holds', async () => {
	const { panel, cleanup } = await open();
	await tick( panel, 'Form submissions' );
	await tick( panel, 'Bounded jobs' );

	await press( panel, 'Export 2 models…' );
	const dialog = panel.querySelector( 'dialog' );

	expect( dialog.textContent ).toContain( 'Export 2 models' );
	expect(
		[ ...dialog.querySelectorAll( '.corex-export__model' ) ].map(
			( row ) => [
				row.querySelector( '.corex-export__model-name' ).textContent,
				row.querySelector( '.corex-export__count' ).textContent,
			]
		)
	).toEqual( [
		[ 'Form submissions', '40' ],
		[ 'Bounded jobs', '3' ],
	] );
	// Only the ticked models were counted for the dialog.
	expect( mockRequests.preview ).toHaveBeenLastCalledWith( [
		'submissions',
		'table-bounded-jobs',
	] );
	expect(
		byText( dialog, 'button', 'Export 43 records from 2 models' )
	).toBeDefined();
	await cleanup();
} );

it( 'opens the dialog of one model, with its scopes, when one is ticked', async () => {
	const { panel, cleanup } = await open();
	await tick( panel, 'Form submissions' );

	await press( panel, 'Export Form submissions…' );
	const dialog = panel.querySelector( 'dialog' );

	expect( dialog.textContent ).toContain( 'Export Form submissions' );
	expect( dialog.querySelector( '.corex-export__scope' ) ).not.toBeNull();
	expect( dialog.querySelector( '.corex-export__model' ) ).toBeNull();
	await cleanup();
} );

it( 'lists a past export once, with its models, its size and when its file expires', async () => {
	const { panel, cleanup } = await open();
	const entry = panel.querySelector( '.corex-export__entry' );

	expect( panel.querySelectorAll( '.corex-export__entry' ) ).toHaveLength(
		1
	);
	expect(
		entry.querySelector( '.corex-export__entry-what' ).textContent
	).toBe( 'Form submissions, Bounded jobs' );
	expect( entry.textContent ).toContain( 'Excel' );
	expect( entry.textContent ).toContain( '43 records' );
	expect( entry.textContent ).toContain( '48 KB' );
	expect( entry.textContent ).toContain( 'Salma Adel' );
	expect(
		entry.querySelector( '.corex-export__entry-file' ).textContent
	).toMatch( /^Expires .*2026/ );
	await cleanup();
} );

it( 'deletes a past export’s file only after it is asked again, then lists what is left', async () => {
	const { panel, cleanup } = await open();

	await press( panel, 'Delete' );
	expect( mockRequests.remove ).not.toHaveBeenCalled();
	expect( panel.textContent ).toContain( 'Delete this file?' );

	mockRequests.history.mockResolvedValue( {
		exports: [
			{
				...PAST,
				file_state: 'deleted',
				removed_by_name: 'Salma Adel',
				removed_at: '2026-10-11T10:00:00+00:00',
			},
		],
	} );
	await press( panel, 'Delete file' );

	expect( mockRequests.remove ).toHaveBeenCalledWith( 31 );
	expect(
		panel.querySelector( '.corex-export__entry-file' ).textContent
	).toMatch( /^Deleted by Salma Adel/ );
	expect( byText( panel, 'button', 'Download' ) ).toBeUndefined();
	await cleanup();
} );

it( 'takes an unfinished export on from where it stopped', async () => {
	mockRequests.history.mockResolvedValue( {
		exports: [
			{ ...PAST, state: 'queued', file_state: 'pending', file_bytes: 0 },
		],
	} );
	const { panel, cleanup } = await open();

	expect(
		panel.querySelector( '.corex-export__entry-file' ).textContent
	).toBe( 'Not finished.' );
	await press( panel, 'Continue' );

	expect( mockRequests.advance ).toHaveBeenCalledWith( 31 );
	await cleanup();
} );
