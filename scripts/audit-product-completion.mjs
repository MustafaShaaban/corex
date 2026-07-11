import path from 'node:path';
import { auditPaths, summarizeFindings } from './product-completion-audit.mjs';

const defaultRoots = [
	'plugins/corex-config/src',
	'plugins/corex-config/assets',
	'plugins/corex-core/src/Admin',
	'plugins/corex-forms/src',
	'addons/corex-email/src',
	'addons/corex-kit-company/src',
	'addons/corex-ui/src',
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
