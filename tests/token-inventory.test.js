const fs = require( 'node:fs' );
const path = require( 'node:path' );

const repositoryRoot = path.resolve( __dirname, '..' );
const generatorPath = path.join(
	repositoryRoot,
	'scripts/generate-token-inventory.mjs'
);
const loadGenerator = () => {
	expect( fs.existsSync( generatorPath ) ).toBe( true );

	return fs.existsSync( generatorPath ) ? require( generatorPath ) : null;
};

describe( 'Spec 057 token inventory generator', () => {
	test.each( [
		[ 'color', [ 'primary' ], '--wp--preset--color--primary' ],
		[ 'font-family', [ 'heading' ], '--wp--preset--font-family--heading' ],
		[ 'font-size', [ '2xl' ], '--wp--preset--font-size--2xl' ],
		[ 'spacing', [ '40' ], '--wp--preset--spacing--40' ],
		[ 'shadow', [ 'md' ], '--wp--preset--shadow--md' ],
		[
			'custom',
			[ 'motion', 'duration', 'base' ],
			'--wp--custom--motion--duration--base',
		],
		[ 'layout', [ 'contentSize' ], '--wp--style--global--content-size' ],
	] )(
		'maps %s definitions to WordPress runtime properties',
		( group, parts, expected ) => {
			const generator = loadGenerator();
			if ( ! generator ) {
				return;
			}

			expect( generator.generatedProperty( group, parts ) ).toBe(
				expected
			);
		}
	);

	test( 'produces byte-stable inventory output for unchanged sources', () => {
		const generator = loadGenerator();
		if ( ! generator ) {
			return;
		}
		const first = generator.serializeInventory(
			generator.buildInventory( repositoryRoot )
		);
		const second = generator.serializeInventory(
			generator.buildInventory( repositoryRoot )
		);

		expect( second ).toEqual( first );
	} );

	test( 'keeps every committed inventory artifact synchronized with source', () => {
		const generator = loadGenerator();
		if ( ! generator ) {
			return;
		}
		const generated = generator.serializeInventory(
			generator.buildInventory( repositoryRoot )
		);

		for ( const [ filename, content ] of Object.entries( generated ) ) {
			const committed = fs.readFileSync(
				path.join(
					repositoryRoot,
					'specs/057-brand-tokens-logo-system/inventories',
					filename
				),
				'utf8'
			);

			expect( committed ).toBe( content );
		}
	} );
} );
