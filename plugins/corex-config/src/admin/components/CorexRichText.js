/**
 * CorexRichText — the admin's editor for a message somebody writes to a person: a reply
 * (spec 106, FR-001 to FR-003, FR-007).
 *
 * It offers what such a message has and nothing else: paragraphs and line breaks, bold, italic,
 * bulleted and numbered lists, and links. No colours, sizes, fonts or images: those belong to
 * the email's template, so that every reply from a site looks like that site.
 *
 * Why CoreX's own, and not one of the editors already in WordPress: the block editor's rich text
 * edits one block, and a list is a block of its own; the classic editor draws in a frame with its
 * own skin, which the admin's tokens, its dark and light and its direction do not reach. This is
 * a `contenteditable` region and a toolbar of the admin's own buttons, and it is small.
 *
 * What it gives back is always reduced to what a reply may hold (`cleanReplyHtml`), what it is
 * given is reduced before it is put on the page, and the server reduces again whatever arrives:
 * this is how a person writes, not the guard.
 *
 * For keyboard and screen reader: the region is a multi-line text box named by its label; the
 * toolbar is one tab stop, moved through with the arrow keys, each button saying whether it is
 * on; a link is asked for in a form in the page, never a browser prompt.
 */
import {
	useCallback,
	useEffect,
	useId,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { cleanReplyHtml } from './replyHtml.js';
import {
	format,
	insert,
	isOn,
	link,
	linkAddress,
	pastedMarkup,
	shortcutOf,
	startParagraph,
	writeParagraphs,
} from './richTextCommands.js';

const ON_A_MAC =
	typeof window !== 'undefined' &&
	/Mac|iPhone|iPad/.test( window.navigator?.platform || '' );

/**
 * @param {string}  key     The key pressed with the modifier.
 * @param {boolean} shifted Whether Shift is held too.
 * @return {{spoken:string,shown:string}} The shortcut as assistive technology is told it, on either kind of keyboard, and as it is written for this one.
 */
function shortcut( key, shifted = false ) {
	const shift = shifted ? 'Shift+' : '';

	return {
		spoken: `Control+${ shift }${ key } Meta+${ shift }${ key }`,
		shown: ON_A_MAC
			? `${ shifted ? '⇧' : '' }⌘${ key }`
			: `Ctrl+${ shift }${ key }`,
	};
}

const TOOLS = [
	{
		command: 'bold',
		label: __( 'Bold', 'corex' ),
		icon: 'editor-bold',
		keys: shortcut( 'B' ),
	},
	{
		command: 'italic',
		label: __( 'Italic', 'corex' ),
		icon: 'editor-italic',
		keys: shortcut( 'I' ),
	},
	{
		command: 'bulleted',
		label: __( 'Bulleted list', 'corex' ),
		icon: 'editor-ul',
		keys: shortcut( '8', true ),
	},
	{
		command: 'numbered',
		label: __( 'Numbered list', 'corex' ),
		icon: 'editor-ol',
		keys: shortcut( '7', true ),
	},
	{
		command: 'link',
		label: __( 'Link', 'corex' ),
		icon: 'admin-links',
		keys: shortcut( 'K' ),
	},
];

const FROM_AN_EDITOR = { blocksAsParagraphs: true, wholeMessage: true };

/**
 * Formatting a browser offers by its own keys, and this editor does. Anything else it offers
 * (underline is Ctrl+U everywhere) would be drawn in the box and missing from the email.
 */
const OFFERED_BY_THE_BROWSER = new Set( [ 'formatBold', 'formatItalic' ] );

/**
 * The link a place in the text is in, if it is in one.
 *
 * @param {Node|null} node   A place in the text.
 * @param {Element}   within The editor's text box: nothing outside it is looked at.
 * @return {Element|null} The link, or null.
 */
function linkAt( node, within ) {
	let current = within.contains( node ) ? node : null;
	while ( current && current !== within ) {
		if ( current.nodeType === 1 && current.tagName === 'A' ) {
			return current;
		}
		current = current.parentNode;
	}

	return null;
}

/**
 * Lets each paragraph and list read in the direction of what is written in it: an Arabic
 * paragraph in an English reply starts from the right, as it will in the email.
 *
 * Said on each one and not once on the text box, which would give the whole message the
 * direction of its first line; and on each one wherever it stands, since a browser leaves the
 * paragraphs written after a list inside the paragraph the list was started in. It is the
 * editor's own marking: what is given back has none.
 *
 * @param {Element} editor The editor's text box.
 */
function eachInItsOwnDirection( editor ) {
	editor.querySelectorAll( 'p, ul, ol' ).forEach( ( block ) => {
		if ( block.getAttribute( 'dir' ) !== 'auto' ) {
			block.setAttribute( 'dir', 'auto' );
		}
	} );
}

/**
 * @param {Object}   props
 * @param {string}   props.value         The message, as markup a reply may hold.
 * @param {Function} props.onChange      Called with the message each time it changes.
 * @param {string}   props.labelledBy    The id of the visible label that names the text box.
 * @param {string}   [props.describedBy] The id of something that says more about it.
 * @param {boolean}  [props.disabled]    When true, nothing can be written or formatted.
 * @return {import('react').ReactElement} The editor.
 */
export default function CorexRichText( {
	value,
	onChange,
	labelledBy,
	describedBy,
	disabled = false,
} ) {
	const id = useId();
	const region = useRef( null );
	const address = useRef( null );
	const kept = useRef( null );
	const keptLink = useRef( null );
	const [ on, setOn ] = useState( {} );
	const [ linking, setLinking ] = useState( null );

	// What the region holds follows the value only when the value was changed from outside
	// (emptied after sending, say): writing it back on every key would move the caret. What is
	// put there is reduced first, whoever gave it: a draft, or a caller yet to be written.
	useEffect( () => {
		const editor = region.current;
		const given = cleanReplyHtml( value, FROM_AN_EDITOR );
		if ( cleanReplyHtml( editor.innerHTML, FROM_AN_EDITOR ) !== given ) {
			editor.innerHTML = given;
			eachInItsOwnDirection( editor );
		}
	}, [ value ] );

	// Which buttons are on follows the caret, wherever it was put: a key, a click, a command.
	const follow = useCallback( () => {
		const editor = region.current;
		const selection = editor?.ownerDocument.getSelection();
		if (
			! editor ||
			! selection ||
			! editor.contains( selection.anchorNode )
		) {
			return;
		}
		const now = {
			bold: isOn( 'bold' ),
			italic: isOn( 'italic' ),
			bulleted: isOn( 'bulleted' ),
			numbered: isOn( 'numbered' ),
			link: Boolean( linkAt( selection.anchorNode, editor ) ),
		};
		setOn( ( before ) =>
			TOOLS.every(
				( tool ) => before[ tool.command ] === now[ tool.command ]
			)
				? before
				: now
		);
	}, [] );

	useEffect( () => {
		document.addEventListener( 'selectionchange', follow );

		return () => document.removeEventListener( 'selectionchange', follow );
	}, [ follow ] );

	// A label names a field and, pressed, puts the keyboard in it. The text box is not a
	// field a `<label>` can point at, so the label that names it is given the second half here.
	useEffect( () => {
		const label = document.getElementById( labelledBy );
		const enter = () => region.current?.focus();
		label?.addEventListener( 'click', enter );

		return () => label?.removeEventListener( 'click', enter );
	}, [ labelledBy ] );

	useEffect( () => {
		if ( linking ) {
			address.current.focus();
		}
	}, [ linking ] );

	const said = () => {
		// A browser leaves the first line somebody types as bare text, outside any paragraph:
		// it is drawn unlike the paragraphs after it, and is not the paragraph the email will
		// have. Entering the box by keyboard starts one; entering it by a click does not, since
		// the caret is not there yet. So the line being written is made one here.
		const at = region.current.ownerDocument.getSelection()?.anchorNode;
		if ( at && at.nodeType === 3 && at.parentNode === region.current ) {
			startParagraph();
		}
		eachInItsOwnDirection( region.current );
		onChange( cleanReplyHtml( region.current.innerHTML, FROM_AN_EDITOR ) );
	};

	// The selection is lost when focus goes to the link form, and is what the link is made of.
	// Only one inside this text box: what is selected elsewhere on the page is not its to link.
	const keepSelection = () => {
		const selection = region.current.ownerDocument.getSelection();
		const range =
			selection && selection.rangeCount > 0
				? selection.getRangeAt( 0 )
				: null;
		kept.current =
			range && region.current.contains( range.commonAncestorContainer )
				? range.cloneRange()
				: null;
	};

	const backToTheSelection = () => {
		region.current.focus();
		const selection = region.current.ownerDocument.getSelection();
		if ( ! selection ) {
			return null;
		}
		if ( kept.current ) {
			selection.removeAllRanges();
			selection.addRange( kept.current );
		} else {
			// Nothing of the message was selected: what is added goes at its end.
			selection.selectAllChildren( region.current );
			selection.collapseToEnd();
		}

		return selection;
	};

	const askForLink = () => {
		keepSelection();
		const inside = kept.current
			? linkAt( kept.current.startContainer, region.current )
			: null;
		keptLink.current = inside;
		setLinking( {
			address: inside ? inside.getAttribute( 'href' ) || '' : '',
			existing: Boolean( inside ),
			refused: false,
		} );
	};

	const run = ( command ) => {
		if ( disabled ) {
			return;
		}
		if ( command === 'link' ) {
			askForLink();
			return;
		}
		region.current.focus();
		format( command );
		said();
		// A command changes what is on without moving the caret, so nothing else would say so.
		follow();
	};

	const leaveLink = () => {
		backToTheSelection();
		keptLink.current = null;
		setLinking( null );
	};

	const applyLink = ( written ) => {
		const goesTo = linkAddress( written );
		const existing = keptLink.current;
		if ( written.trim() !== '' && ! goesTo ) {
			setLinking( { ...linking, address: written, refused: true } );
			return;
		}
		if ( ! goesTo && ! existing ) {
			// No address, and no link to take one from: nothing was asked for.
			leaveLink();
			return;
		}
		const selection = backToTheSelection();
		if ( existing && goesTo ) {
			// The caret is in a link: it is that link's address that changes.
			existing.setAttribute( 'href', goesTo );
		} else if ( existing ) {
			// Removing it: the whole link, not only the part of it that was selected.
			selection?.selectAllChildren( existing );
			link( '' );
		} else if ( ! selection || selection.isCollapsed ) {
			// Nothing was selected to make a link of: the address itself is written.
			const anchor = document.createElement( 'a' );
			anchor.setAttribute( 'href', goesTo );
			anchor.textContent = written.trim();
			insert( anchor.outerHTML );
		} else {
			link( goesTo );
		}
		keptLink.current = null;
		setLinking( null );
		said();
		follow();
	};

	const onKeyDown = ( event ) => {
		const command = shortcutOf( event );
		if ( command ) {
			event.preventDefault();
			run( command );
		}
	};

	const put = ( carried ) => {
		if ( carried ) {
			insert( pastedMarkup( carried ) );
			said();
		}
	};

	const onPaste = ( event ) => {
		// The browser's own paste would put in whatever was copied, as it was.
		event.preventDefault();
		if ( ! disabled ) {
			put( event.clipboardData );
		}
	};

	// What a browser does to the text by its own means, and not through a key or a button of
	// this editor: its own formatting keys, and something dragged in.
	const before = useRef( null );
	before.current = ( event ) => {
		const kind = event.inputType || '';
		if ( disabled ) {
			event.preventDefault();
		} else if ( kind === 'insertFromDrop' ) {
			// Dropped where the browser says, and reduced as a paste is.
			event.preventDefault();
			const [ where ] = event.getTargetRanges?.() || [];
			const selection = region.current.ownerDocument.getSelection();
			if ( where && selection ) {
				selection.setBaseAndExtent(
					where.startContainer,
					where.startOffset,
					where.endContainer,
					where.endOffset
				);
			}
			put( event.dataTransfer );
		} else if (
			kind.startsWith( 'format' ) &&
			! OFFERED_BY_THE_BROWSER.has( kind )
		) {
			event.preventDefault();
		}
	};
	useEffect( () => {
		const editor = region.current;
		const guard = ( event ) => before.current( event );
		editor.addEventListener( 'beforeinput', guard );

		return () => editor.removeEventListener( 'beforeinput', guard );
	}, [] );

	return (
		<div
			className="corex-richtext"
			data-disabled={ disabled ? 'true' : undefined }
		>
			<Tools
				controls={ `${ id }-text` }
				on={ on }
				disabled={ disabled }
				onRun={ run }
			/>
			{ linking && (
				<LinkForm
					id={ `${ id }-link` }
					linking={ linking }
					field={ address }
					onChange={ ( written ) =>
						setLinking( {
							...linking,
							address: written,
							refused: false,
						} )
					}
					onApply={ applyLink }
					onLeave={ leaveLink }
				/>
			) }
			<div
				id={ `${ id }-text` }
				ref={ region }
				className="corex-richtext__text"
				role="textbox"
				aria-multiline="true"
				aria-labelledby={ labelledBy }
				aria-describedby={ describedBy }
				aria-disabled={ disabled ? 'true' : undefined }
				contentEditable={ ! disabled }
				tabIndex={ 0 }
				onFocus={ () =>
					disabled ||
					writeParagraphs( region.current.textContent === '' )
				}
				onInput={ said }
				onKeyDown={ onKeyDown }
				onPaste={ onPaste }
			/>
		</div>
	);
}

/**
 * The formatting buttons: one tab stop, moved along with the arrow keys.
 *
 * @param {Object}   props
 * @param {string}   props.controls The id of the text box they act on.
 * @param {Object}   props.on       Which formatting is on where the caret is, by command.
 * @param {boolean}  props.disabled When true, none of them can be pressed.
 * @param {Function} props.onRun    Called with the command of the button pressed.
 * @return {import('react').ReactElement} The toolbar.
 */
function Tools( { controls, on, disabled, onRun } ) {
	const buttons = useRef( [] );
	const [ reached, setReached ] = useState( 0 );

	// Towards the end of the line with the arrow that points there in the direction the page
	// is read. The admin says its direction on the document, and a screen may say another on
	// a part of itself.
	const onKeyDown = ( event, index ) => {
		const stated = event.currentTarget.closest( '[dir]' );
		const backwards =
			( stated || document.documentElement ).getAttribute( 'dir' ) ===
			'rtl';
		const step = {
			ArrowRight: backwards ? -1 : 1,
			ArrowLeft: backwards ? 1 : -1,
		}[ event.key ];
		let next = null;
		if ( step ) {
			next = ( index + step + TOOLS.length ) % TOOLS.length;
		} else if ( event.key === 'Home' ) {
			next = 0;
		} else if ( event.key === 'End' ) {
			next = TOOLS.length - 1;
		}
		if ( next !== null ) {
			event.preventDefault();
			setReached( next );
			buttons.current[ next ].focus();
		}
	};

	return (
		<div
			className="corex-richtext__tools"
			role="toolbar"
			aria-label={ __( 'Formatting', 'corex' ) }
			aria-controls={ controls }
		>
			{ TOOLS.map( ( tool, index ) => (
				<button
					key={ tool.command }
					ref={ ( node ) => {
						buttons.current[ index ] = node;
					} }
					type="button"
					className="corex-richtext__tool"
					aria-label={ tool.label }
					aria-keyshortcuts={ tool.keys.spoken }
					aria-pressed={ on[ tool.command ] ? 'true' : 'false' }
					title={ sprintf(
						/* translators: 1: what a formatting button does, such as "Bold". 2: its keyboard shortcut, such as "Ctrl+B". */
						__( '%1$s (%2$s)', 'corex' ),
						tool.label,
						tool.keys.shown
					) }
					tabIndex={ index === reached ? 0 : -1 }
					disabled={ disabled }
					// A press must not take the selection out of the text it is about.
					onMouseDown={ ( event ) => event.preventDefault() }
					onClick={ () => onRun( tool.command ) }
					onFocus={ () => setReached( index ) }
					onKeyDown={ ( event ) => onKeyDown( event, index ) }
				>
					<span
						className={ `dashicons dashicons-${ tool.icon }` }
						aria-hidden="true"
					/>
				</button>
			) ) }
		</div>
	);
}

/**
 * Where a link goes, asked for in the page.
 *
 * @param {Object}   props
 * @param {string}   props.id       A prefix for the ids of its parts.
 * @param {Object}   props.linking  `{ address, existing, refused }`.
 * @param {Object}   props.field    A ref for the address field, which is focused when the form opens.
 * @param {Function} props.onChange Called with the address as it is typed.
 * @param {Function} props.onApply  Called with the address to make the link of; '' removes one.
 * @param {Function} props.onLeave  Called to go back to the text with nothing changed.
 * @return {import('react').ReactElement} The form.
 */
function LinkForm( { id, linking, field, onChange, onApply, onLeave } ) {
	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			onApply( linking.address );
		} else if ( event.key === 'Escape' ) {
			// The pane this is in closes on Escape too: this one is for the form. Prevented,
			// which is what keeps a dialog open; stopped, for anything else that listens.
			event.preventDefault();
			event.stopPropagation();
			onLeave();
		}
	};

	return (
		<div
			className="corex-richtext__link"
			role="group"
			// Its own name: the field inside it is the one called "Link address".
			aria-label={ __( 'Link', 'corex' ) }
		>
			<label htmlFor={ `${ id }-address` }>
				{ __( 'Link address', 'corex' ) }
			</label>
			<input
				id={ `${ id }-address` }
				ref={ field }
				type="text"
				inputMode="url"
				// An address reads one way, whichever way the page does.
				dir="ltr"
				value={ linking.address }
				aria-invalid={ linking.refused ? 'true' : undefined }
				aria-describedby={
					linking.refused ? `${ id }-refused` : undefined
				}
				onChange={ ( event ) => onChange( event.target.value ) }
				onKeyDown={ onKeyDown }
			/>
			<span className="corex-richtext__link-actions">
				<button
					type="button"
					className="button button-primary"
					onClick={ () => onApply( linking.address ) }
				>
					{ linking.existing
						? __( 'Change link', 'corex' )
						: __( 'Add link', 'corex' ) }
				</button>
				{ linking.existing && (
					<button
						type="button"
						className="button"
						onClick={ () => onApply( '' ) }
					>
						{ __( 'Remove link', 'corex' ) }
					</button>
				) }
				<button type="button" className="button" onClick={ onLeave }>
					{ __( 'Cancel', 'corex' ) }
				</button>
			</span>
			{ linking.refused && (
				<p
					id={ `${ id }-refused` }
					className="corex-richtext__refused"
					role="alert"
				>
					{ __(
						'Give a web address, an email address or a telephone number starting with tel:.',
						'corex'
					) }
				</p>
			) }
		</div>
	);
}
