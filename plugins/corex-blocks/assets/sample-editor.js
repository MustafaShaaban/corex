/**
 * Sample content in the block editor (spec 115, US-SAFE-14).
 *
 * A block whose class list holds `is-corex-sample` is an example someone has to replace. Visitors do
 * not see it; the editor shows it outlined, with the label "Sample: replace before publishing". The
 * first time its content changes, this script removes the marker, and from then on the block is the
 * site's own content and visitors see it.
 *
 * "Its content" is what a person writes: the attributes of the block and of every block inside it,
 * leaving out the class list, the block's metadata and its lock, which the editor or a person may set
 * without changing a word. A marked block is remembered as it is the first time this script sees it.
 *
 * No build step: it reads the editor's own packages from `window.wp`.
 *
 * @param {Object} wp The global `window.wp`.
 */
( function ( wp ) {
	if ( ! wp || ! wp.hooks || ! wp.data || ! wp.compose || ! wp.element ) {
		return;
	}

	const MARKER = 'is-corex-sample';
	const IGNORED = [ 'className', 'metadata', 'lock' ];
	const { __ } = wp.i18n;

	function classes( className ) {
		return typeof className === 'string'
			? className.split( /\s+/ ).filter( Boolean )
			: [];
	}

	function isSample( attributes ) {
		return classes( attributes && attributes.className ).includes( MARKER );
	}

	function withoutMarker( className ) {
		const rest = classes( className ).filter( ( name ) => name !== MARKER );
		return rest.length > 0 ? rest.join( ' ' ) : undefined;
	}

	// Rich text arrives as an object that knows its own markup; everything else is plain data.
	function readable( key, value ) {
		if ( value && typeof value.toHTMLString === 'function' ) {
			return value.toHTMLString();
		}
		return value;
	}

	function content( block ) {
		const attributes = {};
		Object.keys( block.attributes || {} )
			.filter( ( key ) => ! IGNORED.includes( key ) )
			.sort()
			.forEach( ( key ) => {
				attributes[ key ] = block.attributes[ key ];
			} );
		return {
			name: block.name,
			attributes,
			inner: ( block.innerBlocks || [] ).map( content ),
		};
	}

	function signature( block ) {
		return JSON.stringify( content( block ), readable );
	}

	// The label, drawn by the editor stylesheet from this attribute.
	wp.hooks.addFilter(
		'editor.BlockListBlock',
		'corex/sample-label',
		wp.compose.createHigherOrderComponent(
			( BlockListBlock ) => ( props ) => {
				if ( ! isSample( props.attributes ) ) {
					return wp.element.createElement( BlockListBlock, props );
				}
				return wp.element.createElement( BlockListBlock, {
					...props,
					wrapperProps: {
						...( props.wrapperProps || {} ),
						'data-corex-sample-label': __(
							'Sample: replace before publishing',
							'corex'
						),
					},
				} );
			},
			'withCorexSampleLabel'
		)
	);

	const seen = new Map();
	let blocks = null;

	function visit( list, found ) {
		list.forEach( ( block ) => {
			if ( isSample( block.attributes ) ) {
				found.push( block );
			}
			visit( block.innerBlocks || [], found );
		} );
		return found;
	}

	function check() {
		const editor = wp.data.select( 'core/block-editor' );
		if ( ! editor ) {
			return;
		}
		const current = editor.getBlocks();
		if ( current === blocks ) {
			return;
		}
		blocks = current;

		visit( current, [] ).forEach( ( block ) => {
			const now = signature( block );
			if ( ! seen.has( block.clientId ) ) {
				seen.set( block.clientId, now );
				return;
			}
			if ( seen.get( block.clientId ) !== now ) {
				seen.delete( block.clientId );
				wp.data
					.dispatch( 'core/block-editor' )
					.updateBlockAttributes( block.clientId, {
						className: withoutMarker( block.attributes.className ),
					} );
			}
		} );
	}

	wp.data.subscribe( check );
} )( window.wp );
