/**
 * The Foundations docs page lists the tokens CoreX ships (spec 121, US-DOC-07, DECISIONS #468).
 *
 * The page's token tables are written from theme.json and the admin token stylesheet by
 * scripts/foundations-tokens.mjs. These tests hold the page to the files on every pull request; the
 * docs build runs the same check before it builds.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '..' );
const generator = require(
	path.join( root, 'scripts/foundations-tokens.mjs' )
);
const page = fs.readFileSync( path.join( root, generator.PAGE ), 'utf8' );
const files = generator.filesOf( root );

describe( 'The Foundations docs page', () => {
	test( 'matches theme.json and the admin token stylesheet, row for row', () => {
		expect(
			generator.differences( page, generator.tablesFrom( files ) )
		).toEqual( [] );
	} );

	test( 'names the token when one value changes and the page is not written again', () => {
		const theme = JSON.parse( JSON.stringify( files.theme ) );
		theme.settings.custom.radius.sm = '0.5rem';

		expect(
			generator.differences(
				page,
				generator.tablesFrom( { ...files, theme } )
			)
		).toEqual( [ 'theme: radius--sm differs from what CoreX ships' ] );
	} );

	test( 'describes the admin adapter as it ships, and no navy-era scope', () => {
		expect( page ).toContain( '`.corex-admin`' );
		expect( page ).toContain( '**Dark by default.**' );
		expect( page ).toContain( '[data-corex-theme="light"]' );
		expect( page ).not.toMatch( /scoped to `\.wrap`/i );
	} );
} );
