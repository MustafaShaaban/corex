import { useCallback, useEffect, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildInboxUrl,
	inboxReducer,
	initialInboxState,
	trashNotice,
} from './inbox.js';

function message( result ) {
	return (
		result.envelope.message || __( 'The Inbox request failed.', 'corex' )
	);
}

export function useInbox( config, filters ) {
	const [ state, dispatch ] = useReducer( inboxReducer, initialInboxState );

	const load = useCallback(
		async ( success = '', undo = [] ) => {
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
				dispatch( { type: 'message', message: success, undo } );
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
			const result = await window.Corex.api.get(
				`${ config.restUrl }/${ id }`,
				{
					nonce: config.nonce,
				}
			);
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'detailFailed',
					message: message( result ),
				} );
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

	// Fetches the open submission again without putting the pane back into its loading state.
	// Reopening it did that after every change: the pane blanked, and focus fell out of it.
	const reload = useCallback(
		async ( id ) => {
			const result = await window.Corex.api.get(
				`${ config.restUrl }/${ id }`,
				{ nonce: config.nonce }
			);
			if ( result.envelope.ok ) {
				dispatch( {
					type: 'detailLoaded',
					record: result.envelope.data.submission,
				} );
			}
		},
		[ config.nonce, config.restUrl ]
	);

	const refreshRecord = useCallback(
		async ( id, success ) => {
			await load( success );
			await reload( id );
		},
		[ load, reload ]
	);

	// Opening a submission is reading it (spec 103, FR-047). The pane's header offers to mark it
	// unread again, for somebody who only looked.
	const openAndRead = useCallback(
		async ( id ) => {
			const record = await open( id );
			// A trashed submission is read and nothing else: looking at it changes nothing.
			if ( record && ! record.read_at && ! record.trashed ) {
				const marked = await mutate(
					`/${ id }`,
					{
						mark_read: true,
						expected_updated_at: record.updated_at,
					},
					'patch'
				);
				if ( marked ) {
					await refreshRecord( id, '' );
				}
			}
			return record;
		},
		[ open, mutate, refreshRecord ]
	);

	return {
		state,
		dispatch,
		load,
		open: openAndRead,
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
				await refreshRecord(
					id,
					__( 'Internal note added.', 'corex' )
				);
			}
			return result;
		},
		reply: async ( id, data ) => {
			const result = await mutate( `/${ id }/reply`, data );
			if ( result ) {
				await refreshRecord(
					id,
					__( 'Reply processed by Email Studio.', 'corex' )
				);
			}
			return result;
		},
		resend: async ( id, attemptId ) => {
			const result = await mutate( `/${ id }/resend`, {
				attempt_id: attemptId,
			} );
			if ( result ) {
				await refreshRecord(
					id,
					__( 'Related email resent.', 'corex' )
				);
			}
			return result;
		},
		log: ( id, attemptId ) =>
			window.Corex.api.get(
				`${
					config.restUrl
				}/${ id }/email-log?attempt_id=${ encodeURIComponent(
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
		applyBulk: async ( token, done = {} ) => {
			const result = await mutate( '/bulk/apply', { token } );
			if ( result ) {
				dispatch( { type: 'selectionChanged', ids: [] } );
				await load(
					done.message || __( 'Bulk action applied.', 'corex' ),
					done.undo || []
				);
			}
			return result;
		},
		// One submission, from its pane (spec 105). The notice that follows offers to undo it.
		trash: async ( record ) => {
			const result = await mutate( `/${ record.id }/trash`, {
				expected_updated_at: record.updated_at,
			} );
			if ( result ) {
				dispatch( { type: 'drawerClosed' } );
				await load( trashNotice( 'trash', 1 ), [ record.id ] );
			}
			return result;
		},
		// One or several, from the trash or from "Undo". Several go the way a bulk action does,
		// without a second confirmation: the person has just asked for exactly this.
		restore: async ( ids ) => {
			let result = null;
			if ( ids.length === 1 ) {
				result = await mutate( `/${ ids[ 0 ] }/restore`, {} );
			} else {
				const previewed = await mutate( '/bulk/preview', {
					action: 'restore',
					submission_ids: ids,
					parameters: {},
				} );
				result =
					previewed &&
					( await mutate( '/bulk/apply', {
						token: previewed.preview.token,
					} ) );
			}
			if ( result ) {
				dispatch( { type: 'drawerClosed' } );
				dispatch( { type: 'selectionChanged', ids: [] } );
				await load( trashNotice( 'restore', ids.length ) );
			}
			return result;
		},
		createExport: ( data ) => mutate( '/exports', data ),
		previewExport: ( data ) => mutate( '/exports/preview', data ),
		advanceExport: ( id ) => mutate( `/exports/${ id }/advance`, {} ),
		loadExports: () =>
			window.Corex.api.get( `${ config.restUrl }/exports`, {
				nonce: config.nonce,
			} ),
		deleteExport: ( id ) =>
			window.Corex.api.delete( `${ config.restUrl }/exports/${ id }`, {
				nonce: config.nonce,
			} ),
		downloadExport: ( id ) =>
			window.Corex.api.get(
				`${ config.restUrl }/exports/${ id }/download`,
				{
					nonce: config.nonce,
				}
			),
	};
}
