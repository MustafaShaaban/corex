#!/usr/bin/env node
/**
 * CoreX's Arabic catalogue tools (spec 121, US-NFR-02, DECISIONS #466).
 *
 *   node scripts/i18n-arabic.mjs mo                 compile corex-ar.po into corex-ar.mo
 *   node scripts/i18n-arabic.mjs check [pot]        refuse broken placeholders or markup, a stale .mo, and,
 *                                                   given the template CI generates, list its strings with
 *                                                   no Arabic (exit 1 with --strict)
 *   node scripts/i18n-arabic.mjs scripts            write the script translations for every built bundle
 *
 * `corex-ar.po` in plugins/corex-core/languages is the source. The `.mo` is committed beside it, so
 * a site needs nothing built to read CoreX in Arabic. The script translations are written from the
 * catalogue when the bundles are built (`npm run build` runs `scripts` last): one JSON per bundle that
 * asks for a CoreX string, named as WordPress names them, `corex-ar-<md5 of the bundle's path inside
 * its plug-in>.json`. CoreX's `ScriptTranslations` finds them there.
 *
 * Strings only the command line shows (`packages/cli`) are not counted: a shared host has no
 * command line, and its help stays in English.
 */
import { createHash } from 'node:crypto';
import {
	existsSync,
	readFileSync,
	readdirSync,
	statSync,
	writeFileSync,
	unlinkSync,
} from 'node:fs';
import path from 'node:path';

export const LANGUAGES = 'plugins/corex-core/languages';
const PO = `${ LANGUAGES }/corex-ar.po`;
const MO = `${ LANGUAGES }/corex-ar.mo`;
const LOCALE = 'ar';
const PACKAGE_ROOTS = [ 'plugins', 'addons' ];
const CONTEXT_GLUE = '\u0004';

/** A reference only the command line reaches. */
const COMMAND_LINE_ONLY = /^packages\/cli\//;

/* ------------------------------------------------------------------ PO */

function unquote( line ) {
	const body = line.slice( line.indexOf( '"' ) + 1, line.lastIndexOf( '"' ) );

	return body.replace(
		/\\(["\\nt])/g,
		( _, character ) =>
			( { n: '\n', t: '\t', '"': '"', '\\': '\\' } )[ character ]
	);
}

function quote( text ) {
	return `"${ text
		.replace( /\\/g, '\\\\' )
		.replace( /"/g, '\\"' )
		.replace( /\t/g, '\\t' )
		.replace( /\n/g, '\\n' ) }"`;
}

/**
 * Reads a PO or POT file.
 *
 * @param {string} text The file.
 * @return {{header: string, entries: Array<object>}} Its header and entries, in order.
 */
export function parsePo( text ) {
	const entries = [];
	let entry = null;
	let field = null;

	const start = () => {
		entry = {
			comments: [],
			refs: [],
			flags: [],
			extracted: [],
			ctx: null,
			id: null,
			plural: null,
			str: [],
		};
		entries.push( entry );
		field = null;
	};

	for ( const raw of text.split( /\r?\n/ ) ) {
		const line = raw.trim();

		if ( line === '' ) {
			entry = null;
			continue;
		}
		if ( ! entry ) {
			start();
		}
		if ( line.startsWith( '#:' ) ) {
			entry.refs.push( ...line.slice( 2 ).trim().split( /\s+/ ) );
		} else if ( line.startsWith( '#,' ) ) {
			entry.flags.push(
				...line
					.slice( 2 )
					.split( ',' )
					.map( ( flag ) => flag.trim() )
			);
		} else if ( line.startsWith( '#.' ) ) {
			entry.extracted.push( line.slice( 2 ).trim() );
		} else if ( line.startsWith( '#' ) ) {
			entry.comments.push( line.slice( 1 ).trim() );
		} else if ( line.startsWith( 'msgctxt ' ) ) {
			field = [ 'ctx' ];
			entry.ctx = unquote( line );
		} else if ( line.startsWith( 'msgid_plural ' ) ) {
			field = [ 'plural' ];
			entry.plural = unquote( line );
		} else if ( line.startsWith( 'msgid ' ) ) {
			field = [ 'id' ];
			entry.id = unquote( line );
		} else if ( line.startsWith( 'msgstr[' ) ) {
			const index = Number( line.slice( 7, line.indexOf( ']' ) ) );
			field = [ 'str', index ];
			entry.str[ index ] = unquote( line );
		} else if ( line.startsWith( 'msgstr ' ) ) {
			field = [ 'str', 0 ];
			entry.str[ 0 ] = unquote( line );
		} else if ( line.startsWith( '"' ) && field ) {
			if ( field[ 0 ] === 'str' ) {
				entry.str[ field[ 1 ] ] += unquote( line );
			} else {
				entry[ field[ 0 ] ] += unquote( line );
			}
		}
	}

	const headerIndex = entries.findIndex(
		( item ) => item.id === '' && item.ctx === null
	);
	const header =
		headerIndex >= 0 ? entries.splice( headerIndex, 1 )[ 0 ].str[ 0 ] : '';

	return { header, entries: entries.filter( ( item ) => item.id !== null ) };
}

/**
 * Writes a catalogue back as a PO file.
 *
 * @param {{header: string, entries: Array<object>}} catalog The catalogue.
 * @return {string} The file.
 */
export function serializePo( catalog ) {
	const block = ( entry ) => {
		const lines = [
			...entry.comments.map( ( comment ) => `# ${ comment }` ),
			...entry.extracted.map( ( comment ) => `#. ${ comment }` ),
			...entry.refs.map( ( ref ) => `#: ${ ref }` ),
		];
		if ( entry.flags.length > 0 ) {
			lines.push( `#, ${ entry.flags.join( ', ' ) }` );
		}
		if ( entry.ctx !== null ) {
			lines.push( `msgctxt ${ quote( entry.ctx ) }` );
		}
		lines.push( `msgid ${ quote( entry.id ) }` );
		if ( entry.plural === null ) {
			lines.push( `msgstr ${ quote( entry.str[ 0 ] ?? '' ) }` );
		} else {
			lines.push( `msgid_plural ${ quote( entry.plural ) }` );
			entry.str.forEach( ( form, index ) =>
				lines.push( `msgstr[${ index }] ${ quote( form ?? '' ) }` )
			);
		}
		return lines.join( '\n' );
	};

	return (
		[
			`msgid ""\nmsgstr ""\n${ catalog.header
				.split( '\n' )
				.filter( ( line ) => line !== '' )
				.map( ( line ) => quote( `${ line }\n` ) )
				.join( '\n' ) }`,
			...catalog.entries.map( block ),
		].join( '\n\n' ) + '\n'
	);
}

/**
 * The key a catalogue is looked up by: the context, if any, glued to the original.
 *
 * @param {Object} entry A parsed entry.
 * @return {string} Its key.
 */
export const keyOf = ( entry ) =>
	entry.ctx === null
		? entry.id
		: `${ entry.ctx }${ CONTEXT_GLUE }${ entry.id }`;

/**
 * Whether an entry carries a translation for every form it has.
 *
 * @param {Object} entry A parsed entry.
 * @return {boolean} Whether it does.
 */
export const isTranslated = ( entry ) =>
	entry.str.length > 0 &&
	entry.str.every( ( form ) => typeof form === 'string' && form !== '' );

/* ------------------------------------------------------------------ placeholders */

const PRINTF = /%(?:\d+\$)?[-+ 0#']*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/g;
const TAG = /<\/?([a-z][a-z0-9-]*)\b[^>]*>/gi;
const BRACES = /\{[a-zA-Z_][a-zA-Z0-9_]*\}/g;

function tokens( text ) {
	return {
		printf: ( text.replace( /%%/g, '' ).match( PRINTF ) || [] ).sort(),
		tags: [ ...text.matchAll( TAG ) ]
			.map( ( match ) =>
				match[ 0 ].startsWith( '</' )
					? `/${ match[ 1 ].toLowerCase() }`
					: match[ 1 ].toLowerCase()
			)
			.sort(),
		braces: ( text.match( BRACES ) || [] ).sort(),
	};
}

const same = ( a, b ) =>
	a.length === b.length && a.every( ( item, index ) => item === b[ index ] );
const subset = ( part, whole ) => {
	const left = [ ...whole ];

	return part.every( ( item ) => {
		const at = left.indexOf( item );
		if ( at < 0 ) {
			return false;
		}
		left.splice( at, 1 );
		return true;
	} );
};

/**
 * What is wrong with an entry's translation: a placeholder, a tag or a named slot that differs
 * from its original. A plural form may leave a number out ("one item" says no number) but never
 * adds one, since `sprintf()` fails on a placeholder it has no value for.
 *
 * @param {Object} entry A parsed entry.
 * @return {string[]} One line per problem.
 */
export function problemsOf( entry ) {
	if ( ! isTranslated( entry ) ) {
		return [];
	}

	const problems = [];
	const original = tokens( entry.id );
	const originalPlural =
		entry.plural === null ? null : tokens( entry.plural );

	entry.str.forEach( ( form, index ) => {
		const translated = tokens( form );
		const label = entry.plural === null ? '' : ` (form ${ index })`;

		if ( originalPlural ) {
			const all = [
				...new Set( [ ...originalPlural.printf, ...original.printf ] ),
			];
			if (
				! translated.printf.every( ( token ) => all.includes( token ) )
			) {
				problems.push(
					`placeholders ${ translated.printf.join( ' ' ) } are not the original's${ label }`
				);
			}
		} else if ( ! same( translated.printf, original.printf ) ) {
			problems.push(
				`placeholders ${ translated.printf.join( ' ' ) || 'none' } differ from ${ original.printf.join( ' ' ) || 'none' }`
			);
		}

		if ( ! same( translated.tags, original.tags ) ) {
			problems.push( `markup differs${ label }` );
		}
		if (
			originalPlural
				? ! subset( translated.braces, [
						...original.braces,
						...originalPlural.braces,
					] )
				: ! same( translated.braces, original.braces )
		) {
			problems.push( `named slots differ${ label }` );
		}
	} );

	return problems;
}

/* ------------------------------------------------------------------ MO */

/**
 * Compiles a catalogue into a GNU MO file: little endian, no hash table, originals sorted.
 *
 * @param {{header: string, entries: Array<object>}} catalog The parsed catalogue.
 * @return {Buffer} The file.
 */
export function compileMo( catalog ) {
	const pairs = catalog.entries
		.filter( isTranslated )
		.map( ( entry ) => [
			entry.plural === null
				? keyOf( entry )
				: `${ keyOf( entry ) }\0${ entry.plural }`,
			entry.str.join( '\0' ),
		] );
	pairs.push( [ '', catalog.header ] );
	pairs.sort( ( a, b ) =>
		Buffer.compare( Buffer.from( a[ 0 ] ), Buffer.from( b[ 0 ] ) )
	);

	const count = pairs.length;
	const headerSize = 28;
	const tables = headerSize + count * 16;
	const originals = pairs.map( ( [ original ] ) =>
		Buffer.from( original, 'utf8' )
	);
	const translations = pairs.map( ( [ , translation ] ) =>
		Buffer.from( translation, 'utf8' )
	);

	let offset = tables;
	const originalOffsets = originals.map( ( bytes ) => {
		const at = offset;
		offset += bytes.length + 1;
		return at;
	} );
	const translationOffsets = translations.map( ( bytes ) => {
		const at = offset;
		offset += bytes.length + 1;
		return at;
	} );

	const out = Buffer.alloc( offset );
	out.writeUInt32LE( 0x950412de, 0 );
	out.writeUInt32LE( 0, 4 );
	out.writeUInt32LE( count, 8 );
	out.writeUInt32LE( headerSize, 12 );
	out.writeUInt32LE( headerSize + count * 8, 16 );
	out.writeUInt32LE( 0, 20 );
	out.writeUInt32LE( 0, 24 );

	pairs.forEach( ( _, index ) => {
		out.writeUInt32LE( originals[ index ].length, headerSize + index * 8 );
		out.writeUInt32LE(
			originalOffsets[ index ],
			headerSize + index * 8 + 4
		);
		out.writeUInt32LE(
			translations[ index ].length,
			headerSize + count * 8 + index * 8
		);
		out.writeUInt32LE(
			translationOffsets[ index ],
			headerSize + count * 8 + index * 8 + 4
		);
		originals[ index ].copy( out, originalOffsets[ index ] );
		translations[ index ].copy( out, translationOffsets[ index ] );
	} );

	return out;
}

/* ------------------------------------------------------------------ script translations */

function* walk( directory ) {
	for ( const name of readdirSync( directory ) ) {
		if (
			name === 'node_modules' ||
			name === 'vendor' ||
			name.startsWith( '.' )
		) {
			continue;
		}
		const full = path.join( directory, name );
		if ( statSync( full ).isDirectory() ) {
			yield* walk( full );
		} else if ( name.endsWith( '.js' ) ) {
			yield full;
		}
	}
}

/**
 * The scripts a page can load: built bundles and hand-written assets, each with its path inside its
 * plug-in, which is what WordPress hashes to name a script's translation file.
 *
 * @param {string} root The repository.
 * @return {Array<{file: string, relative: string}>} Every candidate script.
 */
export function scriptsOf( root ) {
	const found = [];

	for ( const packages of PACKAGE_ROOTS ) {
		for ( const plugin of readdirSync( path.join( root, packages ) ) ) {
			for ( const folder of [ 'build', 'assets' ] ) {
				const base = path.join( root, packages, plugin, folder );
				if ( ! existsSync( base ) ) {
					continue;
				}
				for ( const file of walk( base ) ) {
					const relative = path
						.relative( path.join( root, packages, plugin ), file )
						.split( path.sep )
						.join( '/' )
						.replace( /\.min\.js$/, '.js' );
					found.push( { file, relative } );
				}
			}
		}
	}

	return found;
}

/**
 * How a string literal can look in a built script.
 *
 * @param {string} text The original.
 * @return {string[]} It in double quotes, single quotes and backticks.
 */
function literals( text ) {
	const json = JSON.stringify( text ).slice( 1, -1 );

	return [
		`"${ json }"`,
		`'${ json.replace( /\\"/g, '"' ).replace( /'/g, "\\'" ) }'`,
		`\`${ json.replace( /\\"/g, '"' ) }\``,
	];
}

/**
 * The script translations of one script: every translated JavaScript string whose original appears
 * in it as a literal. A match that is not a call to a translation function only adds an entry
 * nothing asks for.
 *
 * @param {string}        source  The script.
 * @param {Array<object>} entries Translated entries referenced from JavaScript.
 * @param {string}        header  The catalogue's header, for the plural rule.
 * @return {object|null} Jed locale data, or null when the script asks for no CoreX string.
 */
export function scriptTranslationsOf( source, entries, header ) {
	if ( ! /["'`]corex["'`]/.test( source ) ) {
		return null;
	}

	const messages = {};
	for ( const entry of entries ) {
		if (
			literals( entry.id ).some( ( literal ) =>
				source.includes( literal )
			)
		) {
			messages[ keyOf( entry ) ] = entry.str;
		}
	}

	if ( Object.keys( messages ).length === 0 ) {
		return null;
	}

	const plural =
		( header.match( /Plural-Forms:\s*([^\n]+)/ ) || [] )[ 1 ] ||
		'nplurals=2; plural=(n != 1);';

	return {
		'translation-revision-date':
			( header.match( /PO-Revision-Date:\s*([^\n]+)/ ) || [] )[ 1 ] || '',
		generator: 'scripts/i18n-arabic.mjs',
		domain: 'messages',
		locale_data: {
			messages: {
				'': {
					domain: 'messages',
					'plural-forms': plural.trim(),
					lang: LOCALE,
				},
				...messages,
			},
		},
	};
}

/**
 * WordPress's file name for a script's translations.
 *
 * @param {string} relative The script's path inside its plug-in.
 * @return {string} The file name.
 */
export const scriptFileName = ( relative ) =>
	`corex-${ LOCALE }-${ createHash( 'md5' ).update( relative ).digest( 'hex' ) }.json`;

function writeScripts( root ) {
	const catalog = parsePo( readFileSync( path.join( root, PO ), 'utf8' ) );
	const entries = catalog.entries.filter(
		( entry ) =>
			isTranslated( entry ) &&
			entry.refs.some( ( ref ) =>
				/\.(?:js|jsx|ts|tsx)(?::|$)/.test( ref )
			)
	);
	const languages = path.join( root, LANGUAGES );

	for ( const stale of readdirSync( languages ).filter( ( name ) =>
		/^corex-ar-[0-9a-f]{32}\.json$/.test( name )
	) ) {
		unlinkSync( path.join( languages, stale ) );
	}

	const byName = new Map();
	for ( const { file, relative } of scriptsOf( root ) ) {
		const data = scriptTranslationsOf(
			readFileSync( file, 'utf8' ),
			entries,
			catalog.header
		);
		if ( ! data ) {
			continue;
		}
		const name = scriptFileName( relative );
		// Two plug-ins with a bundle at the same path share a name; one file answers both.
		const earlier = byName.get( name );
		if ( earlier ) {
			Object.assign(
				earlier.locale_data.messages,
				data.locale_data.messages
			);
		} else {
			byName.set( name, data );
		}
	}

	for ( const [ name, data ] of byName ) {
		writeFileSync( path.join( languages, name ), JSON.stringify( data ) );
	}

	return byName.size;
}

/* ------------------------------------------------------------------ check */

/**
 * What WordPress translates from the theme's own files: the title and description of each pattern
 * file and the title of each style variation, in the theme's text domain (CoreX's) with the
 * contexts below. The template CI generates does not list them, so the check reads them here.
 *
 * @param {string} root The repository.
 * @return {Array<object>} Entries as `parsePo()` returns them, untranslated.
 */
export function themeFileEntries( root ) {
	const entry = ( ref, ctx, id ) => ( {
		comments: [],
		refs: [ ref ],
		flags: [],
		extracted: [],
		ctx,
		id,
		plural: null,
		str: [ '' ],
	} );
	const patterns = path.join( root, 'theme', 'patterns' );
	const styles = path.join( root, 'theme', 'styles' );
	const entries = [];

	for ( const name of readdirSync( patterns )
		.filter( ( file ) => file.endsWith( '.php' ) )
		.sort() ) {
		const header = readFileSync(
			path.join( patterns, name ),
			'utf8'
		).slice( 0, 2000 );

		for ( const [ field, ctx ] of [
			[ 'Title', 'Pattern title' ],
			[ 'Description', 'Pattern description' ],
		] ) {
			const match = header.match(
				new RegExp( `^\\s*\\*\\s*${ field }:\\s*(.+)$`, 'm' )
			);
			if ( match ) {
				entries.push(
					entry( `theme/patterns/${ name }`, ctx, match[ 1 ].trim() )
				);
			}
		}
	}

	for ( const name of readdirSync( styles )
		.filter( ( file ) => file.endsWith( '.json' ) )
		.sort() ) {
		const { title } = JSON.parse(
			readFileSync( path.join( styles, name ), 'utf8' )
		);
		if ( title ) {
			entries.push(
				entry( `theme/styles/${ name }`, 'Style variation name', title )
			);
		}
	}

	return entries;
}

/**
 * Everything wrong with the catalogue.
 *
 * @param {string}      root The repository.
 * @param {string|null} pot  A template to compare with, or null.
 * @return {{problems: string[], missing: string[]}} Broken translations and a stale .mo; strings with no Arabic.
 */
export function checkCatalogue( root, pot ) {
	const catalog = parsePo( readFileSync( path.join( root, PO ), 'utf8' ) );
	const problems = [];

	for ( const entry of catalog.entries ) {
		for ( const problem of problemsOf( entry ) ) {
			problems.push( `"${ entry.id.slice( 0, 80 ) }": ${ problem }` );
		}
	}

	const mo = path.join( root, MO );
	if (
		! existsSync( mo ) ||
		Buffer.compare( readFileSync( mo ), compileMo( catalog ) ) !== 0
	) {
		problems.push(
			`${ MO } is not compiled from ${ PO }: run node scripts/i18n-arabic.mjs mo`
		);
	}

	const missing = [];
	const translated = new Map(
		catalog.entries.map( ( entry ) => [ keyOf( entry ), entry ] )
	);
	const required = [
		...( pot ? parsePo( readFileSync( pot, 'utf8' ) ).entries : [] ),
		...themeFileEntries( root ),
	];
	for ( const entry of required ) {
		if (
			entry.refs.length > 0 &&
			entry.refs.every( ( ref ) => COMMAND_LINE_ONLY.test( ref ) )
		) {
			continue;
		}
		const own = translated.get( keyOf( entry ) );
		if ( ! own || ! isTranslated( own ) ) {
			missing.push(
				`"${ entry.id }" (${ entry.refs[ 0 ] || 'no reference' })`
			);
		}
	}

	return { problems, missing };
}

function main( ROOT, [ command, ...rest ] ) {
	if ( command === 'mo' ) {
		writeFileSync(
			path.join( ROOT, MO ),
			compileMo(
				parsePo( readFileSync( path.join( ROOT, PO ), 'utf8' ) )
			)
		);
		process.stdout.write( `Wrote ${ MO }\n` );
		return 0;
	}

	if ( command === 'scripts' ) {
		process.stdout.write(
			`Wrote ${ writeScripts( ROOT ) } Arabic script translation files into ${ LANGUAGES }\n`
		);
		return 0;
	}

	if ( command === 'check' ) {
		const pot =
			rest.find( ( argument ) => ! argument.startsWith( '--' ) ) || null;
		const { problems, missing } = checkCatalogue( ROOT, pot );

		problems.forEach( ( line ) =>
			process.stdout.write( `PROBLEM ${ line }\n` )
		);
		missing.forEach( ( line ) =>
			process.stdout.write( `NO ARABIC ${ line }\n` )
		);
		process.stdout.write(
			`${ problems.length } problem(s); ${ missing.length } string(s) with no Arabic.\n`
		);

		return problems.length > 0 ||
			( rest.includes( '--strict' ) && missing.length > 0 )
			? 1
			: 0;
	}

	process.stderr.write(
		'Usage: node scripts/i18n-arabic.mjs mo | scripts | check [corex.pot] [--strict]\n'
	);
	return 2;
}

// Run as a command, not when a test imports it: `import.meta` does not survive Jest's transform.
if ( path.basename( process.argv[ 1 ] ?? '' ) === 'i18n-arabic.mjs' ) {
	process.exitCode = main(
		path.resolve( path.dirname( process.argv[ 1 ] ), '..' ),
		process.argv.slice( 2 )
	);
}
