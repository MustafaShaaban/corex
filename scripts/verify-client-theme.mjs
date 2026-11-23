/**
 * `npm run verify:client-theme`: does every client theme that is a child of the CoreX theme keep
 * the CoreX theme's entries in the lists it declares? (spec 135, FR-014; DECISIONS #660)
 *
 * WordPress merges a child theme's `theme.json` into the parent's key by key, except a preset list:
 * a palette, a font family list, a font size list, a spacing list or a shadow list the child
 * declares replaces the parent's whole list. `customTemplates` and `templateParts` merge by
 * position. So a list the child declares has to name every entry of the CoreX theme's, or the site
 * silently loses a colour, a size or a template's title. A list the child does not declare is
 * inherited whole, and is fine.
 *
 * For every client theme under `sites/` (the flat `sites/<client>/*-theme/` and the older
 * `sites/<client>/themes/*`) whose `style.css` names `Template: corex`, this reads its `theme.json`
 * and each `styles/*.json`, and reports one line per entry of the CoreX theme's (`theme/theme.json`)
 * that a declared list lacks, and per `file:./` font source that exists in neither theme. A theme
 * with no `Template:` line is reported as standalone and not checked. It needs Node and nothing
 * else: no install, and no git.
 *
 *   node scripts/verify-client-theme.mjs          check, one line per finding
 *   node scripts/verify-client-theme.mjs --json   the same result as one JSON object
 *
 * Run from the repository root. The checks are exported for the tests; the command at the bottom
 * runs only when this file is the script Node was started with.
 */
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

/** Every preset list WordPress replaces whole when a child declares it. */
export const PRESET_LISTS = [
	[ 'color', 'palette' ],
	[ 'color', 'gradients' ],
	[ 'color', 'duotone' ],
	[ 'typography', 'fontFamilies' ],
	[ 'typography', 'fontSizes' ],
	[ 'spacing', 'spacingSizes' ],
	[ 'shadow', 'presets' ],
];

/** The top-level lists WordPress merges by position; an entry of each is named by its `name`. */
export const POSITIONAL_LISTS = [ 'customTemplates', 'templateParts' ];

/** Where the CoreX theme is, from the repository root. */
export const PARENT_DIR = 'theme';

const PARENT_SLUG = 'corex';
const FONT_SOURCE = /^file:\.\//;

/**
 * The `Template:` a theme's `style.css` header names, read the way WordPress reads a header.
 *
 * @param {string} styleCss The stylesheet's contents.
 * @return {string|null} The parent's directory name, or null when the header names none.
 */
export function templateOf( styleCss ) {
	const found = /^[ \t/*#@]*Template:(.*)$/im.exec(
		styleCss.slice( 0, 8192 )
	);

	if ( ! found ) {
		return null;
	}

	const value = found[ 1 ].replace( /\s*(?:\*\/|\?>).*$/, '' ).trim();

	return value === '' ? null : value;
}

const listAt = ( json, group, list ) => {
	const value = json?.settings?.[ group ]?.[ list ];

	return Array.isArray( value ) ? value : null;
};

const keysOf = ( entries, key ) =>
	entries
		.map( ( entry ) => entry?.[ key ] )
		.filter( ( value ) => typeof value === 'string' );

const missingFrom = ( theirs, ours, key ) => {
	const have = new Set( keysOf( ours, key ) );

	return keysOf( theirs, key ).filter( ( name ) => ! have.has( name ) );
};

/**
 * The parent's slugs a child's declared preset lists lack.
 *
 * @param {string} file   The child's file, as it is reported.
 * @param {Object} parent The CoreX theme's decoded `theme.json`.
 * @param {Object} child  The decoded file: a `theme.json` or a style variation.
 * @return {Object[]} One finding per missing slug.
 */
export function presetFindings( file, parent, child ) {
	return PRESET_LISTS.flatMap( ( [ group, list ] ) => {
		const ours = listAt( child, group, list );
		const theirs = listAt( parent, group, list );

		if ( ours === null || theirs === null ) {
			return [];
		}

		return missingFrom( theirs, ours, 'slug' ).map( ( entry ) => ( {
			file,
			list: `settings.${ group }.${ list }`,
			entry,
		} ) );
	} );
}

/**
 * The parent's custom templates and template parts a child's declared lists lack.
 *
 * @param {string} file   The child's `theme.json`, as it is reported.
 * @param {Object} parent The CoreX theme's decoded `theme.json`.
 * @param {Object} child  The child's decoded `theme.json`.
 * @return {Object[]} One finding per missing entry.
 */
export function positionalFindings( file, parent, child ) {
	return POSITIONAL_LISTS.flatMap( ( list ) => {
		if (
			! Array.isArray( child?.[ list ] ) ||
			! Array.isArray( parent?.[ list ] )
		) {
			return [];
		}

		return missingFrom( parent[ list ], child[ list ], 'name' ).map(
			( entry ) => ( { file, list, entry } )
		);
	} );
}

const fontSources = ( json ) =>
	( listAt( json, 'typography', 'fontFamilies' ) ?? [] ).flatMap(
		( family ) =>
			( Array.isArray( family?.fontFace )
				? family.fontFace
				: []
			).flatMap( ( face ) => [ face?.src ].flat() )
	);

/**
 * The `file:./` font sources a child lists that exist in neither the child nor the CoreX theme.
 *
 * WordPress serves a source the child does not hold from the parent's folder, so a source either
 * theme holds is fine.
 *
 * @param {string}                    file       The child's file, as it is reported.
 * @param {string}                    themeDir   The child's directory, from the repository root.
 * @param {Object}                    child      The decoded file.
 * @param {(file: string) => boolean} fileExists Called with a path from the repository root; true when it is a file.
 * @return {Object[]} One finding per source found nowhere.
 */
export function fontFindings( file, themeDir, child, fileExists ) {
	return [
		...new Set(
			fontSources( child ).filter(
				( source ) =>
					typeof source === 'string' && FONT_SOURCE.test( source )
			)
		),
	]
		.filter( ( source ) => {
			const relative = source.replace( FONT_SOURCE, '' );

			return (
				! fileExists( `${ themeDir }/${ relative }` ) &&
				! fileExists( `${ PARENT_DIR }/${ relative }` )
			);
		} )
		.map( ( entry ) => ( { file, list: 'font', entry } ) );
}

/**
 * Every finding for one child theme.
 *
 * @param {Object}                    theme            The child.
 * @param {string}                    theme.dir        Its directory, from the repository root.
 * @param {Object}                    theme.parent     The CoreX theme's decoded `theme.json`.
 * @param {?Object}                   theme.themeJson  Its decoded `theme.json`, or null when it has none.
 * @param {Object[]}                  theme.variations Its style variations: `{ file, json }`, file from the root.
 * @param {(file: string) => boolean} theme.fileExists Called with a path from the repository root.
 * @return {Object[]} The findings, in file order.
 */
export function childThemeFindings( {
	dir,
	parent,
	themeJson,
	variations,
	fileExists,
} ) {
	const files = [
		...( themeJson === null
			? []
			: [ { file: `${ dir }/theme.json`, json: themeJson, own: true } ] ),
		...variations.map( ( variation ) => ( { ...variation, own: false } ) ),
	];

	return files.flatMap( ( { file, json, own } ) => [
		...presetFindings( file, parent, json ),
		...( own ? positionalFindings( file, parent, json ) : [] ),
		...fontFindings( file, dir, json, fileExists ),
	] );
}

class UnreadableFile extends Error {}

const readJson = ( root, file ) => {
	let contents;

	try {
		contents = readFileSync( path.join( root, file ), 'utf8' );
	} catch {
		throw new UnreadableFile( `${ file }: could not be read` );
	}

	try {
		return JSON.parse( contents );
	} catch {
		throw new UnreadableFile( `${ file }: not valid JSON` );
	}
};

const isDirectory = ( absolute ) =>
	existsSync( absolute ) && statSync( absolute ).isDirectory();

const directoriesIn = ( absolute ) =>
	isDirectory( absolute )
		? readdirSync( absolute )
				.filter( ( entry ) =>
					isDirectory( path.join( absolute, entry ) )
				)
				.sort()
		: [];

/**
 * Every client theme under `sites/`, in both layouts the shared-host builder packages.
 *
 * @param {string} root The repository root.
 * @return {string[]} Each theme's directory from the root, for each folder that holds a `style.css`.
 */
export function clientThemeDirs( root ) {
	return directoriesIn( path.join( root, 'sites' ) )
		.flatMap( ( client ) => [
			...directoriesIn( path.join( root, 'sites', client ) )
				.filter( ( entry ) => entry.endsWith( '-theme' ) )
				.map( ( entry ) => `sites/${ client }/${ entry }` ),
			...directoriesIn(
				path.join( root, 'sites', client, 'themes' )
			).map( ( entry ) => `sites/${ client }/themes/${ entry }` ),
		] )
		.filter( ( dir ) => existsSync( path.join( root, dir, 'style.css' ) ) );
}

/**
 * Every style variation of a theme: each `.json` under `styles/`, at any depth, as WordPress reads them.
 *
 * @param {string} root The repository.
 * @param {string} dir  The theme, repository-relative.
 * @return {{file: string, json: Object}[]} The variations, by path.
 */
const variationsOf = ( root, dir ) => {
	const styles = path.join( root, dir, 'styles' );

	if ( ! isDirectory( styles ) ) {
		return [];
	}

	return readdirSync( styles, { recursive: true } )
		.map( ( entry ) => String( entry ).split( path.sep ).join( '/' ) )
		.filter( ( entry ) => entry.endsWith( '.json' ) )
		.sort()
		.map( ( entry ) => {
			const file = `${ dir }/styles/${ entry }`;

			return { file, json: readJson( root, file ) };
		} );
};

const checkOne = ( root, dir, parentOf, fileExists ) => {
	const template = templateOf(
		readFileSync( path.join( root, dir, 'style.css' ), 'utf8' )
	);

	if ( template === null ) {
		return { theme: { dir, kind: 'standalone' }, findings: [] };
	}

	// Matched as WordPress matches a theme's directory: exactly, so `Corex` is not the CoreX theme.
	if ( template !== PARENT_SLUG ) {
		return { theme: { dir, kind: 'other', template }, findings: [] };
	}

	const hasThemeJson = existsSync( path.join( root, dir, 'theme.json' ) );

	return {
		theme: { dir, kind: 'child', template },
		findings: childThemeFindings( {
			dir,
			parent: parentOf(),
			themeJson: hasThemeJson
				? readJson( root, `${ dir }/theme.json` )
				: null,
			variations: variationsOf( root, dir ),
			fileExists,
		} ),
	};
};

/**
 * Check every client theme in a repository.
 *
 * @param {string} root The repository root.
 * @return {Object} `{ status, themes, missing, failures }`.
 */
export function verifyClientThemes( root ) {
	const fileExists = ( file ) => {
		const absolute = path.join( root, file );

		return existsSync( absolute ) && statSync( absolute ).isFile();
	};
	let parent = null;
	// Read once, and only when there is a child to compare.
	const parentOf = () =>
		( parent ??= readJson( root, `${ PARENT_DIR }/theme.json` ) );
	const themes = [];
	const missing = [];
	const failures = [];

	for ( const dir of clientThemeDirs( root ) ) {
		try {
			const { theme, findings } = checkOne(
				root,
				dir,
				parentOf,
				fileExists
			);

			themes.push( theme );
			missing.push( ...findings );
		} catch ( error ) {
			if ( ! ( error instanceof UnreadableFile ) ) {
				throw error;
			}

			themes.push( { dir, kind: 'unchecked' } );
			failures.push( error.message );
		}
	}

	return {
		status: missing.length === 0 && failures.length === 0 ? 'pass' : 'fail',
		themes,
		missing,
		// The CoreX theme unreadable is one failure, however many children needed it.
		failures: [ ...new Set( failures ) ],
	};
}

/**
 * The result as lines, one per finding, then a summary.
 *
 * @param {Object} result What verifyClientThemes() returned.
 * @return {string[]} The lines.
 */
export function report( result ) {
	const children = result.themes.filter(
		( theme ) => theme.kind === 'child'
	);
	const lines = [
		...result.failures.map( ( failure ) => `FAIL\t${ failure }` ),
		...result.themes
			.filter( ( theme ) => theme.kind === 'standalone' )
			.map(
				( theme ) =>
					`STANDALONE\t${ theme.dir }\tno Template: line, not a child of the CoreX theme; not checked`
			),
		...result.themes
			.filter( ( theme ) => theme.kind === 'other' )
			.map(
				( theme ) =>
					`OTHER\t${ theme.dir }\tTemplate: ${ theme.template } is not the CoreX theme; not checked`
			),
		...result.missing.map(
			( { file, list, entry } ) =>
				`MISSING\t${ file }\t${ list }\t${ entry }`
		),
	];

	const summary =
		children.length === 0 && result.failures.length === 0
			? 'client-theme\tPASS\tnothing to check: no client theme is a child of the CoreX theme'
			: [
					'client-theme',
					result.status.toUpperCase(),
					`${ children.length } child themes checked`,
					`${ result.missing.length } missing`,
				].join( '\t' );

	return [ ...lines, summary ];
}

const isMain =
	path.basename( process.argv[ 1 ] ?? '' ) === 'verify-client-theme.mjs';

if ( isMain ) {
	const result = verifyClientThemes( process.cwd() );

	process.stdout.write(
		process.argv.includes( '--json' )
			? JSON.stringify( result, null, 2 ) + '\n'
			: report( result ).join( '\n' ) + '\n'
	);
	process.exitCode = result.status === 'pass' ? 0 : 1;
}
