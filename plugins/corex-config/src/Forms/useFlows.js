import { useCallback, useEffect, useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildFlowListUrl,
	draftFromDetail,
	flowEndpoint,
	flowReducer,
	initialFlowState,
} from './flowEditor.js';

function failureMessage( result ) {
	return result.envelope.message || __( 'The flow request failed.', 'corex' );
}

export function useFlows( config ) {
	const [ state, dispatch ] = useReducer( flowReducer, initialFlowState );

	const load = useCallback(
		async ( search = '', lifecycle = '', message = '' ) => {
			dispatch( { type: 'load' } );
			const [ list, extensions ] = await Promise.all( [
				window.Corex.api.get(
					buildFlowListUrl( config.restUrl, search, lifecycle ),
					{ nonce: config.nonce }
				),
				window.Corex.api.get( `${ flowEndpoint( config.restUrl ) }/extensions`, {
					nonce: config.nonce,
				} ),
			] );
			if ( ! list.envelope.ok || ! extensions.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message: failureMessage(
						! list.envelope.ok ? list : extensions
					),
				} );
				return false;
			}
			dispatch( {
				type: 'loaded',
				payload: {
					flows: list.envelope.data.flows,
					extensions: extensions.envelope.data,
				},
				message,
			} );
			return true;
		},
		[ config.nonce, config.restUrl ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	const select = useCallback(
		async ( flowId ) => {
			dispatch( { type: 'load' } );
			const result = await window.Corex.api.get(
				flowEndpoint( config.restUrl, flowId ),
				{ nonce: config.nonce }
			);
			const draft = result.envelope.ok
				? draftFromDetail( result.envelope.data )
				: null;
			if ( ! draft ) {
				dispatch( { type: 'failed', message: failureMessage( result ) } );
				return false;
			}
			dispatch( { type: 'selected', payload: draft } );
			return true;
		},
		[ config.nonce, config.restUrl ]
	);

	const create = async ( values ) => {
		dispatch( { type: 'mutating' } );
		const result = await window.Corex.api.post(
			flowEndpoint( config.restUrl ),
			newFlowPayload( values ),
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( { type: 'failed', message: failureMessage( result ) } );
			return false;
		}
		await load( '', '', __( 'Flow created.', 'corex' ) );
		await select( result.envelope.data.flow.id );
		return true;
	};

	const saveDraft = async () => {
		dispatch( { type: 'mutating' } );
		const { flow, version, configuration } = state.draft;
		const result = await window.Corex.api.patch(
			flowEndpoint( config.restUrl, flow.id ),
			{
				expected_version: version.version_number,
				expected_checksum: version.checksum,
				configuration,
			},
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( { type: 'failed', message: failureMessage( result ) } );
			return false;
		}
		await select( flow.id );
		return true;
	};

	const transition = async ( action ) => {
		const { flow, version } = state.draft;
		dispatch( { type: 'mutating' } );
		const result = await window.Corex.api.post(
			flowEndpoint( config.restUrl, flow.id, action ),
			{ expected_version: version.version_number },
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( { type: 'failed', message: failureMessage( result ) } );
			return false;
		}
		await select( flow.id );
		return true;
	};

	const test = async () => {
		const { flow, version } = state.draft;
		dispatch( { type: 'mutating' } );
		const result = await window.Corex.api.post(
			flowEndpoint( config.restUrl, flow.id, 'test' ),
			{ expected_version: version.version_number },
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( { type: 'failed', message: failureMessage( result ) } );
			return false;
		}
		dispatch( {
			type: 'test_result',
			payload: result.envelope.data,
			message: __( 'Marked test completed.', 'corex' ),
		} );
		return true;
	};

	return {
		state,
		dispatch,
		load,
		select,
		create,
		saveDraft,
		publish: () => transition( 'publish' ),
		unpublish: () => transition( 'unpublish' ),
		close: () => transition( 'close' ),
		test,
	};
}

function newFlowPayload( values ) {
	return {
		slug: values.slug,
		name: values.name,
		description: values.description || '',
		owner_id: configOwner( values.ownerId ),
		placement_type: 'none',
		placement_id: null,
		test_mode: false,
		configuration: {
			schema: [],
			validation: {},
			routing: {
				rules: [],
				fallback: { type: 'flow_owner', config: {} },
			},
			email_routes: [],
			success: { type: 'inline', message: values.successMessage },
			placement_snapshot: { type: 'none' },
		},
	};
}

function configOwner( ownerId ) {
	return Number.isInteger( ownerId ) && ownerId > 0 ? ownerId : 0;
}
