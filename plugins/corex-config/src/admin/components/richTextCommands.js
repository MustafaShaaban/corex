/**
 * What the keys, the toolbar and a paste mean to `CorexRichText`, and the one place it asks the
 * browser to act on them.
 *
 * The commands go through `document.execCommand`. It is marked deprecated and is implemented by
 * every browser, with nothing put in its place: an editor that formats a selection either uses
 * it or carries its own model of the document. A reply has paragraphs, two kinds of emphasis,
 * two kinds of list and links, and that does not pay for a model. Every call is in this file, so
 * the day there is a replacement, this file changes and the editor does not.
 */
import { cleanReplyHtml } from './replyHtml.js';

/** Each formatting command: the browser's name for it. A link is made by `link()`, not here. */
const BROWSER_COMMANDS = {
	bold: 'bold',
	italic: 'italic',
	bulleted: 'insertUnorderedList',
	numbered: 'insertOrderedList',
};

/** A browser that edits can be asked; a test's document, and a very old browser, cannot. */
const canAsk = () =>
	typeof document !== 'undefined' &&
	typeof document.execCommand === 'function';

/**
 * Applies a formatting command to what is selected in the focused editor.
 *
 * @param {string} command `bold`, `italic`, `bulleted` or `numbered`.
 * @return {boolean} Whether the browser did it.
 */
export function format( command ) {
	return canAsk() && BROWSER_COMMANDS[ command ]
		? document.execCommand( BROWSER_COMMANDS[ command ], false )
		: false;
}

/**
 * Whether a formatting command is on where the caret is.
 *
 * @param {string} command `bold`, `italic`, `bulleted` or `numbered`.
 * @return {boolean} True when what is typed next would carry it.
 */
export function isOn( command ) {
	try {
		return (
			canAsk() &&
			Boolean( BROWSER_COMMANDS[ command ] ) &&
			document.queryCommandState( BROWSER_COMMANDS[ command ] )
		);
	} catch {
		return false;
	}
}

/**
 * Tells the browser a new line in the editor is a paragraph, not a `div`, and starts the first
 * paragraph of an empty one: a browser leaves the first line somebody types as bare text.
 *
 * @param {boolean} empty Whether the editor holds nothing yet.
 */
export function writeParagraphs( empty ) {
	if ( ! canAsk() ) {
		return;
	}
	document.execCommand( 'defaultParagraphSeparator', false, 'p' );
	if ( empty ) {
		document.execCommand( 'formatBlock', false, 'p' );
	}
}

/**
 * Makes the line the caret is on a paragraph.
 *
 * @return {boolean} Whether the browser did it.
 */
export function startParagraph() {
	return canAsk() ? document.execCommand( 'formatBlock', false, 'p' ) : false;
}

/**
 * Puts markup where the caret is, in place of what is selected.
 *
 * @param {string} html Markup already reduced to what a reply may hold.
 * @return {boolean} Whether the browser did it.
 */
export function insert( html ) {
	return canAsk() && html
		? document.execCommand( 'insertHTML', false, html )
		: false;
}

/**
 * Makes what is selected a link, or removes the link the caret is in.
 *
 * @param {string} address Where it goes, as `linkAddress()` answers it; '' to remove.
 * @return {boolean} Whether the browser did it.
 */
export function link( address ) {
	if ( ! canAsk() ) {
		return false;
	}

	return address
		? document.execCommand( 'createLink', false, address )
		: document.execCommand( 'unlink', false );
}

const BY_KEY = { b: 'bold', i: 'italic', k: 'link' };
const BY_KEY_WITH_SHIFT = { 8: 'bulleted', 7: 'numbered' };
const BY_PLACE = { KeyB: 'bold', KeyI: 'italic', KeyK: 'link' };
const BY_PLACE_WITH_SHIFT = { Digit8: 'bulleted', Digit7: 'numbered' };

/**
 * The command a key press asks for, if any.
 *
 * Ctrl or Command with B, I and K; with Shift, 8 for a bulleted list and 7 for a numbered one,
 * which is what mail programs use.
 *
 * Read from the character the key writes, and from the key's place on the keyboard where it
 * writes no Latin letter or digit: with Shift a digit key reports `*` or `(`, and on an Arabic
 * or a Russian keyboard the B key reports another letter. The character comes first, so that a
 * keyboard whose letters are laid out differently keeps its own B.
 *
 * @param {KeyboardEvent} event A key press in the editor.
 * @return {string|null} `bold`, `italic`, `link`, `bulleted`, `numbered`, or null.
 */
export function shortcutOf( event ) {
	if ( ! ( event.ctrlKey || event.metaKey ) || event.altKey ) {
		return null;
	}
	const pressed = String( event.key ).toLowerCase();
	const [ byKey, byPlace ] = event.shiftKey
		? [ BY_KEY_WITH_SHIFT, BY_PLACE_WITH_SHIFT ]
		: [ BY_KEY, BY_PLACE ];
	if ( /^[a-z0-9]$/.test( pressed ) ) {
		return byKey[ pressed ] || null;
	}

	return byPlace[ event.code ] || null;
}

/**
 * Where a link goes, from what somebody typed: the web, a mailbox or a telephone, or nowhere.
 *
 * The server decides again whatever is sent (`ReplyBody`). This is so that the person sees the
 * link they will get while they are still writing.
 *
 * @param {string} written What was typed as the address.
 * @return {string} The address, or '' when a reader of an email could not follow it.
 */
export function linkAddress( written ) {
	const address = String( written || '' ).trim();
	// A space may be in a file's name or a telephone number, never in a host or a mailbox.
	if ( /^https?:\/\/[^\s/?#]+([/?#].*)?$/i.test( address ) ) {
		return address.replace( / /g, '%20' );
	}
	if ( /^(mailto:\S+|tel:\S.*)$/i.test( address ) ) {
		return address;
	}
	if ( /^[a-z][a-z0-9+.-]*:/i.test( address ) ) {
		return '';
	}
	if ( /^[^\s@/]+@[^\s@/]+\.[^\s@/]+$/.test( address ) ) {
		return `mailto:${ address }`;
	}

	// A host: at least two names with a dot between them, and then whatever follows.
	return /^[^\s/#?.]+(\.[^\s/#?.]+)+([/#?]\S*)?$/.test( address )
		? `https://${ address }`
		: '';
}

const escaped = ( text ) =>
	text
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );

/**
 * What a paste puts in the editor: the paragraphs, emphasis, lists and links of what was copied,
 * and nothing else of its styling (FR-003).
 *
 * @param {DataTransfer} clipboard The paste event's `clipboardData`.
 * @return {string} Markup that holds only what a reply may.
 */
export function pastedMarkup( clipboard ) {
	const html = clipboard.getData( 'text/html' );
	if ( html ) {
		return cleanReplyHtml( html, { blocksAsParagraphs: true } );
	}
	const text = clipboard
		.getData( 'text/plain' )
		.replace( /\r\n?/g, '\n' )
		.replace( /^\n+|\n+$/g, '' );
	const lines = ( written ) => escaped( written ).replace( /\n/g, '<br>' );
	const paragraphs = text
		.split( /\n[ \t]*\n+/ )
		.filter( ( one ) => one.trim() !== '' );
	// A word or a line goes into the sentence it was pasted into, with its own spaces.
	if ( paragraphs.length < 2 ) {
		return lines( text );
	}

	return paragraphs
		.map( ( paragraph ) => `<p>${ lines( paragraph.trim() ) }</p>` )
		.join( '' );
}
