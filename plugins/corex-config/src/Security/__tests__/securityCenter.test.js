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
