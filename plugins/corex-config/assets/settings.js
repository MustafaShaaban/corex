/**
 * Corex Settings screen behaviour (no build step; the page works without it):
 *
 *  - Media picker (spec 032): "Select/Change image" opens the WordPress media frame and writes
 *    the chosen address into the field and its preview; "Remove" clears it.
 *  - Save bar (spec 119, US-SET-01): every control carries its stored value in
 *    `data-corex-saved`. The bar reads "No changes", "Unsaved changes" or "All changes saved",
 *    Discard puts every control back without a reload, and leaving with unsaved changes asks
 *    first. Discard is the form's reset button and Save its submit button, so both work without
 *    this script.
 *  - Remove a saved secret (US-SET-15): the box to tick becomes a "Remove" button that asks for
 *    confirmation, naming what stops working.
 *  - Test connection (US-SET-03): PageSpeed and Cloudflare keys are tested on the server; the
 *    answer is said in a live region and the "Last tested" line is brought up to date.
 *  - Driver-aware rows, the accessible selects and the save toast, as before.
 */
( function () {
	const settings = window.corexSettings || {};
	const i18n = settings.i18n || {};

	function text( key, fallback ) {
		return i18n[ key ] || fallback;
	}

	/**
	 * What a control holds now, in the form `data-corex-saved` keeps: "1"/"0" for a box to tick.
	 *
	 * @param {HTMLElement} control a form control
	 * @return {string} its value
	 */
	function currentValue( control ) {
		return control.type === 'checkbox'
			? ( control.checked && '1' ) || '0'
			: control.value;
	}

	/**
	 * The controls whose changes count: drawn with a stored value and editable.
	 *
	 * @param {HTMLFormElement} form the page's form
	 * @return {HTMLElement[]} the controls
	 */
	function trackedControls( form ) {
		return Array.prototype.slice
			.call( form.querySelectorAll( '[data-corex-saved]' ) )
			.filter( function ( control ) {
				return ! control.disabled && ! control.readOnly;
			} );
	}

	/**
	 * Whether anything on the page differs from what is stored.
	 *
	 * @param {HTMLFormElement} form the page's form
	 * @return {boolean} true with unsaved changes
	 */
	function hasChanges( form ) {
		return trackedControls( form ).some( function ( control ) {
			return (
				currentValue( control ) !==
				control.getAttribute( 'data-corex-saved' )
			);
		} );
	}

	function mediaContainer( el ) {
		return el ? el.closest( '.corex-media' ) : null;
	}

	function setPreview( input, url ) {
		const box = mediaContainer( input );
		if ( ! box ) {
			return;
		}
		const preview = box.querySelector( '.corex-media-preview' );
		const placeholder = box.querySelector( '.corex-media__placeholder' );
		const hasUrl = !! url;
		if ( preview ) {
			preview.src = url || '';
			preview.hidden = ! hasUrl;
		}
		if ( placeholder ) {
			placeholder.hidden = hasUrl;
		}
	}

	/**
	 * A value set by this script is announced to the save bar as a person's edit would be.
	 *
	 * @param {HTMLElement} control the control whose value was set
	 */
	function changed( control ) {
		control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}

	function initMedia() {
		document.addEventListener( 'click', function ( event ) {
			const selectBtn = event.target.closest( '.corex-media-select' );
			const removeBtn = event.target.closest( '.corex-media-remove' );

			if ( selectBtn && window.wp && window.wp.media ) {
				event.preventDefault();
				const input = document.getElementById(
					selectBtn.dataset.target
				);
				const frame = window.wp.media( {
					title: selectBtn.textContent,
					button: { text: selectBtn.textContent },
					multiple: false,
				} );
				frame.on( 'select', function () {
					const url = frame
						.state()
						.get( 'selection' )
						.first()
						.toJSON().url;
					if ( input ) {
						input.value = url;
						changed( input );
					}
					setPreview( input, url );
				} );
				frame.open();
			}

			if ( removeBtn ) {
				event.preventDefault();
				const target = document.getElementById(
					removeBtn.dataset.target
				);
				if ( target ) {
					target.value = '';
					changed( target );
				}
				setPreview( target, '' );
			}
		} );
	}

	/*
	 * A saved secret's "Remove": the box to tick is kept, and hidden behind a button that asks
	 * first. Ticked, it says the value goes when the page is saved, with a way to keep it.
	 */
	function initSecretRemoval( form ) {
		form.querySelectorAll( '[data-corex-secret-remove]' ).forEach(
			function ( wrap ) {
				const box = wrap.querySelector( 'input[type="checkbox"]' );
				const label = wrap.querySelector( 'label' );
				if ( ! box || ! label ) {
					return;
				}
				const effect = document.getElementById( box.id + '-effect' );

				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'button corex-secret-remove__button';

				const note = document.createElement( 'span' );
				note.className = 'corex-secret-remove__note';
				note.setAttribute( 'role', 'status' );

				box.hidden = true;
				label.hidden = true;
				wrap.appendChild( button );
				wrap.appendChild( note );

				function show() {
					button.textContent = box.checked
						? text( 'keep', 'Keep it' )
						: text( 'remove', 'Remove' );
					note.textContent = box.checked
						? text( 'removing', 'Removed when you save.' )
						: '';
				}

				button.addEventListener( 'click', function () {
					if ( ! box.checked ) {
						const question = text(
							'confirm',
							'Remove the saved value? %s'
						).replace( '%s', effect ? effect.textContent : '' );
						// eslint-disable-next-line no-alert -- the browser's own dialog is read by every screen reader.
						if ( ! window.confirm( question ) ) {
							return;
						}
					}
					box.checked = ! box.checked;
					changed( box );
					show();
				} );

				// Discard unticks the box; the button follows.
				box.addEventListener( 'change', show );
				show();
			}
		);
	}

	/**
	 * The save bar's state, its buttons, Discard and the question before leaving.
	 *
	 * @param {HTMLFormElement} form the page's form
	 */
	function initSaveBar( form ) {
		const bar = form.querySelector( '[data-corex-savebar]' );
		if ( ! bar ) {
			return;
		}

		const words = bar.querySelector( '[data-corex-savebar-text]' );
		const save = bar.querySelector( '[data-corex-save]' );
		const discard = bar.querySelector( '[data-corex-discard]' );
		const error = bar.querySelector( '[data-corex-savebar-error]' );
		let justSaved = bar.getAttribute( 'data-state' ) === 'saved';
		let submitting = false;

		function update() {
			const dirty = hasChanges( form );
			let state = justSaved ? 'saved' : 'clean';
			if ( dirty ) {
				state = 'dirty';
			}
			bar.setAttribute( 'data-state', state );
			if ( words ) {
				words.textContent = bar.getAttribute( 'data-text-' + state );
			}
			save.disabled = ! dirty;
			discard.disabled = ! dirty;
		}

		function edited() {
			justSaved = false;
			update();
		}

		form.addEventListener( 'input', edited );
		form.addEventListener( 'change', edited );

		// Discard: every control back to what is stored, without a reload. The form's own reset
		// would go back to what the page was drawn with, which for a refused field is what was
		// typed.
		form.addEventListener( 'reset', function ( event ) {
			event.preventDefault();
			trackedControls( form ).forEach( function ( control ) {
				const saved = control.getAttribute( 'data-corex-saved' );
				if ( control.type === 'checkbox' ) {
					control.checked = saved === '1';
				} else {
					control.value = saved;
				}
				if ( control.classList.contains( 'corex-media__url' ) ) {
					setPreview( control, saved );
				}
				changed( control );
			} );
			update();
		} );

		// The save is sent from the page, so a save that does not go through (a server error, a
		// lost connection) says so and leaves every edit where it was. One that goes through is
		// answered with a redirect to the page, which is then opened as the browser would.
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.fetch || ! window.FormData ) {
				submitting = true;
				return;
			}
			event.preventDefault();
			if ( error ) {
				error.hidden = true;
			}
			save.disabled = true;
			save.setAttribute( 'data-corex-working', 'true' );

			window
				.fetch( form.action, {
					method: 'POST',
					body: new window.FormData( form ),
					credentials: 'same-origin',
					redirect: 'manual',
				} )
				.then( function ( response ) {
					if (
						response.type !== 'opaqueredirect' &&
						( response.status < 300 || response.status >= 400 )
					) {
						throw new Error( 'not saved' );
					}
					submitting = true;
					window.location.assign( form.action );
				} )
				.catch( function () {
					save.removeAttribute( 'data-corex-working' );
					if ( error ) {
						error.textContent =
							bar.getAttribute( 'data-text-failed' );
						error.hidden = false;
					}
					update();
				} );
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( submitting || ! form.isConnected || ! hasChanges( form ) ) {
				return undefined;
			}
			event.preventDefault();
			event.returnValue = bar.getAttribute( 'data-text-leave' );

			return event.returnValue;
		} );

		update();
	}

	/**
	 * "Test connection" for a provider's key: the server tests it and answers the line the page
	 * keeps for it.
	 *
	 * @param {HTMLFormElement} form the page's form
	 */
	function initProviderTests( form ) {
		form.querySelectorAll( '[data-corex-provider-test]' ).forEach(
			function ( panel ) {
				const button = panel.querySelector(
					'[data-corex-provider-test-button]'
				);
				if ( ! button || ! window.Corex || ! window.Corex.api ) {
					return;
				}
				const provider = panel.getAttribute(
					'data-corex-provider-test'
				);
				const result = panel.querySelector(
					'.corex-provider-test__result'
				);
				const status = panel.querySelector(
					'[data-corex-provider-status]'
				);

				button.addEventListener( 'click', function () {
					button.disabled = true;
					button.setAttribute( 'aria-busy', 'true' );
					button.setAttribute( 'data-corex-working', 'true' );
					result.textContent = text( 'testing', 'Testing…' );

					window.Corex.api
						.post(
							String( settings.restUrl ).replace( /\/$/, '' ) +
								'/settings/providers/' +
								provider +
								'/test',
							{},
							{ nonce: settings.nonce }
						)
						.then( function ( res ) {
							const envelope = ( res && res.envelope ) || {};
							result.textContent = envelope.message || '';
							if (
								status &&
								envelope.data &&
								envelope.data.status
							) {
								status.textContent = envelope.data.status;
							}
						} )
						.catch( function () {
							result.textContent = text(
								'testFailed',
								'The test request failed.'
							);
						} )
						.then( function () {
							button.disabled = false;
							button.removeAttribute( 'aria-busy' );
							button.removeAttribute( 'data-corex-working' );
						} );
				} );
			}
		);
	}

	// The accessible select lives in corex-core (assets/js/corex-select.js), shared with every
	// CoreX screen. The native <select> is still what submits.
	function initSelects() {
		const enhance = window.Corex && window.Corex.enhanceSelect;
		if ( ! enhance ) {
			return;
		}
		document
			.querySelectorAll( '.corex-settings-form select' )
			.forEach( enhance );
	}

	// Driver-aware fields: a row with data-corex-show-for is visible only when the controlling
	// field's current value is one of data-corex-show-values, re-evaluated on every change.
	function applyConditionalRows() {
		document
			.querySelectorAll( '[data-corex-show-for]' )
			.forEach( function ( row ) {
				const key = row.getAttribute( 'data-corex-show-for' );
				const allowed = (
					row.getAttribute( 'data-corex-show-values' ) || ''
				).split( ' ' );
				const control = document.getElementById(
					key.replace( /\./g, '_' )
				);
				const current = control ? control.value : '';
				row.hidden = allowed.indexOf( current ) === -1;
			} );
	}

	function initConditionalRows() {
		const form = document.querySelector( '.corex-settings-form' );
		if ( ! form ) {
			return;
		}
		applyConditionalRows();
		form.addEventListener( 'change', applyConditionalRows );
	}

	// Save toast: dismiss on the close button and auto-hide after a few seconds. Under reduced
	// motion the toast stays until dismissed. Without scripts it is a static banner.
	function initToast() {
		const toast = document.querySelector( '[data-corex-toast]' );
		if ( ! toast ) {
			return;
		}
		// A refused setting's line names the field: it is marked as not taken and described by it.
		const refused = Array.prototype.slice
			.call( toast.querySelectorAll( '[data-corex-refused]' ) )
			.map( function ( line ) {
				const field = document.getElementById(
					line.getAttribute( 'data-corex-refused' )
				);
				if ( field ) {
					field.setAttribute( 'aria-invalid', 'true' );
					field.setAttribute(
						'aria-describedby',
						[ field.getAttribute( 'aria-describedby' ), line.id ]
							.filter( Boolean )
							.join( ' ' )
					);
				}

				return field;
			} )
			.filter( Boolean );

		function remove() {
			if ( ! toast.parentNode ) {
				return;
			}
			const held = toast.contains( toast.ownerDocument.activeElement );
			toast.parentNode.removeChild( toast );
			// The button that was pressed went with it. Focus goes to the first field to type
			// again, and to the way to save otherwise.
			if ( held ) {
				const next =
					refused.filter( function ( field ) {
						return field.offsetParent !== null;
					} )[ 0 ] ||
					document.querySelector( '[data-corex-save]' ) ||
					document.querySelector( '.corex-settings__save button' );
				if ( next ) {
					next.focus();
				}
			}
		}

		const dismiss = toast.querySelector( '[data-corex-toast-dismiss]' );
		if ( dismiss ) {
			dismiss.addEventListener( 'click', remove );
		}

		// An alert that is in the page as it arrives is not reliably read out: it is given focus.
		if ( toast.hasAttribute( 'data-corex-toast-stays' ) ) {
			toast.focus();
		}

		const reduce =
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( ! reduce && ! toast.hasAttribute( 'data-corex-toast-stays' ) ) {
			window.setTimeout( remove, 5000 );
		}
	}

	function initPage() {
		const form = document.querySelector( '[data-corex-settings-page]' );
		if ( ! form ) {
			return;
		}
		initSecretRemoval( form );
		initProviderTests( form );
		initSaveBar( form );
	}

	initMedia();
	initSelects();
	initConditionalRows();
	initPage();
	initToast();

	// Exported for unit tests (no effect in the browser, where there is no CommonJS module).
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { currentValue, hasChanges, trackedControls };
	}
} )();
