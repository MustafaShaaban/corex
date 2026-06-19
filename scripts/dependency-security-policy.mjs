const REQUIRED_ECOSYSTEMS = [ 'composer', 'npm-docs', 'npm-root' ];
const REQUIRED_EXCEPTION_FIELDS = [
	'ecosystem',
	'advisoryId',
	'package',
	'severityCeiling',
	'exposure',
	'reason',
	'control',
	'owner',
	'reviewAfter',
	'upstreamTrigger',
];
const SEVERITY_RANK = new Map( [
	[ 'unknown', -1 ],
	[ 'info', 0 ],
	[ 'low', 1 ],
	[ 'moderate', 2 ],
	[ 'high', 3 ],
	[ 'critical', 4 ],
] );
const ALLOWED_EXPOSURES = new Set( [
	'shipped-runtime',
	'ci',
	'local-dev-server',
	'build-test-transitive',
	'unreachable',
] );

const normalizeSeverity = ( rawSeverity ) =>
	String( rawSeverity || 'unknown' ).toLowerCase();
const policyKey = ( policyEntry ) =>
	`${ policyEntry.ecosystem }:${ policyEntry.advisoryId }`;

const advisoryIdFromUrl = ( url, fallback ) => {
	const match =
		typeof url === 'string' ? url.match( /(GHSA-[a-z0-9-]+)$/i ) : null;
	return match ? match[ 1 ] : String( fallback );
};

const sortedFindings = ( findings ) =>
	[ ...findings ].sort(
		( left, right ) =>
			left.ecosystem.localeCompare( right.ecosystem ) ||
			left.advisoryId.localeCompare( right.advisoryId ) ||
			left.package.localeCompare( right.package )
	);

const npmFinding = ( advisory, vulnerability, packageName, ecosystem ) => ( {
	ecosystem,
	advisoryId: advisoryIdFromUrl( advisory.url, advisory.source ),
	package: advisory.name || vulnerability.name || packageName,
	severity: normalizeSeverity( advisory.severity || vulnerability.severity ),
	paths: [ ...( vulnerability.nodes || [] ) ].sort(),
	range: advisory.range || vulnerability.range || '',
	url: advisory.url || '',
} );

export function normalizeNpmAudit( payload, ecosystem ) {
	const findings = Object.entries( payload?.vulnerabilities || {} ).flatMap(
		( [ packageName, vulnerability ] ) =>
			( Array.isArray( vulnerability.via ) ? vulnerability.via : [] )
				.filter(
					( advisory ) => advisory && typeof advisory === 'object'
				)
				.map( ( advisory ) =>
					npmFinding(
						advisory,
						vulnerability,
						packageName,
						ecosystem
					)
				)
	);
	return sortedFindings( findings );
}

const composerFinding = ( advisory, packageName ) => {
	const resolvedPackage = advisory.packageName || packageName;
	return {
		ecosystem: 'composer',
		advisoryId: String( advisory.advisoryId ),
		package: resolvedPackage,
		severity: normalizeSeverity( advisory.severity ),
		paths: [ resolvedPackage ],
		range: advisory.affectedVersions || '',
		url: advisory.link || '',
	};
};

export function normalizeComposerAudit( payload ) {
	const advisories = payload?.advisories;
	if ( ! advisories || Array.isArray( advisories ) ) {
		return [];
	}
	const findings = Object.entries( advisories ).flatMap(
		( [ packageName, packageAdvisories ] ) =>
			( packageAdvisories || [] ).map( ( advisory ) =>
				composerFinding( advisory, packageName )
			)
	);
	return sortedFindings( findings );
}

const policyViolation = ( type, finding, message ) => ( {
	type,
	ecosystem: finding.ecosystem,
	advisoryId: finding.advisoryId,
	package: finding.package,
	message,
} );

const missingExceptionFields = ( policyEntry ) => {
	const missing = REQUIRED_EXCEPTION_FIELDS.filter(
		( field ) =>
			typeof policyEntry[ field ] !== 'string' ||
			policyEntry[ field ].trim() === ''
	);
	const pathsAreValid =
		Array.isArray( policyEntry.paths ) &&
		policyEntry.paths.length > 0 &&
		policyEntry.paths.every(
			( dependencyPath ) =>
				typeof dependencyPath === 'string' &&
				dependencyPath.trim() !== ''
		);
	return pathsAreValid ? missing : [ ...missing, 'paths' ];
};

const isValidIsoDate = ( isoDate ) => {
	if ( ! /^\d{4}-\d{2}-\d{2}$/.test( isoDate || '' ) ) {
		return false;
	}
	const parsedDate = new Date( `${ isoDate }T00:00:00Z` );
	return (
		! Number.isNaN( parsedDate.valueOf() ) &&
		parsedDate.toISOString().slice( 0, 10 ) === isoDate
	);
};

const exceptionValidationMessage = ( policyEntry, seenKeys ) => {
	const missing = missingExceptionFields( policyEntry );
	if ( missing.length > 0 ) {
		return `Missing required fields: ${ missing.join( ', ' ) }.`;
	}
	if ( ! isValidIsoDate( policyEntry.reviewAfter ) ) {
		return 'reviewAfter must be a valid ISO date.';
	}
	if (
		! SEVERITY_RANK.has( normalizeSeverity( policyEntry.severityCeiling ) )
	) {
		return 'severityCeiling must be a recognized severity.';
	}
	if ( ! ALLOWED_EXPOSURES.has( policyEntry.exposure ) ) {
		return 'exposure must be a recognized exposure class.';
	}
	return seenKeys.has( policyKey( policyEntry ) )
		? 'Duplicate policy exception key.'
		: null;
};

const invalidExceptionViolation = ( policyEntry, message ) => ( {
	type: 'invalid-exception',
	ecosystem: policyEntry.ecosystem || 'policy',
	advisoryId: policyEntry.advisoryId || '-',
	package: policyEntry.package || '-',
	message,
} );

const policyStructureViolations = ( policy ) => {
	if ( policy?.version !== 1 || ! Array.isArray( policy?.exceptions ) ) {
		return [
			invalidExceptionViolation(
				{},
				'Policy must use version 1 and an exceptions array.'
			),
		];
	}
	const seenKeys = new Set();
	return policy.exceptions.flatMap( ( policyEntry ) => {
		const message = exceptionValidationMessage( policyEntry, seenKeys );
		seenKeys.add( policyKey( policyEntry ) );
		return message
			? [ invalidExceptionViolation( policyEntry, message ) ]
			: [];
	} );
};

const pathsMatch = ( finding, policyEntry ) =>
	JSON.stringify( [ ...finding.paths ].sort() ) ===
	JSON.stringify( [ ...policyEntry.paths ].sort() );

const metadataMatches = ( finding, policyEntry ) => {
	const severityExceedsCeiling =
		SEVERITY_RANK.get( normalizeSeverity( finding.severity ) ) >
		SEVERITY_RANK.get( normalizeSeverity( policyEntry.severityCeiling ) );
	return (
		policyEntry.package === finding.package &&
		pathsMatch( finding, policyEntry ) &&
		! severityExceedsCeiling
	);
};

const isForbiddenException = ( finding, policyEntry ) => {
	const isHighRisk =
		SEVERITY_RANK.get( normalizeSeverity( finding.severity ) ) >=
		SEVERITY_RANK.get( 'high' );
	return (
		isHighRisk &&
		[ 'shipped-runtime', 'ci' ].includes( policyEntry.exposure )
	);
};

const findingPolicyViolation = ( finding, policyEntry, currentDate ) => {
	if ( ! policyEntry ) {
		return policyViolation(
			'unbounded',
			finding,
			'No exact policy exception exists.'
		);
	}
	if ( policyEntry.reviewAfter < currentDate ) {
		return policyViolation(
			'expired',
			finding,
			'Exception review date has passed.'
		);
	}
	if ( ! metadataMatches( finding, policyEntry ) ) {
		return policyViolation(
			'metadata-mismatch',
			finding,
			'Package, dependency path, or severity no longer matches policy.'
		);
	}
	if ( isForbiddenException( finding, policyEntry ) ) {
		return policyViolation(
			'forbidden',
			finding,
			'High or critical runtime/CI exposure cannot be excepted.'
		);
	}
	return null;
};

const evaluateFindings = ( findings, exceptionMap, currentDate ) => {
	const accepted = [];
	const violations = [];
	const usedExceptionKeys = new Set();
	for ( const finding of sortedFindings( findings ) ) {
		const key = policyKey( finding );
		const policyEntry = exceptionMap.get( key );
		const findingViolation = findingPolicyViolation(
			finding,
			policyEntry,
			currentDate
		);
		if ( policyEntry ) {
			usedExceptionKeys.add( key );
		}
		if ( findingViolation ) {
			violations.push( findingViolation );
		} else {
			accepted.push( { finding, exception: policyEntry } );
		}
	}
	return { accepted, violations, usedExceptionKeys };
};

const staleExceptionViolations = ( exceptionMap, usedExceptionKeys ) =>
	[ ...exceptionMap.entries() ].flatMap( ( [ key, policyEntry ] ) =>
		usedExceptionKeys.has( key )
			? []
			: [
					policyViolation(
						'stale',
						policyEntry,
						'Policy exception has no matching current advisory.'
					),
			  ]
	);

export function evaluatePolicy(
	findings,
	policy,
	{ now = new Date().toISOString().slice( 0, 10 ) } = {}
) {
	const structureViolations = policyStructureViolations( policy );
	if ( structureViolations.length > 0 ) {
		return {
			status: 'fail',
			accepted: [],
			violations: structureViolations,
		};
	}
	const exceptionMap = new Map(
		policy.exceptions.map( ( policyEntry ) => [
			policyKey( policyEntry ),
			policyEntry,
		] )
	);
	const policyEvaluation = evaluateFindings( findings, exceptionMap, now );
	policyEvaluation.violations.push(
		...staleExceptionViolations(
			exceptionMap,
			policyEvaluation.usedExceptionKeys
		)
	);
	return {
		status: policyEvaluation.violations.length === 0 ? 'pass' : 'fail',
		accepted: policyEvaluation.accepted,
		violations: policyEvaluation.violations,
	};
}

const unavailableAudit = ( ecosystem, exitCode, error ) => ( {
	ecosystem,
	status: 'unavailable',
	exitCode,
	findings: [],
	error,
} );

const auditPayloadMatches = ( ecosystem, payload ) =>
	ecosystem === 'composer'
		? Object.prototype.hasOwnProperty.call( payload, 'advisories' )
		: payload?.vulnerabilities &&
		  typeof payload.vulnerabilities === 'object';

const normalizedAuditFindings = ( ecosystem, payload ) =>
	ecosystem === 'composer'
		? normalizeComposerAudit( payload )
		: normalizeNpmAudit( payload, ecosystem );

const parseAuditPayload = ( stdout ) => {
	try {
		return { payload: JSON.parse( stdout ), error: null };
	} catch {
		return {
			payload: null,
			error: 'Audit command did not return valid JSON.',
		};
	}
};

export function normalizeAuditExecution( {
	ecosystem,
	exitCode,
	stdout,
	stderr,
} ) {
	const allowedExitCodes =
		ecosystem === 'composer' ? [ 0, 1, 2, 3 ] : [ 0, 1 ];
	if ( ! allowedExitCodes.includes( exitCode ) ) {
		return unavailableAudit(
			ecosystem,
			exitCode,
			String(
				stderr || `Audit command exited with code ${ exitCode }.`
			).trim()
		);
	}
	const parsedAudit = parseAuditPayload( stdout );
	if ( parsedAudit.error ) {
		return unavailableAudit( ecosystem, exitCode, parsedAudit.error );
	}
	const { payload } = parsedAudit;
	if ( ! auditPayloadMatches( ecosystem, payload ) ) {
		return unavailableAudit(
			ecosystem,
			exitCode,
			'Audit JSON does not match the expected report shape.'
		);
	}
	return {
		ecosystem,
		status: 'ready',
		exitCode,
		findings: normalizedAuditFindings( ecosystem, payload ),
	};
}

const requiredAuditResults = ( audits ) => {
	const auditMap = new Map(
		audits.map( ( audit ) => [ audit.ecosystem, audit ] )
	);
	return REQUIRED_ECOSYSTEMS.map(
		( ecosystem ) =>
			auditMap.get( ecosystem ) || {
				ecosystem,
				status: 'unavailable',
				findings: [],
				error: 'Required audit result is missing.',
			}
	);
};

const unavailableAuditSet = ( audits ) => ( {
	status: 'unavailable',
	exitCode: 2,
	accepted: [],
	ecosystems: audits.map( ( audit ) => ( {
		name: audit.ecosystem,
		status: audit.status === 'ready' ? 'not-evaluated' : 'unavailable',
		findingCount: audit.findings.length,
		acceptedExceptionCount: 0,
	} ) ),
	violations: audits
		.filter( ( audit ) => audit.status !== 'ready' )
		.map( ( audit ) => ( {
			type: 'unavailable',
			ecosystem: audit.ecosystem,
			message: audit.error,
		} ) ),
} );

const ecosystemEvaluations = ( audits, policyEvaluation ) =>
	audits.map( ( audit ) => ( {
		name: audit.ecosystem,
		status: policyEvaluation.violations.some(
			( policyViolationEntry ) =>
				policyViolationEntry.ecosystem === audit.ecosystem
		)
			? 'fail'
			: 'pass',
		findingCount: audit.findings.length,
		acceptedExceptionCount: policyEvaluation.accepted.filter(
			( acceptedException ) =>
				acceptedException.finding.ecosystem === audit.ecosystem
		).length,
	} ) );

export function evaluateAuditSet( audits, policy ) {
	const requiredAudits = requiredAuditResults( audits );
	if ( requiredAudits.some( ( audit ) => audit.status !== 'ready' ) ) {
		return unavailableAuditSet( requiredAudits );
	}
	const findings = requiredAudits.flatMap( ( audit ) => audit.findings );
	const policyEvaluation = evaluatePolicy( findings, policy );
	return {
		...policyEvaluation,
		ecosystems: ecosystemEvaluations( requiredAudits, policyEvaluation ),
		exitCode: policyEvaluation.status === 'pass' ? 0 : 1,
	};
}
