/**
 * The copy field of a CoreX standalone page (spec 101, FR-011a): a read-only field holding a
 * value, and a button that puts it on the clipboard and says that it did. The markup is what
 * `StandalonePage::copyField()` writes; the button is written hidden and this script shows it.
 */

const LINK = 'http://example.test/?corex_preview=abc';

const FIELD =
	'<div class="corex-standalone__copy" data-corex-copy' +
	' data-copied="Copied." data-manual="Select and copy by hand.">' +
	'<div><label for="link">Preview link</label>' +
	'<p role="status" aria-live="polite" data-corex-copy-status></p></div>' +
	`<input id="link" type="text" readonly value="${ LINK }" data-corex-copy-value />` +
	'<button type="button" data-corex-copy-button hidden><span>Copy link</span></button>' +
	'</div>';

const run = () =>
	jest.isolateModules( () => {
		require( '../js/corex-copy-field.js' );
	} );

const parts = () => ( {
	field: document.querySelector( '[data-corex-copy]' ),
	input: document.querySelector( '[data-corex-copy-value]' ),
	button: document.querySelector( '[data-corex-copy-button]' ),
	status: document.querySelector( '[data-corex-copy-status]' ),
} );

/** Let the promise the click started settle. */
const settled = async () => {
	await Promise.resolve();
	await Promise.resolve();
	await Promise.resolve();
};

describe( 'corex-copy-field', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML = FIELD;
		document.execCommand = jest.fn( () => true );
	} );

	afterEach( () => {
		jest.useRealTimers();
		document.body.innerHTML = '';
		delete window.navigator.clipboard;
		delete document.execCommand;
	} );

	it( 'shows the button, which the page wrote hidden', () => {
		run();

		expect( parts().button.hidden ).toBe( false );
	} );

	it( 'selects the whole value when the field is focused', () => {
		run();
		const { input } = parts();

		input.focus();

		expect( input.selectionStart ).toBe( 0 );
		expect( input.selectionEnd ).toBe( LINK.length );
	} );

	it( 'puts the value on the clipboard and says so', async () => {
		const writeText = jest.fn( () => Promise.resolve() );
		window.navigator.clipboard = { writeText };
		run();
		const { field, button, status } = parts();

		button.click();
		await settled();

		expect( writeText ).toHaveBeenCalledWith( LINK );
		expect( document.execCommand ).not.toHaveBeenCalled();
		expect( status.textContent ).toBe( 'Copied.' );
		expect( field.classList.contains( 'is-copied' ) ).toBe( true );
	} );

	it( 'goes back to how it was, so a second press is said again', async () => {
		window.navigator.clipboard = { writeText: () => Promise.resolve() };
		run();
		const { field, button, status } = parts();

		button.click();
		await settled();
		jest.advanceTimersByTime( 4000 );

		expect( status.textContent ).toBe( '' );
		expect( field.classList.contains( 'is-copied' ) ).toBe( false );

		button.click();
		await settled();

		expect( status.textContent ).toBe( 'Copied.' );
	} );

	it( 'copies the selected field where there is no Clipboard API', async () => {
		run();
		const { input, button, status } = parts();

		button.click();
		await settled();

		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( input.selectionEnd ).toBe( LINK.length );
		expect( status.textContent ).toBe( 'Copied.' );
	} );

	it( 'copies the selected field when the Clipboard API refuses', async () => {
		window.navigator.clipboard = {
			writeText: () => Promise.reject( new Error( 'NotAllowedError' ) ),
		};
		run();
		const { button, status } = parts();

		button.click();
		await settled();

		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( status.textContent ).toBe( 'Copied.' );
	} );

	it.each( [
		[ 'answers that it did not', () => false ],
		[
			'throws',
			() => {
				throw new Error( 'not allowed' );
			},
		],
	] )(
		'leaves the value selected and says to copy it by hand when the older way %s',
		async ( _, command ) => {
			document.execCommand = jest.fn( command );
			run();
			const { field, input, button, status } = parts();

			button.click();
			await settled();

			expect( status.textContent ).toBe( 'Select and copy by hand.' );
			expect( field.classList.contains( 'is-copied' ) ).toBe( false );
			expect( document.activeElement ).toBe( input );
			expect( input.selectionEnd ).toBe( LINK.length );

			// It is what the person has to do next, so it stays until they press again.
			jest.advanceTimersByTime( 60000 );
			expect( status.textContent ).toBe( 'Select and copy by hand.' );
		}
	);

	it( 'binds a field once when the script is on the page twice', async () => {
		const writeText = jest.fn( () => Promise.resolve() );
		window.navigator.clipboard = { writeText };
		run();
		run();

		parts().button.click();
		await settled();

		expect( writeText ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does nothing on a page with no copy field', () => {
		document.body.innerHTML = '<main></main>';

		expect( run ).not.toThrow();
	} );
} );
