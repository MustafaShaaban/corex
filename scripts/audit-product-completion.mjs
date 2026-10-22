import path from 'node:path';
import { auditPaths, summarizeFindings } from './product-completion-audit.mjs';

// Everything CoreX ships, and the docs site (spec 116). A list of folders meant a screen in a
// folder nobody listed, Guides among them, could say "Planned" unseen.
const defaultRoots = [
	'plugins',
	'addons',
	'packages',
	'theme',
	'docs-app/src',
];
const repositoryRoot = path.resolve( process.cwd() );
const findings = auditPaths( repositoryRoot, defaultRoots );
const summary = summarizeFindings( findings );

if ( process.argv.includes( '--json' ) ) {
	process.stdout.write(
		`${ JSON.stringify( { summary, findings }, null, 2 ) }\n`
	);
} else {
	for ( const finding of findings ) {
		process.stdout.write(
			`${ finding.file }:${ finding.line }\t${ finding.rule }\t${ finding.excerpt }\n`
		);
	}
	process.stdout.write(
		`Product completion audit: ${ summary.findingCount } findings in ${ summary.files.length } files.\n`
	);
}

process.exitCode = findings.length === 0 ? 0 : 1;
