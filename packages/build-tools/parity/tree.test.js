/**
 * The parity helper turns markup into the tree the parity contract compares (spec 136, FR-011;
 * DECISIONS #671): the elements that carry a class of the block, each with its tag, those classes
 * sorted and its nesting.
 */
const { parityTree, normalizeTree, formatTree } = require( './tree' );

const treeOf = ( markup, options = {} ) =>
	parityTree( markup, { prefix: 'corex-hero', ...options } );

describe( 'FR-011: the parity tree', () => {
	test( 'a class of the block is the prefix, or the prefix followed by __ or --', () => {
		const { tree } = treeOf(
			'<section class="corex-hero corex-hero--split corex-heroes corex-hero-x wp-block-corex-hero is-selected">' +
				'<h2 class="corex-hero__title">T</h2></section>'
		);

		expect( tree ).toEqual( [
			{
				tag: 'section',
				classes: [ 'corex-hero', 'corex-hero--split' ],
				children: [ { tag: 'h2', classes: [ 'corex-hero__title' ] } ],
			},
		] );
	} );

	test( 'sorts the block classes, so their order in the markup does not matter', () => {
		const { tree } = treeOf(
			'<div class="corex-hero--split corex-hero"></div>'
		);

		expect( tree[ 0 ].classes ).toEqual( [
			'corex-hero',
			'corex-hero--split',
		] );
	} );

	test( 'an element without a class of the block is transparent: its children keep their nesting', () => {
		const { tree } = treeOf(
			'<section class="corex-hero"><div class="layout"><span>' +
				'<p class="corex-hero__eyebrow">E</p></span>' +
				'<div><a class="corex-hero__cta">Go</a></div></div></section>' +
				'<p class="unrelated">x</p>'
		);

		expect( tree ).toEqual( [
			{
				tag: 'section',
				classes: [ 'corex-hero' ],
				children: [
					{ tag: 'p', classes: [ 'corex-hero__eyebrow' ] },
					{ tag: 'a', classes: [ 'corex-hero__cta' ] },
				],
			},
		] );
	} );

	test( 'keeps the nesting after a source element, which is void', () => {
		const { tree } = treeOf(
			'<section class="corex-hero"><picture><source class="corex-hero__source" srcset="a.webp">' +
				'<img class="corex-hero__bg" src="a.jpg" alt="" /></picture></section>'
		);

		expect( tree[ 0 ].children ).toEqual( [
			{ tag: 'source', classes: [ 'corex-hero__source' ] },
			{ tag: 'img', classes: [ 'corex-hero__bg' ] },
		] );
	} );

	test( 'skips an element marked data-corex-editor-only and everything inside it', () => {
		const { tree, errors } = treeOf(
			'<section class="corex-hero"><div data-corex-editor-only>' +
				'<button type="button">Remove</button><input /></div></section>'
		);

		expect( errors ).toEqual( [] );
		expect( tree ).toEqual( [
			{ tag: 'section', classes: [ 'corex-hero' ] },
		] );
	} );

	test( 'reports an editor-only element that carries a class of the block, or holds one', () => {
		const { tree, errors } = treeOf(
			'<section class="corex-hero">' +
				'<span data-corex-editor-only class="corex-hero__cta">Go</span>' +
				'<div data-corex-editor-only><p class="corex-hero__eyebrow">E</p></div>' +
				'</section>'
		);

		expect( tree ).toEqual( [
			{ tag: 'section', classes: [ 'corex-hero' ] },
		] );
		expect( errors ).toEqual( [
			'<span> is editor-only (data-corex-editor-only) and carries the block class corex-hero__cta',
			'<p> is editor-only (data-corex-editor-only) and carries the block class corex-hero__eyebrow',
		] );
	} );

	test( 'skips what a child block draws (data-corex-parity-child) and keeps the element that holds it', () => {
		// A block that holds blocks: the holder is compared, and the child blocks are compared
		// through their own fixtures, so their elements are not part of this tree even when they
		// print the parent's prefix.
		const { tree, errors } = treeOf(
			'<section class="corex-hero"><div class="corex-hero__slot">' +
				'<div data-corex-parity-child><p class="corex-hero__eyebrow">E</p></div>' +
				'<div data-corex-parity-child><p>Text</p></div>' +
				'</div></section>'
		);

		expect( errors ).toEqual( [] );
		expect( tree ).toEqual( [
			{
				tag: 'section',
				classes: [ 'corex-hero' ],
				children: [ { tag: 'div', classes: [ 'corex-hero__slot' ] } ],
			},
		] );
	} );

	test( 'removes the elements named in pageOnly, with what they hold', () => {
		const { tree } = treeOf(
			'<form class="corex-hero"><div class="corex-hero__trap"><input class="corex-hero__field" /></div>' +
				'<button class="corex-hero__submit">Send</button></form>',
			{ pageOnly: [ 'corex-hero__trap' ] }
		);

		expect( tree ).toEqual( [
			{
				tag: 'form',
				classes: [ 'corex-hero' ],
				children: [
					{ tag: 'button', classes: [ 'corex-hero__submit' ] },
				],
			},
		] );
	} );

	test( 'markup with no element of the block gives an empty tree', () => {
		expect( treeOf( '' ).tree ).toEqual( [] );
		expect( treeOf( '<p>Text</p>' ).tree ).toEqual( [] );
	} );

	test( 'formats a tree as an outline, one element per line', () => {
		const { tree } = treeOf(
			'<section class="corex-hero"><div class="corex-hero__inner">' +
				'<h2 class="corex-hero__title">T</h2></div><img class="corex-hero__bg" /></section>'
		);

		expect( formatTree( tree ) ).toBe(
			[
				'section.corex-hero',
				'  div.corex-hero__inner',
				'    h2.corex-hero__title',
				'  img.corex-hero__bg',
			].join( '\n' )
		);
		expect( formatTree( [] ) ).toBe( '(empty)' );
	} );

	test( 'normalizes a fixture tree into the same shape: keys in order, classes sorted, no empty children', () => {
		expect(
			normalizeTree( [
				{
					children: [],
					classes: [ 'corex-hero--split', 'corex-hero' ],
					tag: 'SECTION',
				},
			] )
		).toEqual( [
			{ tag: 'section', classes: [ 'corex-hero', 'corex-hero--split' ] },
		] );
	} );
} );
