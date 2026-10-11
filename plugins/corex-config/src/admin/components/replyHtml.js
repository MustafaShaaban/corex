/**
 * Reduces markup to the formatting a reply has: paragraphs, line breaks, emphasis, lists and
 * links to the web, a mailbox or a telephone (spec 106).
 *
 * The server reduces what is posted, and is the guard (`ReplyBody::reduce()`). This is the same
 * rule in the browser, for the places markup is put on the page: a stored reply being read, what
 * the editor holds, and what is pasted into it. A value that reached the store some other way is still
 * not markup the admin runs.
 *
 * The markup is read into a document of its own, which runs no script and loads no image, and
 * what is allowed is rebuilt from it. Nothing is removed from a live tree.
 */

const ALLOWED = new Set( [
	'P',
	'BR',
	'STRONG',
	'B',
	'EM',
	'I',
	'UL',
	'OL',
	'LI',
	'A',
] );

/**
 * Blocks a reply does not have, each of which is a paragraph where a page or an editor wrote
 * it. The same list as the server's (`ReplyBody`), so that both ends read a paste one way.
 */
const BLOCKS = new Set( [
	'DIV',
	'H1',
	'H2',
	'H3',
	'H4',
	'H5',
	'H6',
	'BLOCKQUOTE',
	'SECTION',
	'ARTICLE',
	'HEADER',
	'FOOTER',
	'ASIDE',
	'MAIN',
	'NAV',
	'PRE',
	'ADDRESS',
	'FIGURE',
	'FIGCAPTION',
	'CENTER',
	'FORM',
	'FIELDSET',
	'DETAILS',
	'SUMMARY',
	'CAPTION',
	'TD',
	'TH',
	'DT',
	'DD',
] );

/** What makes an element a wrapper of blocks, not a block of text itself. */
const HOLDS_BLOCKS = [
	...BLOCKS,
	'P',
	'UL',
	'OL',
	'LI',
	'TABLE',
	'THEAD',
	'TBODY',
	'TFOOT',
	'TR',
	'DL',
	'HR',
]
	.join( ',' )
	.toLowerCase();

const EMPHASIS_AND_LINKS = 'a,b,strong,em,i';

/** Removed with everything inside them. */
const DROPPED = new Set( [ 'SCRIPT', 'STYLE', 'TITLE', 'NOSCRIPT' ] );

const GOES_SOMEWHERE = /^(https?:|mailto:|tel:)/i;

/** A line end in markup is how the markup was laid out. A browser draws it as a space. */
const LAID_OUT_LINE_END = /[ \t]*[\r\n]+[ \t]*/g;

const said = ( element, how ) => ( element.getAttribute( how ) || '' ).trim();

/**
 * Whether an element is one a reply keeps, where it stands.
 *
 * @param {Element} child The element read.
 * @param {Element} into  What it would be written into.
 * @return {boolean} False for what is rebuilt as its words alone.
 */
function isKept( child, into ) {
	if ( ! ALLOWED.has( child.tagName ) ) {
		return false;
	}
	if ( child.tagName === 'A' ) {
		return GOES_SOMEWHERE.test( said( child, 'href' ) );
	}
	// A list item is one in a list.
	if ( child.tagName === 'LI' ) {
		return [ 'UL', 'OL' ].includes( into.tagName );
	}
	// A word processor wraps a whole document in a "bold" it then says is not bold.
	if ( [ 'B', 'STRONG' ].includes( child.tagName ) ) {
		return ! /font-weight:\s*(normal|400)/i.test( said( child, 'style' ) );
	}

	return true;
}

/**
 * Where the words of something a reply does not keep are written: into a paragraph of their
 * own for a block, into emphasis for a span that is styled as emphasis, or straight on.
 *
 * @param {Element} child   The element read.
 * @param {Element} into    What is being written into.
 * @param {Object}  options As `cleanReplyHtml()` takes them.
 * @return {Element|null} Where to write its children; null when it says nothing.
 */
function placeFor( child, into, options ) {
	if ( ! options.blocksAsParagraphs ) {
		return into;
	}
	const block = BLOCKS.has( child.tagName ) || child.tagName === 'LI';
	if ( block && child.textContent.trim() === '' ) {
		// An empty block is how a page spaces itself, and says nothing.
		return null;
	}
	if (
		block &&
		into.tagName !== 'P' &&
		! child.querySelector( HOLDS_BLOCKS )
	) {
		return into.appendChild( into.ownerDocument.createElement( 'P' ) );
	}
	if ( child.tagName !== 'SPAN' ) {
		return into;
	}
	// A word processor says bold and italic with a style, not a tag.
	let place = into;
	const style = said( child, 'style' );
	if ( /font-weight:\s*(bold|[6-9]00)/i.test( style ) ) {
		place = place.appendChild(
			into.ownerDocument.createElement( 'STRONG' )
		);
	}
	if ( /font-style:\s*italic/i.test( style ) ) {
		place = place.appendChild( into.ownerDocument.createElement( 'EM' ) );
	}

	return place;
}

function rebuiltText( text, into, preformatted ) {
	const create = ( data ) => into.ownerDocument.createTextNode( data );
	if ( ! preformatted ) {
		into.appendChild( create( text.replace( LAID_OUT_LINE_END, ' ' ) ) );
		return;
	}
	// Preformatted text is the one place a line end is a line.
	text.replace( /^\r?\n|\r?\n$/g, '' )
		.split( /\r?\n/ )
		.forEach( ( line, index ) => {
			if ( index > 0 ) {
				into.appendChild( into.ownerDocument.createElement( 'BR' ) );
			}
			into.appendChild( create( line ) );
		} );
}

function rebuilt( node, into, options, preformatted = false ) {
	node.childNodes.forEach( ( child ) => {
		if ( child.nodeType === 3 ) {
			rebuiltText( child.data, into, preformatted );
			return;
		}
		if ( child.nodeType !== 1 || DROPPED.has( child.tagName ) ) {
			return;
		}
		if ( child.tagName === 'HR' ) {
			// A rule parts what is above it from what is below: see `inParagraphs()`.
			into.appendChild( into.ownerDocument.createElement( 'HR' ) );
			return;
		}
		const inside = preformatted || child.tagName === 'PRE';
		if ( ! isKept( child, into ) ) {
			// Its words stay; it stops being what it was.
			const place = placeFor( child, into, options );
			if ( place ) {
				rebuilt( child, place, options, inside );
			}
			return;
		}

		const copy = into.ownerDocument.createElement( child.tagName );
		if ( child.tagName === 'A' ) {
			copy.setAttribute( 'href', said( child, 'href' ) );
			if ( options.forReading ) {
				copy.setAttribute( 'target', '_blank' );
				copy.setAttribute( 'rel', 'noopener noreferrer' );
			}
		}
		rebuilt( child, copy, options, inside );
		into.appendChild( copy );
	} );
}

/**
 * Moves emphasis or a link written around blocks inside each of them.
 *
 * A page writes a card as a link around two blocks, and a word processor a whole document as
 * one "bold" around its paragraphs. A paragraph cannot stand inside either: read again, the
 * markup comes apart, and the link lost its address. Inside each block, both hold.
 *
 * @param {Element} parent What is being gone through: the wrapper itself, then what it holds.
 * @param {Element} inline The emphasis or link being moved in.
 */
function movedInside( parent, inline ) {
	let run = null;
	Array.from( parent.childNodes ).forEach( ( child ) => {
		const element = child.nodeType === 1;
		if ( element && child.querySelector( HOLDS_BLOCKS ) ) {
			run = null;
			movedInside( child, inline );
			return;
		}
		if ( element && child.matches( HOLDS_BLOCKS ) ) {
			run = null;
			const within = inline.cloneNode( false );
			within.append( ...child.childNodes );
			child.append( within );
			return;
		}
		if ( ! run && child.nodeType === 3 && child.data.trim() === '' ) {
			return;
		}
		if ( ! run ) {
			run = inline.cloneNode( false );
			parent.insertBefore( run, child );
		}
		run.appendChild( child );
	} );
}

function withEmphasisInsideBlocks( body ) {
	// Each turn removes one wrapper for good, and adds none that wraps a block.
	for (;;) {
		const wrapper = Array.from(
			body.querySelectorAll( EMPHASIS_AND_LINKS )
		).find( ( inline ) => inline.querySelector( HOLDS_BLOCKS ) );
		if ( ! wrapper ) {
			return;
		}
		movedInside( wrapper, wrapper );
		wrapper.replaceWith( ...wrapper.childNodes );
	}
}

/**
 * Puts what is written straight into the box, outside any paragraph or list, into paragraphs.
 *
 * A browser does not start a paragraph for the first line somebody types: the line is bare text,
 * and only the next one is a paragraph. Drawn, the first then sits closer to the second than
 * the second does to the third, and it is not the paragraph the email will have.
 *
 * @param {Element} written The rebuilt markup's container.
 */
function inParagraphs( written ) {
	let run = null;
	Array.from( written.childNodes ).forEach( ( node ) => {
		const block =
			node.nodeType === 1 &&
			[ 'P', 'UL', 'OL', 'HR' ].includes( node.tagName );
		if ( block ) {
			run = null;
			return;
		}
		if ( ! run ) {
			run = written.ownerDocument.createElement( 'P' );
			written.insertBefore( run, node );
		}
		run.appendChild( node );
	} );
	// A run of nothing but space between two blocks is how the markup was laid out.
	Array.from( written.children ).forEach( ( block ) => {
		if ( block.tagName === 'P' && block.textContent.trim() === '' ) {
			block.remove();
		}
	} );
}

/**
 * Leaves nothing a reply does not have, and nothing that says nothing.
 *
 * @param {Element} written The rebuilt markup's container.
 */
function tidied( written ) {
	// A rule has parted two paragraphs by now. One inside a list item is a line break.
	written.querySelectorAll( 'hr' ).forEach( ( rule ) => {
		if ( rule.parentNode === written ) {
			rule.remove();
		} else {
			rule.replaceWith( written.ownerDocument.createElement( 'BR' ) );
		}
	} );
	written.querySelectorAll( EMPHASIS_AND_LINKS ).forEach( ( inline ) => {
		if ( inline.textContent.trim() === '' ) {
			inline.replaceWith( inline.textContent );
		}
	} );
}

/**
 * @param {string}  html                         Markup from anywhere.
 * @param {Object}  [options]
 * @param {boolean} [options.forReading]         The reply is shown, not edited or sent: its links open in a new tab.
 * @param {boolean} [options.blocksAsParagraphs] The markup comes from a paste or an editor: a block a reply does not have becomes a paragraph, so its lines stay apart.
 * @param {boolean} [options.wholeMessage]       The markup is the whole of a message, not a piece being put into one: what stands outside any paragraph or list is put into paragraphs.
 * @return {string} Markup that holds only what a reply may.
 */
export function cleanReplyHtml( html, options = {} ) {
	if ( ! html ) {
		return '';
	}
	// With a doctype: without one a table is read as standing inside the paragraph before it.
	const read = new window.DOMParser().parseFromString(
		`<!DOCTYPE html><body>${ html }</body>`,
		'text/html'
	);
	if ( options.blocksAsParagraphs ) {
		withEmphasisInsideBlocks( read.body );
	}
	const written = read.createElement( 'div' );
	rebuilt( read.body, written, options );
	if ( options.wholeMessage || written.querySelector( ':scope > hr' ) ) {
		inParagraphs( written );
	}
	tidied( written );

	return written.innerHTML;
}

/**
 * Whether markup says anything: a reply of empty paragraphs is not a reply.
 *
 * @param {string} [html] Markup, such as the editor gives back.
 * @return {boolean} True when there is at least one word in it.
 */
export function hasWords( html ) {
	if ( ! html ) {
		return false;
	}
	const read = new window.DOMParser().parseFromString(
		`<!DOCTYPE html><body>${ html }</body>`,
		'text/html'
	);

	// A space that does not break is still only a space.
	return (
		read.body.textContent
			.split( String.fromCharCode( 160 ) )
			.join( ' ' )
			.trim() !== ''
	);
}
