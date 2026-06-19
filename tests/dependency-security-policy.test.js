const fs = require( 'node:fs' );
const path = require( 'node:path' );

const fixture = ( name ) =>
	JSON.parse(
		fs.readFileSync(
			path.join( __dirname, 'Fixtures', 'dependency-security', name ),
			'utf8'
		)
	);

const {
	normalizeNpmAudit,
	normalizeComposerAudit,
	evaluatePolicy,
	normalizeAuditExecution,
	evaluateAuditSet,
} = require( '../scripts/dependency-security-policy.mjs' );

const npmFinding = () =>
	normalizeNpmAudit( fixture( 'npm-audit.json' ), 'npm-root' )[ 0 ];

const exceptionFor = ( finding, overrides = {} ) => ( {
	ecosystem: finding.ecosystem,
	advisoryId: finding.advisoryId,
	package: finding.package,
	paths: finding.paths,
	severityCeiling: finding.severity,
	exposure: 'build-test-transitive',
	reason: 'Only reached by repository-controlled lint input.',
	control: 'Do not process untrusted files in this toolchain.',
	owner: 'Corex maintainers',
	reviewAfter: '2026-12-31',
	upstreamTrigger: 'Remove after the parent toolchain resolves the advisory.',
	...overrides,
} );

describe( 'dependency security audit normalization', () => {
	test( 'normalizes direct npm advisory objects and ignores wrapper vulnerability nodes', () => {
		expect(
			normalizeNpmAudit( fixture( 'npm-audit.json' ), 'npm-root' )
		).toEqual( [
			{
				ecosystem: 'npm-root',
				advisoryId: 'GHSA-3ppc-4f35-3m26',
				package: 'minimatch',
				severity: 'high',
				paths: [
					'node_modules/markdownlint-cli/node_modules/minimatch',
				],
				range: '<3.1.3',
				url: 'https://github.com/advisories/GHSA-3ppc-4f35-3m26',
			},
		] );
	} );

	test( 'normalizes Composer advisories with stable advisory identity', () => {
		expect(
			normalizeComposerAudit( fixture( 'composer-audit.json' ) )
		).toEqual( [
			{
				ecosystem: 'composer',
				advisoryId: 'PKSA-1111-2222-3333',
				package: 'vendor/package',
				severity: 'moderate',
				paths: [ 'vendor/package' ],
				range: '<1.2.3',
				url: 'https://github.com/advisories/GHSA-aaaa-bbbb-cccc',
			},
		] );
	} );

	test( 'sorts findings deterministically by ecosystem and advisory identity', () => {
		const payload = fixture( 'npm-audit.json' );
		payload.vulnerabilities.zeta = {
			...payload.vulnerabilities.minimatch,
			name: 'zeta',
			via: [
				{
					...payload.vulnerabilities.minimatch.via[ 0 ],
					name: 'zeta',
					url: 'https://github.com/advisories/GHSA-zzzz-yyyy-xxxx',
				},
			],
		};

		expect(
			normalizeNpmAudit( payload, 'npm-root' ).map(
				( item ) => item.advisoryId
			)
		).toEqual( [ 'GHSA-3ppc-4f35-3m26', 'GHSA-zzzz-yyyy-xxxx' ] );
	} );
} );

describe( 'dependency security policy evaluation', () => {
	test( 'passes a clean audit with an empty policy', () => {
		expect(
			evaluatePolicy( [], { version: 1, exceptions: [] } )
		).toMatchObject( {
			status: 'pass',
			accepted: [],
			violations: [],
		} );
	} );

	test( 'rejects an advisory that has no exact policy entry', () => {
		const finding = npmFinding();
		const result = evaluatePolicy( [ finding ], {
			version: 1,
			exceptions: [],
		} );

		expect( result.status ).toBe( 'fail' );
		expect( result.violations ).toEqual( [
			expect.objectContaining( {
				type: 'unbounded',
				ecosystem: 'npm-root',
				advisoryId: finding.advisoryId,
			} ),
		] );
	} );

	test.each( [
		{ label: 'package', overrides: { package: 'different-package' } },
		{ label: 'severity', overrides: { severityCeiling: 'moderate' } },
	] )(
		'rejects $label metadata that no longer matches policy',
		( { overrides } ) => {
			const finding = npmFinding();
			const result = evaluatePolicy( [ finding ], {
				version: 1,
				exceptions: [ exceptionFor( finding, overrides ) ],
			} );

			expect( result.violations ).toEqual( [
				expect.objectContaining( { type: 'metadata-mismatch' } ),
			] );
		}
	);

	test.each( [
		{ label: 'non-string dependency path', overrides: { paths: [ 42 ] } },
		{
			label: 'unknown severity ceiling',
			overrides: { severityCeiling: 'urgent' },
		},
		{
			label: 'unknown exposure class',
			overrides: { exposure: 'external-tool' },
		},
		{
			label: 'impossible review date',
			overrides: { reviewAfter: '2026-02-31' },
		},
	] )(
		'rejects structurally invalid exception metadata: $label',
		( { overrides } ) => {
			const finding = npmFinding();
			const policyEntry = exceptionFor( finding, overrides );
			const policyEvaluation = evaluatePolicy(
				[ finding ],
				{ version: 1, exceptions: [ policyEntry ] },
				{ now: '2026-06-19' }
			);

			expect( policyEvaluation.violations ).toEqual( [
				expect.objectContaining( { type: 'invalid-exception' } ),
			] );
		}
	);

	test( 'rejects a dependency path that no longer matches policy', () => {
		const finding = npmFinding();
		const result = evaluatePolicy( [ finding ], {
			version: 1,
			exceptions: [
				exceptionFor( finding, {
					paths: [ 'node_modules/different-path' ],
				} ),
			],
		} );

		expect( result.violations ).toEqual( [
			expect.objectContaining( { type: 'metadata-mismatch' } ),
		] );
	} );

	test.each( [ 'shipped-runtime', 'ci' ] )(
		'rejects a high advisory excepted as %s exposure',
		( exposure ) => {
			const finding = npmFinding();
			const result = evaluatePolicy( [ finding ], {
				version: 1,
				exceptions: [ exceptionFor( finding, { exposure } ) ],
			} );

			expect( result.violations ).toEqual( [
				expect.objectContaining( { type: 'forbidden' } ),
			] );
		}
	);

	test( 'accepts a complete and current development-tool exception', () => {
		const finding = npmFinding();
		const result = evaluatePolicy(
			[ finding ],
			{ version: 1, exceptions: [ exceptionFor( finding ) ] },
			{ now: '2026-06-19' }
		);

		expect( result ).toMatchObject( { status: 'pass', violations: [] } );
		expect( result.accepted ).toHaveLength( 1 );
	} );

	test.each( [
		'ecosystem',
		'advisoryId',
		'package',
		'paths',
		'severityCeiling',
		'exposure',
		'reason',
		'control',
		'owner',
		'reviewAfter',
		'upstreamTrigger',
	] )( 'rejects an exception missing required field %s', ( field ) => {
		const finding = npmFinding();
		const entry = exceptionFor( finding );
		delete entry[ field ];

		const result = evaluatePolicy(
			[ finding ],
			{ version: 1, exceptions: [ entry ] },
			{ now: '2026-06-19' }
		);

		expect( result.violations ).toEqual( [
			expect.objectContaining( { type: 'invalid-exception' } ),
		] );
	} );

	test( 'rejects an exception after its review date', () => {
		const finding = npmFinding();
		const result = evaluatePolicy(
			[ finding ],
			{
				version: 1,
				exceptions: [
					exceptionFor( finding, { reviewAfter: '2026-06-18' } ),
				],
			},
			{ now: '2026-06-19' }
		);

		expect( result.violations ).toEqual( [
			expect.objectContaining( { type: 'expired' } ),
		] );
	} );

	test( 'rejects a stale exception after its advisory disappears', () => {
		const finding = npmFinding();
		const result = evaluatePolicy(
			[],
			{ version: 1, exceptions: [ exceptionFor( finding ) ] },
			{ now: '2026-06-19' }
		);

		expect( result.violations ).toEqual( [
			expect.objectContaining( {
				type: 'stale',
				ecosystem: finding.ecosystem,
				advisoryId: finding.advisoryId,
			} ),
		] );
	} );
} );

describe( 'dependency security command boundary', () => {
	test( 'accepts npm advisory exit code 1 when stdout is valid audit JSON', () => {
		const audit = normalizeAuditExecution( {
			ecosystem: 'npm-root',
			exitCode: 1,
			stdout: JSON.stringify( fixture( 'npm-audit.json' ) ),
			stderr: '',
		} );

		expect( audit ).toMatchObject( {
			ecosystem: 'npm-root',
			status: 'ready',
			exitCode: 1,
		} );
		expect( audit.findings ).toHaveLength( 1 );
	} );

	test( 'reports malformed audit output as unavailable', () => {
		expect(
			normalizeAuditExecution( {
				ecosystem: 'npm-docs',
				exitCode: 1,
				stdout: 'registry gateway error',
				stderr: '',
			} )
		).toMatchObject( {
			status: 'unavailable',
			error: expect.stringContaining( 'valid JSON' ),
		} );
	} );

	test( 'returns exit code 2 when any required audit is unavailable', () => {
		const result = evaluateAuditSet(
			[
				{ ecosystem: 'npm-root', status: 'ready', findings: [] },
				{
					ecosystem: 'npm-docs',
					status: 'unavailable',
					findings: [],
					error: 'timeout',
				},
				{ ecosystem: 'composer', status: 'ready', findings: [] },
			],
			{ version: 1, exceptions: [] }
		);

		expect( result ).toMatchObject( {
			status: 'unavailable',
			exitCode: 2,
		} );
	} );

	test( 'returns exit code 1 for policy violations', () => {
		const finding = npmFinding();
		const cleanAudits = [
			{ ecosystem: 'npm-root', status: 'ready', findings: [] },
			{ ecosystem: 'npm-docs', status: 'ready', findings: [] },
			{ ecosystem: 'composer', status: 'ready', findings: [] },
		];

		expect(
			evaluateAuditSet(
				[
					{ ...cleanAudits[ 0 ], findings: [ finding ] },
					...cleanAudits.slice( 1 ),
				],
				{ version: 1, exceptions: [] }
			)
		).toMatchObject( { status: 'fail', exitCode: 1 } );
	} );

	test( 'returns exit code 0 with per-ecosystem summaries for a clean audit set', () => {
		const cleanAudits = [
			{ ecosystem: 'npm-root', status: 'ready', findings: [] },
			{ ecosystem: 'npm-docs', status: 'ready', findings: [] },
			{ ecosystem: 'composer', status: 'ready', findings: [] },
		];
		expect(
			evaluateAuditSet( cleanAudits, { version: 1, exceptions: [] } )
		).toMatchObject( {
			status: 'pass',
			exitCode: 0,
			ecosystems: [
				{
					name: 'composer',
					status: 'pass',
					findingCount: 0,
					acceptedExceptionCount: 0,
				},
				{
					name: 'npm-docs',
					status: 'pass',
					findingCount: 0,
					acceptedExceptionCount: 0,
				},
				{
					name: 'npm-root',
					status: 'pass',
					findingCount: 0,
					acceptedExceptionCount: 0,
				},
			],
		} );
	} );
} );
