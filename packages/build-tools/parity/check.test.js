/**
 * A `parity.json` that could not fail is itself a failure (spec 136, "The parity contract",
 * FR-012): an empty tree, a first root that does not carry the prefix, or a `pageOnly` class
 * that is a class of the block, which could hide a whole subtree.
 */
const { fixtureProblems } = require( './check' );

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
