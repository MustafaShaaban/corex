/**
 * The forms `corex/form` can name, as its chooser loads them (spec 136, FR-020): every flow ever
 * published (closed and expired ones too), or the registered forms. A list that loaded, even empty, is told from one that failed,
 * and a person who may not list the forms (the flows need `corex_manage_forms`) is told so.
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { normalizePublishedFlows } from '../flowBlockEditor.js';

const LOADING = { status: 'loading' };

/** The statuses and codes of a refusal, from CoreX's routes (403) and WordPress's (401, 403). */
const REFUSED_STATUSES = [ 401, 403 ];
const REFUSED_CODES = [ 'forbidden', 'rest_forbidden' ];

/**
 * @param {string} source `flow` or `registered`.
 * @return {Promise<Object>} `{status: 'ready', items}`, or `{status: 'failed', forbidden, message}`.
 */
export function loadChoices( source ) {
	// Every flow, unfiltered: normalizePublishedFlows drops only the drafts, so a saved block that
	// names a closed or expired flow still finds it, as the flow blocks' editor does.
	const path = source === 'flow' ? '/corex/v1/flows' : '/corex/v1/forms';

	return apiFetch( { path } ).then(
		( response ) => ( {
			status: 'ready',
			items:
				source === 'flow'
					? normalizePublishedFlows( response )
					: ( Array.isArray( response ) && response ) || [],
		} ),
		( error ) => ( {
			status: 'failed',
			forbidden:
				REFUSED_STATUSES.includes( error?.data?.status ) ||
				REFUSED_CODES.includes( error?.code ),
			message: String( error?.message || '' ),
		} )
	);
}

/**
 * The forms of a source, loaded again when the source changes. An answer that arrives after the
 * source changed is dropped.
 *
 * @param {string} source `flow` or `registered`.
 * @return {Object} `{status: 'loading'}`, or what `loadChoices()` settles with.
 */
export function useChoices( source ) {
	const [ choices, setChoices ] = useState( LOADING );

	useEffect( () => {
		let current = true;
		setChoices( LOADING );
		loadChoices( source ).then( ( loaded ) => {
			if ( current ) {
				setChoices( loaded );
			}
		} );

		return () => {
			current = false;
		};
	}, [ source ] );

	return choices;
}
