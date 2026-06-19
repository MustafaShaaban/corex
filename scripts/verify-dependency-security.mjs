import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
	evaluateAuditSet,
	normalizeAuditExecution,
} from './dependency-security-policy.mjs';

const repositoryRoot = path.resolve(
	path.dirname( fileURLToPath( import.meta.url ) ),
	'..'
);
const npmCommand = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const auditCommands = [
	{
		ecosystem: 'npm-root',
		command: npmCommand,
		args: [ 'audit', '--package-lock-only', '--json' ],
		cwd: repositoryRoot,
	},
	{
		ecosystem: 'npm-docs',
		command: npmCommand,
		args: [ 'audit', '--package-lock-only', '--json' ],
		cwd: path.join( repositoryRoot, 'docs-app' ),
	},
	{
		ecosystem: 'composer',
		command: 'composer',
		args: [ 'audit', '--locked', '--format=json', '--no-interaction' ],
		cwd: repositoryRoot,
	},
];

const runAudit = ( auditCommand ) => {
	const auditExecution = spawnSync( auditCommand.command, auditCommand.args, {
		cwd: auditCommand.cwd,
		encoding: 'utf8',
		shell: process.platform === 'win32',
		windowsHide: true,
	} );
	return normalizeAuditExecution( {
		ecosystem: auditCommand.ecosystem,
		exitCode: auditExecution.status,
		stdout: auditExecution.stdout || '',
		stderr: auditExecution.error?.message || auditExecution.stderr || '',
	} );
};

const unavailablePolicyStatus = ( audits, error ) => ( {
	status: 'unavailable',
	exitCode: 2,
	accepted: [],
	ecosystems: audits.map( ( audit ) => ( {
		name: audit.ecosystem,
		status: audit.status === 'ready' ? 'not-evaluated' : 'unavailable',
		findingCount: audit.findings.length,
		acceptedExceptionCount: 0,
	} ) ),
	violations: [
		{
			type: 'unavailable',
			ecosystem: 'policy',
			message: `Dependency security policy is unavailable: ${ error.message }`,
		},
	],
} );

const evaluateRepository = ( audits ) => {
	try {
		const policyPath = path.join(
			repositoryRoot,
			'.github',
			'dependency-security-policy.json'
		);
		return evaluateAuditSet(
			audits,
			JSON.parse( readFileSync( policyPath, 'utf8' ) )
		);
	} catch ( error ) {
		return unavailablePolicyStatus( audits, error );
	}
};

const writeHumanStatus = ( securityStatus ) => {
	for ( const ecosystem of securityStatus.ecosystems ) {
		process.stdout.write(
			`${ ecosystem.name }\t${ ecosystem.status.toUpperCase() }\t${
				ecosystem.findingCount
			} findings\t${
				ecosystem.acceptedExceptionCount
			} accepted exceptions\n`
		);
	}
	for ( const statusViolation of securityStatus.violations ) {
		process.stderr.write(
			`${ statusViolation.type.toUpperCase() }\t${
				statusViolation.ecosystem
			}\t${ statusViolation.advisoryId || '-' }\t${
				statusViolation.message
			}\n`
		);
	}
	process.stdout.write(
		`Dependency security: ${ securityStatus.status.toUpperCase() }\n`
	);
};

const auditResults = auditCommands.map( runAudit );
const securityStatus = evaluateRepository( auditResults );
securityStatus.generatedAt = new Date().toISOString();

if ( process.argv.includes( '--json' ) ) {
	process.stdout.write( `${ JSON.stringify( securityStatus, null, 2 ) }\n` );
} else {
	writeHumanStatus( securityStatus );
}
process.exitCode = securityStatus.exitCode;
