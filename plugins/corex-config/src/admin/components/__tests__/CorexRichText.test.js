/**
 * The reply editor, rendered (spec 106, FR-001 to FR-003, FR-007).
 *
 * A test's document does not edit: it has no `execCommand`. So what is held here is the editor's
 * own part: what it is to assistive technology, which command a key or a button asks the browser
 * for, what it gives back, and what a paste becomes. What the browser then does with a command
 * is the browser suite's, on a real one.
 */
import { createRoot, useState } from '@wordpress/element';
// eslint-disable-next-line import/no-extraneous-dependencies
import { act } from 'react';

import CorexRichText from '../CorexRichText.js';

let container;
let root;
let asked;
let changes;

function Editor( { start = '', disabled = false } ) {
	const [ value, setValue ] = useState( start );

	return (
		<div>
			<span id="message-label">Message</span>
			<CorexRichText
				value={ value }
				onChange={ ( next ) => {
					changes.push( next );
					setValue( next );
				} }
				labelledBy="message-label"
				describedBy="message-note"
				disabled={ disabled }
			/>
			<button type="button" onClick={ () => setValue( '' ) }>
				Empty it
			</button>
		</div>
	);
}

function show( props = {} ) {
	act( () => {
		root.render( <Editor { ...props } /> );
	} );
}

const text = () => container.querySelector( '[role="textbox"]' );
const tool = ( name ) =>
	container.querySelector(
		`[role="toolbar"] button[aria-label="${ name }"]`
	);
const linkField = () =>
	container.querySelector( '.corex-richtext__link input' );

function write( html ) {
	act( () => {
		text().innerHTML = html;
		text().dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

function press( target, key, more = {} ) {
	const event = new window.KeyboardEvent( 'keydown', {
		key,
		bubbles: true,
		cancelable: true,
		...more,
	} );
	act( () => {
		target.dispatchEvent( event );
	} );

	return event;
}

function typeInto( field, value ) {
	const { set } = Object.getOwnPropertyDescriptor(
		Object.getPrototypeOf( field ),
		'value'
	);
	act( () => {
		set.call( field, value );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	asked = [];
	changes = [];
	document.execCommand = jest.fn( ( ...call ) => {
		asked.push( call );
		return true;
	} );
	document.queryCommandState = jest.fn( () => false );
	container = document.createElement( 'div' );
	document.body.appendChild( container );
	root = createRoot( container );
} );

afterEach( () => {
	act( () => root.unmount() );
	document.body.innerHTML = '';
	document.documentElement.removeAttribute( 'dir' );
	delete document.execCommand;
	delete document.queryCommandState;
} );

describe( 'what it is to a keyboard and a screen reader', () => {
	it( 'is a multi-line text box named by its label and described by what is said about it', () => {
		show();

		expect( text().getAttribute( 'aria-multiline' ) ).toBe( 'true' );
		expect( text().getAttribute( 'aria-labelledby' ) ).toBe(
			'message-label'
		);
		expect( text().getAttribute( 'aria-describedby' ) ).toBe(
			'message-note'
		);
		expect( text().getAttribute( 'contenteditable' ) ).toBe( 'true' );
	} );

	it( 'has a toolbar of five named buttons that say whether they are on, and is one tab stop', () => {
		show();
		const toolbar = container.querySelector( '[role="toolbar"]' );
		const buttons = [ ...toolbar.querySelectorAll( 'button' ) ];

		expect( toolbar.getAttribute( 'aria-label' ) ).toBe( 'Formatting' );
		expect( toolbar.getAttribute( 'aria-controls' ) ).toBe( text().id );
		expect(
			buttons.map( ( button ) => button.getAttribute( 'aria-label' ) )
		).toEqual( [
			'Bold',
			'Italic',
			'Bulleted list',
			'Numbered list',
			'Link',
		] );
		expect(
			buttons.map( ( button ) => button.getAttribute( 'aria-pressed' ) )
		).toEqual( [ 'false', 'false', 'false', 'false', 'false' ] );
		expect( buttons.map( ( button ) => button.tabIndex ) ).toEqual( [
			0, -1, -1, -1, -1,
		] );
	} );

	it( 'moves along the toolbar with the arrow keys, and the other way where the page reads right to left', () => {
		show();
		act( () => tool( 'Bold' ).focus() );

		press( tool( 'Bold' ), 'ArrowRight' );
		expect( document.activeElement ).toBe( tool( 'Italic' ) );
		expect( tool( 'Italic' ).tabIndex ).toBe( 0 );
		expect( tool( 'Bold' ).tabIndex ).toBe( -1 );

		press( tool( 'Italic' ), 'End' );
		expect( document.activeElement ).toBe( tool( 'Link' ) );
		press( tool( 'Link' ), 'ArrowRight' );
		expect( document.activeElement ).toBe( tool( 'Bold' ) );

		document.documentElement.setAttribute( 'dir', 'rtl' );
		press( tool( 'Bold' ), 'ArrowLeft' );
		expect( document.activeElement ).toBe( tool( 'Italic' ) );
	} );

	it( 'says which formatting is on where the caret is', () => {
		show( { start: '<p>Hello</p>' } );
		document.queryCommandState.mockImplementation(
			( command ) => command === 'bold'
		);
		act( () => {
			const range = document.createRange();
			range.selectNodeContents( text().querySelector( 'p' ) );
			document.getSelection().removeAllRanges();
			document.getSelection().addRange( range );
			document.dispatchEvent( new window.Event( 'selectionchange' ) );
		} );

		expect( tool( 'Bold' ).getAttribute( 'aria-pressed' ) ).toBe( 'true' );
		expect( tool( 'Italic' ).getAttribute( 'aria-pressed' ) ).toBe(
			'false'
		);
	} );

	// A command with nothing selected changes what is on and moves no caret, so the browser
	// announces no change of selection: the button said "off" until the next letter was typed.
	it.each( [
		[ 'the button', () => act( () => tool( 'Bold' ).click() ) ],
		[ 'the keys', () => press( text(), 'b', { ctrlKey: true } ) ],
	] )(
		'says a command is on as soon as %s turned it on, the caret not having moved',
		( _, turnOn ) => {
			show( { start: '<p>Hello</p>' } );
			act( () => {
				const range = document.createRange();
				range.selectNodeContents( text().querySelector( 'p' ) );
				range.collapse( false );
				document.getSelection().removeAllRanges();
				document.getSelection().addRange( range );
			} );
			// What the browser answers once it has been asked for bold.
			document.execCommand.mockImplementation( ( ...call ) => {
				asked.push( call );
				document.queryCommandState.mockImplementation(
					( command ) => command === 'bold'
				);
				return true;
			} );

			turnOn();

			expect( tool( 'Bold' ).getAttribute( 'aria-pressed' ) ).toBe(
				'true'
			);
		}
	);

	it( 'tells assistive technology each shortcut on either kind of keyboard, and shows it', () => {
		show();

		expect( tool( 'Bold' ).getAttribute( 'aria-keyshortcuts' ) ).toBe(
			'Control+B Meta+B'
		);
		expect(
			tool( 'Bulleted list' ).getAttribute( 'aria-keyshortcuts' )
		).toBe( 'Control+Shift+8 Meta+Shift+8' );
		expect( tool( 'Bold' ).title ).toBe( 'Bold (Ctrl+B)' );
		expect( tool( 'Numbered list' ).title ).toBe(
			'Numbered list (Ctrl+Shift+7)'
		);
	} );
} );

describe( 'what it gives back', () => {
	it( "gives back what was written as what a reply may hold, with an editor's blocks as paragraphs", () => {
		show();

		write(
			'<div>Hello Salma,</div><div>We will <b>call</b> you.</div><script>steal()</script>'
		);

		expect( changes ).toEqual( [
			'<p>Hello Salma,</p><p>We will <b>call</b> you.</p>',
		] );
	} );

	it( 'leaves what is in the box alone while it is being written, so the caret stays', () => {
		show();
		write( '<p>Hello</p>' );
		const paragraph = text().querySelector( 'p' );
		act( () => {
			const more = document.createElement( 'p' );
			more.textContent = 'More';
			text().appendChild( more );
			text().dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
		} );

		// The paragraph the caret was in is the one still there: the editor did not write
		// the box again from the value it gave back.
		expect( paragraph.isConnected ).toBe( true );
		expect( text().firstChild ).toBe( paragraph );
		expect( changes ).toEqual( [
			'<p>Hello</p>',
			'<p>Hello</p><p>More</p>',
		] );
	} );

	it( 'is emptied when the message is emptied from outside', () => {
		show( { start: '<p>Sent already</p>' } );
		expect( text().textContent ).toBe( 'Sent already' );

		act( () => {
			[ ...container.querySelectorAll( 'button' ) ]
				.find( ( button ) => button.textContent === 'Empty it' )
				.click();
		} );

		expect( text().innerHTML ).toBe( '' );
	} );
} );

describe( 'the first line', () => {
	it( 'is made a paragraph as it is written, where a browser would leave it bare', () => {
		show();
		act( () => {
			text().textContent = 'Hello';
			const range = document.createRange();
			range.setStart( text().firstChild, 5 );
			range.collapse( true );
			document.getSelection().removeAllRanges();
			document.getSelection().addRange( range );
			text().dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
		} );

		expect( asked ).toContainEqual( [ 'formatBlock', false, 'p' ] );
		// And what is given back is a paragraph whether or not the browser has caught up.
		expect( changes.at( -1 ) ).toBe( '<p>Hello</p>' );
	} );

	it( 'is left alone once it is in a paragraph', () => {
		show( { start: '<p>Hello</p>' } );
		act( () => {
			const range = document.createRange();
			range.setStart( text().querySelector( 'p' ).firstChild, 5 );
			range.collapse( true );
			document.getSelection().removeAllRanges();
			document.getSelection().addRange( range );
			text().dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
		} );

		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'formatBlock'
		);
	} );
} );

describe( 'the direction a paragraph reads in', () => {
	it( 'is its own, and is not in what is given back', () => {
		show( { start: '<p>Hello</p><ul><li>One</li></ul>' } );

		expect(
			Array.from( text().children ).map( ( block ) =>
				block.getAttribute( 'dir' )
			)
		).toEqual( [ 'auto', 'auto' ] );
		// The text box itself follows the page, so an empty one starts where the page does.
		expect( text().hasAttribute( 'dir' ) ).toBe( false );

		act( () => {
			const added = document.createElement( 'p' );
			added.textContent = 'مرحبا';
			text().appendChild( added );
			text().dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
		} );

		expect( text().lastChild.getAttribute( 'dir' ) ).toBe( 'auto' );
		expect( changes.at( -1 ) ).toBe(
			'<p>Hello</p><ul><li>One</li></ul><p>مرحبا</p>'
		);
	} );
} );

describe( 'the direction of a paragraph written after a list', () => {
	// A browser leaves the list, and every paragraph written after it, inside the paragraph
	// the list was started in. A page's own reader would not build this, so it is built here.
	it( 'is its own too, though a browser leaves it inside another paragraph', () => {
		show( { start: '<p>First line</p>' } );
		act( () => {
			const outer = document.createElement( 'p' );
			const list = document.createElement( 'ul' );
			list.appendChild( document.createElement( 'li' ) ).textContent =
				'One';
			const after = document.createElement( 'p' );
			after.textContent = 'مرحبا';
			outer.append( list, after );
			text().appendChild( outer );
			text().dispatchEvent(
				new window.Event( 'input', { bubbles: true } )
			);
		} );

		expect(
			Array.from( text().querySelectorAll( 'p, ul' ) ).map( ( block ) =>
				block.getAttribute( 'dir' )
			)
		).toEqual( [ 'auto', 'auto', 'auto', 'auto' ] );
		expect( changes.at( -1 ) ).toBe(
			'<p>First line</p><ul><li>One</li></ul><p>مرحبا</p>'
		);
	} );
} );

describe( 'formatting', () => {
	it.each( [
		[ 'Bold', 'bold' ],
		[ 'Italic', 'italic' ],
		[ 'Bulleted list', 'insertUnorderedList' ],
		[ 'Numbered list', 'insertOrderedList' ],
	] )( 'the %s button asks the browser for %s', ( name, command ) => {
		show();

		act( () => tool( name ).click() );

		expect( asked ).toContainEqual( [ command, false ] );
	} );

	it.each( [
		[ 'b', { ctrlKey: true }, 'bold' ],
		[ 'i', { metaKey: true }, 'italic' ],
		[ '8', { ctrlKey: true, shiftKey: true }, 'insertUnorderedList' ],
		[ '7', { ctrlKey: true, shiftKey: true }, 'insertOrderedList' ],
	] )(
		'the keys %s %j ask for it too, and the browser does nothing of its own',
		( key, more, command ) => {
			show();

			const event = press( text(), key, more );

			expect( asked ).toContainEqual( [ command, false ] );
			expect( event.defaultPrevented ).toBe( true );
		}
	);

	it( 'leaves every other key to the browser', () => {
		show();

		const event = press( text(), 'a', { ctrlKey: true } );

		expect( event.defaultPrevented ).toBe( false );
		expect( asked ).toEqual( [] );
	} );

	it( 'does not take the selection out of the text when a button is pressed', () => {
		show();
		const down = new window.MouseEvent( 'mousedown', {
			bubbles: true,
			cancelable: true,
		} );

		act( () => {
			tool( 'Bold' ).dispatchEvent( down );
		} );

		expect( down.defaultPrevented ).toBe( true );
	} );

	it( 'writes paragraphs, not divisions, from the moment the box is entered', () => {
		show();

		act( () => text().focus() );

		expect( asked ).toContainEqual( [
			'defaultParagraphSeparator',
			false,
			'p',
		] );
		// And the first line is a paragraph too, which a browser would leave as bare text.
		expect( asked ).toContainEqual( [ 'formatBlock', false, 'p' ] );
	} );

	it( 'formats nothing and cannot be written in while it is off', () => {
		show( { disabled: true } );

		act( () => tool( 'Bold' ).click() );
		press( text(), 'b', { ctrlKey: true } );

		// It can still be reached, to be read: entering it, and a paste, ask nothing either.
		act( () => text().focus() );
		const paste = new window.Event( 'paste', {
			bubbles: true,
			cancelable: true,
		} );
		paste.clipboardData = { getData: () => '<p>Pasted</p>' };
		const typed = new window.Event( 'beforeinput', {
			bubbles: true,
			cancelable: true,
		} );
		typed.inputType = 'insertText';
		act( () => {
			text().dispatchEvent( paste );
			text().dispatchEvent( typed );
		} );

		expect( asked ).toEqual( [] );
		expect( paste.defaultPrevented ).toBe( true );
		expect( typed.defaultPrevented ).toBe( true );
		expect( tool( 'Bold' ).disabled ).toBe( true );
		expect( text().getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( text().getAttribute( 'contenteditable' ) ).toBe( 'false' );
	} );
} );

describe( 'a link', () => {
	it( 'is asked for in a form in the page, with the keyboard in its field', () => {
		show();

		press( text(), 'k', { ctrlKey: true } );

		expect( linkField() ).not.toBeNull();
		expect( document.activeElement ).toBe( linkField() );
		expect(
			container
				.querySelector( '.corex-richtext__link label' )
				.getAttribute( 'for' )
		).toBe( linkField().id );
	} );

	it( 'is written as its address where nothing was selected, with the scheme it meant', () => {
		show();
		act( () => tool( 'Link' ).click() );
		typeInto( linkField(), 'example.com/offer' );

		press( linkField(), 'Enter' );

		expect( asked ).toContainEqual( [
			'insertHTML',
			false,
			'<a href="https://example.com/offer">example.com/offer</a>',
		] );
		expect( linkField() ).toBeNull();
	} );

	it( 'refuses an address a reader of an email could not follow, and says what one is', () => {
		show();
		act( () => tool( 'Link' ).click() );
		typeInto( linkField(), 'javascript:alert(1)' );

		press( linkField(), 'Enter' );

		expect( linkField().getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect(
			container.querySelector( '[role="alert"]' ).textContent
		).toContain( 'web address' );
		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'createLink'
		);
		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'insertHTML'
		);
	} );

	it( 'is left with Escape, which changes nothing and closes nothing else', () => {
		show();
		act( () => tool( 'Link' ).click() );
		const closedSomethingElse = jest.fn();
		// What is around the editor: a pane that closes on Escape, say.
		document.body.addEventListener( 'keydown', closedSomethingElse );

		const escape = press( linkField(), 'Escape' );

		expect( linkField() ).toBeNull();
		// Prevented is what keeps the dialog the editor is in open: a dialog closes on an
		// Escape nobody prevented, whoever stopped it on the way.
		expect( escape.defaultPrevented ).toBe( true );
		expect( closedSomethingElse ).not.toHaveBeenCalled();
		expect( document.activeElement ).toBe( text() );
		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'createLink'
		);
	} );

	it( 'changes the address of the link the caret is in, and can remove it', () => {
		show( {
			start: '<p>See <a href="https://old.example.com">the offer</a>.</p>',
		} );
		const inLink = () => {
			const range = document.createRange();
			range.selectNodeContents( text().querySelector( 'a' ) );
			range.collapse( true );
			document.getSelection().removeAllRanges();
			document.getSelection().addRange( range );
		};

		act( inLink );
		act( () => tool( 'Link' ).click() );
		expect( linkField().value ).toBe( 'https://old.example.com' );
		typeInto( linkField(), 'https://new.example.com' );
		press( linkField(), 'Enter' );

		expect( text().querySelector( 'a' ).getAttribute( 'href' ) ).toBe(
			'https://new.example.com'
		);
		expect( changes.at( -1 ) ).toContain( 'https://new.example.com' );

		act( inLink );
		act( () => tool( 'Link' ).click() );
		act( () => {
			[ ...container.querySelectorAll( '.corex-richtext__link button' ) ]
				.find( ( button ) => button.textContent === 'Remove link' )
				.click();
		} );

		expect( asked ).toContainEqual( [ 'unlink', false ] );
	} );
} );

describe( 'a link, and what is selected', () => {
	const select = ( node ) => {
		const range = document.createRange();
		range.selectNodeContents( node );
		document.getSelection().removeAllRanges();
		document.getSelection().addRange( range );
	};

	it( 'is made of the words selected in the text', () => {
		show( { start: '<p>See the offer.</p>' } );
		act( () => select( text().querySelector( 'p' ) ) );

		act( () => tool( 'Link' ).click() );
		typeInto( linkField(), 'example.com/offer' );
		press( linkField(), 'Enter' );

		expect( asked ).toContainEqual( [
			'createLink',
			false,
			'https://example.com/offer',
		] );
		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'insertHTML'
		);
	} );

	// What is selected elsewhere on the page is not the editor's to make a link of: the
	// address is written at the end of the message instead.
	it( 'is not made of what is selected outside the text', () => {
		show( { start: '<p>See the offer.</p>' } );
		const elsewhere = document.createElement( 'p' );
		elsewhere.innerHTML =
			'Not <a href="https://elsewhere.example">mine</a>';
		document.body.appendChild( elsewhere );
		act( () => select( elsewhere.querySelector( 'a' ) ) );

		act( () => tool( 'Link' ).click() );
		expect( linkField().value ).toBe( '' );
		typeInto( linkField(), 'example.com' );
		press( linkField(), 'Enter' );

		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain(
			'createLink'
		);
		expect( asked ).toContainEqual( [
			'insertHTML',
			false,
			'<a href="https://example.com">example.com</a>',
		] );
		const caret = document.getSelection();
		expect( text().contains( caret.anchorNode ) ).toBe( true );
		expect( elsewhere.querySelector( 'a' ).getAttribute( 'href' ) ).toBe(
			'https://elsewhere.example'
		);
	} );

	it( 'is not made, and nothing is removed, where no address was given', () => {
		show( { start: '<p>See the offer.</p>' } );
		act( () => select( text().querySelector( 'p' ) ) );
		act( () => tool( 'Link' ).click() );

		press( linkField(), 'Enter' );

		expect( linkField() ).toBeNull();
		expect( asked.map( ( call ) => call[ 0 ] ) ).not.toContain( 'unlink' );
		expect( changes ).toEqual( [] );
	} );

	it( 'has an address field that reads left to right whichever way the page does', () => {
		document.documentElement.setAttribute( 'dir', 'rtl' );
		show();
		act( () => tool( 'Link' ).click() );

		expect( linkField().getAttribute( 'dir' ) ).toBe( 'ltr' );
	} );
} );

describe( 'what a browser would do by its own means', () => {
	const before = ( inputType, more = {} ) => {
		const event = new window.Event( 'beforeinput', {
			bubbles: true,
			cancelable: true,
		} );
		Object.assign( event, { inputType, ...more } );
		act( () => {
			text().dispatchEvent( event );
		} );

		return event;
	};

	// Underline is Ctrl+U in every browser. Drawn in the box, it would be missing from the
	// email: what the editor shows is what is sent.
	it.each( [
		[ 'formatUnderline' ],
		[ 'formatStrikeThrough' ],
		[ 'formatFontColor' ],
		[ 'formatJustifyCenter' ],
	] )( 'refuses %s, which a reply does not have', ( inputType ) => {
		show();

		expect( before( inputType ).defaultPrevented ).toBe( true );
	} );

	it.each( [
		[ 'formatBold' ],
		[ 'formatItalic' ],
		[ 'insertText' ],
		[ 'insertParagraph' ],
		[ 'deleteContentBackward' ],
		[ 'historyUndo' ],
	] )( 'leaves %s to the browser', ( inputType ) => {
		show();

		expect( before( inputType ).defaultPrevented ).toBe( false );
	} );

	it( 'reduces what is dragged in as it does what is pasted, where it was dropped', () => {
		show( { start: '<p>Hello</p>' } );
		const word = text().querySelector( 'p' ).firstChild;

		const drop = before( 'insertFromDrop', {
			dataTransfer: {
				getData: ( type ) =>
					type === 'text/html'
						? '<span style="color:red"><img src="x" onerror="steal()">big <u>news</u></span>'
						: '',
			},
			getTargetRanges: () => [
				{
					startContainer: word,
					startOffset: 2,
					endContainer: word,
					endOffset: 2,
				},
			],
		} );

		expect( drop.defaultPrevented ).toBe( true );
		expect( asked ).toContainEqual( [ 'insertHTML', false, 'big news' ] );
		expect( document.getSelection().anchorNode ).toBe( word );
		expect( document.getSelection().anchorOffset ).toBe( 2 );
	} );
} );

describe( 'what it is given', () => {
	// The next caller hands it a stored draft. What it is handed goes on the page, so it is
	// reduced on the way in as it is on the way out.
	it( 'is reduced before it is put on the page', () => {
		show( {
			start:
				'<p>Draft</p><img src="x" onerror="steal()">' +
				'<a href="javascript:alert(1)" style="color:red">x</a><script>steal()</script>',
		} );

		expect( text().querySelector( 'img, script, a, [style]' ) ).toBeNull();
		expect( text().textContent ).toBe( 'Draftx' );
	} );
} );

describe( 'the label that names it', () => {
	it( 'puts the keyboard in the text when it is pressed, as a label does', () => {
		show();

		act( () => document.getElementById( 'message-label' ).click() );

		expect( document.activeElement ).toBe( text() );
	} );
} );

describe( 'a paste', () => {
	it( 'puts in what a reply may hold of what was copied, and not what the browser would have', () => {
		show();
		const paste = new window.Event( 'paste', {
			bubbles: true,
			cancelable: true,
		} );
		paste.clipboardData = {
			getData: ( type ) =>
				type === 'text/html'
					? '<div style="color:red"><p style="font-size:40px">Two <b>things</b></p><img src="x" onerror="steal()"></div>'
					: 'Two things',
		};

		act( () => {
			text().dispatchEvent( paste );
		} );

		expect( paste.defaultPrevented ).toBe( true );
		expect( asked ).toContainEqual( [
			'insertHTML',
			false,
			'<p>Two <b>things</b></p>',
		] );
	} );

	// A paste made by a script, or by a browser that carries nothing with it.
	it( 'puts in nothing, and does not fail, when the paste carries nothing', () => {
		show();
		const paste = new window.Event( 'paste', {
			bubbles: true,
			cancelable: true,
		} );

		act( () => {
			text().dispatchEvent( paste );
		} );

		expect( paste.defaultPrevented ).toBe( true );
		expect( asked ).toEqual( [] );
	} );
} );
