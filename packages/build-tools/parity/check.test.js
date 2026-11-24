/**
 * A `parity.json` that could not fail is itself a failure (spec 136, "The parity contract",
 * FR-012): an empty tree, a first root that does not carry the prefix, or a `pageOnly` class
 * that is a class of the block, which could hide a whole subtree.
 */
const { fixtureProblems, isKnownNeed } = require( './check' );

const node = ( classes ) => ( { tag: 'div', classes } );

describe( 'FR-012: a fixture that proves something', () => {
	test( 'a well-formed fixture has no problem', () => {
		expect(
			fixtureProblems( 'corex/hero', {
				prefix: 'corex-hero',
				pageOnly: [ 'corex-trap' ],
				tree: [ node( [ 'corex-hero' ] ) ],
			} )
		).toEqual( [] );
	} );

	test( 'an empty or missing tree fails, naming the block', () => {
		expect(
			fixtureProblems( 'corex/hero', { prefix: 'corex-hero' } )
		).toEqual( [
			'corex/hero: parity.json has no tree, so the comparison proves nothing',
		] );
		expect(
			fixtureProblems( 'corex/hero', { prefix: 'corex-hero', tree: [] } )
		).toEqual( [
			'corex/hero: parity.json has no tree, so the comparison proves nothing',
		] );
	} );

	test( 'a first root that does not carry the prefix fails, naming the block', () => {
		expect(
			fixtureProblems( 'corex/hero', {
				prefix: 'corex-heros',
				tree: [ node( [ 'corex-hero' ] ) ],
			} )
		).toEqual( [
			'corex/hero: the first root of parity.json does not carry the prefix class corex-heros',
		] );
		expect(
			fixtureProblems( 'corex/hero', {
				prefix: 'corex-hero',
				tree: [ node( [ 'corex-hero__inner' ] ) ],
			} )
		).toEqual( [
			'corex/hero: the first root of parity.json does not carry the prefix class corex-hero',
		] );
	} );

	test( "a child block's first root carries a class of its parent's prefix, not the prefix itself", () => {
		const child = { parent: [ 'corex/accordion' ] };

		expect(
			fixtureProblems(
				'corex/accordion-item',
				{
					prefix: 'corex-accordion',
					tree: [ node( [ 'corex-accordion__item' ] ) ],
				},
				child
			)
		).toEqual( [] );
		expect(
			fixtureProblems(
				'corex/accordion-item',
				{
					prefix: 'corex-accordion',
					tree: [ node( [ 'corex-accordions__item' ] ) ],
				},
				child
			)
		).toEqual( [
			"corex/accordion-item: the first root of parity.json carries no class of the block corex-accordion (a child block prints its parent's prefix)",
		] );
	} );

	test( 'a pageOnly class that is a class of the block fails, naming the block', () => {
		expect(
			fixtureProblems( 'corex/hero', {
				prefix: 'corex-hero',
				pageOnly: [
					'corex-hero',
					'corex-hero__trap',
					'corex-hero--x',
					'trap',
				],
				tree: [ node( [ 'corex-hero' ] ) ],
			} )
		).toEqual( [
			'corex/hero: pageOnly may not name a class of the block: corex-hero, corex-hero__trap, corex-hero--x',
		] );
	} );
} );

describe( 'what a fixture may say the page needs', () => {
	test( 'a current post, or an active plug-in named by its file', () => {
		expect( isKnownNeed( 'post' ) ).toBe( true );
		expect(
			isKnownNeed( 'plugin:corex-newsletter/corex-newsletter.php' )
		).toBe( true );
	} );

	test( 'anything else, a plug-in with no file among it, is unknown', () => {
		expect( isKnownNeed( 'posts' ) ).toBe( false );
		expect( isKnownNeed( 'plugin:' ) ).toBe( false );
		expect( isKnownNeed( 'plugin:corex-newsletter' ) ).toBe( false );
	} );
} );

describe( 'FR-012: a block that holds blocks, drawn by the editor', () => {
	const fs = require( 'fs' );
	const os = require( 'os' );
	const path = require( 'path' );
	const { installStandIns } = require( './stand-ins' );
	const { editorParityProblem } = require( './check' );

	installStandIns( jest );

	// The probe's script lives outside the repository, so it names React by its full path.
	const REACT = JSON.stringify( require.resolve( 'react' ) );
	let folder;

	/**
	 * A block in a scratch folder: its block.json, its editor script and its parity.json.
	 *
	 * @param {Object} metadata The block.json.
	 * @param {string} edit     The body of its edit, given `h`, `props` and the block editor.
	 * @param {Object} fixture  The parity.json.
	 * @return {string} What editorParityProblem says of it.
	 */
	function probe( metadata, edit, fixture ) {
		fs.writeFileSync(
			path.join( folder, 'block.json' ),
			JSON.stringify( { editorScript: 'file:./index.js', ...metadata } )
		);
		fs.writeFileSync(
			path.join( folder, 'index.js' ),
			[
				`const { createElement: h } = require( ${ REACT } );`,
				"const editor = require( '@wordpress/block-editor' );",
				"require( '@wordpress/blocks' ).registerBlockType(",
				`	${ JSON.stringify( metadata.name ) },`,
				`	{ edit: ( props ) => { ${ edit } } }`,
				');',
			].join( '\n' )
		);
		fs.writeFileSync(
			path.join( folder, 'parity.json' ),
			JSON.stringify( fixture )
		);

		return editorParityProblem( folder );
	}

	beforeEach( () => {
		folder = fs.mkdtempSync( path.join( os.tmpdir(), 'corex-holder-' ) );
	} );

	afterEach( () => fs.rmSync( folder, { recursive: true, force: true } ) );

	test( 'the element that holds the child blocks is compared, and the children are not drawn', () => {
		expect(
			probe(
				{ name: 'corex/probe-holder' },
				"return h( 'div', editor.useInnerBlocksProps( editor.useBlockProps( { className: 'corex-probe' } ) ) );",
				{
					prefix: 'corex-probe',
					innerBlocks: [
						{
							name: 'corex/probe-child',
							attributes: { question: 'Q' },
						},
					],
					tree: [ { tag: 'div', classes: [ 'corex-probe' ] } ],
				}
			)
		).toBe( '' );
	} );

	test( "a child block is drawn with the block context its fixture gives, and prints its parent's prefix", () => {
		const edit =
			"const level = props.context[ 'corex/probeLevel' ]; " +
			"return h( 'div', editor.useBlockProps( { className: 'corex-probe__item' } ), " +
			"h( level ? 'h' + level : 'span', { className: 'corex-probe__question' } ) );";
		const fixture = {
			prefix: 'corex-probe',
			context: { 'corex/probeLevel': 3 },
			tree: [
				{
					tag: 'div',
					classes: [ 'corex-probe__item' ],
					children: [
						{ tag: 'h3', classes: [ 'corex-probe__question' ] },
					],
				},
			],
		};

		expect(
			probe(
				{ name: 'corex/probe-child', parent: [ 'corex/probe-holder' ] },
				edit,
				fixture
			)
		).toBe( '' );
		// Without the parent named, the first root must carry the prefix itself.
		expect( probe( { name: 'corex/probe-orphan' }, edit, fixture ) ).toBe(
			'corex/probe-orphan: the first root of parity.json does not carry the prefix class corex-probe'
		);
	} );
} );
