import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const rules = [
	{
		id: 'planned-capability',
		pattern: /\bplanned (?:future )?capabilit(?:y|ies)\b/i,
	},
	{ id: 'future-add-on', pattern: /\bfuture add-on\b/i },
	{ id: 'sample-data', pattern: /\bsample data\b/i },
	{
		id: 'code-defined-editor',
		pattern: /\b(?:editing|editor) is code-defined\b/i,
	},
	{
		id: 'disabled-required-action',
		pattern:
			/\b(?:test send|request access|editing|routing|builder) is disabled\b/i,
	},
	{
		id: 'planned-state',
		pattern: /STATE_PLANNED|__\(\s*['"]Planned['"]/,
	},
	{ id: 'reference-layout', pattern: /\breference layout\b/i },
	{ id: 'reference-only', pattern: /\breference only\b/i },
	{
		id: 'read-only-surface',
		pattern: /\bread-only (?:surface|inventory)\b/i,
	},
	{ id: 'coming-soon', pattern: /\bcoming soon\b/i },
];

const ignoredPath = ( file ) => {
	const normalized = file.replaceAll( '\\', '/' );
	return (
		normalized.startsWith( 'specs/' ) ||
		/(^|\/)(?:build|dist|node_modules|vendor|wp|test-results)(?:\/|$)/.test(
			normalized
		) ||
		/--disabled\.svg$/i.test( normalized )
	);
};

export const scanText = ( file, source ) => {
	if ( ignoredPath( file ) ) {
		return [];
	}

	const findings = [];
	for ( const [ index, lineSource ] of source.split( /\r?\n/ ).entries() ) {
		const rule = rules.find( ( candidate ) =>
			candidate.pattern.test( lineSource )
		);
		if ( rule ) {
			findings.push( {
				file: file.replaceAll( '\\', '/' ),
				line: index + 1,
				rule: rule.id,
				excerpt: lineSource.trim().slice( 0, 240 ),
			} );
		}
	}

	return findings;
};

export const summarizeFindings = ( findings ) => {
	const ordered = [ ...findings ].sort(
		( left, right ) =>
			left.rule.localeCompare( right.rule ) ||
			left.file.localeCompare( right.file ) ||
			left.line - right.line
	);
	const rulesByCount = {};
	for ( const finding of ordered ) {
		rulesByCount[ finding.rule ] =
			( rulesByCount[ finding.rule ] || 0 ) + 1;
	}

	return {
		findingCount: ordered.length,
		files: [
			...new Set( ordered.map( ( finding ) => finding.file ) ),
		].sort(),
		rules: rulesByCount,
	};
};

const sourceExtensions = new Set( [
	'.css',
	'.html',
	'.js',
	'.json',
	'.jsx',
	'.md',
	'.mjs',
	'.php',
	'.scss',
	'.ts',
	'.tsx',
] );

const collectFiles = ( root ) => {
	if ( ! existsSync( root ) ) {
		return [];
	}
	if ( statSync( root ).isFile() ) {
		return sourceExtensions.has( path.extname( root ).toLowerCase() )
			? [ root ]
			: [];
	}

	return readdirSync( root, { withFileTypes: true } ).flatMap( ( entry ) => {
		const target = path.join( root, entry.name );
		if ( ignoredPath( target ) ) {
			return [];
		}
		return collectFiles( target );
	} );
};

export const auditPaths = ( repositoryRoot, roots ) =>
	roots
		.flatMap( ( root ) =>
			collectFiles( path.join( repositoryRoot, root ) )
		)
		.flatMap( ( file ) =>
			scanText(
				path.relative( repositoryRoot, file ),
				readFileSync( file, 'utf8' )
			)
		)
		.sort(
			( left, right ) =>
				left.file.localeCompare( right.file ) || left.line - right.line
		);
