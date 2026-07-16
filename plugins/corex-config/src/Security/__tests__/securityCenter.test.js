import {
	buildLoginPolicyPayload,
	initialSecurityState,
	lockoutSummary,
	modeActionState,
	normalizeLoginPolicy,
	normalizeReadiness,
	securityEndpoint,
	securityReducer,
} from '../securityCenterState.js';

const readiness = {
	target_hash: 'ready-hash',
	checks: [
		{ key: 'https', label: 'HTTPS', status: 'pass', detail: 'SSL is active.' },
		{ key: 'auth_salts', label: 'Auth salts', status: 'review', detail: 'Configure all salts.' },
	],
};

describe( 'Operations & Security client state', () => {
	it( 'builds stable Security Center REST endpoints', () => {
		expect( securityEndpoint( '/corex/v1', 'security/lockouts' ) ).toBe( '/corex/v1/security/lockouts' );
		expect( securityEndpoint( '/corex/v1/', '/security/recovery/reset-login' ) ).toBe( '/corex/v1/security/recovery/reset-login' );
	} );

	it( 'normalizes readiness blockers for the launch checklist', () => {
		const normalized = normalizeReadiness( readiness );

		expect( normalized.targetHash ).toBe( 'ready-hash' );
		expect( normalized.blockingKeys ).toEqual( [ 'auth_salts' ] );
		expect( normalized.blockingCount ).toBe( 1 );
		expect( normalized.checks[ 0 ].blocking ).toBe( false );
	} );

	it( 'requires typed PRODUCTION for launch and a checkbox for Maintenance', () => {
		let state = securityReducer( initialSecurityState(), {
			type: 'loaded',
			payload: { mode: 'staging', readiness },
		} );

		state = securityReducer( state, { type: 'selectMode', mode: 'production' } );
		expect( modeActionState( state ) ).toMatchObject( {
			requiresPhrase: true,
			requiredPhrase: 'PRODUCTION',
			ready: false,
			blockingCount: 1,
		} );

		state = securityReducer( state, { type: 'setProductionPhrase', phrase: 'PRODUCTION' } );
		expect( modeActionState( state ).ready ).toBe( true );

		state = securityReducer( state, { type: 'selectMode', mode: 'maintenance' } );
		expect( modeActionState( state ).ready ).toBe( false );
		state = securityReducer( state, { type: 'setMaintenanceConfirmed', confirmed: true } );
		expect( modeActionState( state ).ready ).toBe( true );
	} );

	it( 'normalizes and serializes login policy edits without raw credential fields', () => {
		const policy = normalizeLoginPolicy( {
			enabled: true,
			block_default_endpoints: true,
			custom_slug: 'team-login',
			max_attempts: '3',
			window_seconds: '600',
			lockout_seconds: '1800',
			trusted_proxies: [ '10.0.0.0/8' ],
			retention_days: '45',
			successful_login_logging: false,
			password: 'must-not-leak',
		} );

		expect( policy ).toMatchObject( {
			blockDefaultEndpoints: true,
			customSlug: 'team-login',
			maxAttempts: 3,
			windowSeconds: 600,
			lockoutSeconds: 1800,
			successfulLoginLogging: false,
		} );

		expect( buildLoginPolicyPayload( policy ) ).toEqual( {
			enabled: true,
			block_default_endpoints: true,
			custom_slug: 'team-login',
			max_attempts: 3,
			window_seconds: 600,
			lockout_seconds: 1800,
			trusted_proxies: [ '10.0.0.0/8' ],
			retention_days: 45,
			successful_login_logging: false,
		} );
	} );

	it( 'defaults login policy to the same values the server stores', () => {
		// These disagreed with LoginProtectionSettingsStore: `enabled` defaulted to true here and
		// false there, `windowSeconds` to 900 against 300. An unsaved form therefore described a
		// state the site was not actually in — and claimed protection was on when it was off.
		const policy = normalizeLoginPolicy( {} );

		expect( policy.enabled ).toBe( false );
		expect( policy.blockDefaultEndpoints ).toBe( false );
		expect( policy.windowSeconds ).toBe( 300 );
		expect( policy.maxAttempts ).toBe( 5 );
		expect( policy.retentionDays ).toBe( 30 );
	} );

	it( 'carries the resulting login address so the owner can be told where to sign in', () => {
		const policy = normalizeLoginPolicy( {
			enabled: true,
			custom_slug: 'team-login',
			login_url: 'https://example.test/team-login/',
		} );

		expect( policy.loginUrl ).toBe( 'https://example.test/team-login/' );
		expect( policy.slugSubstituted ).toBe( false );
	} );

	it( 'flags a stored address that had to be substituted', () => {
		// The store falls back to a working address rather than lock the owner out, but leaving
		// that silent means the screen reports protection at an address the site does not serve.
		const policy = normalizeLoginPolicy( {
			enabled: true,
			custom_slug: 'corex-login',
			stored_slug: 'ab',
			slug_substituted: true,
		} );

		expect( policy.slugSubstituted ).toBe( true );
		expect( policy.storedSlug ).toBe( 'ab' );
	} );

	it( 'does not serialise display-only fields back to the server', () => {
		const payload = buildLoginPolicyPayload(
			normalizeLoginPolicy( { enabled: true, custom_slug: 'team-login', login_url: 'https://example.test/team-login/', slug_substituted: true } )
		);

		expect( payload.login_url ).toBeUndefined();
		expect( payload.slug_substituted ).toBeUndefined();
		expect( payload.stored_slug ).toBeUndefined();
	} );

	it( 'counts a lockout as active only when it says so', () => {
		// `active` defaulted to true, so any row lacking the flag was reported as ongoing.
		const rows = [ { id: 'a', active: true }, { id: 'b', active: false }, { id: 'c' } ];

		expect( lockoutSummary( rows ) ).toEqual( { active: 1, expired: 2 } );
	} );

	it( 'keeps the account and reason a lockout was recorded with', () => {
		const state = securityReducer( initialSecurityState(), {
			type: 'loaded',
			payload: {
				lockouts: [ {
					id: 'abc-1',
					identity: 'a1b2c3d4e5f6',
					account: 'owner',
					reason: 'threshold_exceeded',
					active: true,
					locked_until: 'July 16, 2026 9:41 pm',
				} ],
			},
		} );

		expect( state.lockouts[ 0 ] ).toMatchObject( {
			identity: 'a1b2c3d4e5f6',
			account: 'owner',
			reason: 'threshold_exceeded',
			active: true,
			lockedUntil: 'July 16, 2026 9:41 pm',
		} );
	} );

	it( 'tracks lockouts, recovery result, activity, and recoverable errors', () => {
		let state = securityReducer( initialSecurityState(), {
			type: 'loaded',
			payload: {
				mode: 'production',
				lockouts: [
					{ id: 1, identity: 'owner hash', network: 'network hash', active: true },
					{ id: 2, identity: 'old hash', network: 'old network', active: false },
				],
				activity: [ { id: 5, kind: 'login.locked', label: 'Login locked', tone: 'warning' } ],
			},
		} );

		expect( lockoutSummary( state.lockouts ) ).toEqual( { active: 1, expired: 1 } );
		expect( state.activity[ 0 ].kind ).toBe( 'login.locked' );

		state = securityReducer( state, {
			type: 'recovered',
			result: { restored_login_url: 'https://example.test/wp-login.php', released_lockouts: 1 },
			message: 'Login recovery completed.',
		} );
		expect( state.recovery.released_lockouts ).toBe( 1 );
		expect( state.notice ).toEqual( { tone: 'success', message: 'Login recovery completed.' } );

		state = securityReducer( state, { type: 'error', message: 'Recovery failed.' } );
		expect( state.notice ).toEqual( { tone: 'error', message: 'Recovery failed.' } );
	} );
} );
