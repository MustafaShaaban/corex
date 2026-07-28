/**
 * `detail()` returns the record the endpoint sent (#149 item 1a).
 *
 * This file exists because the test that already covered the detail modal could not fail. It calls
 * `recordRows()` directly with a well-formed record, and `recordRows()` was never the broken part —
 * the hook above it unwrapped a `record` key that `DataController::show()` does not emit, so the
 * modal received `undefined` for every source on every install, and the unit test stayed green
 * throughout.
 *
 * So this drives the real hook against a stubbed transport and asserts on what a caller gets. It is
 * the layer the other test skips, and the only layer where the defect lived.
 *
 * Rendered through `createRoot` + `act`, as the other component tests do — the repo has no
 * testing-library dependency and this does not add one.
 */
import { createRoot } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import { useDataExplorer } from '../data/useDataExplorer.js';

/** What `DataController::show()` actually returns: the record at the envelope root. */
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

it( 'resolves to the record itself, not to a key the endpoint never sends', async () => {
	const explorer = mountExplorer( async ( url ) =>
		url.endsWith( '/sources' )
			? { envelope: { ok: true, data: { sources: CONFIG.sources } } }
			: { envelope: { ok: true, data: SUBMISSION_RECORD } }
	);

	let record;
	await act( async () => {
		record = await explorer().detail( 7 );
	} );

	// The assertion the old test could not make. Before the fix this was `undefined`, and the
	// modal rendered its empty state — a sentence that reads as a fact about the record.
	expect( record ).toEqual( SUBMISSION_RECORD );
	expect( record.fields ).toHaveLength( 1 );
} );

it( 'asks the detail route for the record it was given', async () => {
	const seen = [];
	const explorer = mountExplorer( async ( url ) => {
		seen.push( url );
		return url.endsWith( '/sources' )
			? { envelope: { ok: true, data: { sources: CONFIG.sources } } }
			: { envelope: { ok: true, data: SUBMISSION_RECORD } };
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
