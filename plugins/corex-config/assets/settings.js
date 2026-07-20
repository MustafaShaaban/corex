/**
 * Corex settings screen behaviour (no build step; every feature degrades gracefully when
 * this script is absent):
 *
 *  - Media picker (spec 032): "Select/Change image" opens the WordPress media frame and writes
 *    the chosen URL into the field + framed preview; "Remove" clears it back to the placeholder.
 *    Without JS the field stays an editable URL input.
 *  - Settings tabs (spec 060): progressive-enhancement ARIA tabs over the real settings
 *    sections. With JS one panel shows at a time with arrow-key navigation; without JS every
 *    panel stays visible and usable.
 */
( function () {
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
				}
				setPreview( target, '' );
			}
		} );
	}

	function initTabs() {
		const tablist = document.querySelector( '.corex-settings-tabs' );
		if ( ! tablist ) {
			return;
		}

		const tabs = Array.prototype.slice.call(
			tablist.querySelectorAll( '[role="tab"]' )
		);
		const activeInput = document.querySelector(
			'.corex-settings__active-tab'
		);

		function panelFor( tab ) {
			return document.getElementById(
				tab.getAttribute( 'aria-controls' )
			);
		}

		function activate( tab, moveFocus ) {
			tabs.forEach( function ( current ) {
				const selected = current === tab;
				current.setAttribute(
					'aria-selected',
					selected ? 'true' : 'false'
				);
				current.setAttribute( 'tabindex', selected ? '0' : '-1' );
				current.classList.toggle( 'is-active', selected );
				const panel = panelFor( current );
				if ( panel ) {
					panel.hidden = ! selected;
					panel.classList.toggle( 'is-active', selected );
				}
			} );
			if ( activeInput ) {
				activeInput.value = tab.dataset.corexTab;
			}
			if ( moveFocus ) {
				tab.focus();
			}
		}

		// Hide every panel except the server-selected one (JS-on view).
		tabs.forEach( function ( tab ) {
			const panel = panelFor( tab );
			if ( panel ) {
				panel.hidden = tab.getAttribute( 'aria-selected' ) !== 'true';
			}
		} );

		tablist.addEventListener( 'click', function ( event ) {
			const tab = event.target.closest( '[role="tab"]' );
			if ( tab ) {
				activate( tab, false );
			}
		} );

		tablist.addEventListener( 'keydown', function ( event ) {
			// During keydown on a tab, the event target is the focused tab.
			const index = tabs.indexOf(
				event.target.closest( '[role="tab"]' )
			);
			if ( index < 0 ) {
				return;
			}
			let next;
			if ( event.key === 'ArrowRight' || event.key === 'ArrowDown' ) {
				next = ( index + 1 ) % tabs.length;
			} else if ( event.key === 'ArrowLeft' || event.key === 'ArrowUp' ) {
				next = ( index - 1 + tabs.length ) % tabs.length;
			} else if ( event.key === 'Home' ) {
				next = 0;
			} else if ( event.key === 'End' ) {
				next = tabs.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			activate( tabs[ next ], true );
		} );
	}

	// The accessible select lives in corex-core (assets/js/corex-select.js) and is shared with
	// every other CoreX screen; this used to carry its own copy of the same ~150 lines. The
	// native <select> is still what submits, so the form keeps working without JavaScript.
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
	// field's current value is one of data-corex-show-values. Re-evaluated whenever a control
	// changes (the enhanced select dispatches a native change event), so the Captcha provider
	// fields/links appear and disappear immediately — before save — matching the chosen driver.
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
	// motion the toast stays until dismissed (no timed disappearance). Without JS the toast is a
	// static role="status" banner — announced and readable, just not auto-dismissed.
	function initToast() {
		const toast = document.querySelector( '[data-corex-toast]' );
		if ( ! toast ) {
			return;
		}

		function remove() {
			if ( toast.parentNode ) {
				toast.parentNode.removeChild( toast );
			}
		}

		const dismiss = toast.querySelector( '[data-corex-toast-dismiss]' );
		if ( dismiss ) {
			dismiss.addEventListener( 'click', remove );
		}

		const reduce =
			window.matchMedia &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( ! reduce ) {
			window.setTimeout( remove, 5000 );
		}
	}

	initMedia();
	initTabs();
	initSelects();
	initConditionalRows();
	initToast();
} )();
