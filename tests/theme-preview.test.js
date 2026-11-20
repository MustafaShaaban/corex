/**
 * The theme's preview image shows the theme (spec 134, US-BRD-07, FR-025 to FR-027, SC-008;
 * DECISIONS #638).
 *
 * `npm run theme:preview` captures the front page of a scratch site into theme/screenshot.png and
 * writes, beside it, a fingerprint of the colours of theme/theme.json and theme/styles/dark.json.
 * These tests recompute the fingerprint from the two files, so a colour changed without a new
 * capture fails here and names the command; and they read the image's size from its header. The
 * command's refusals and what it writes are tested with WP-CLI and the browser stubbed.
 */
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const root = path.resolve( __dirname, '..' );
const preview = require( path.join( root, 'scripts/theme-preview.mjs' ) );

const THEME = 'theme/theme.json';
const DARK = 'theme/styles/dark.json';

/**
 * A copy of the files the fingerprint reads, in a temporary folder outside the tree, with `edit`
 * applied to the parsed copy of one file.
 *
 * @param {string}                 file The file to change, relative to the root.
 * @param {(json: Object) => void} edit What to change.
 * @return {string} The temporary root.
 */
function copyWith( file, edit ) {
	const copy = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-preview-' ) );

	for ( const source of [ THEME, DARK, preview.FINGERPRINT ] ) {
		fs.mkdirSync( path.dirname( path.join( copy, source ) ), {
			recursive: true,
		} );
		fs.copyFileSync( path.join( root, source ), path.join( copy, source ) );
	}

	const target = path.join( copy, file );
	const json = JSON.parse( fs.readFileSync( target, 'utf8' ) );
	edit( json );
	fs.writeFileSync( target, JSON.stringify( json, null, '\t' ) );

	return copy;
}

const copies = [];
const changed = ( file, edit ) => {
	const copy = copyWith( file, edit );
	copies.push( copy );
	return copy;
};

afterAll( () => {
	copies.forEach( ( copy ) =>
		fs.rmSync( copy, { recursive: true, force: true } )
	);
} );

describe( 'The theme preview (spec 134, US-BRD-07)', () => {
	// FR-027, SC-008, TC-BRD-07-03.
	test( 'FR-027: the fingerprint beside the image matches the colours of theme.json and dark.json', () => {
		expect( preview.staleness( root ) ).toEqual( [] );
	} );

	// FR-027, TC-BRD-07-03: the failure path, on a copy outside the tree.
	test.each( [
		[
			'a palette colour',
			THEME,
			( json ) => {
				json.settings.color.palette[ 0 ].color = '#123456';
			},
		],
		[
			'a dark palette colour',
			DARK,
			( json ) => {
				json.settings.color.palette[ 0 ].color = '#123456';
			},
		],
		[
			'a custom colour',
			THEME,
			( json ) => {
				json.settings.custom.color.onAccent = '#123456';
			},
		],
		[
			'a shadow',
			DARK,
			( json ) => {
				json.settings.shadow.presets[ 0 ].shadow = '0 0 1px #000';
			},
		],
	] )(
		'FR-027: %s changed without a new capture fails, naming the command and the file',
		( _what, file, edit ) => {
			const problems = preview.staleness( changed( file, edit ) );

			expect( problems ).toHaveLength( 1 );
			expect( problems[ 0 ] ).toContain( 'npm run theme:preview' );
			expect( problems[ 0 ] ).toContain( file );
		}
	);

	test( 'FR-027: the order of keys and entries, the layout of the file and a colour name do not change the fingerprint', () => {
		const copy = changed( THEME, ( json ) => {
			json.settings.color.palette.reverse();
			json.settings.color.palette[ 0 ].name = 'Another name';
			json.settings.shadow.presets.reverse();
			json.settings.custom.color = Object.fromEntries(
				Object.entries( json.settings.custom.color ).reverse()
			);
		} );

		expect( preview.staleness( copy ) ).toEqual( [] );
	} );

	test( 'FR-027: a colour written in another case is a change: the hash covers values exactly', () => {
		const copy = changed( THEME, ( json ) => {
			const accent = json.settings.color.palette.find(
				( entry ) => entry.slug === 'accent'
			);
			accent.color = accent.color.toUpperCase();
		} );

		expect( preview.staleness( copy ) ).toEqual( [
			expect.stringContaining( THEME ),
		] );
	} );

	test( 'FR-027: a missing fingerprint fails, naming the command', () => {
		const copy = changed( THEME, () => {} );
		fs.rmSync( path.join( copy, preview.FINGERPRINT ) );

		const problems = preview.staleness( copy );

		expect( problems ).toHaveLength( 1 );
		expect( problems[ 0 ] ).toContain( 'npm run theme:preview' );
	} );

	test( 'FR-026: the command the messages name is the one package.json runs', () => {
		const { scripts } = require( path.join( root, 'package.json' ) );

		expect( preview.COMMAND ).toBe( 'npm run theme:preview' );
		expect( scripts[ 'theme:preview' ] ).toBe(
			'node scripts/theme-preview.mjs'
		);
	} );

	// FR-025, SC-008, TC-BRD-07-02.
	test( 'FR-025: the image is a PNG of 1200 by 900', () => {
		const image = fs.readFileSync( path.join( root, preview.IMAGE ) );

		expect( preview.pngSize( image ) ).toEqual( {
			width: 1200,
			height: 900,
		} );
	} );

	test( 'FR-025: a file that is not a PNG is not read as one', () => {
		expect( () =>
			preview.pngSize( Buffer.from( 'not a picture' ) )
		).toThrow( 'not a PNG' );
	} );
} );

/**
 * A clean scratch site, as WP-CLI reads it.
 *
 * @param {string} home     Its home address.
 * @param {string} themeDir The folder its CoreX theme is in.
 * @return {Object} The site.
 */
const cleanSite = ( home, themeDir ) => ( {
	home,
	environment: 'development',
	stylesheet: 'corex',
	template: 'corex',
	themeDir,
	userStyles: [],
	customised: [],
	kits: [ 'company' ],
	showOnFront: 'page',
	front: { slug: 'home', kit: true },
	kitFront: 'home',
	otherContent: [],
} );

describe( 'What the command refuses (spec 134, FR-026)', () => {
	const URL = 'http://127.0.0.1:8916';
	const THEME_DIR = path.join( root, 'theme' );
	const where = { url: URL, themeDir: THEME_DIR };
	const scratch = cleanSite( `${ URL }/`, THEME_DIR );

	test( 'FR-026: a clean scratch site with the theme, the default style and the Company kit is accepted', () => {
		expect( preview.refusals( scratch, where ) ).toEqual( [] );
	} );

	test.each( [
		[
			'another site than the address',
			{ home: 'http://corex.local/' },
			'WP-CLI reads a site at http://corex.local/',
		],
		[
			'a production site',
			{ environment: 'production' },
			'CoreX reads this site as production',
		],
		[
			'a staging site',
			{ environment: 'staging' },
			'CoreX reads this site as staging',
		],
		[
			'another theme',
			{ stylesheet: 'twentytwentyfive', template: 'twentytwentyfive' },
			'The active theme is twentytwentyfive',
		],
		[
			'a child theme of CoreX',
			{ stylesheet: 'corex-child' },
			'The active theme is corex-child',
		],
		[
			'a CoreX theme from another folder than this checkout',
			{ themeDir: path.join( os.tmpdir(), 'copied', 'corex' ) },
			'not this checkout',
		],
		[
			'a chosen style or the site’s own styles',
			{ userStyles: [ 'settings', 'styles' ] },
			'styles of its own (settings, styles)',
		],
		[
			'a template changed in the Site Editor',
			{ customised: [ 'wp_template: front-page' ] },
			'changed in the Site Editor (wp_template: front-page)',
		],
		[ 'no Company kit', { kits: [] }, 'The Company kit is not applied' ],
		[
			'a front page that is no page of the kit',
			{ front: { slug: 'home', kit: false } },
			"The front page is not the Company kit's front page (home)",
		],
		[
			'another page of the kit as the front page',
			{ front: { slug: 'about', kit: true } },
			"The front page is not the Company kit's front page (home)",
		],
		[
			'the latest posts as the front page',
			{ showOnFront: 'posts' },
			"The front page is not the Company kit's front page",
		],
		[
			'content that is not the kit’s',
			{ otherContent: [ 'page: our-team', 'post: news' ] },
			'sample content (page: our-team, post: news)',
		],
	] )( 'FR-026: refuses %s, and says so', ( _what, change, says ) => {
		const problems = preview.refusals( { ...scratch, ...change }, where );

		expect( problems ).toEqual( [ expect.stringContaining( says ) ] );
	} );

	test( 'FR-026: WordPress’s own sample post, sample page and fallback menu do not make a site unclean', () => {
		expect(
			preview.refusals(
				{
					...scratch,
					otherContent: [
						'post: hello-world',
						'page: sample-page',
						'wp_navigation: page list',
					],
				},
				where
			)
		).toEqual( [] );
	} );
} );

describe( 'How the command finds WP-CLI (spec 134, FR-026)', () => {
	const found = ( files ) => ( file ) => files.includes( file );
	const COMPOSER = 'C:\\Users\\me\\AppData\\Roaming\\Composer\\vendor';

	test( 'FR-026: runs `wp` itself where it is a program (not Windows)', () => {
		expect(
			preview.wpCliCommand( {
				platform: 'linux',
				env: { PATH: '/usr/local/bin' },
				exists: found( [] ),
			} )
		).toEqual( { file: 'wp', prefix: [] } );
	} );

	test( 'FR-026: runs the WP-CLI that COREX_WP_CLI names, with WP_CLI_PHP or php', () => {
		expect(
			preview.wpCliCommand( {
				platform: 'win32',
				env: { COREX_WP_CLI: 'D:\\tools\\wp-cli.phar' },
				exists: found( [] ),
			} )
		).toEqual( { file: 'php', prefix: [ 'D:\\tools\\wp-cli.phar' ] } );
		expect(
			preview.wpCliCommand( {
				platform: 'linux',
				env: {
					COREX_WP_CLI: '/opt/wp-cli.phar',
					WP_CLI_PHP: '/opt/php83/bin/php',
				},
				exists: found( [] ),
			} )
		).toEqual( {
			file: '/opt/php83/bin/php',
			prefix: [ '/opt/wp-cli.phar' ],
		} );
	} );

	test( 'FR-026: on Windows, runs the PHP behind `wp.bat` with php, not the batch file through a shell', () => {
		const boot = `${ COMPOSER }\\wp-cli\\wp-cli\\php\\boot-fs.php`;

		expect(
			preview.wpCliCommand( {
				platform: 'win32',
				env: { PATH: `C:\\Windows;${ COMPOSER }\\bin` },
				exists: found( [ `${ COMPOSER }\\bin\\wp.bat`, boot ] ),
			} )
		).toEqual( { file: 'php', prefix: [ boot ] } );
		expect(
			preview.wpCliCommand( {
				platform: 'win32',
				env: { Path: 'C:\\tools' },
				exists: found( [
					'C:\\tools\\wp.bat',
					'C:\\tools\\wp-cli.phar',
				] ),
			} )
		).toEqual( { file: 'php', prefix: [ 'C:\\tools\\wp-cli.phar' ] } );
	} );

	test( 'FR-026: on Windows with no WP-CLI it can run, it finds none', () => {
		expect(
			preview.wpCliCommand( {
				platform: 'win32',
				env: { PATH: 'C:\\tools' },
				exists: found( [ 'C:\\tools\\wp.bat' ] ),
			} )
		).toBeNull();
	} );
} );

describe( 'What the capture checks on the page (spec 134, FR-025, FR-026)', () => {
	const tokens = preview.colourTokens(
		JSON.parse( fs.readFileSync( path.join( root, THEME ), 'utf8' ) )
	);
	const served = () =>
		Object.fromEntries( preview.customProperties( tokens ) );

	test( 'FR-025: finds CoreX product copy in page text, and nothing in the kit’s neutral copy', () => {
		expect(
			preview.productCopy(
				'Build something great. A clear, confident sentence about what your company does and who it helps.'
			)
		).toEqual( [] );
		expect(
			preview.productCopy(
				'CoreX. Discipline, at every layer. A WordPress framework, Core X and COREX.'
			)
		).toEqual( [
			'CoreX',
			'Discipline, at every layer',
			'framework',
			'Core X',
			'COREX',
		] );
	} );

	test( 'FR-026: the colours a page serves are compared token by token with the files', () => {
		const wrong = {
			...served(),
			'--wp--preset--color--accent': '#c9a25e',
			'--wp--custom--color--band-ink': '',
		};

		expect( preview.colourDifferences( tokens, served() ) ).toEqual( [] );
		expect( preview.colourDifferences( tokens, wrong ) ).toEqual( [
			'--wp--preset--color--accent is #c9a25e, theme.json has #ad8643',
			'--wp--custom--color--band-ink is (not set), theme.json has #f4f5f7',
		] );
	} );

	test( 'FR-025: a page with product copy or other colours is not captured, and says why', () => {
		expect(
			preview.pageProblems( tokens, served(), 'Build something great' )
		).toEqual( [] );
		expect(
			preview.pageProblems(
				tokens,
				{ ...served(), '--wp--preset--color--accent': '#000000' },
				'Built with CoreX'
			)
		).toEqual( [
			expect.stringContaining( '--wp--preset--color--accent is #000000' ),
			expect.stringContaining( 'CoreX product copy ("CoreX")' ),
		] );
	} );
} );

describe( 'What the command writes (spec 134, FR-025, FR-026), with WP-CLI and the browser stubbed', () => {
	const URL = 'http://127.0.0.1:8917';
	// The header of a 1200 by 900 PNG: all the command reads of an image before it writes one.
	const PNG = Buffer.concat( [
		Buffer.from( [ 137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 13 ] ),
		Buffer.from( 'IHDR', 'latin1' ),
		Buffer.from( [ 0, 0, 4, 176, 0, 0, 3, 132 ] ),
	] );

	/**
	 * A repository with the two theme files and no image or fingerprint, and a WordPress folder
	 * whose name has a space in it.
	 *
	 * @return {{repo: string, wordpress: string}} The two folders.
	 */
	function folders() {
		const repo = changed( THEME, () => {} );
		fs.rmSync( path.join( repo, preview.FINGERPRINT ) );
		const wordpress = fs.mkdtempSync(
			path.join( os.tmpdir(), 'corex scratch ' )
		);
		fs.writeFileSync( path.join( wordpress, 'wp-load.php' ), '<?php' );
		copies.push( wordpress );

		return { repo, wordpress };
	}

	/**
	 * The boundary, stubbed: WP-CLI reads `site` over a clean scratch site, the browser gives
	 * `page`, and every line the command prints is kept.
	 *
	 * @param {string} repo The repository.
	 * @param {Object} site What differs from a clean scratch site.
	 * @param {Object} page What the browser gives.
	 * @return {Object} The boundary, with `lines` and `captured`.
	 */
	function boundary( repo, site = {}, page = { problems: [], image: PNG } ) {
		const stub = { lines: [], captured: 0 };

		return Object.assign( stub, {
			readSite: () => ( {
				site: {
					...cleanSite(
						`${ URL }/`,
						fs.realpathSync( path.join( repo, 'theme' ) )
					),
					...site,
				},
			} ),
			capture: async () => {
				stub.captured++;
				return page;
			},
			log: ( line ) => stub.lines.push( line ),
			error: ( line ) => stub.lines.push( line ),
		} );
	}

	const run = ( repo, wordpress, stub ) =>
		preview.main(
			repo,
			[ `--url=${ URL }`, `--wp-path=${ wordpress }` ],
			stub
		);

	const written = ( repo ) =>
		[ preview.IMAGE, preview.FINGERPRINT ].filter( ( file ) =>
			fs.existsSync( path.join( repo, file ) )
		);

	test( 'FR-026: a clean site gives the image and its fingerprint', async () => {
		const { repo, wordpress } = folders();

		await expect( run( repo, wordpress, boundary( repo ) ) ).resolves.toBe(
			0
		);
		expect( written( repo ) ).toEqual( [
			preview.IMAGE,
			preview.FINGERPRINT,
		] );
		expect( preview.staleness( repo ) ).toEqual( [] );
	} );

	test( 'FR-026: a refused site opens no browser and writes nothing', async () => {
		const { repo, wordpress } = folders();
		const stub = boundary( repo, { environment: 'production' } );

		await expect( run( repo, wordpress, stub ) ).resolves.toBe( 1 );
		expect( stub.captured ).toBe( 0 );
		expect( written( repo ) ).toEqual( [] );
		expect( stub.lines.join( '\n' ) ).toContain(
			'CoreX reads this site as production'
		);
	} );

	test( 'FR-026: a page that fails its checks writes nothing', async () => {
		const { repo, wordpress } = folders();
		const stub = boundary(
			repo,
			{},
			{ problems: [ 'The page shows CoreX product copy ("CoreX").' ] }
		);

		await expect( run( repo, wordpress, stub ) ).resolves.toBe( 1 );
		expect( written( repo ) ).toEqual( [] );
		expect( stub.lines.join( '\n' ) ).toContain( 'CoreX product copy' );
	} );

	test( 'FR-026: a folder that holds no WordPress is refused before WP-CLI runs', async () => {
		const { repo } = folders();
		const stub = boundary( repo );
		stub.readSite = () => {
			throw new Error( 'WP-CLI must not run' );
		};

		await expect(
			run( repo, path.join( repo, 'theme' ), stub )
		).resolves.toBe( 1 );
		expect( stub.lines.join( '\n' ) ).toContain( 'no wp-load.php' );
		expect( written( repo ) ).toEqual( [] );
	} );

	test( 'FR-026: WP-CLI’s own error is printed when it fails', async () => {
		const { repo, wordpress } = folders();
		const stub = boundary( repo );
		stub.readSite = () => ( {
			error: 'Error: Error establishing a database connection.',
		} );

		await expect( run( repo, wordpress, stub ) ).resolves.toBe( 1 );
		expect( stub.lines.join( '\n' ) ).toContain(
			'Error establishing a database connection.'
		);
		expect( written( repo ) ).toEqual( [] );
	} );
} );
