/**
 * What the reply editor's keys and paste mean, as plain functions (spec 106, FR-001 to FR-003).
 */
import { linkAddress, pastedMarkup, shortcutOf } from '../richTextCommands.js';

const key = ( pressed, more = {} ) => ( {
	key: pressed,
	ctrlKey: false,
	metaKey: false,
	shiftKey: false,
	altKey: false,
	...more,
} );

describe( 'a keyboard shortcut', () => {
	it.each( [
		[ 'b', { ctrlKey: true }, 'bold' ],
		[ 'B', { metaKey: true }, 'bold' ],
		[ 'i', { ctrlKey: true }, 'italic' ],
		[ 'k', { ctrlKey: true }, 'link' ],
		[ '8', { ctrlKey: true, shiftKey: true }, 'bulleted' ],
		[ '7', { metaKey: true, shiftKey: true }, 'numbered' ],
	] )( '%s with %j is %s', ( pressed, more, command ) => {
		expect( shortcutOf( key( pressed, more ) ) ).toBe( command );
	} );

	it.each( [
		[ 'b', {} ],
		[ 'b', { ctrlKey: true, altKey: true } ],
		[ 'x', { ctrlKey: true } ],
		[ '8', { ctrlKey: true } ],
	] )(
		'%s with %j is no command, and is left to the browser',
		( pressed, more ) => {
			expect( shortcutOf( key( pressed, more ) ) ).toBeNull();
		}
	);

	// Where the key writes no Latin letter or digit, it is known by its place: an Arabic and a
	// Russian keyboard, and the digits of a German one with Shift held.
	it.each( [
		[ 'لا', 'KeyB', { ctrlKey: true }, 'bold' ],
		[ 'ш', 'KeyI', { ctrlKey: true }, 'italic' ],
		[ 'ن', 'KeyK', { metaKey: true }, 'link' ],
		[ '(', 'Digit8', { ctrlKey: true, shiftKey: true }, 'bulleted' ],
		[ '/', 'Digit7', { ctrlKey: true, shiftKey: true }, 'numbered' ],
		[ '*', 'Digit8', { ctrlKey: true, shiftKey: true }, 'bulleted' ],
	] )( '%s on the key %s with %j is %s', ( pressed, code, more, command ) => {
		expect( shortcutOf( key( pressed, { code, ...more } ) ) ).toBe(
			command
		);
	} );

	// A keyboard whose Latin letters are laid out differently keeps its own B: on Dvorak the
	// letter X is where QWERTY has B.
	it( 'reads a Latin letter as the letter, not as the place its key has elsewhere', () => {
		expect(
			shortcutOf( key( 'x', { code: 'KeyB', ctrlKey: true } ) )
		).toBeNull();
		expect(
			shortcutOf( key( 'b', { code: 'KeyN', ctrlKey: true } ) )
		).toBe( 'bold' );
		expect(
			shortcutOf(
				key( '(', { code: 'Digit9', ctrlKey: true, shiftKey: true } )
			)
		).toBeNull();
	} );
} );

describe( 'where a link goes', () => {
	it.each( [
		[ 'https://example.com/offer', 'https://example.com/offer' ],
		[ '  http://example.com  ', 'http://example.com' ],
		[ 'example.com/offer', 'https://example.com/offer' ],
		[ 'www.example.com', 'https://www.example.com' ],
		[ 'sales@example.com', 'mailto:sales@example.com' ],
		[ 'mailto:sales@example.com', 'mailto:sales@example.com' ],
		[ 'tel:+201001234567', 'tel:+201001234567' ],
		[ 'tel:+20 100 123 4567', 'tel:+20 100 123 4567' ],
		[ 'https://example.com/a b.pdf', 'https://example.com/a%20b.pdf' ],
		[ 'example.com', 'https://example.com' ],
	] )( '%s goes to %s', ( written, address ) => {
		expect( linkAddress( written ) ).toBe( address );
	} );

	it.each( [
		[ '' ],
		[ '   ' ],
		[ 'javascript:alert(1)' ],
		[ 'data:text/html,x' ],
		[ 'pricing' ],
		[ '/pricing' ],
		[ '#top' ],
		[ '...' ],
		[ 'example.' ],
		[ 'http://a b.com' ],
		[ 'mailto:' ],
	] )( '%s goes nowhere a reader of an email could follow', ( written ) => {
		expect( linkAddress( written ) ).toBe( '' );
	} );
} );

describe( 'what is pasted', () => {
	const clipboard = ( data ) => ( {
		getData: ( type ) => data[ type ] || '',
	} );

	it( 'keeps paragraphs, emphasis, lists and links of a page, and drops the rest of its styling', () => {
		const pasted = pastedMarkup(
			clipboard( {
				'text/html':
					'<meta charset="utf-8"><div style="color:red"><h2 class="t">Our offer</h2>' +
					'<p style="font-size:40px">Two <b>things</b> and <a href="https://example.com/x" target="_blank" style="color:red">a link</a>.</p>' +
					'<ul class="list"><li>One</li></ul><img src="https://example.com/pixel.gif"><script>steal()</script></div>',
				'text/plain': 'ignored',
			} )
		);

		expect( pasted ).toBe(
			'<p>Our offer</p><p>Two <b>things</b> and <a href="https://example.com/x">a link</a>.</p><ul><li>One</li></ul>'
		);
	} );

	it( 'keeps the lines of plain text as paragraphs and line breaks, and none of it as markup', () => {
		const pasted = pastedMarkup(
			clipboard( {
				'text/plain':
					'Hello <b>Salma</b>,\n\nWe will call you.\nRegards',
			} )
		);

		expect( pasted ).toBe(
			'<p>Hello &lt;b&gt;Salma&lt;/b&gt;,</p><p>We will call you.<br>Regards</p>'
		);
	} );

	// Pasted into a sentence, a word keeps the space it was copied with, and stands in no
	// paragraph of its own.
	it( 'puts a word or a line of plain text into the sentence it is pasted into', () => {
		expect( pastedMarkup( clipboard( { 'text/plain': 'big ' } ) ) ).toBe(
			'big '
		);
		expect(
			pastedMarkup( clipboard( { 'text/plain': ' a <b>\r\nline\n' } ) )
		).toBe( ' a &lt;b&gt;<br>line' );
	} );

	it( 'pastes nothing from nothing', () => {
		expect( pastedMarkup( clipboard( {} ) ) ).toBe( '' );
	} );
} );
