import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useReducer,
	useRef,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildFlowListUrl,
	copyVersionEndpoint,
	draftFromDetail,
	flowEndpoint,
	flowReducer,
	initialFlowState,
	requestedFlow,
} from './flowEditor.js';
import { usePending } from '../admin/components/working.js';

function failureMessage( result ) {
	return result.envelope.message || __( 'The flow request failed.', 'corex' );
}

export function useFlows( config ) {
	const [ state, dispatch ] = useReducer( flowReducer, initialFlowState );
	// The control whose command is out. One status disables every button on the screen; this
	// is which of them was pressed. It covers the whole command, with the reads that follow a
	// write, so the button works until the screen is current again and not only until the
	// first answer.
	const [ pending, during ] = usePending();
	// The draft as it is now, for a command that runs after another in the same handler: Save
	// and then Publish must publish the version the save made, and the render that handler
	// was made in still holds the one before it. Set as soon as a draft is loaded, and after
	// every render for the edits made on screen.
	const latest = useRef( null );
	useLayoutEffect( () => {
		latest.current = state.draft;
	} );

	const load = useCallback(
		async ( search = '', lifecycle = '', message = '' ) => {
			dispatch( { type: 'load' } );
			const [ list, extensions ] = await Promise.all( [
				window.Corex.api.get(
					buildFlowListUrl( config.restUrl, search, lifecycle ),
					{ nonce: config.nonce }
				),
				window.Corex.api.get(
					`${ flowEndpoint( config.restUrl ) }/extensions`,
					{
						nonce: config.nonce,
					}
				),
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
		load().then( ( loaded ) => {
			// A block's editor note links to its flow (spec 122): open it once the list is in.
			const asked = requestedFlow( window.location.search );
			if ( loaded && asked ) {
				select( asked );
			}
		} );
		// Once, when the screen opens: `select` is stable and is defined below.
		// eslint-disable-next-line react-hooks/exhaustive-deps
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
				dispatch( {
					type: 'failed',
					message: failureMessage( result ),
				} );
				return null;
			}
			latest.current = draft;
			dispatch( { type: 'selected', payload: draft } );
			return draft;
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

	// The saved draft, as it was read back, or null when it was not saved.
	const saveDraft = async () => {
		dispatch( { type: 'mutating' } );
		const { flow, version, configuration } = latest.current;
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
			return null;
		}
		return select( flow.id );
	};

	const transition = async ( action ) => {
		const { flow, version } = latest.current;
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

	// An earlier version becomes the saved draft; nothing is published (spec 122, FR-005).
	const copyVersion = async ( versionNumber ) => {
		const { flow, version } = latest.current;
		dispatch( { type: 'mutating' } );
		const result = await window.Corex.api.post(
			copyVersionEndpoint( config.restUrl, flow.id, versionNumber ),
			{
				expected_version: version.version_number,
				expected_checksum: version.checksum,
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

	// When the flow takes answers, saved on its own and at once (spec 122, FR-026).
	const saveAvailability = async ( availability ) => {
		const { flow } = latest.current;
		dispatch( { type: 'mutating' } );
		const result = await window.Corex.api.patch(
			flowEndpoint( config.restUrl, flow.id, 'availability' ),
			{ availability },
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( { type: 'failed', message: failureMessage( result ) } );
			return false;
		}
		dispatch( {
			type: 'flow_changed',
			payload: result.envelope.data.flow,
			message: __( 'Availability saved.', 'corex' ),
		} );
		return true;
	};

	// Who "Routed owner" would be for the routing on screen (spec 122, FR-019). A read: it
	// changes nothing, so it leaves the screen's status alone.
	const previewRouting = async ( routing, schema ) => {
		const { flow } = latest.current;
		const result = await window.Corex.api.post(
			flowEndpoint( config.restUrl, flow.id, 'routing-preview' ),
			{ routing, schema },
			{ nonce: config.nonce }
		);
		return result.envelope.ok ? result.envelope.data.lines : null;
	};

	const test = async () => {
		const { flow, version } = latest.current;
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
		pending,
		load: ( search, lifecycle ) =>
			during( 'filters', () => load( search, lifecycle ) ),
		// Named for the flow it opens, so the screen can draw the editor's placeholder while
		// it is fetched. The commands above call the unnamed one: they are already named.
		select: ( flowId ) =>
			during( `open:${ flowId }`, () => select( flowId ) ),
		create: ( values ) => during( 'create', () => create( values ) ),
		saveDraft: () => during( 'saveDraft', saveDraft ),
		publish: () => during( 'publish', () => transition( 'publish' ) ),
		unpublish: () => during( 'unpublish', () => transition( 'unpublish' ) ),
		close: () => during( 'close', () => transition( 'close' ) ),
		test: () => during( 'test', test ),
		copyVersion: ( versionNumber ) =>
			during( `copy:${ versionNumber }`, () =>
				copyVersion( versionNumber )
			),
		saveAvailability: ( availability ) =>
			during( 'availability', () => saveAvailability( availability ) ),
		previewRouting,
		teams: teamsClient( config ),
	};
}

/**
 * The Teams view's requests (spec 122, FR-012). Each resolves to the envelope, so the view says
 * what went wrong in the server's words.
 *
 * @param {Object} config The screen's configuration: `restUrl` and `nonce`.
 * @return {Object} `list`, `create`, `update` and `remove`.
 */
export function teamsClient( config ) {
	const base = config.restUrl.replace( /flows\/?$/, 'teams' );
	const options = { nonce: config.nonce };
	const address = ( key ) => `${ base }/${ encodeURIComponent( key ) }`;

	return {
		list: () =>
			window.Corex.api.get( base, options ).then( ( r ) => r.envelope ),
		create: ( team ) =>
			window.Corex.api
				.post( base, team, options )
				.then( ( r ) => r.envelope ),
		update: ( key, team ) =>
			window.Corex.api
				.patch( address( key ), team, options )
				.then( ( r ) => r.envelope ),
		remove: ( key ) =>
			window.Corex.api
				.delete( address( key ), options )
				.then( ( r ) => r.envelope ),
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
