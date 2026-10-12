/**
 * The copy field of a CoreX standalone page (spec 101, FR-011a): the markup that
 * `StandalonePage::copyField()` writes. The page writes the button hidden and this script shows
 * it, so where the script does not run there is no button, and the value is still in a field
 * that can be selected and copied by hand.
 *
 * A browser gives the Clipboard API only to a page served over HTTPS or from the local machine.
 * Elsewhere, and where it refuses, the field is selected and copied the older way; where that
 * fails too the value is left selected and the page says what to do. What the page says is
 * written into the markup by PHP, where it is translated.
 */
( function () {
	/** How long "copied" is said for, in milliseconds, before a second press can say it again. */
	const SAID_FOR = 4000;

	/**
	 * Select the field and ask the browser to copy the selection.
	 *
	 * @param {HTMLInputElement} input The field.
	 * @return {boolean} Whether the browser says it copied.
	 */
	function copySelection( input ) {
		input.focus();
		input.select();

		// Where copying is not allowed, one browser answers false and another throws. Either
		// way nothing was copied, and the caller says so with the field still selected.
		try {
			return document.execCommand( 'copy' );
		} catch {
			return false;
		}
	}

	/**
	 * Put the field's value on the clipboard.
	 *
	 * @param {HTMLInputElement} input The field.
	 * @return {Promise<boolean>} Whether it is there.
	 */
	function copy( input ) {
		const clipboard = window.navigator.clipboard;

		if ( clipboard ) {
			return clipboard.writeText( input.value ).then(
				() => true,
				() => copySelection( input )
			);
		}

		return Promise.resolve( copySelection( input ) );
	}

	document.querySelectorAll( '[data-corex-copy]' ).forEach( ( field ) => {
		// The script is written beside each field, so a page with two fields runs it twice.
		if ( field.dataset.corexCopy ) {
			return;
		}
		field.dataset.corexCopy = 'bound';

		const input = field.querySelector( '[data-corex-copy-value]' );
		const button = field.querySelector( '[data-corex-copy-button]' );
		const status = field.querySelector( '[data-corex-copy-status]' );
		let takenBack = 0;

		input.addEventListener( 'focus', () => input.select() );

		button.addEventListener( 'click', () => {
			window.clearTimeout( takenBack );

			copy( input ).then( ( copied ) => {
				field.classList.toggle( 'is-copied', copied );
				status.textContent = copied
					? field.dataset.copied
					: field.dataset.manual;

				if ( copied ) {
					takenBack = window.setTimeout( () => {
						field.classList.remove( 'is-copied' );
						status.textContent = '';
					}, SAID_FOR );
				}
			} );
		} );

		button.hidden = false;
	} );
} )();
