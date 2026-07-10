import {
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	buildEndpoint,
	composePreviewHtml,
	emailStudioReducer,
	initialEmailStudioState,
	plainTextFromHtml,
	previewDocument,
	validateDraftForm,
} from './emailStudioClient.js';

const EMPTY_DRAFT = {
	subject: '',
	from_name: '',
	from_address: '',
	html_body: '',
	plain_text: '',
	plain_text_mode: 'auto',
	layout_id: 0,
	layout_version: 0,
	variable_keys: [],
};

const emptyDraft = ( layouts ) =>
	layouts[ 0 ]
		? {
				...EMPTY_DRAFT,
				layout_id: layouts[ 0 ].id,
				layout_version: layouts[ 0 ].version,
		  }
		: { ...EMPTY_DRAFT };

function formValues( event ) {
	event.preventDefault();
	return Object.fromEntries( new FormData( event.currentTarget ).entries() );
}

function useStudioApi( config ) {
	const [ state, dispatch ] = useReducer(
		emailStudioReducer,
		initialEmailStudioState
	);
	const load = useCallback(
		async ( message = '' ) => {
			dispatch( { type: 'load' } );
			const result = await window.Corex.api.get( config.restUrl, {
				nonce: config.nonce,
			} );
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message:
						result.envelope.message ||
						__( 'The request failed.', 'corex' ),
				} );
				return false;
			}
			dispatch( {
				type: 'loaded',
				payload: result.envelope.data,
				message,
			} );
			return true;
		},
		[ config.nonce, config.restUrl ]
	);

	useEffect( () => {
		load();
	}, [ load ] );
	const post = useCallback(
		async ( kind, data, id, successMessage ) => {
			dispatch( { type: 'mutating' } );
			const result = await window.Corex.api.post(
				buildEndpoint( config.restUrl, kind, id ),
				data,
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message:
						result.envelope.message ||
						__( 'The request failed.', 'corex' ),
				} );
				return null;
			}
			await load( successMessage );
			return result.envelope.data;
		},
		[ config.nonce, config.restUrl, load ]
	);

	return { state, dispatch, post };
}

function useTemplateSelection( { config, layouts, dispatch } ) {
	const [ detail, setDetail ] = useState( null );
	const [ draft, setDraft ] = useState( EMPTY_DRAFT );
	const [ errors, setErrors ] = useState( {} );
	const selectTemplate = useCallback(
		async ( template ) => {
			const result = await window.Corex.api.get(
				buildEndpoint( config.restUrl, 'template', template.id ),
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message:
						result.envelope.message ||
						__( 'The request failed.', 'corex' ),
				} );
				return;
			}
			const next = result.envelope.data;
			const latest = next.versions[ next.versions.length - 1 ];
			setDetail( next );
			setDraft(
				latest ? { ...EMPTY_DRAFT, ...latest } : emptyDraft( layouts )
			);
			setErrors( {} );
		},
		[ config.nonce, config.restUrl, dispatch, layouts ]
	);

	return { detail, draft, errors, setDraft, setErrors, selectTemplate };
}

function draftChangeHandler( setDraft ) {
	return ( event ) => {
		const { name, value } = event.target;
		if ( name === 'layout_selection' ) {
			const [ layoutId, layoutVersion ] = value
				.split( ':' )
				.map( Number );
			setDraft( ( current ) => ( {
				...current,
				layout_id: layoutId,
				layout_version: layoutVersion,
			} ) );
			return;
		}
		let nextValue = value;
		if ( name === 'variable_keys' ) {
			nextValue = value
				.split( ',' )
				.map( ( key ) => key.trim() )
				.filter( Boolean );
		}
		setDraft( ( current ) => ( { ...current, [ name ]: nextValue } ) );
	};
}

function useTemplateCommands( { post, selection } ) {
	const createTemplate = async ( event ) => {
		const data = await post(
			'templates',
			formValues( event ),
			'',
			__( 'Template created.', 'corex' )
		);
		if ( data?.template ) {
			await selection.selectTemplate( data.template );
		}
	};
	const saveDraft = async ( event ) => {
		event.preventDefault();
		const errors = validateDraftForm( selection.draft, __ );
		selection.setErrors( errors );
		if ( Object.keys( errors ).length > 0 ) {
			return;
		}
		const data = await post(
			'draft',
			selection.draft,
			selection.detail.template.id,
			__( 'Draft revision saved.', 'corex' )
		);
		if ( data ) {
			await selection.selectTemplate( selection.detail.template );
		}
	};
	const activate = async ( version ) => {
		const data = await post(
			'activate',
			{ version },
			selection.detail.template.id,
			__( 'Template activated.', 'corex' )
		);
		if ( data ) {
			await selection.selectTemplate( selection.detail.template );
		}
	};

	return { createTemplate, saveDraft, activate };
}

function useInsertions( { selection, dispatch, setTab } ) {
	const missingSelectionMessage = ( kind ) =>
		kind === 'variable'
			? __(
					'Select or create a template before inserting a variable.',
					'corex'
			  )
			: __(
					'Select or create a template before inserting a partial.',
					'corex'
			  );
	const token = ( kind, value ) =>
		kind === 'variable' ? `{{ ${ value } }}` : `{{> ${ value } }}`;
	const variableKeys = ( current, kind, value ) =>
		kind === 'variable'
			? Array.from( new Set( [ ...current.variable_keys, value ] ) )
			: current.variable_keys;
	const insert = ( kind, value ) => {
		if ( ! selection.detail ) {
			dispatch( {
				type: 'notice',
				message: missingSelectionMessage( kind ),
			} );
			setTab( 'templates' );
			return;
		}
		selection.setDraft( ( current ) => ( {
			...current,
			html_body: `${ current.html_body }${ token( kind, value ) }`,
			variable_keys: variableKeys( current, kind, value ),
		} ) );
		setTab( 'templates' );
	};

	return {
		insertVariable: ( key ) => insert( 'variable', key ),
		insertPartial: ( slug ) => insert( 'partial', slug ),
	};
}

function recipientRule( values ) {
	return values.recipient_source === 'literal'
		? { source: 'literal', value: values.recipient_value }
		: { source: 'context', path: values.recipient_value };
}

function replyRule( values ) {
	if ( values.reply_to_source === 'literal' ) {
		return { source: 'literal', value: values.reply_to_value };
	}
	if ( values.reply_to_source === 'context' ) {
		return { source: 'context', path: values.reply_to_value };
	}
	return null;
}

function routePayload( values ) {
	return {
		trigger: values.trigger,
		flow_id: values.flow_id ? Number( values.flow_id ) : null,
		template_id: Number( values.template_id ),
		template_version_policy: values.template_version_policy,
		recipient_rules: [ recipientRule( values ) ],
		reply_to_rule: replyRule( values ),
		enabled: values.enabled === 'on',
		priority: Number( values.priority ),
	};
}

function layoutPayload( values ) {
	return {
		slug: values.slug,
		name: values.name,
		regions: {
			header: values.header,
			accent: values.accent,
			body: values.body,
			button: values.button,
			footer: values.footer,
		},
	};
}

function useAssetCommands( post ) {
	const saveLayout = ( event ) => {
		const values = formValues( event );
		return post(
			'layouts',
			layoutPayload( values ),
			'',
			__( 'Layout revision saved.', 'corex' )
		);
	};
	const savePartial = ( event ) =>
		post(
			'partials',
			{ ...formValues( event ), status: 'active', variable_keys: [] },
			'',
			__( 'Partial revision saved.', 'corex' )
		);
	const saveRoute = ( event ) => {
		const values = formValues( event );
		return post(
			'routes',
			routePayload( values ),
			'',
			__( 'Route saved.', 'corex' )
		);
	};

	return { saveLayout, savePartial, saveRoute };
}

function useDeliveryCommands( { post, selection } ) {
	const [ lastResult, setLastResult ] = useState( null );
	const testSend = async ( event ) => {
		const values = formValues( event );
		const data = await post(
			'test',
			{
				to: [ values.to ],
				subject: values.subject,
				body: values.body,
				template_slug: selection.detail?.template?.slug || null,
			},
			'',
			__( 'Test completed.', 'corex' )
		);
		if ( data?.result ) {
			setLastResult( data.result );
		}
	};
	const resend = ( attempt, message ) =>
		post(
			'resend',
			message,
			attempt.attempt_id,
			__( 'Retry attempt created.', 'corex' )
		);

	return { lastResult, testSend, resend };
}

function useHealth( { config, state, dispatch, selection } ) {
	const [ health, setHealth ] = useState( null );
	useEffect( () => {
		setHealth( null );
	}, [ selection.detail?.template.id ] );
	const runHealth = async () => {
		dispatch( { type: 'mutating' } );
		const endpoint = buildEndpoint(
			config.restUrl,
			'health',
			selection.detail.template.id
		);
		const result = await window.Corex.api.get(
			`${ endpoint }?version=${ selection.detail.template.draft_version }`,
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( {
				type: 'failed',
				message:
					result.envelope.message ||
					__( 'The request failed.', 'corex' ),
			} );
			return;
		}
		setHealth( result.envelope.data.health );
		dispatch( { type: 'loaded', payload: state.data } );
	};

	return { health, runHealth };
}

function usePreview( draft, data ) {
	const [ direction, setDirection ] = useState( 'ltr' );
	const [ device, setDevice ] = useState( 'desktop' );
	const samples = useMemo(
		() =>
			Object.fromEntries(
				draft.variable_keys.map( ( key ) => [ key, `Sample ${ key }` ] )
			),
		[ draft.variable_keys ]
	);
	const html = useMemo(
		() => composePreviewHtml( draft, data.layouts, data.partials ),
		[ draft, data.layouts, data.partials ]
	);
	const document = useMemo(
		() => previewDocument( html, samples, direction ),
		[ html, samples, direction ]
	);
	const plain =
		draft.plain_text_mode === 'auto'
			? plainTextFromHtml( draft.html_body )
			: draft.plain_text;

	return { direction, device, document, plain, setDirection, setDevice };
}

export function useEmailStudio( config, setTab ) {
	const api = useStudioApi( config );
	const selection = useTemplateSelection( {
		config,
		layouts: api.state.data.layouts,
		dispatch: api.dispatch,
	} );
	const commands = useTemplateCommands( { post: api.post, selection } );
	const insertions = useInsertions( {
		selection,
		dispatch: api.dispatch,
		setTab,
	} );
	const assets = useAssetCommands( api.post );
	const delivery = useDeliveryCommands( { post: api.post, selection } );
	const health = useHealth( {
		config,
		state: api.state,
		dispatch: api.dispatch,
		selection,
	} );
	const preview = usePreview( selection.draft, api.state.data );

	const onDraftChange = draftChangeHandler( selection.setDraft );

	return {
		...api,
		selection,
		commands,
		insertions,
		assets,
		delivery,
		health,
		preview,
		onDraftChange,
	};
}
