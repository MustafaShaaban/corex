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

	// Progressive-enhancement accessible select: upgrades each native <select> in the settings
	// form into an in-DOM ARIA listbox so the open dropdown is readable in dark mode on every
	// browser. The native <select> is kept (hidden) so the form still submits and the field
	// degrades to a usable native control without JS.
	function enhanceSelect( native ) {
		if ( native.dataset.corexEnhanced ) {
			return;
		}
		native.dataset.corexEnhanced = '1';

		const labelEl = native.id
			? document.querySelector( 'label[for="' + native.id + '"]' )
			: null;
		const labelText = labelEl ? labelEl.textContent.trim() : '';

		const wrap = document.createElement( 'div' );
		wrap.className = 'corex-select corex-select--enhanced';

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'corex-select__button';
		button.setAttribute( 'aria-haspopup', 'listbox' );
		button.setAttribute( 'aria-expanded', 'false' );
		if ( labelText ) {
			button.setAttribute( 'aria-label', labelText );
		}
		// Mirror a disabled section's native select so the custom control is non-actionable too.
		button.disabled = native.disabled;

		const valueText = document.createElement( 'span' );
		valueText.className = 'corex-select__value';
		const chevron = document.createElement( 'span' );
		chevron.className = 'corex-select__chevron';
		chevron.setAttribute( 'aria-hidden', 'true' );
		button.append( valueText, chevron );

		const list = document.createElement( 'ul' );
		list.className = 'corex-select__list';
		list.setAttribute( 'role', 'listbox' );
		if ( labelText ) {
			list.setAttribute( 'aria-label', labelText );
		}
		list.hidden = true;

		const options = Array.prototype.map.call(
			native.options,
			function ( opt ) {
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.className = 'corex-select__option';
				li.textContent = opt.textContent;
				li.dataset.value = opt.value;
				list.appendChild( li );
				return li;
			}
		);

		let active = Math.max( 0, native.selectedIndex );

		function syncValue() {
			valueText.textContent = native.options[ native.selectedIndex ]
				? native.options[ native.selectedIndex ].textContent
				: '';
			options.forEach( function ( li, i ) {
				const isSel = i === native.selectedIndex;
				li.setAttribute( 'aria-selected', isSel ? 'true' : 'false' );
				li.classList.toggle( 'is-selected', isSel );
			} );
		}

		function markActive() {
			options.forEach( function ( li, i ) {
				li.classList.toggle( 'is-active', i === active );
			} );
		}

		function open() {
			active = Math.max( 0, native.selectedIndex );
			markActive();
			list.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
		}
		function close() {
			list.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );
		}
		function choose( index ) {
			native.selectedIndex = index;
			native.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			syncValue();
			close();
			button.focus();
		}

		button.addEventListener( 'click', function () {
			if ( list.hidden ) {
				open();
			} else {
				close();
			}
		} );

		button.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				close();
			} else if (
				list.hidden &&
				( event.key === 'ArrowDown' ||
					event.key === 'Enter' ||
					event.key === ' ' )
			) {
				event.preventDefault();
				open();
			} else if ( ! list.hidden && event.key === 'ArrowDown' ) {
				event.preventDefault();
				active = Math.min( active + 1, options.length - 1 );
				markActive();
			} else if ( ! list.hidden && event.key === 'ArrowUp' ) {
				event.preventDefault();
				active = Math.max( active - 1, 0 );
				markActive();
			} else if (
				! list.hidden &&
				( event.key === 'Enter' || event.key === ' ' )
			) {
				event.preventDefault();
				choose( active );
			}
		} );

		options.forEach( function ( li, i ) {
			li.addEventListener( 'mouseenter', function () {
				active = i;
				markActive();
			} );
			li.addEventListener( 'mousedown', function ( event ) {
				event.preventDefault();
				choose( i );
			} );
		} );

		document.addEventListener( 'mousedown', function ( event ) {
			if ( ! wrap.contains( event.target ) ) {
				close();
			}
		} );

		native.style.display = 'none';
		native.after( wrap );
		wrap.append( button, list );
		syncValue();
	}

	function initSelects() {
		document
			.querySelectorAll( '.corex-settings-form select' )
			.forEach( enhanceSelect );
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
