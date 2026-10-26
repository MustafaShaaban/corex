#!/usr/bin/env node
/**
 * The token tables of the Foundations docs page, written from the files CoreX ships (spec 121,
 * US-DOC-07, DECISIONS #468).
 *
 *   node scripts/foundations-tokens.mjs --write    rewrite the tables in the page
 *   node scripts/foundations-tokens.mjs --check    exit 1, naming each token whose row differs
 *
 * The tables sit between `<!-- tokens:theme -->` / `<!-- /tokens:theme -->` and
 * `<!-- tokens:admin -->` / `<!-- /tokens:admin -->` in
 * docs-app/src/content/docs/design-system/foundations.md. The theme's come from theme/theme.json
 * and its dark variation, the admin's from corex-admin-tokens.css. The docs build runs the check
 * before it builds, and so does the Jest suite on every pull request.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

export const PAGE = 'docs-app/src/content/docs/design-system/foundations.md';
const THEME = 'theme/theme.json';
const DARK = 'theme/styles/dark.json';
const ADMIN = 'plugins/corex-core/assets/css/corex-admin-tokens.css';

const cell = ( value ) => `\`${ String( value ).replace( /\|/g, '\\|' ) }\``;
// WordPress's name for a slug: a letter after a digit starts a word (`2xl` is printed `2-xl`).
const kebab = ( value ) => value.replace( /(\d)([a-z])/g, '$1-$2' );

/**
 * One table: a header and a row per token, each row keyed by the token it describes.
 *
 * @param {string[]}                  head The column titles.
 * @param {Array<[string, string[]]>} rows Each token and its cells.
 * @return {{head: string[], rows: Array<[string, string[]]>}} The table.
 */
const table = ( head, rows ) => ( { head, rows } );

/**
 * The admin stylesheet's custom properties in one rule, keyed by name.
 *
 * @param {string} css     The stylesheet, comments removed.
 * @param {string} opening The rule's selector and its opening brace.
 * @return {Object<string,string>} Each custom property and its value.
 */
function declarations( css, opening ) {
	const start = css.indexOf( opening );
	if ( start < 0 ) {
		throw new Error( `${ ADMIN } has no rule starting ${ opening }` );
	}
	const body = css.slice( start + opening.length, css.indexOf( '}', start ) );

	return Object.fromEntries(
		[
			...body.matchAll( /(--corex-admin-[a-z0-9-]+)\s*:\s*([^;]+);/g ),
		].map( ( match ) => [
			match[ 1 ],
			match[ 2 ].replace( /\s+/g, ' ' ).trim(),
		] )
	);
}

/**
 * Every table the page carries, from the shipped files.
 *
 * @param {{theme: object, dark: object, adminCss: string}} files The files, read.
 * @return {{theme: object[], admin: object[]}} The tables, by section.
 */
export function tablesFrom( { theme, dark, adminCss } ) {
	const settings = theme.settings;
	const darkColours = Object.fromEntries(
		dark.settings.color.palette.map( ( entry ) => [
			entry.slug,
			entry.color,
		] )
	);
	const custom = ( group, prefix ) =>
		Object.entries( settings.custom[ group ] ).map( ( [ key, value ] ) => [
			`${ prefix }--${ key }`,
			[ cell( `--wp--custom--${ prefix }--${ key }` ), cell( value ) ],
		] );

	const css = adminCss.replace( /\/\*[\s\S]*?\*\//g, '' );
	const adminDark = declarations(
		css,
		'.corex-admin,\nbody.login.corex-login,\nbody.corex-admin-screen {'
	);
	const adminLight = declarations(
		css,
		'body.corex-admin-screen.corex-appearance-light {'
	);

	return {
		theme: [
			table(
				[
					'Colour',
					'Custom property',
					'Default',
					'Dark variation',
					'Use',
				],
				settings.color.palette.map( ( entry ) => [
					`color--${ entry.slug }`,
					[
						cell( entry.slug ),
						cell( `--wp--preset--color--${ entry.slug }` ),
						cell( entry.color ),
						cell( darkColours[ entry.slug ] ?? '' ),
						entry.name,
					],
				] )
			),
			table(
				[ 'Font family', 'Custom property', 'Stack' ],
				settings.typography.fontFamilies.map( ( entry ) => [
					`font-family--${ entry.slug }`,
					[
						cell( entry.slug ),
						cell( `--wp--preset--font-family--${ entry.slug }` ),
						cell( entry.fontFamily ),
					],
				] )
			),
			table(
				[
					'Font size',
					'Custom property',
					'Size',
					'Smallest on a phone',
					'Role',
				],
				settings.typography.fontSizes.map( ( entry ) => [
					`font-size--${ entry.slug }`,
					[
						cell( entry.slug ),
						cell(
							`--wp--preset--font-size--${ kebab( entry.slug ) }`
						),
						cell( entry.size ),
						entry.fluid && entry.fluid.min
							? cell( entry.fluid.min )
							: 'fixed',
						entry.name,
					],
				] )
			),
			table(
				[ 'Spacing', 'Custom property', 'Size' ],
				settings.spacing.spacingSizes.map( ( entry ) => [
					`spacing--${ entry.slug }`,
					[
						cell( entry.slug ),
						cell( `--wp--preset--spacing--${ entry.slug }` ),
						cell( entry.size ),
					],
				] )
			),
			table(
				[ 'Shadow', 'Custom property', 'Value' ],
				settings.shadow.presets.map( ( entry ) => [
					`shadow--${ entry.slug }`,
					[
						cell( entry.slug ),
						cell( `--wp--preset--shadow--${ entry.slug }` ),
						cell( entry.shadow ),
					],
				] )
			),
			table(
				[ 'Custom property', 'Value' ],
				[
					...custom( 'radius', 'radius' ),
					...custom( 'tracking', 'tracking' ),
					...Object.entries( settings.custom.motion.duration ).map(
						( [ key, value ] ) => [
							`motion--duration--${ key }`,
							[
								cell(
									`--wp--custom--motion--duration--${ key }`
								),
								cell( value ),
							],
						]
					),
					...Object.entries( settings.custom.motion.easing ).map(
						( [ key, value ] ) => [
							`motion--easing--${ key }`,
							[
								cell(
									`--wp--custom--motion--easing--${ key }`
								),
								cell( value ),
							],
						]
					),
				]
			),
		],
		admin: [
			table(
				[ 'Custom property', 'Dark (default)', 'Light' ],
				Object.entries( adminDark ).map( ( [ name, value ] ) => [
					name,
					[
						cell( name ),
						cell( value ),
						cell( adminLight[ name ] ?? value ),
					],
				] )
			),
		],
	};
}

/**
 * A table as markdown.
 *
 * @param {Object}                    table      A table from `tablesFrom()`.
 * @param {string[]}                  table.head The column titles.
 * @param {Array<[string, string[]]>} table.rows Each token and its cells.
 * @return {string} The markdown.
 */
function markdown( { head, rows } ) {
	const line = ( cells ) => `| ${ cells.join( ' | ' ) } |`;

	return [
		line( head ),
		line( head.map( () => '---' ) ),
		...rows.map( ( [ , cells ] ) => line( cells ) ),
	].join( '\n' );
}

const section = ( name, tables ) =>
	`<!-- tokens:${ name } -->\n${ tables.map( markdown ).join( '\n\n' ) }\n<!-- /tokens:${ name } -->`;

const between = ( page, name ) => {
	const match = page.match(
		new RegExp(
			`<!-- tokens:${ name } -->[\\s\\S]*?<!-- /tokens:${ name } -->`
		)
	);
	if ( ! match ) {
		throw new Error( `${ PAGE } has no <!-- tokens:${ name } --> section` );
	}
	return match[ 0 ];
};

/**
 * The page with its tables rewritten.
 *
 * @param {string} page   The page.
 * @param {Object} tables From `tablesFrom()`.
 * @return {string} The page.
 */
export function rewrite( page, tables ) {
	return Object.entries( tables ).reduce(
		( text, [ name, list ] ) =>
			text.replace( between( text, name ), section( name, list ) ),
		page
	);
}

/**
 * The tokens whose row on the page is not the row the files give, and the rows the page lacks.
 *
 * @param {string} page   The page.
 * @param {Object} tables From `tablesFrom()`.
 * @return {string[]} One line per token.
 */
export function differences( page, tables ) {
	const found = [];

	for ( const [ name, list ] of Object.entries( tables ) ) {
		const written = between( page, name );
		for ( const { rows } of list ) {
			for ( const [ token, cells ] of rows ) {
				if ( ! written.includes( `| ${ cells.join( ' | ' ) } |` ) ) {
					found.push(
						`${ name }: ${ token } differs from what CoreX ships`
					);
				}
			}
		}
		const expected = section( name, list );
		if ( found.length === 0 && written !== expected ) {
			found.push(
				`${ name }: the page lists a token CoreX does not ship`
			);
		}
	}

	return found;
}

/**
 * The shipped files, read from a repository.
 *
 * @param {string} root The repository.
 * @return {{theme: Object, dark: Object, adminCss: string}} The files, read.
 */
export function filesOf( root ) {
	const json = ( file ) =>
		JSON.parse( readFileSync( path.join( root, file ), 'utf8' ) );

	return {
		theme: json( THEME ),
		dark: json( DARK ),
		adminCss: readFileSync( path.join( root, ADMIN ), 'utf8' ),
	};
}

// Run as a command, not when a test imports it: `import.meta` does not survive Jest's transform.
if ( path.basename( process.argv[ 1 ] ?? '' ) === 'foundations-tokens.mjs' ) {
	const root = path.resolve( path.dirname( process.argv[ 1 ] ), '..' );
	const file = path.join( root, PAGE );
	const page = readFileSync( file, 'utf8' );
	const tables = tablesFrom( filesOf( root ) );

	if ( process.argv.includes( '--write' ) ) {
		writeFileSync( file, rewrite( page, tables ) );
		process.stdout.write( `Wrote the token tables of ${ PAGE }\n` );
	} else {
		const found = differences( page, tables );
		found.forEach( ( line ) => process.stdout.write( `${ line }\n` ) );
		if ( found.length > 0 ) {
			process.stdout.write(
				`Run node scripts/foundations-tokens.mjs --write\n`
			);
			process.exitCode = 1;
		}
	}
}
