/**
 * What a paste and an editor's own markup become (spec 106, FR-002 to FR-004).
 *
 * Each case here is something a page, a word processor or a browser's editor writes, and was
 * found by putting it through: the reduction kept nothing it should not, and lost or joined
 * what it should have kept. What the reduction refuses is held in `replyState.test.js`.
 */
import { cleanReplyHtml, hasWords } from '../replyHtml.js';

const pasted = ( html ) => cleanReplyHtml( html, { blocksAsParagraphs: true } );
const written = ( html ) =>
	cleanReplyHtml( html, { blocksAsParagraphs: true, wholeMessage: true } );

describe( 'a line end in markup', () => {
	// Word and Outlook wrap the markup they put on the clipboard. The server takes a line end
	// for a line somebody typed, so one that is only layout must not reach it.
	it( 'is the space a browser draws, not a line', () => {
		expect(
			pasted(
				'<p class=MsoNormal>Dear Salma,\nthank you for\r\nwriting to us.</p>'
			)
		).toBe( '<p>Dear Salma, thank you for writing to us.</p>' );
	} );

	it( 'is a line in preformatted text, the one place it is', () => {
		expect( pasted( '<pre>First\nSecond</pre>' ) ).toBe(
			'<p>First<br>Second</p>'
		);
	} );
} );

describe( 'blocks a reply does not have', () => {
	it.each( [
		[ '<aside>One</aside><aside>Two</aside>' ],
		[ '<main>One</main><nav>Two</nav>' ],
		[ '<figure>One</figure><center>Two</center>' ],
		[ '<form>One</form><fieldset>Two</fieldset>' ],
		[ '<details><summary>One</summary></details><div>Two</div>' ],
		[ '<table><tr><td>One</td><td>Two</td></tr></table>' ],
		[ '<dl><dt>One</dt><dd>Two</dd></dl>' ],
	] )( '%s is two paragraphs', ( html ) => {
		expect( pasted( html ) ).toBe( '<p>One</p><p>Two</p>' );
	} );

	it( 'keeps what is above a rule apart from what is below it', () => {
		expect( pasted( 'One<hr>Two' ) ).toBe( '<p>One</p><p>Two</p>' );
		expect( pasted( '<ul><li>One<hr>Two</li></ul>' ) ).toBe(
			'<ul><li>One<br>Two</li></ul>'
		);
	} );

	it( 'keeps a table apart from the paragraph before it', () => {
		expect( pasted( '<p>Para<table><tr><td>cell</td></tr></table>' ) ).toBe(
			'<p>Para</p><p>cell</p>'
		);
	} );

	it( 'makes a list item that is in no list a paragraph', () => {
		expect( written( '<li>One</li><li>Two</li>' ) ).toBe(
			'<p>One</p><p>Two</p>'
		);
		expect( cleanReplyHtml( '<li>One</li>' ) ).toBe( 'One' );
	} );

	it( 'drops the title of a pasted document, which is not in its text', () => {
		expect(
			pasted(
				'<html><head><title>Word doc</title></head><body><p>Hello</p></body></html>'
			)
		).toBe( '<p>Hello</p>' );
	} );
} );

describe( 'emphasis or a link written around blocks', () => {
	// A paragraph cannot stand inside a link: read again, the markup came apart and the link
	// had lost its address.
	it( 'goes inside each of them, a link with its address', () => {
		const card = pasted(
			'<a href="https://example.com/p"><div>Card title</div><div>Card text</div></a>'
		);

		expect( card ).toBe(
			'<p><a href="https://example.com/p">Card title</a></p>' +
				'<p><a href="https://example.com/p">Card text</a></p>'
		);
		// And it is the same read a second time, which is what it is posted as.
		expect( written( card ) ).toBe( card );
	} );

	it( 'reaches the items of a list and the text beside it', () => {
		expect(
			pasted( '<strong>Note <ul><li>One</li><li>Two</li></ul></strong>' )
		).toBe(
			'<strong>Note </strong><ul><li><strong>One</strong></li><li><strong>Two</strong></li></ul>'
		);
	} );

	it( 'leaves what a link around a block does not go anywhere as its words', () => {
		expect(
			pasted( '<a href="javascript:alert(1)"><div>Card</div></a>' )
		).toBe( '<p>Card</p>' );
	} );
} );

describe( 'what a word processor writes', () => {
	// Google Docs wraps what is copied in a `<b>` it says is not bold, and says real bold and
	// italic with a style on a span.
	it( 'reads emphasis from the style that says it, and not from a wrapper that says there is none', () => {
		expect(
			pasted(
				'<b style="font-weight:normal" id="docs-internal-guid-1">' +
					'<p><span style="font-weight:700">Bold</span> and ' +
					'<span style="font-style:italic">italic</span> and ' +
					'<span style="font-weight:400;color:red">plain</span></p>' +
					'<p><span style="font-weight:bold;font-style:italic">Both</span></p></b>'
			)
		).toBe(
			'<p><strong>Bold</strong> and <em>italic</em> and plain</p>' +
				'<p><strong><em>Both</em></strong></p>'
		);
	} );

	it( 'leaves no emphasis with nothing in it', () => {
		expect(
			pasted(
				'<p>One<span style="font-weight:700"> </span>two<b></b></p>'
			)
		).toBe( '<p>One two</p>' );
	} );
} );

describe( 'whether a reply says anything', () => {
	it.each( [ [ '<p>Hello</p>' ], [ 'Hello' ], [ '<ul><li>One</li></ul>' ] ] )(
		'%s does',
		( html ) => {
			expect( hasWords( html ) ).toBe( true );
		}
	);

	it.each( [
		[ '' ],
		[ '<p><br></p>' ],
		[ '<p>&nbsp;</p><p> </p>' ],
		[ '<ul><li></li></ul>' ],
		[ undefined ],
	] )( '%s does not', ( html ) => {
		expect( hasWords( html ) ).toBe( false );
	} );
} );
