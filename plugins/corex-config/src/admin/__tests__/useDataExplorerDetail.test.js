/**
 * `detail()` returns the record the endpoint sent.
 *
 * This drives the real hook against a stubbed transport and asserts on what a caller gets:
 * `recordRows()`, which the detail's other test covers, was never the broken part.
 *
 * The transport here answers what the site answers: `{ record }`, from
 * `DataManagementController::show()`, read off a running site on 2026-10-08 and held on the
 * server's side by `DataManagementControllerTest`. It used to answer a bare record, written
 * from `DataController::show()`, a controller that is bound and not registered. So this file
 * passed while every record's detail showed one field named "Record" holding a line of JSON.
 * A stub is only as true as what it was copied from.
 *
 * Rendered through `createRoot` + `act`, as the other component tests do — the repo has no
 * testing-library dependency and this does not add one.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useDataExplorer } from '../data/useDataExplorer.js';

/** One record, as a source returns it. The route sends it under `record`. */
const SUBMISSION_RECORD = {
	id: 7,
	date: '2026-07-28T09:00:00+00:00',
	form: 'contact',
	fields: [ { label: 'CV', value: 'https://example.test/cv.pdf' } ],
};

const CONFIG = {
	restUrl: 'https://example.test/wp-json/corex/v1/data',
	nonce: 'test-nonce',
	sources: [
		{ key: 'submissions', label: 'Submissions', access: 'allowed' },
	],
};

/**
 * Mount the hook and hand back its return value.
 *
 * @param {Function} onApiGet Stub for `window.Corex.api.get`.
 * @return {Object} The hook's latest return value.
 */
function mountExplorer( onApiGet ) {
	window.Corex = { api: { get: onApiGet } };

	let explorer;

	function Probe() {
		explorer = useDataExplorer( CONFIG );
		return null;
	}

	const container = document.createElement( 'div' );
	document.body.appendChild( container );

	act( () => {
		createRoot( container ).render( <Probe /> );
	} );

	return () => explorer;
}

beforeEach( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	document.body.innerHTML = '';
	delete window.Corex;
} );

it( 'resolves to the record itself, out of the answer that carries it', async () => {
	const explorer = mountExplorer( async ( url ) =>
		url.endsWith( '/sources' )
			? { envelope: { ok: true, data: { sources: CONFIG.sources } } }
			: { envelope: { ok: true, data: { record: SUBMISSION_RECORD } } }
	);

	let record;
	await act( async () => {
		record = await explorer().detail( 7 );
	} );

	// Read one level too shallow this is `{ record: {…} }`, and the detail draws the whole
	// record as one field named "Record".
	expect( record ).toEqual( SUBMISSION_RECORD );
	expect( record.fields ).toHaveLength( 1 );
} );

it( 'asks the detail route for the record it was given', async () => {
	const seen = [];
	const explorer = mountExplorer( async ( url ) => {
		seen.push( url );
		return url.endsWith( '/sources' )
			? { envelope: { ok: true, data: { sources: CONFIG.sources } } }
			: { envelope: { ok: true, data: { record: SUBMISSION_RECORD } } };
	} );

	await act( async () => {
		await explorer().detail( 7 );
	} );

	expect( seen ).toContain( `${ CONFIG.restUrl }/submissions/7` );
} );

it( 'returns null and does not throw when the endpoint refuses', async () => {
	const explorer = mountExplorer( async ( url ) =>
		url.endsWith( '/sources' )
			? { envelope: { ok: true, data: { sources: CONFIG.sources } } }
			: { envelope: { ok: false, message: 'That record was not found.' } }
	);

	let record;
	await act( async () => {
		record = await explorer().detail( 999 );
	} );

	// Null rather than a rejected promise: the caller opens a modal with it, and an unhandled
	// rejection there would take the whole screen down instead of one record.
	expect( record ).toBeNull();
} );
