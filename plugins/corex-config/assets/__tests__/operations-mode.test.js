/**
 * The mode form's disclosure, as the operator meets it (2026-10-07).
 *
 * `operations-mode.js` is a build-free IIFE, so it is loaded by evaluating the file against a real
 * DOM — the pattern `corex-runtime-forms.test.js` uses. The markup below is what
 * `OperationsSecurityScreen::modeCard()` renders for a site in Coming soon.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const SCRIPT = path.join( __dirname, '..', 'operations-mode.js' );

/**
 * The form as the server renders it while the selection is the current mode: every block hidden
 * and inert, the "nothing to change" line shown.
 *
 * @param {string}  current         The mode the site is in.
 * @param {boolean} [declared=true] Whether the site declared that mode, or only inherits it.
 * @return {HTMLFormElement} The form, in the document, with the script bound.
 */
function modeForm( current, declared = true ) {
	document.body.innerHTML = `
		<form data-corex-mode-form data-current-mode="${ current }" data-mode-declared="${
			declared ? '1' : '0'
		}">
			<select data-corex-mode-select>
				<option value="development">Development</option>
				<option value="coming-soon">Coming soon</option>
			</select>
			<p data-corex-mode-same>This is the mode the site is in.</p>
			<div data-mode="development" hidden></div>
			<div data-mode="coming-soon" hidden>
				<input type="checkbox" name="corex_confirm" value="1" disabled />
			</div>
			<button type="submit" data-corex-mode-apply>Apply mode</button>
		</form>`;

	const form = document.querySelector( 'form' );
	form.querySelector( 'select' ).value = current;

	// eslint-disable-next-line no-eval
	window.eval( fs.readFileSync( SCRIPT, 'utf8' ) );

	return form;
}

/**
 * Choose a mode the way the upgraded control does: set the native select and announce it.
 *
 * @param {HTMLFormElement} form The mode form.
 * @param {string}          mode The mode to select.
 */
function choose( form, mode ) {
	const select = form.querySelector( 'select' );
	select.value = mode;
	select.dispatchEvent( new window.Event( 'change' ) );
}

/**
 * What the form is asking of the operator right now.
 *
 * @param {HTMLFormElement} form The mode form.
 * @return {Object} The visible blocks, the submittable inputs, and the two controls' states.
 */
function asked( form ) {
	const blocks = [ ...form.querySelectorAll( '[data-mode]' ) ];

	return {
		described: blocks
			.filter( ( block ) => ! block.hidden )
			.map( ( block ) => block.getAttribute( 'data-mode' ) ),
		submittable: [
			...form.querySelectorAll( 'input:not([disabled])' ),
		].map( ( input ) => input.name ),
		saysNothingToChange: ! form.querySelector( '[data-corex-mode-same]' )
			.hidden,
		canApply: ! form.querySelector( '[data-corex-mode-apply]' ).disabled,
	};
}

describe( 'the operations mode form', () => {
	it( 'asks for nothing while the selection is the mode the site is in', () => {
		const form = modeForm( 'coming-soon' );

		// It used to describe Coming soon and offer its acknowledgement beside a disabled button.
		expect( asked( form ) ).toEqual( {
			described: [],
			submittable: [],
			saysNothingToChange: true,
			canApply: false,
		} );
	} );

	it( 'offers the mode a site only inherits, because declaring it is a change', () => {
		// The screen tells such a site to "declare a mode". The button used to be disabled for the
		// one mode it was most likely to declare.
		const form = modeForm( 'coming-soon', false );

		choose( form, 'coming-soon' );

		expect( asked( form ) ).toEqual( {
			described: [ 'coming-soon' ],
			submittable: [ 'corex_confirm' ],
			saysNothingToChange: false,
			canApply: true,
		} );
	} );

	it( 'describes a different mode once it is chosen, and only that one', () => {
		const form = modeForm( 'development' );

		choose( form, 'coming-soon' );

		expect( asked( form ) ).toEqual( {
			described: [ 'coming-soon' ],
			submittable: [ 'corex_confirm' ],
			saysNothingToChange: false,
			canApply: true,
		} );
	} );

	it( 'goes back to asking nothing when the current mode is chosen again', () => {
		const form = modeForm( 'development' );

		choose( form, 'coming-soon' );
		choose( form, 'development' );

		expect( asked( form ) ).toEqual( {
			described: [],
			submittable: [],
			saysNothingToChange: true,
			canApply: false,
		} );
	} );
} );
