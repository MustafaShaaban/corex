import {
	useCallback,
	useEffect,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	buildEndpoint,
	buildHealthUrl,
	drawingErrors,
	emailStudioReducer,
	initialEmailStudioState,
	isEmailAddress,
	previewRequest,
	testSendRequest,
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
};

const emptyDraft = ( layouts ) =>
	layouts[ 0 ]
		? {
				...EMPTY_DRAFT,
				layout_id: layouts[ 0 ].id,
				layout_version: layouts[ 0 ].version,
			}
		: { ...EMPTY_DRAFT };

/**
 * Project a stored version onto the editable draft shape.
 *
 * Spreading the version wholesale also carried its server-owned fields — `id`,
 * `template_id`, `version`, `checksum`, `created_by`, `created_at` — and the draft is
 * posted as the request body. WordPress resolves JSON body params *before* URL params
 * (WP_REST_Request::get_parameter_order), so a body `id` shadows the route's own {id}:
 * saving a draft for template 3859 looked up the version id instead and 404'd. Only the
 * fields the form actually edits may travel back.
 *
 * @param {Object} version A stored template version.
 * @return {Object} The editable draft fields, defaulted where the version is silent.
 */
const draftFrom = ( version ) =>
	Object.fromEntries(
		Object.keys( EMPTY_DRAFT ).map( ( key ) => [
			key,
			version[ key ] ?? EMPTY_DRAFT[ key ],
		] )
	);

function formValues( event ) {
	event.preventDefault();
	return Object.fromEntries( new FormData( event.currentTarget ).entries() );
}

/**
 * The operator-facing reason a request failed.
 *
 * A rejected draft answers with a summary *and* `details.fields` naming what is wrong with
 * which field. Only the summary was ever read, so "The draft contains invalid or unsafe
 * content." arrived with the actual reason still attached to the response and unseen.
 *
 * @param {Object} envelope The failed response envelope.
 * @return {string} The summary, plus any per-field reasons the server supplied.
 */
function failureMessage( envelope ) {
	const summary = envelope.message || __( 'The request failed.', 'corex' );
	const fields = envelope.details && envelope.details.fields;
	const reasons = fields ? Object.values( fields ).filter( Boolean ) : [];

	return reasons.length > 0
		? `${ summary } ${ reasons.join( ' ' ) }`
		: summary;
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
					message: failureMessage( result.envelope ),
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
			dispatch( { type: 'mutating', control: kind } );
			const result = await window.Corex.api.post(
				buildEndpoint( config.restUrl, kind, id ),
				data,
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message: failureMessage( result.envelope ),
				} );
				return null;
			}
			await load( successMessage );
			return result.envelope.data;
		},
		[ config.nonce, config.restUrl, load ]
	);

	return { state, dispatch, post, load };
}

function useTemplateSelection( { config, layouts, dispatch } ) {
	const [ detail, setDetail ] = useState( null );
	const [ draft, setDraft ] = useState( EMPTY_DRAFT );
	const [ errors, setErrors ] = useState( {} );
	// The id of the template that was chosen from the list and has not arrived. The editor
	// went on showing the last one, with nothing to say another was coming. Set by
	// chooseTemplate(), the list's own way in: a save reads its template again through
	// selectTemplate(), and must not take the open editor away to do it.
	const [ selecting, setSelecting ] = useState( null );
	const selectTemplate = useCallback(
		async ( template ) => {
			const result = await window.Corex.api.get(
				buildEndpoint( config.restUrl, 'template', template.id ),
				{ nonce: config.nonce }
			);
			if ( ! result.envelope.ok ) {
				dispatch( {
					type: 'failed',
					message: failureMessage( result.envelope ),
				} );
				return;
			}
			const next = result.envelope.data;
			const latest = next.versions[ next.versions.length - 1 ];
			setDetail( next );
			setDraft( latest ? draftFrom( latest ) : emptyDraft( layouts ) );
			setErrors( {} );
		},
		[ config.nonce, config.restUrl, dispatch, layouts ]
	);

	const chooseTemplate = async ( template ) => {
		setSelecting( template.id );
		try {
			await selectTemplate( template );
		} finally {
			setSelecting( null );
		}
	};

	return {
		detail,
		draft,
		errors,
		selecting,
		setDraft,
		setErrors,
		selectTemplate,
		chooseTemplate,
	};
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
		setDraft( ( current ) => ( { ...current, [ name ]: value } ) );
	};
}

function useTemplateCommands( { post, selection } ) {
	// What a saved draft is told about: a variable its emails do not supply, a From address on
	// another domain (spec 120).
	const [ warnings, setWarnings ] = useState( {} );
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
			setWarnings( data.warnings || {} );
			await selection.selectTemplate( selection.detail.template );
		}
	};
	// Puts a template CoreX ships back as it was shipped (spec 120).
	const reset = async () => {
		const data = await post(
			'reset',
			{},
			selection.detail.template.id,
			__( 'The shipped version is back, as a new version.', 'corex' )
		);
		if ( data ) {
			setWarnings( {} );
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

	return { createTemplate, saveDraft, activate, reset, warnings };
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

function useDeliveryCommands( { api, config, selection, preview } ) {
	const { post, dispatch, load } = api;
	const [ lastResult, setLastResult ] = useState( null );
	const [ testError, setTestError ] = useState( '' );
	// The email the preview shows, sent to one address: the server draws it again with the
	// same samples (spec 120, story 1).
	const testSend = async ( event ) => {
		event.preventDefault();
		const to = String(
			new FormData( event.currentTarget ).get( 'to' ) || ''
		).trim();
		setLastResult( null );
		if ( ! isEmailAddress( to ) ) {
			setTestError(
				sprintf(
					/* translators: %s: what was typed as the test address. */
					__(
						'Enter a valid email address: %s is not one.',
						'corex'
					),
					to === '' ? __( 'an empty field', 'corex' ) : to
				)
			);
			return;
		}
		setTestError( '' );
		dispatch( { type: 'mutating', control: 'test' } );
		const result = await window.Corex.api.post(
			buildEndpoint( config.restUrl, 'test' ),
			testSendRequest(
				selection.detail.template.id,
				selection.draft,
				preview.overrides,
				to
			),
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			setTestError( drawingErrors( result.envelope ).join( ' ' ) );
			dispatch( { type: 'notice' } );
			return;
		}
		setLastResult( result.envelope.data );
		await load();
	};
	const resend = ( attempt, message ) =>
		post(
			'resend',
			message,
			attempt.attempt_id,
			__( 'Retry attempt created.', 'corex' )
		);

	// The "Capture only CoreX mail" switch (spec 113).
	const saveScope = ( scope ) =>
		post(
			'captureScope',
			{ scope },
			'',
			scope === 'corex'
				? __( 'Only CoreX mail is captured now.', 'corex' )
				: __( 'All mail from this site is captured now.', 'corex' )
		);

	return { lastResult, testError, testSend, resend, saveScope };
}

function useHealth( { config, dispatch, selection } ) {
	const [ health, setHealth ] = useState( null );
	useEffect( () => {
		setHealth( null );
	}, [ selection.detail?.template.id ] );
	const runHealth = async () => {
		dispatch( { type: 'mutating', control: 'health' } );
		const result = await window.Corex.api.get(
			buildHealthUrl(
				config.restUrl,
				selection.detail.template.id,
				selection.detail.template.draft_version
			),
			{ nonce: config.nonce }
		);
		if ( ! result.envelope.ok ) {
			dispatch( {
				type: 'failed',
				message: failureMessage( result.envelope ),
			} );
			return;
		}
		setHealth( result.envelope.data.health );
		// The request is over and the studio is as it was. It used to be "loaded" again from
		// its own state, which is not what the server sends: read a second time, "Recent test
		// sends" came out empty (#313).
		dispatch( { type: 'notice' } );
	};

	return { health, runHealth };
}

/** How long the editor is left still before the preview is drawn again. */
const PREVIEW_PAUSE = 400;

/**
 * The preview the server draws of the draft being edited (spec 120, story 1): asked again a
 * moment after the draft, a sample value or the direction changes, while a tab that shows it
 * is open.
 *
 * @param {Object}  config    Email Studio's routes and nonce.
 * @param {Object}  selection The template and draft in the editor.
 * @param {boolean} wanted    Whether a tab that shows the preview is open.
 * @return {Object} The drawing, its state, and its controls.
 */
function usePreview( config, selection, wanted ) {
	const [ direction, setDirection ] = useState( 'ltr' );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ overrides, setOverrides ] = useState( {} );
	const [ drawn, setDrawn ] = useState( { status: 'idle' } );
	const asked = useRef( 0 );
	const templateId = selection.detail?.template?.id;

	useEffect( () => {
		setOverrides( {} );
	}, [ templateId ] );

	useEffect( () => {
		if ( ! wanted || ! templateId ) {
			return undefined;
		}
		const request = ++asked.current;
		setDrawn( ( current ) => ( { ...current, status: 'loading' } ) );
		const timer = setTimeout( async () => {
			const result = await window.Corex.api.post(
				buildEndpoint( config.restUrl, 'preview', templateId ),
				previewRequest( selection.draft, overrides, direction ),
				{ nonce: config.nonce }
			);
			// Only the answer to the latest question is shown.
			if ( request !== asked.current ) {
				return;
			}
			setDrawn(
				result.envelope.ok
					? { status: 'ready', ...result.envelope.data }
					: {
							status: 'error',
							errors: drawingErrors( result.envelope ),
						}
			);
		}, PREVIEW_PAUSE );

		return () => clearTimeout( timer );
	}, [
		wanted,
		templateId,
		selection.draft,
		overrides,
		direction,
		config.restUrl,
		config.nonce,
	] );

	const setSample = ( key, value ) =>
		setOverrides( ( current ) => ( { ...current, [ key ]: value } ) );

	return {
		direction,
		device,
		drawn,
		overrides,
		setDirection,
		setDevice,
		setSample,
	};
}

/** The tabs that show the drawn email. */
const DRAWN_TABS = [ 'preview', 'plain', 'test' ];

export function useEmailStudio( config, setTab, tab = '' ) {
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
	const preview = usePreview( config, selection, DRAWN_TABS.includes( tab ) );
	const delivery = useDeliveryCommands( {
		api,
		config,
		selection,
		preview,
	} );
	const health = useHealth( {
		config,
		dispatch: api.dispatch,
		selection,
	} );
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
