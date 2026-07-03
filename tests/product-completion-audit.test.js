const {
	scanText,
	summarizeFindings,
} = require( '../scripts/product-completion-audit.mjs' );

describe( 'CoreX product completion audit', () => {
	test.each( [
		[
			'planned-capability',
			'A visual builder is a planned future capability.',
		],
		[ 'future-add-on', 'Blog Pro is a future add-on — reference only.' ],
		[ 'sample-data', 'Sample data · reference layout, not live metrics.' ],
		[ 'code-defined-editor', 'Editing is code-defined.' ],
		[
			'disabled-required-action',
			'Test send is disabled because no result is returned.',
		],
		[ 'planned-state', "__('Planned', 'corex')" ],
	] )( 'reports %s copy', ( expectedRule, source ) => {
		const findings = scanText( 'Screen.php', source );

		expect( findings ).toEqual( [
			expect.objectContaining( {
				file: 'Screen.php',
				rule: expectedRule,
				line: 1,
			} ),
		] );
	} );

	test( 'allows a real optional-dependency activation path', () => {
		const source =
			'WooCommerce is required for this layout. Install and activate WooCommerce to enable it.';

		expect( scanText( 'WooLayout.php', source ) ).toEqual( [] );
	} );

	test( 'ignores historical and generated paths', () => {
		expect(
			scanText(
				'specs/065-admin-product-completion/spec.md',
				'planned future capability'
			)
		).toEqual( [] );
		expect(
			scanText(
				'plugins/corex-config/build/index.js',
				'planned future capability'
			)
		).toEqual( [] );
	} );

	test( 'summarizes findings deterministically by rule and file', () => {
		const summary = summarizeFindings( [
			...scanText( 'b.php', 'Sample data' ),
			...scanText( 'a.php', "__('Planned', 'corex')" ),
			...scanText( 'a.php', 'Reference layout' ),
		] );

		expect( summary ).toEqual( {
			findingCount: 3,
			files: [ 'a.php', 'b.php' ],
			rules: {
				'planned-state': 1,
				'reference-layout': 1,
				'sample-data': 1,
			},
		} );
	} );
} );
