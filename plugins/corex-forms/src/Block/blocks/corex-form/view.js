/*
 * Corex Form block — front-end submit. Posts the form's fields (including the
 * honeypot) as JSON to the secured REST endpoint with the WP REST nonce, then
 * swaps the aria-live status message. Loaded only when the block is on the page
 * (declared as viewScript in block.json). No build step, no dependencies.
 */
( function () {
	'use strict';

	function collect( form ) {
		var data = {};
		form.querySelectorAll( 'input[name], textarea[name]' ).forEach( function ( el ) {
			data[ el.name ] = el.value;
		} );
		return data;
	}

	function submit( form, event ) {
		event.preventDefault();

		var status = form.querySelector( '.corex-form__status' );

		fetch( form.dataset.corexEndpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': form.dataset.corexNonce
			},
			body: JSON.stringify( collect( form ) )
		} )
			.then( function ( response ) {
				return response.json().then( function ( body ) {
					return { ok: response.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				if ( status ) {
					status.textContent = result.ok
						? form.dataset.corexSuccess
						: form.dataset.corexError;
				}
				if ( result.ok ) {
					form.reset();
				}
			} )
			.catch( function () {
				if ( status ) {
					status.textContent = form.dataset.corexError;
				}
			} );
	}

	document.querySelectorAll( '.corex-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			submit( form, event );
		} );
	} );
} )();
