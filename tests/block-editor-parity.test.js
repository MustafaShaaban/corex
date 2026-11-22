/**
 * The editor draws the page (spec 136, User Story 2, FR-012, SC-003; DECISIONS #671).
 *
 * Every `parity.json` of the framework, found by search under `plugins/` and `addons/`, is drawn
 * by rendering its block's `edit` to markup with the shared stand-ins of `@wordpress/*`, and the
 * tree of the elements that carry a class of the block is compared with the fixture's. The same
 * fixtures are drawn through WordPress by `tests/Integration/Blocks/ParityTest.php`.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const {
	KNOWN_NEEDS,
	findFiles,
	formatTree,
	installStandIns,
	normalizeTree,
	parityTree,
	registeredBlock,
	renderEdit,
} = require( '../packages/build-tools/parity' );

installStandIns( jest );

const ROOT = path.resolve( __dirname, '..' );
const fixtures = findFiles(
	[ path.join( ROOT, 'plugins' ), path.join( ROOT, 'addons' ) ],
	'parity.json'
).map( ( file ) => {
	const folder = path.dirname( file );
	const metadata = JSON.parse(
		fs.readFileSync( path.join( folder, 'block.json' ), 'utf8' )
	);

	return [
		metadata.name,
		{
			folder,
			metadata,
			fixture: JSON.parse( fs.readFileSync( file, 'utf8' ) ),
		},
	];
} );

const json = ( tree ) => JSON.stringify( tree, null, 2 );

function editorScriptOf( folder, metadata ) {
	const script = String( metadata.editorScript || '' ).replace(
		/^file:/,
		''
	);

	return path.join( folder, script || 'index.js' );
}

describe( 'FR-012: every parity fixture, drawn by the block editor', () => {
	test( 'finds the framework fixtures by search', () => {
		expect( fixtures.length ).toBeGreaterThan( 0 );
	} );

	describe.each( fixtures )(
		'%s',
		( name, { folder, metadata, fixture } ) => {
			test( 'its editor draws the tree of its parity.json', () => {
				const unknownNeeds = ( fixture.needs || [] ).filter(
					( need ) => ! KNOWN_NEEDS.includes( need )
				);
				if ( unknownNeeds.length > 0 ) {
					throw new Error(
						`${ name }: parity.json needs what no test can give: ${ unknownNeeds.join( ', ' ) }`
					);
				}

				require( editorScriptOf( folder, metadata ) );
				const settings = registeredBlock( name );
				if ( ! settings || typeof settings.edit !== 'function' ) {
					throw new Error(
						`${ name }: its editor script registered no edit for the block`
					);
				}

				const markup = renderEdit( settings.edit, {
					metadata,
					attributes: fixture.attributes || {},
					context: ( fixture.needs || [] ).includes( 'post' )
						? { postId: 1, postType: 'post' }
						: {},
				} );
				const { tree, errors } = parityTree( markup, {
					prefix: fixture.prefix,
				} );
				const expected = normalizeTree( fixture.tree );
				const matches =
					errors.length === 0 && json( tree ) === json( expected );

				// Empty when the trees match; otherwise the block, both trees and the markup.
				expect(
					matches
						? ''
						: [
								`${ name }: the editor does not draw the tree of its parity.json.`,
								...errors,
								'Editor:',
								formatTree( tree ),
								'parity.json:',
								formatTree( expected ),
								'Editor markup:',
								markup,
							].join( '\n' )
				).toBe( '' );
			} );
		}
	);
} );
