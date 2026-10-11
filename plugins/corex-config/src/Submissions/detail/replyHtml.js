/**
 * Reduces markup to the formatting a reply has: paragraphs, line breaks, emphasis, lists and
 * links to the web, a mailbox or a telephone (spec 106).
 *
 * The server reduces what is posted, and is the guard (`ReplyBody::reduce()`). This is the same
 * rule in the browser, for the two places markup is put on the page: a stored reply being read,
 * and, with the editor, what is pasted. A value that reached the store some other way is still
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

/** Removed with everything inside them. */
const DROPPED = new Set( [ 'SCRIPT', 'STYLE' ] );

const GOES_SOMEWHERE = /^(https?:|mailto:|tel:)/i;

function rebuilt( node, into, options ) {
	node.childNodes.forEach( ( child ) => {
		if ( child.nodeType === 3 ) {
			into.appendChild( into.ownerDocument.createTextNode( child.data ) );
			return;
		}
		if ( child.nodeType !== 1 || DROPPED.has( child.tagName ) ) {
			return;
		}

		const address = ( child.getAttribute( 'href' ) || '' ).trim();
		const kept =
			ALLOWED.has( child.tagName ) &&
			( child.tagName !== 'A' || GOES_SOMEWHERE.test( address ) );
		if ( ! kept ) {
			// Its words stay; it stops being what it was.
			rebuilt( child, into, options );
			return;
		}

		const copy = into.ownerDocument.createElement( child.tagName );
		if ( child.tagName === 'A' ) {
			copy.setAttribute( 'href', address );
			if ( options.forReading ) {
				copy.setAttribute( 'target', '_blank' );
				copy.setAttribute( 'rel', 'noopener noreferrer' );
			}
		}
		rebuilt( child, copy, options );
		into.appendChild( copy );
	} );
}

/**
 * @param {string}  html                 Markup from anywhere.
 * @param {Object}  [options]
 * @param {boolean} [options.forReading] The reply is shown, not edited or sent: its links open in a new tab.
 * @return {string} Markup that holds only what a reply may.
 */
export function cleanReplyHtml( html, options = {} ) {
	if ( ! html ) {
		return '';
	}
	const read = new window.DOMParser().parseFromString(
		`<body>${ html }</body>`,
		'text/html'
	);
	const written = read.createElement( 'div' );
	rebuilt( read.body, written, options );

	return written.innerHTML;
}
