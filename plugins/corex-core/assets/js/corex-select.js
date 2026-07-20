/**
 * Progressive-enhancement accessible select, shared by every CoreX admin screen.
 *
 * Upgrades a native <select> into the approved in-DOM ARIA listbox (`.corex-select`), because a
 * native select draws its OPEN menu through the operating system: no `option:hover` rule can
 * reach that popup, so the dark-mode highlight is unreachable from CSS. The React screens use the
 * CorexSelect component; this is the same control for server-rendered forms.
 *
 * The native <select> is kept, hidden, as the submitted value — these are real POST forms
 * (operations mode, submission retention) and the field must still degrade to a working native
 * control when JavaScript does not run.
 *
 * Opt in per control with `data-corex-select` rather than by living in a particular container,
 * so a screen does not have to be inside a magic wrapper to get the approved control.
 */
( function () {
	const TYPEAHEAD_RESET_MS = 500;

	function enhanceSelect( native ) {
		if ( native.dataset.corexEnhanced ) {
			return;
		}
		native.dataset.corexEnhanced = '1';

		// A wrapping <label> names the control from its whole subtree, so read the text but apply
		// it as aria-label — otherwise the embedded control contributes its own VALUE and the
		// control renames itself on every selection.
		const labelEl = native.id
			? document.querySelector( 'label[for="' + native.id + '"]' )
			: native.closest( 'label' );
		const labelText = labelEl ? labelEl.textContent.trim() : '';

		const wrap = document.createElement( 'div' );
		wrap.className = 'corex-select corex-select--enhanced';

		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'corex-select__button';
		// The ARIA select-only combobox pattern, and the same role the native <select> it
		// replaces exposes implicitly — assistive technology sees no change.
		button.setAttribute( 'role', 'combobox' );
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

		const listId =
			( native.id ||
				'corex-select-' + Math.random().toString( 36 ).slice( 2 ) ) +
			'__list';
		const list = document.createElement( 'ul' );
		list.className = 'corex-select__list';
		list.setAttribute( 'role', 'listbox' );
		list.id = listId;
		if ( labelText ) {
			list.setAttribute( 'aria-label', labelText );
		}
		list.hidden = true;

		const options = Array.prototype.map.call(
			native.options,
			function ( opt, i ) {
				const li = document.createElement( 'li' );
				li.setAttribute( 'role', 'option' );
				li.className = 'corex-select__option';
				li.id = listId + '-option-' + i;
				li.textContent = opt.textContent;
				li.dataset.value = opt.value;
				list.appendChild( li );
				return li;
			}
		);

		let active = Math.max( 0, native.selectedIndex );
		let typed = { term: '', at: 0 };

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
			// The button keeps focus while the menu is open; the active option is reported
			// rather than focused.
			if ( options[ active ] ) {
				button.setAttribute(
					'aria-activedescendant',
					options[ active ].id
				);
			}
		}

		function open() {
			active = Math.max( 0, native.selectedIndex );
			markActive();
			list.hidden = false;
			button.setAttribute( 'aria-expanded', 'true' );
			button.setAttribute( 'aria-controls', listId );
		}

		function close() {
			list.hidden = true;
			button.setAttribute( 'aria-expanded', 'false' );
			button.removeAttribute( 'aria-activedescendant' );
		}

		function choose( index ) {
			native.selectedIndex = index;
			native.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			syncValue();
			close();
			button.focus();
		}

		/**
		 * Jump to the next option starting with what was typed — native selects do this.
		 *
		 * @param {string} key A single printable character just pressed.
		 */
		function typeaheadTo( key ) {
			const now = Date.now();
			const term =
				now - typed.at > TYPEAHEAD_RESET_MS ? key : typed.term + key;
			typed = { term, at: now };

			const from = list.hidden ? native.selectedIndex : active;
			const lower = term.toLowerCase();
			// Search after the current option so repeating one letter cycles the matches.
			for ( let step = 1; step <= options.length; step++ ) {
				const index = ( Math.max( 0, from ) + step ) % options.length;
				if (
					options[ index ].textContent
						.toLowerCase()
						.startsWith( lower )
				) {
					if ( list.hidden ) {
						choose( index );
					} else {
						active = index;
						markActive();
					}
					return;
				}
			}
		}

		button.addEventListener( 'click', function () {
			if ( list.hidden ) {
				open();
			} else {
				close();
			}
		} );

		function moveActive( to ) {
			active = Math.max( 0, Math.min( to, options.length - 1 ) );
			markActive();
		}

		/**
		 * What each key does, by menu state. A table rather than a branch ladder, matching the
		 * React CorexSelect so the two controls cannot drift apart on keyboard behaviour.
		 *
		 * Tab is absent from `closed` deliberately: it must keep moving focus normally.
		 */
		const keyActions = {
			closed: {
				ArrowDown: open,
				ArrowUp: open,
				Enter: open,
				' ': open,
			},
			open: {
				ArrowDown() {
					moveActive( active + 1 );
				},
				ArrowUp() {
					moveActive( active - 1 );
				},
				Home() {
					moveActive( 0 );
				},
				End() {
					moveActive( options.length - 1 );
				},
				Enter() {
					choose( active );
				},
				' '() {
					choose( active );
				},
			},
		};

		button.addEventListener( 'keydown', function ( event ) {
			const key = event.key;

			// Escape and Tab both dismiss, but only Escape consumes the keystroke — Tab has to
			// go on and move focus.
			if ( key === 'Escape' ) {
				event.preventDefault();
				close();
				return;
			}
			if ( key === 'Tab' ) {
				close();
				return;
			}

			const action = keyActions[ list.hidden ? 'closed' : 'open' ][ key ];
			if ( action ) {
				event.preventDefault();
				action();
				return;
			}

			// Single printable character: type-ahead. Modifier combos are shortcuts, not typing.
			if (
				key.length === 1 &&
				! event.altKey &&
				! event.ctrlKey &&
				! event.metaKey
			) {
				event.preventDefault();
				typeaheadTo( key );
			}
		} );

		options.forEach( function ( li, i ) {
			li.addEventListener( 'mouseenter', function () {
				active = i;
				markActive();
			} );
			// mousedown, not click: a click would blur the button first and the outside-click
			// handler would close the menu before the choice landed.
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

	function initSelects( scope ) {
		( scope || document )
			.querySelectorAll( '.corex-admin select[data-corex-select]' )
			.forEach( enhanceSelect );
	}

	// Exposed so a screen that renders selects after load can upgrade them, and so the settings
	// script can reuse this instead of carrying its own copy.
	window.Corex = window.Corex || {};
	window.Corex.enhanceSelect = enhanceSelect;
	window.Corex.initSelects = initSelects;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initSelects();
		} );
	} else {
		initSelects();
	}
} )();
