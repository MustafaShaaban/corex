import { useCallback, useEffect, useMemo, useReducer, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildListUrl,
	canAction,
	dataEndpoint,
	dataReducer,
	initialDataState,
	normalizeCatalog,
} from '../dataClient.js';

function message( result, fallback ) {
	return result?.envelope?.message || fallback;
}

export function useDataExplorer( config ) {
	const initialCatalog = normalizeCatalog( config.sources );
	const initialSource = initialCatalog.find( ( source ) => source.access === 'allowed' );
	const [ catalog, setCatalog ] = useState( initialCatalog );
	const [ state, dispatch ] = useReducer(
		dataReducer,
		initialDataState( initialSource?.key || '' )
	);

	const request = useCallback(
		async ( method, url, payload ) => {
			const result = method === 'get'
				? await window.Corex.api.get( url, { nonce: config.nonce } )
				: await window.Corex.api[ method ]( url, payload, { nonce: config.nonce } );
			if ( ! result?.envelope?.ok ) {
				throw new Error( message( result, __( 'The request failed. Try again.', 'corex' ) ) );
			}
			return result.envelope.data;
		},
		[ config.nonce ]
	);

	useEffect( () => {
		request( 'get', `${ config.restUrl }/sources` )
			.then( ( payload ) => setCatalog( normalizeCatalog( payload.sources ) ) )
			.catch( () => undefined );
	}, [ config.restUrl, request ] );

	useEffect( () => {
		if ( state.sourceKey ) return;
		const allowed = catalog.find( ( source ) => source.access === 'allowed' );
		if ( allowed ) dispatch( { type: 'source', sourceKey: allowed.key } );
	}, [ catalog, state.sourceKey ] );

	useEffect( () => {
		if ( ! state.sourceKey ) return;
		dispatch( { type: 'loading' } );
		request( 'get', buildListUrl( config.restUrl, state.sourceKey, state.query ) )
			.then( ( payload ) => dispatch( { type: 'loaded', payload: {
				...payload,
				rows: ( payload.rows || [] ).map( ( row, index ) => ( { id: row.id ?? index, ...row } ) ),
			} } ) )
			.catch( ( error ) => dispatch( { type: 'error', message: error.message } ) );
	}, [ config.restUrl, request, state.sourceKey, state.query ] );

	const source = useMemo(
		() => catalog.find( ( item ) => item.key === state.sourceKey ) || null,
		[ catalog, state.sourceKey ]
	);

	const reload = useCallback( () => {
		dispatch( { type: 'query', patch: { page: state.query.page } } );
	}, [ state.query.page ] );

	const previewMutation = useCallback( async ( operation, recordIds, values = {} ) => {
		dispatch( { type: 'request', request: 'mutation-preview' } );
		try {
			const payload = await request(
				'post', dataEndpoint( config.restUrl, state.sourceKey, 'mutation-preview' ),
				{ operation, record_ids: recordIds, values }
			);
			dispatch( { type: 'preview', preview: payload.preview } );
		} catch ( error ) {
			dispatch( { type: 'error', message: error.message } );
		}
	}, [ config.restUrl, request, state.sourceKey ] );

	const applyMutation = useCallback( async () => {
		if ( ! state.preview?.token ) return;
		dispatch( { type: 'request', request: 'mutation-apply' } );
		try {
			await request(
				'post', dataEndpoint( config.restUrl, state.sourceKey, 'mutation-apply' ),
				{ token: state.preview.token }
			);
			dispatch( { type: 'success', message: __( 'The record changes were applied.', 'corex' ) } );
			reload();
		} catch ( error ) {
			dispatch( { type: 'error', message: error.message } );
		}
	}, [ config.restUrl, reload, request, state.preview, state.sourceKey ] );

	const createExport = useCallback( async ( exportRequest ) => {
		dispatch( { type: 'request', request: 'export' } );
		try {
			const payload = await request(
				'post', dataEndpoint( config.restUrl, state.sourceKey, 'export' ), exportRequest
			);
			dispatch( { type: 'success', message: __( 'The export was queued.', 'corex' ) } );
			return payload.export;
		} catch ( error ) {
			dispatch( { type: 'error', message: error.message } );
			return null;
		}
	}, [ config.restUrl, request, state.sourceKey ] );

	const detail = useCallback( async ( recordId ) => {
		try {
			const payload = await request( 'get', `${ config.restUrl }/${ state.sourceKey }/${ recordId }` );
			return payload.record;
		} catch ( error ) {
			dispatch( { type: 'error', message: error.message } );
			return null;
		}
	}, [ config.restUrl, request, state.sourceKey ] );

	return {
		catalog,
		source,
		state,
		dispatch,
		request,
		reload,
		previewMutation,
		applyMutation,
		createExport,
		detail,
		can: ( operation ) => canAction( source, operation ),
	};
}
