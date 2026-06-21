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

	initMedia();
	initTabs();
} )();
