/**
 * CorexSelect — the approved admin selection control.
 *
 * The design system has declared a Select since the component inventory was written
 * (`admin:corex-select`, ApprovedComponentInventory.php), and `.corex-select` in the admin shell
 * has styled it all along, but nothing in React ever rendered it: every admin screen used a raw
 * <select> or Gutenberg's SelectControl instead. Both draw their OPEN menu through the operating
 * system, which is why the dark-mode highlight could never be fixed from CSS — no `option:hover`
 * rule can reach a popup the page does not paint. This renders the menu in the DOM, so the
 * palette applies to it like anything else.
 *
 * ARIA follows the collapsed-listbox pattern: a button owning a listbox, with the active option
 * tracked through `aria-activedescendant` rather than by moving focus, so the button keeps focus
 * while the menu is open.
 */
import {
	useCallback,
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/** Type-ahead resets once typing stops, matching how a native select behaves. */
const TYPEAHEAD_RESET_MS = 500;

export default function CorexSelect( {
	label,
	value,
	options = [],
	onChange,
	id,
	disabled = false,
	block = false,
	describedBy,
	// Submitted field name. Several screens read their forms with `new FormData( form )`
	// (useEmailStudio.js), and a <button> contributes nothing to FormData — so when this control
	// stands in for a named <select>, it also renders a hidden input carrying the value. Without
	// it the field would silently vanish from the request.
	name,
	// Uncontrolled seed, for the form-driven screens that used `defaultValue` on a native select.
	defaultValue,
	// Shown, disabled, when there is nothing to choose from. An optional dependency that is not
	// installed yields an empty list (Principle IX), and an empty popup reads as a broken control
	// rather than an absent one.
	emptyLabel = __( 'No options available', 'corex' ),
} ) {
	const generatedId = useId();
	const baseId = id || `corex-select-${ generatedId }`;
	const listId = `${ baseId }__list`;

	const [ open, setOpen ] = useState( false );
	const [ activeIndex, setActiveIndex ] = useState( 0 );
	// Only consulted when `value` is not supplied, so a controlled parent stays the only source
	// of truth and this never competes with it.
	const [ uncontrolled, setUncontrolled ] = useState(
		defaultValue ?? options[ 0 ]?.value ?? ''
	);

	const isControlled = value !== undefined;
	const currentValue = isControlled ? value : uncontrolled;

	const wrapRef = useRef( null );
	const buttonRef = useRef( null );
	const typeahead = useRef( { term: '', at: 0 } );

	const isEmpty = options.length === 0;
	const inert = disabled || isEmpty;

	const selectedIndex = useMemo( () => {
		const found = options.findIndex(
			( option ) => option.value === currentValue
		);
		return found === -1 ? 0 : found;
	}, [ options, currentValue ] );

	const selectedLabel = isEmpty
		? emptyLabel
		: options[ selectedIndex ]?.label ?? '';

	const close = useCallback( () => {
		setOpen( false );
	}, [] );

	const openMenu = useCallback( () => {
		if ( inert ) {
			return;
		}
		setActiveIndex( selectedIndex );
		setOpen( true );
	}, [ inert, selectedIndex ] );

	const choose = useCallback(
		( index ) => {
			const option = options[ index ];
			close();
			// Focus returns to the button, or the menu closes under a focused element that no
			// longer exists and the tab order restarts at the top of the page.
			buttonRef.current?.focus();
			if ( ! option || option.value === currentValue ) {
				return;
			}
			if ( ! isControlled ) {
				setUncontrolled( option.value );
			}
			onChange?.( option.value );
		},
		[ close, currentValue, isControlled, onChange, options ]
	);

	// Clicking anywhere else dismisses the menu. Bound only while open so a screen with many
	// selects is not listening on every document click.
	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onDocumentDown = ( event ) => {
			if ( ! wrapRef.current?.contains( event.target ) ) {
				close();
			}
		};
		document.addEventListener( 'mousedown', onDocumentDown );
		return () =>
			document.removeEventListener( 'mousedown', onDocumentDown );
	}, [ open, close ] );

	/** Jump to the next option starting with what was typed — native selects do this. */
	const typeaheadTo = useCallback(
		( key ) => {
			const now = Date.now();
			const term =
				now - typeahead.current.at > TYPEAHEAD_RESET_MS
					? key
					: typeahead.current.term + key;
			typeahead.current = { term, at: now };

			const from = open ? activeIndex : selectedIndex;
			const lower = term.toLowerCase();
			// Start the search after the current option so repeating one letter cycles.
			for ( let step = 1; step <= options.length; step++ ) {
				const index = ( from + step ) % options.length;
				if (
					options[ index ].label.toLowerCase().startsWith( lower )
				) {
					if ( open ) {
						setActiveIndex( index );
					} else {
						choose( index );
					}
					return;
				}
			}
		},
		[ activeIndex, choose, open, options, selectedIndex ]
	);

	/**
	 * What each key does, by menu state. A table rather than a branch ladder so a new key is a
	 * new entry instead of another arm in an already-long conditional — and so the two states
	 * can be read side by side, which is where keyboard bugs hide.
	 *
	 * Tab is absent from `closed` deliberately: it must keep moving focus normally.
	 */
	const keyActions = {
		closed: {
			ArrowDown: openMenu,
			ArrowUp: openMenu,
			Enter: openMenu,
			' ': openMenu,
		},
		open: {
			ArrowDown: () =>
				setActiveIndex( ( index ) =>
					Math.min( index + 1, options.length - 1 )
				),
			ArrowUp: () =>
				setActiveIndex( ( index ) => Math.max( index - 1, 0 ) ),
			Home: () => setActiveIndex( 0 ),
			End: () => setActiveIndex( options.length - 1 ),
			Enter: () => choose( activeIndex ),
			' ': () => choose( activeIndex ),
		},
	};

	const onKeyDown = ( event ) => {
		if ( inert ) {
			return;
		}

		const { key } = event;

		// Escape and Tab both dismiss, but only Escape consumes the keystroke — Tab has to go on
		// and move focus.
		if ( key === 'Escape' ) {
			event.preventDefault();
			close();
			return;
		}
		if ( key === 'Tab' ) {
			close();
			return;
		}

		const action = keyActions[ open ? 'open' : 'closed' ][ key ];
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
	};

	const classes = block ? 'corex-select corex-select--block' : 'corex-select';

	return (
		<div className={ classes } ref={ wrapRef }>
			{ name && (
				<input type="hidden" name={ name } value={ currentValue } />
			) }
			<button
				type="button"
				id={ baseId }
				ref={ buttonRef }
				className="corex-select__button"
				// The ARIA select-only combobox pattern, and the same role a native <select>
				// exposes implicitly — so replacing the control changes nothing for assistive
				// technology, or for the tests and users that address it by role.
				role="combobox"
				aria-haspopup="listbox"
				aria-expanded={ open }
				aria-controls={ open ? listId : undefined }
				aria-activedescendant={
					open && ! isEmpty
						? `${ baseId }__option-${ activeIndex }`
						: undefined
				}
				// A wrapping <label> would name this from its whole subtree, and an embedded
				// control contributes its VALUE — so the control would rename itself on every
				// selection. The label text is applied directly instead.
				aria-label={ label }
				aria-describedby={ describedBy }
				disabled={ inert }
				onClick={ () => ( open ? close() : openMenu() ) }
				onKeyDown={ onKeyDown }
			>
				<span className="corex-select__value">{ selectedLabel }</span>
				<span className="corex-select__chevron" aria-hidden="true" />
			</button>
			{ open && ! isEmpty && (
				<ul
					className="corex-select__list"
					role="listbox"
					id={ listId }
					aria-label={ label }
				>
					{ options.map( ( option, index ) => (
						<li
							key={ option.value }
							id={ `${ baseId }__option-${ index }` }
							role="option"
							aria-selected={ index === selectedIndex }
							className={ [
								'corex-select__option',
								index === activeIndex ? 'is-active' : '',
								index === selectedIndex ? 'is-selected' : '',
							]
								.filter( Boolean )
								.join( ' ' ) }
							onMouseEnter={ () => setActiveIndex( index ) }
							// mousedown, not click: a click would first blur the button and the
							// outside-click handler would close the menu before the choice landed.
							onMouseDown={ ( event ) => {
								event.preventDefault();
								choose( index );
							} }
						>
							{ option.label }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
