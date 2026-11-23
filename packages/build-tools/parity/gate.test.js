/**
 * Which files are a block's editor, and what they import (spec 136, FR-013; DECISIONS #671).
 * The gate reads import and require statements only, after comments are removed, and follows
 * local modules through cycles.
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { editorScriptsOf, editorImportsOf, gateFailures } = require( './gate' );

const SSR = '@wordpress/server-side-render';

describe( 'FR-013: the editor files and their imports', () => {
	let folder;

	/**
	 * Writes the files of a block folder and returns the block, as the gate reads it.
	 *
	 * @param {Object}          files          File name => its text.
	 * @param {string|string[]} [editorScript] The block.json `editorScript`.
	 * @param {string}          [kind]         The block's `corex.editor`.
	 * @return {Object} The block.
	 */
	function block(
		files,
		editorScript = 'file:./index.js',
		kind = 'records'
	) {
		Object.entries( files ).forEach( ( [ file, text ] ) => {
			fs.mkdirSync( path.dirname( path.join( folder, file ) ), {
				recursive: true,
			} );
			fs.writeFileSync( path.join( folder, file ), text );
		} );
		const metadata = {
			name: 'corex/probe',
			editorScript,
			corex: { editor: kind },
		};

		return { name: 'corex/probe', kind, folder, metadata };
	}

	beforeEach( () => {
		folder = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-gate-' ) );
	} );

	afterEach( () => fs.rmSync( folder, { recursive: true, force: true } ) );

	test( 'the editor is every file: entry of editorScript, a string or a list', () => {
		expect(
			editorScriptsOf( folder, { editorScript: 'file:./index.js' } )
		).toEqual( [ path.join( folder, 'index.js' ) ] );
		expect(
			editorScriptsOf( folder, {
				editorScript: [
					'file:./index.js',
					'wp-blocks',
					'file:./extra.js',
				],
			} )
		).toEqual( [
			path.join( folder, 'index.js' ),
			path.join( folder, 'extra.js' ),
		] );
		expect( editorScriptsOf( folder, {} ) ).toEqual( [] );
	} );

	test( 'a mention of the server render in a comment or a string is not an import', () => {
		const probe = block( {
			'index.js': [
				`// import ServerSideRender from '${ SSR }';`,
				`/* require( '${ SSR }' ) */`,
				`const note = 'the server render lives in ${ SSR }';`,
			].join( '\n' ),
		} );

		expect( editorImportsOf( probe ) ).not.toContain( SSR );
		expect( gateFailures( probe ) ).toEqual( [
			`corex/probe: is records and its editor does not preview by ${ SSR }`,
		] );
	} );

	test( 'a static import, a dynamic import() and a require all count', () => {
		const forms = [
			`import ServerSideRender from '${ SSR }';`,
			`const load = () => import( '${ SSR }' );`,
			`const ServerSideRender = require( '${ SSR }' );`,
		];

		forms.forEach( ( text ) =>
			expect(
				editorImportsOf( block( { 'index.js': text } ) )
			).toContain( SSR )
		);
	} );

	test( 'follows local modules, .jsx and .ts included, through a cycle, and an array editorScript', () => {
		const probe = block(
			{
				'index.js': "import Edit from './edit';\nimport './extra';",
				'edit.jsx': "import { a } from './cycle';\nexport default a;",
				'cycle.ts': "import Edit from './edit';\nexport const a = 1;",
				'extra.js': "export * from './preview';",
				'preview/index.js': `import ServerSideRender from '${ SSR }';`,
			},
			[ 'file:./index.js', 'wp-blocks' ]
		);

		expect( editorImportsOf( probe ) ).toContain( SSR );
		expect( gateFailures( probe ) ).toEqual( [] );
	} );
} );
