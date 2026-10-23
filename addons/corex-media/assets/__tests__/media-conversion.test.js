/**
 * "Convert existing library" draws a run as the server answers it (spec 119, US-SET-06).
 */
const { isActive, show } = require( '../media-conversion.js' );

function panel( available = '1' ) {
	document.body.innerHTML =
		`<div data-corex-media-conversion data-available="${ available }">` +
		'<button data-corex-conversion-start hidden></button>' +
		'<button data-corex-conversion-cancel hidden></button>' +
		'<progress data-corex-conversion-progress max="1" value="0" hidden></progress>' +
		'<p data-corex-conversion-summary>Not run yet.</p>' +
		'<div data-corex-conversion-failures hidden><ul></ul></div></div>';

	return document.querySelector( '[data-corex-media-conversion]' );
}

afterEach( () => {
	document.body.innerHTML = '';
} );

it( 'shows a running conversion with its progress and a way to cancel', () => {
	const box = panel();
	show( box, {
		active: true,
		total: 40,
		processed: 12,
		summary: 'Converting: 10 converted, 2 skipped, 0 failed, of 40 images.',
		failures: [],
	} );

	expect( box.querySelector( '[data-corex-conversion-start]' ).hidden ).toBe(
		true
	);
	expect( box.querySelector( '[data-corex-conversion-cancel]' ).hidden ).toBe(
		false
	);
	expect( box.querySelector( 'progress' ).value ).toBe( 12 );
	expect( box.querySelector( 'progress' ).max ).toBe( 40 );
	expect(
		box.querySelector( '[data-corex-conversion-summary]' ).textContent
	).toContain( '10 converted' );
} );

it( 'lists the files that failed by name, as text', () => {
	const box = panel();
	show( box, {
		active: false,
		total: 2,
		processed: 2,
		summary:
			'Finished on 23 October 2026: 1 converted, 0 skipped, 1 failed, of 2 images.',
		failures: [
			{ file: '<b>gone</b>.jpg', reason: 'the file is missing' },
		],
	} );

	const failures = box.querySelector( '[data-corex-conversion-failures]' );
	expect( failures.hidden ).toBe( false );
	expect( failures.querySelector( 'li' ).textContent ).toBe(
		'<b>gone</b>.jpg: the file is missing'
	);
	expect( failures.querySelector( 'b' ) ).toBeNull();
	expect( box.querySelector( '[data-corex-conversion-cancel]' ).hidden ).toBe(
		true
	);
} );

it( 'offers no start while conversion is unavailable', () => {
	const box = panel( '0' );
	show( box, null );

	expect(
		box.querySelector( '[data-corex-conversion-start]' ).disabled
	).toBe( true );
	expect( isActive( null ) ).toBe( false );
} );
