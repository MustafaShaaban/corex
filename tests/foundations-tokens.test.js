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

	// Spec 134, FR-010 (spec 121, FR-025): the kit's custom colours and the shadows, light and dark.
	test( 'lists each custom colour and each shadow with its default and its dark value', () => {
		const rows = generator
			.tablesFrom( files )
			.theme.flatMap( ( { rows: list } ) => list );
		const row = ( token ) =>
			rows.find( ( [ name ] ) => name === token )?.[ 1 ];

		expect( row( 'custom--color--band-ink' ) ).toEqual( [
			'`--wp--custom--color--band-ink`',
			'`#f4f5f7`',
			'`#f4f5f7`',
		] );
		expect( row( 'custom--color--on-accent' ) ).toEqual( [
			'`--wp--custom--color--on-accent`',
			'`#14151a`',
			'`#14151a`',
		] );
		expect( row( 'shadow--md' ) ).toEqual( [
			'`md`',
			'`--wp--preset--shadow--md`',
			'`0 8px 24px rgb(20 21 26 / 10%)`',
			'`0 8px 24px rgb(0 0 0 / 24%)`',
		] );

		const dark = JSON.parse( JSON.stringify( files.dark ) );
		dark.settings.custom.color.bandInk = '#ffffff';
		expect(
			generator.differences(
				page,
				generator.tablesFrom( { ...files, dark } )
			)
		).toEqual( [
			'theme: custom--color--band-ink differs from what CoreX ships',
		] );
	} );

	test( 'refuses a token value that holds a backslash', () => {
		const theme = JSON.parse( JSON.stringify( files.theme ) );
		theme.settings.custom.radius.sm = '0.5rem\\|x';

		expect( () => generator.tablesFrom( { ...files, theme } ) ).toThrow(
			'A token value holds a backslash'
		);
	} );

	test( 'describes the admin adapter as it ships, and no navy-era scope', () => {
		expect( page ).toContain( '`.corex-admin`' );
		expect( page ).toContain( '**Dark by default.**' );
		expect( page ).toContain( '[data-corex-theme="light"]' );
		expect( page ).not.toMatch( /scoped to `\.wrap`/i );
	} );
} );
