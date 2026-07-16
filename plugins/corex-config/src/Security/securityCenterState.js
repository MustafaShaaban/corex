export const MODES = [ 'development', 'staging', 'production', 'maintenance' ];

export function securityEndpoint( root, path = '' ) {
	const base = String( root || '' ).replace( /\/$/, '' );
	const suffix = String( path || '' ).replace( /^\//, '' );
	return suffix ? `${ base }/${ suffix }` : base;
}

export function initialSecurityState() {
	return {
		status: 'idle',
		mode: 'staging',
		selectedMode: 'staging',
		readiness: normalizeReadiness( {} ),
		productionPhrase: '',
		maintenanceConfirmed: false,
		loginPolicy: normalizeLoginPolicy( {} ),
		lockouts: [],
		recovery: null,
		activity: [],
		notice: null,
	};
}

export function normalizeReadiness( snapshot = {} ) {
	const checks = Array.isArray( snapshot.checks ) ? snapshot.checks.map( normalizeCheck ) : [];
	const blockingKeys = Array.isArray( snapshot.blocking_keys )
		? snapshot.blocking_keys.map( String )
		: checks.filter( ( check ) => check.blocking ).map( ( check ) => check.key );

	return {
		checks,
		blockingKeys,
		blockingCount: blockingKeys.length,
		targetHash: snapshot.target_hash || '',
	};
}

export function normalizeLoginPolicy( policy = {} ) {
	return {
		// Defaults mirror the server (LoginProtectionSettingsStore). They used to disagree —
		// `enabled` defaulted to true here and false there, `windowSeconds` to 900 against 300 —
		// so an unsaved form showed a state the site was not in.
		enabled: policy.enabled === true,
		blockDefaultEndpoints: policy.blockDefaultEndpoints === true || policy.block_default_endpoints === true,
		customSlug: policy.customSlug || policy.custom_slug || 'corex-login',
		loginUrl: policy.loginUrl || policy.login_url || '',
		slugSubstituted: policy.slugSubstituted === true || policy.slug_substituted === true,
		storedSlug: policy.storedSlug || policy.stored_slug || '',
		maxAttempts: clampInteger( policy.maxAttempts ?? policy.max_attempts, 5, 1, 50 ),
		windowSeconds: clampInteger( policy.windowSeconds ?? policy.window_seconds, 300, 60, 86400 ),
		lockoutSeconds: clampInteger( policy.lockoutSeconds ?? policy.lockout_seconds, 900, 60, 604800 ),
		trustedProxies: Array.isArray( policy.trustedProxies )
			? policy.trustedProxies.map( String )
			: ( Array.isArray( policy.trusted_proxies ) ? policy.trusted_proxies.map( String ) : [] ),
		retentionDays: clampInteger( policy.retentionDays ?? policy.retention_days, 30, 1, 365 ),
		successfulLoginLogging: policy.successfulLoginLogging !== false && policy.successful_login_logging !== false,
	};
}

export function securityReducer( state = initialSecurityState(), action = {} ) {
	switch ( action.type ) {
		case 'loading':
			return { ...state, status: 'loading', notice: null };
		case 'loaded': {
			const mode = normalizeMode( action.payload?.mode || state.mode );
			return {
				...state,
				status: 'ready',
				mode,
				selectedMode: mode,
				readiness: normalizeReadiness( action.payload?.readiness ),
				loginPolicy: normalizeLoginPolicy( action.payload?.loginPolicy ),
				lockouts: normalizeLockouts( action.payload?.lockouts ),
				activity: normalizeActivity( action.payload?.activity ),
				notice: null,
			};
		}
		case 'selectMode':
			return { ...state, selectedMode: normalizeMode( action.mode ), productionPhrase: '', maintenanceConfirmed: false };
		case 'setProductionPhrase':
			return { ...state, productionPhrase: String( action.phrase || '' ) };
		case 'setMaintenanceConfirmed':
			return { ...state, maintenanceConfirmed: action.confirmed === true };
		case 'setLoginPolicy':
			return { ...state, loginPolicy: normalizeLoginPolicy( { ...state.loginPolicy, ...( action.patch || {} ) } ) };
		case 'savingLoginPolicy':
			return { ...state, status: 'saving', notice: null };
		case 'savedLoginPolicy':
			return {
				...state,
				status: 'ready',
				loginPolicy: normalizeLoginPolicy( action.policy || state.loginPolicy ),
				notice: { tone: 'success', message: action.message || 'Login protection settings saved.' },
			};
		case 'lockoutsLoaded':
			return { ...state, lockouts: normalizeLockouts( action.lockouts ) };
		case 'recovered':
			return {
				...state,
				recovery: action.result || null,
				notice: { tone: 'success', message: action.message || 'Login recovery completed.' },
			};
		case 'activityLoaded':
			return { ...state, activity: normalizeActivity( action.activity ) };
		case 'error':
			return { ...state, status: 'ready', notice: { tone: 'error', message: action.message || 'Security action failed.' } };
		default:
			return state;
	}
}

export function modeActionState( state ) {
	const selected = normalizeMode( state?.selectedMode );
	if ( selected === 'production' ) {
		return {
			requiresPhrase: true,
			requiredPhrase: 'PRODUCTION',
			ready: String( state?.productionPhrase || '' ) === 'PRODUCTION',
			blockingCount: state?.readiness?.blockingCount || 0,
		};
	}
	if ( selected === 'maintenance' ) {
		return {
			requiresPhrase: false,
			requiredPhrase: '',
			ready: state?.maintenanceConfirmed === true,
			blockingCount: 0,
		};
	}
	return {
		requiresPhrase: false,
		requiredPhrase: '',
		ready: true,
		blockingCount: 0,
	};
}

export function buildLoginPolicyPayload( policy ) {
	const normalized = normalizeLoginPolicy( policy );
	return {
		enabled: normalized.enabled,
		block_default_endpoints: normalized.blockDefaultEndpoints,
		custom_slug: normalized.customSlug,
		max_attempts: normalized.maxAttempts,
		window_seconds: normalized.windowSeconds,
		lockout_seconds: normalized.lockoutSeconds,
		trusted_proxies: normalized.trustedProxies,
		retention_days: normalized.retentionDays,
		successful_login_logging: normalized.successfulLoginLogging,
	};
}

export function lockoutSummary( lockouts = [] ) {
	const rows = normalizeLockouts( lockouts );
	return {
		active: rows.filter( ( lockout ) => lockout.active ).length,
		expired: rows.filter( ( lockout ) => ! lockout.active ).length,
	};
}

function normalizeCheck( check = {} ) {
	return {
		key: check.key || '',
		label: check.label || check.key || '',
		status: check.status === 'pass' ? 'pass' : 'review',
		detail: check.detail || '',
		blocking: check.blocking === true || check.status === 'review',
		resolutionUrl: check.resolution_url || '',
	};
}

function normalizeLockouts( lockouts = [] ) {
	return Array.isArray( lockouts )
		? lockouts.map( ( lockout ) => ( {
			id: lockout.id || '',
			// Identities are stored hashed by design, so a short fingerprint is all there is.
			identity: lockout.identity || '',
			network: lockout.network || '',
			account: lockout.account || '',
			reason: lockout.reason || '',
			// `active` defaulted to true, so a row with no flag was reported as an ongoing lockout.
			active: lockout.active === true,
			lockedUntil: lockout.lockedUntil || lockout.locked_until || '',
		} ) )
		: [];
}

function normalizeActivity( activity = [] ) {
	return Array.isArray( activity )
		? activity.map( ( event ) => ( {
			id: event.id || '',
			kind: event.kind || 'security.event',
			label: event.label || event.kind || 'Security event',
			tone: event.tone || 'info',
			occurredAt: event.occurred_at || '',
		} ) )
		: [];
}

function normalizeMode( mode ) {
	return MODES.includes( mode ) ? mode : 'staging';
}

function clampInteger( value, fallback, min, max ) {
	const number = Number.parseInt( value, 10 );
	if ( Number.isNaN( number ) ) {
		return fallback;
	}
	return Math.min( max, Math.max( min, number ) );
}
