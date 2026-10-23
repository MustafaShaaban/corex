/**
 * The block editor's side of sample content (spec 115, US-SAFE-14, FR-012 and FR-013): a marked block
 * is labelled, and the first change to its content removes the marker. A change that is not content
 * (its classes, its metadata) leaves the marker where it is.
 *
 * The script has no build step and reads the editor from `window.wp`, so it is loaded into a page
 * whose `wp` is a small stand-in that records what the script asks of the editor.
 */

let filters;
let listener;
let tree;
let updates;

function block( clientId, attributes, innerBlocks = [] ) {
	return { clientId, name: 'core/group', attributes, innerBlocks };
}

function stat( clientId, value ) {
	return {
		clientId,
		name: 'corex/stat',
		attributes: { value, label: 'Projects' },
		innerBlocks: [],
	};
}

/**
 * The editor's tree changes, as it does after every edit: a new array.
 *
 * @param {Object[]} blocks The editor's blocks after the change.
 */
function editorShows( blocks ) {
	tree = blocks;
	listener();
}

beforeEach( () => {
	filters = {};
	updates = [];
	window.wp = {
		i18n: { __: ( text ) => text },
		hooks: {
			addFilter: ( name, namespace, callback ) => {
				filters[ name ] = callback;
			},
		},
		compose: {
			createHigherOrderComponent: ( make ) => make,
		},
		element: {
			createElement: ( component, props ) => ( { component, props } ),
		},
		data: {
			select: () => ( { getBlocks: () => tree } ),
			dispatch: () => ( {
				updateBlockAttributes: ( clientId, attributes ) =>
					updates.push( { clientId, attributes } ),
			} ),
			subscribe: ( callback ) => {
				listener = callback;
			},
		},
	};
	jest.isolateModules( () => {
		require( '../sample-editor.js' );
	} );
} );

afterEach( () => {
	delete window.wp;
} );

it( 'labels a sample block, and only a sample block', () => {
	const wrap = filters[ 'editor.BlockListBlock' ]( 'BlockListBlock' );

	const sample = wrap( {
		attributes: { className: 'alignfull is-corex-sample' },
	} );
	const other = wrap( { attributes: { className: 'alignfull' } } );

	expect( sample.props.wrapperProps[ 'data-corex-sample-label' ] ).toBe(
		'Sample: replace before publishing'
	);
	expect( other.props.wrapperProps ).toBeUndefined();
} );

it( 'removes the marker the first time a figure inside the sample changes', () => {
	editorShows( [
		block( 'stats', { className: 'alignfull is-corex-sample' }, [
			stat( 'one', '100+' ),
		] ),
	] );
	expect( updates ).toEqual( [] );

	editorShows( [
		block( 'stats', { className: 'alignfull is-corex-sample' }, [
			stat( 'one', '240' ),
		] ),
	] );

	expect( updates ).toEqual( [
		{ clientId: 'stats', attributes: { className: 'alignfull' } },
	] );
} );

it( 'keeps the marker when only the classes or metadata change', () => {
	editorShows( [ block( 'quote', { className: 'is-corex-sample' }, [] ) ] );

	editorShows( [
		block(
			'quote',
			{
				className: 'is-corex-sample is-style-card',
				metadata: { name: 'Sample quote' },
			},
			[]
		),
	] );

	expect( updates ).toEqual( [] );
} );

it( 'reads rich text by its markup, so a changed word counts', () => {
	const text = ( html ) => ( { toHTMLString: () => html } );
	editorShows( [
		block( 'quote', { className: 'is-corex-sample' }, [
			{
				clientId: 'p',
				name: 'core/paragraph',
				attributes: { content: text( 'A happy client' ) },
				innerBlocks: [],
			},
		] ),
	] );

	editorShows( [
		block( 'quote', { className: 'is-corex-sample' }, [
			{
				clientId: 'p',
				name: 'core/paragraph',
				attributes: { content: text( 'Dana, Acme' ) },
				innerBlocks: [],
			},
		] ),
	] );

	expect( updates ).toEqual( [
		{ clientId: 'quote', attributes: { className: undefined } },
	] );
} );

it( 'removes the marker of the edited sample only', () => {
	editorShows( [
		block( 'stats', { className: 'is-corex-sample' }, [
			stat( 'one', '100+' ),
		] ),
		block( 'quote', { className: 'is-corex-sample' }, [] ),
	] );

	editorShows( [
		block( 'stats', { className: 'is-corex-sample' }, [
			stat( 'one', '240' ),
		] ),
		block( 'quote', { className: 'is-corex-sample' }, [] ),
	] );

	expect( updates.map( ( update ) => update.clientId ) ).toEqual( [
		'stats',
	] );
} );
