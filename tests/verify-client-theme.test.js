/**
 * `npm run verify:client-theme` (spec 135, FR-014; DECISIONS #660).
 *
 * A client theme is a child of the CoreX theme, and a preset list a child declares replaces the
 * CoreX theme's whole list. The check fails a declared list that lacks an entry of the CoreX
 * theme's. Every parent here is the repository's own `theme/theme.json`, read or copied, never
 * written into a test (FR-040), so the cases hold on a line whose theme has other tokens.
 */
const { spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const {
	PRESET_LISTS,
	childThemeFindings,
	clientThemeDirs,
	report,
	templateOf,
	verifyClientThemes,
} = require( '../scripts/verify-client-theme.mjs' );

const frameworkRoot = path.resolve( __dirname, '..' );
const script = path.join( frameworkRoot, 'scripts', 'verify-client-theme.mjs' );
const parentFile = path.join( frameworkRoot, 'theme', 'theme.json' );
const DIR = 'sites/acme/acme-theme';

/** @return {Object} A fresh copy of the repository's CoreX theme.json. */
const parent = () => JSON.parse( fs.readFileSync( parentFile, 'utf8' ) );

/**
 * A child theme.json as make:site writes it: every preset list of the parent's, its custom tokens
 * and its layout.
 *
 * @return {Object} The child.
 */
const fullChild = () => {
	const theirs = parent();
	const settings = {};

	for ( const [ group, list ] of PRESET_LISTS ) {
		if ( theirs.settings?.[ group ]?.[ list ] ) {
			settings[ group ] = {
				...settings[ group ],
				[ list ]: theirs.settings[ group ][ list ],
			};
		}
	}

	return {
		version: theirs.version,
		settings: {
			...settings,
			custom: theirs.settings.custom,
			layout: theirs.settings.layout,
		},
	};
};

/** Every `file:./` font source the parent lists. */
const parentFontSources = () =>
	parent()
		.settings.typography.fontFamilies.flatMap( ( family ) =>
			( family.fontFace ?? [] ).flatMap( ( face ) => [ face.src ].flat() )
		)
		.filter( ( source ) => source.startsWith( 'file:./' ) );

/**
 * A file-exists callback that holds the parent's font files and nothing else.
 *
 * @param {string} file A path from the repository root.
 * @return {boolean} Whether it is one of the parent's font files.
 */
const parentHoldsItsFonts = ( file ) =>
	parentFontSources().some(
		( source ) => file === `theme/${ source.slice( 'file:./'.length ) }`
	);

const findings = ( fields ) =>
	childThemeFindings( {
		dir: DIR,
		parent: parent(),
		themeJson: fullChild(),
		variations: [],
		fileExists: parentHoldsItsFonts,
		...fields,
	} );

const roots = [];

afterAll( () => {
	for ( const root of roots ) {
		fs.rmSync( root, { recursive: true, force: true, maxRetries: 3 } );
	}
} );

const write = ( root, file, contents ) => {
	const target = path.join( root, ...file.split( '/' ) );

	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.writeFileSync( target, contents );
};

const CHILD_STYLE = '/*\nTheme Name: Acme\nTemplate: corex\n*/\n';

/**
 * A repository on disk: a copy of the repository's CoreX theme.json and of the font files it
 * lists, and the files given.
 *
 * @param {Object<string,string>} files Path from the root => contents.
 * @return {string} Its root.
 */
const repository = ( files = {} ) => {
	const root = fs.mkdtempSync(
		path.join( os.tmpdir(), 'corex-client-theme-' )
	);
	roots.push( root );
	fs.mkdirSync( path.join( root, 'theme' ) );
	fs.copyFileSync( parentFile, path.join( root, 'theme', 'theme.json' ) );

	for ( const source of parentFontSources() ) {
		const relative = source.slice( 'file:./'.length );
		const target = path.join( root, 'theme', ...relative.split( '/' ) );

		fs.mkdirSync( path.dirname( target ), { recursive: true } );
		fs.copyFileSync(
			path.join( frameworkRoot, 'theme', ...relative.split( '/' ) ),
			target
		);
	}

	for ( const [ file, contents ] of Object.entries( files ) ) {
		write( root, file, contents );
	}

	return root;
};

describe( 'a child theme', () => {
	it( 'passes when it lists every entry of the CoreX theme’s', () => {
		expect( findings() ).toEqual( [] );
	} );

	it( 'fails a palette that lacks one slug, naming the file, the list and the slug', () => {
		const child = fullChild();
		const [ removed ] = child.settings.color.palette.splice( 0, 1 );

		expect( findings( { themeJson: child } ) ).toEqual( [
			{
				file: `${ DIR }/theme.json`,
				list: 'settings.color.palette',
				entry: removed.slug,
			},
		] );
	} );

	it( 'passes a list it does not declare, which it inherits whole', () => {
		const child = fullChild();
		delete child.settings.color;
		delete child.settings.typography;

		expect( findings( { themeJson: child } ) ).toEqual( [] );
	} );

	it( 'passes with no theme.json at all', () => {
		expect( findings( { themeJson: null } ) ).toEqual( [] );
	} );

	it( 'fails a style variation whose palette is partial', () => {
		const palette = parent().settings.color.palette;
		const variation = {
			version: parent().version,
			settings: { color: { palette: palette.slice( 1 ) } },
		};

		expect(
			findings( {
				variations: [
					{ file: `${ DIR }/styles/dark.json`, json: variation },
				],
			} )
		).toEqual( [
			{
				file: `${ DIR }/styles/dark.json`,
				list: 'settings.color.palette',
				entry: palette[ 0 ].slug,
			},
		] );
	} );

	it( 'fails a declared customTemplates list that lacks an entry of the CoreX theme’s', () => {
		const [ first, ...rest ] = parent().customTemplates;
		const child = { ...fullChild(), customTemplates: rest };

		expect( findings( { themeJson: child } ) ).toEqual( [
			{
				file: `${ DIR }/theme.json`,
				list: 'customTemplates',
				entry: first.name,
			},
		] );
	} );

	it( 'fails a declared templateParts list that lacks an entry of the CoreX theme’s', () => {
		const parts = parent().templateParts;
		const child = { ...fullChild(), templateParts: parts.slice( 1 ) };

		expect( findings( { themeJson: child } ) ).toEqual( [
			{
				file: `${ DIR }/theme.json`,
				list: 'templateParts',
				entry: parts[ 0 ].name,
			},
		] );
	} );

	it( 'fails a declared templateParts list that holds every entry in another order', () => {
		const parts = parent().templateParts;
		const child = { ...fullChild(), templateParts: [ ...parts ].reverse() };

		expect( findings( { themeJson: child } ) ).toEqual(
			parts.length > 1
				? parts.map( ( part, at ) => ( {
						file: `${ DIR }/theme.json`,
						list: 'templateParts',
						entry: `${ part.name } (expected at position ${ at + 1 })`,
					} ) )
				: []
		);
	} );

	it( 'fails a customTemplates list with a client entry before the CoreX theme’s, and passes one after', () => {
		const own = { name: 'page-acme', title: 'Acme', postTypes: [ 'page' ] };
		const theirs = parent().customTemplates;

		expect(
			findings( {
				themeJson: {
					...fullChild(),
					customTemplates: [ own, ...theirs ],
				},
			} ).map( ( { entry } ) => entry )
		).toEqual(
			theirs.map(
				( entry, at ) =>
					`${ entry.name } (expected at position ${ at + 1 })`
			)
		);
		expect(
			findings( {
				themeJson: {
					...fullChild(),
					customTemplates: [ ...theirs, own ],
				},
			} )
		).toEqual( [] );
	} );

	it( 'passes a font source only the CoreX theme holds, and fails one neither theme holds', () => {
		const child = fullChild();
		const [ family ] = child.settings.typography.fontFamilies;
		const [ face ] = family.fontFace;
		const nowhere = `${ [ face.src ].flat()[ 0 ] }.gone.woff2`;

		expect( findings( { themeJson: child } ) ).toEqual( [] );

		face.src = [ nowhere ];

		expect( findings( { themeJson: child } ) ).toEqual( [
			{ file: `${ DIR }/theme.json`, list: 'font', entry: nowhere },
		] );
	} );

	it( 'passes a font source the child theme holds itself', () => {
		const child = fullChild();
		const [ face ] = child.settings.typography.fontFamilies[ 0 ].fontFace;
		const own = 'file:./assets/fonts/acme.woff2';
		face.src = own;

		expect(
			findings( {
				themeJson: child,
				fileExists: ( file ) =>
					file === `${ DIR }/assets/fonts/acme.woff2` ||
					parentHoldsItsFonts( file ),
			} )
		).toEqual( [] );
	} );
} );

describe( 'the Template: header', () => {
	it( 'is read with any case of key, and trimmed', () => {
		expect( templateOf( '/*\ntemplate:   corex  \n*/' ) ).toBe( 'corex' );
		expect( templateOf( '/*\n * Template: corex */' ) ).toBe( 'corex' );
	} );

	it( 'is none when the header has no such line', () => {
		expect( templateOf( '/*\nTheme Name: Acme\n*/' ) ).toBeNull();
	} );
} );

describe( 'a repository', () => {
	it( 'finds the client themes in both layouts', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/older/themes/older/style.css': CHILD_STYLE,
			'sites/acme/acme-site/acme-site.php': '<?php',
		} );

		expect( clientThemeDirs( root ) ).toEqual( [
			'sites/acme/acme-theme',
			'sites/older/themes/older',
		] );
	} );

	it( 'reports a standalone theme and checks nothing in it', () => {
		const root = repository( {
			'sites/muva/muva-theme/style.css': '/*\nTheme Name: Muva\n*/\n',
			// A palette of one colour, which would fail a child.
			'sites/muva/muva-theme/theme.json': JSON.stringify( {
				settings: {
					color: {
						palette: parent().settings.color.palette.slice( 0, 1 ),
					},
				},
			} ),
		} );
		const result = verifyClientThemes( root );

		expect( result.status ).toBe( 'pass' );
		expect( result.missing ).toEqual( [] );
		expect( report( result ) ).toContain(
			'STANDALONE\tsites/muva/muva-theme\tno Template: line, not a child of the CoreX theme; not checked'
		);
	} );

	it( 'reports a theme that names another parent and checks nothing in it', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css':
				'/*\nTemplate: twentytwentyfive\n*/\n',
			'sites/acme/acme-theme/theme.json': '{ not json',
		} );
		const result = verifyClientThemes( root );

		expect( result.status ).toBe( 'pass' );
		expect( report( result ).join( '\n' ) ).toContain(
			'OTHER\tsites/acme/acme-theme\tTemplate: twentytwentyfive'
		);
	} );

	it( 'passes with no sites/ folder, and says there was nothing to check', () => {
		const result = verifyClientThemes( repository() );

		expect( result.status ).toBe( 'pass' );
		expect( report( result ) ).toEqual( [
			'client-theme\tPASS\tnothing to check: no client theme is a child of the CoreX theme',
		] );
	} );

	it( 'fails a theme.json that is not valid JSON, naming the file', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/acme/acme-theme/theme.json': '{ not json',
		} );
		const result = verifyClientThemes( root );

		expect( result.status ).toBe( 'fail' );
		expect( result.failures ).toEqual( [
			'sites/acme/acme-theme/theme.json: not valid JSON',
		] );
	} );

	it( 'fails a style variation that is not valid JSON, naming the file', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/acme/acme-theme/styles/dark.json': '{',
		} );

		expect( verifyClientThemes( root ).failures ).toEqual( [
			'sites/acme/acme-theme/styles/dark.json: not valid JSON',
		] );
	} );

	it( 'reads a variation in a folder under styles/, as WordPress does', () => {
		const partial = {
			settings: {
				color: { palette: parent().settings.color.palette.slice( 1 ) },
			},
		};
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/acme/acme-theme/styles/colors/acme.json':
				JSON.stringify( partial ),
		} );
		const result = verifyClientThemes( root );
		const [ first ] = parent().settings.color.palette;

		expect( result.status ).toBe( 'fail' );
		expect( result.missing ).toEqual( [
			expect.objectContaining( {
				file: 'sites/acme/acme-theme/styles/colors/acme.json',
				entry: first.slug,
			} ),
		] );
	} );

	it( 'takes a parent named in another case for another theme, as WordPress does', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': '/*\nTemplate: Corex\n*/\n',
		} );

		expect( report( verifyClientThemes( root ) ).join( '\n' ) ).toContain(
			'OTHER\tsites/acme/acme-theme\tTemplate: Corex'
		);
	} );

	it( 'fails, once, when a child needs the CoreX theme and it cannot be read', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/beta/beta-theme/style.css': CHILD_STYLE,
		} );
		fs.rmSync( path.join( root, 'theme', 'theme.json' ) );

		expect( verifyClientThemes( root ).failures ).toEqual( [
			'theme/theme.json: could not be read',
		] );
	} );
} );

describe( 'the script, run for real', () => {
	const run = ( root, args = [] ) =>
		spawnSync( process.execPath, [ script, ...args ], {
			cwd: root,
			encoding: 'utf8',
		} );

	it( 'passes a full child and fails it, by slug, once an entry is gone', () => {
		const child = fullChild();
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
			'sites/acme/acme-theme/theme.json': JSON.stringify(
				child,
				null,
				'\t'
			),
		} );

		const passed = run( root );

		expect( passed.status ).toBe( 0 );
		expect( passed.stdout ).toBe(
			'client-theme\tPASS\t1 child themes checked\t0 missing\n'
		);

		const [ removed ] = child.settings.color.palette.splice( -1, 1 );
		write(
			root,
			'sites/acme/acme-theme/theme.json',
			JSON.stringify( child, null, '\t' )
		);
		const failed = run( root );

		expect( failed.status ).toBe( 1 );
		expect( failed.stdout ).toBe(
			`MISSING\tsites/acme/acme-theme/theme.json\tsettings.color.palette\t${ removed.slug }\n` +
				'client-theme\tFAIL\t1 child themes checked\t1 missing\n'
		);
	} );

	it( 'prints the same result as one JSON object with --json', () => {
		const root = repository( {
			'sites/acme/acme-theme/style.css': CHILD_STYLE,
		} );
		const result = JSON.parse( run( root, [ '--json' ] ).stdout );

		expect( result ).toEqual( {
			status: 'pass',
			themes: [
				{
					dir: 'sites/acme/acme-theme',
					kind: 'child',
					template: 'corex',
				},
			],
			missing: [],
			failures: [],
		} );
	} );
} );
