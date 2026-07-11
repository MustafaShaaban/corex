import { useCallback, useEffect, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildInboxUrl,
	inboxReducer,
	initialInboxState,
} from './inbox.js';

function message( result ) {
	return result.envelope.message || __( 'The Inbox request failed.', 'corex' );
}

export function useInbox( config, filters ) {
	const [ state, dispatch ] = useReducer( inboxReducer, initialInboxState );

	const load = useCallback(
		async ( success = '' ) => {
			dispatch( { type: 'loading' } );
			const result = await window.Corex.api.get(
				buildInboxUrl( config.restUrl, filters ),
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( { type: 'failed', message: message( result ) } );
				return false;
			}
			dispatch( { type: 'loaded', page: result.envelope.data } );
			if ( success ) {
				dispatch( { type: 'message', message: success } );
			}
			return true;
		},
		[ config.nonce, config.restUrl, filters ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	const open = useCallback(
		async ( id ) => {
			dispatch( { type: 'detailLoading', id } );
			const result = await window.Corex.api.get( `${ config.restUrl }/${ id }`, {
				nonce: config.nonce,
			} );
			if ( ! result.envelope.ok ) {
				dispatch( { type: 'detailFailed', message: message( result ) } );
				return null;
			}
			const record = result.envelope.data.submission;
			dispatch( { type: 'detailLoaded', record } );
			return record;
		},
		[ config.nonce, config.restUrl ]
	);

	const mutate = useCallback(
		async ( path, data, method = 'post' ) => {
			const result = await window.Corex.api[ method ](
				`${ config.restUrl }${ path }`,
				data,
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( { type: 'failed', message: message( result ) } );
				return null;
			}
			return result.envelope.data;
		},
		[ config.nonce, config.restUrl ]
	);

	const refreshRecord = useCallback(
		async ( id, success ) => {
			await load( success );
			await open( id );
		},
		[ load, open ]
	);

	return {
		state,
		dispatch,
		load,
		open,
		close: () => dispatch( { type: 'drawerClosed' } ),
		update: async ( id, data ) => {
			const result = await mutate( `/${ id }`, data, 'patch' );
			if ( result ) {
				await refreshRecord( id, __( 'Submission updated.', 'corex' ) );
			}
			return result;
		},
		addNote: async ( id, data ) => {
			const result = await mutate( `/${ id }/notes`, data );
			if ( result ) {
				await refreshRecord( id, __( 'Internal note added.', 'corex' ) );
			}
			return result;
		},
		reply: async ( id, data ) => {
			const result = await mutate( `/${ id }/reply`, data );
			if ( result ) {
				await refreshRecord( id, __( 'Reply processed by Email Studio.', 'corex' ) );
			}
			return result;
		},
		resend: async ( id, attemptId ) => {
			const result = await mutate( `/${ id }/resend`, { attempt_id: attemptId } );
			if ( result ) {
				await refreshRecord( id, __( 'Related email resent.', 'corex' ) );
			}
			return result;
		},
		log: ( id, attemptId ) =>
			window.Corex.api.get(
				`${ config.restUrl }/${ id }/email-log?attempt_id=${ encodeURIComponent(
					attemptId
				) }`,
				{ nonce: config.nonce }
			),
		previewBulk: ( action, ids, parameters ) =>
			mutate( '/bulk/preview', {
				action,
				submission_ids: ids,
				parameters,
			} ),
		applyBulk: async ( token ) => {
			const result = await mutate( '/bulk/apply', { token } );
			if ( result ) {
				dispatch( { type: 'selectionChanged', ids: [] } );
				await load( __( 'Bulk action applied.', 'corex' ) );
			}
			return result;
		},
		createExport: ( data ) => mutate( '/exports', data ),
		loadExports: () =>
			window.Corex.api.get( `${ config.restUrl }/exports`, {
				nonce: config.nonce,
			} ),
		downloadExport: ( id ) =>
			window.Corex.api.get( `${ config.restUrl }/exports/${ id }/download`, {
				nonce: config.nonce,
			} ),
	};
}
