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
