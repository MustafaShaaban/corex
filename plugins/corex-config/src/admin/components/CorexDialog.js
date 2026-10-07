/**
 * CorexDialog — the one way a React admin surface asks for a decision in front of the screen
 * (spec 103, FR-034 and FR-035).
 *
 * It is the native `<dialog>`, opened modally, rendered where it is written in the tree. Two
 * things follow from that, and both are why it exists:
 *
 * - **It is styled.** Every CoreX admin style and token is scoped under `.corex-admin`. The
 *   WordPress `Modal` is drawn in a portal at the end of `<body>`, outside that scope, so a
 *   CoreX control inside one had no styles and no tokens at all. A modal `<dialog>` is shown in
 *   the browser's top layer without leaving its place in the document.
 * - **The browser keeps the focus.** A modal dialog makes the rest of the page inert, moves focus
 *   into itself, closes on Escape and puts focus back where it was. None of that is written here,
 *   so none of it can be written wrong.
 *
 * Closing is the caller's: this asks to be closed and the caller stops rendering it.
 */
import { useId, useLayoutEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}                    props
 * @param {string}                    props.title       Names the dialog, visibly and to assistive technology.
 * @param {Function}                  props.onClose     Called when the person asks to leave: Escape, the close button, or a click outside.
 * @param {import('react').ReactNode} props.children    The dialog's content.
 * @param {import('react').ReactNode} [props.footer]    Actions, kept in view under content that scrolls.
 * @param {string}                    [props.className] An extra class for the surface that uses it.
 * @param {boolean}                   [props.busy]      When true, leaving is refused: something is in progress that leaving would lose sight of.
 * @return {import('react').ReactElement} The dialog.
 */
export default function CorexDialog( {
	title,
	onClose,
	children,
	footer,
	className = '',
	busy = false,
} ) {
	const dialog = useRef( null );
	const titleId = useId();

	// A layout effect, for its cleanup: that runs while the dialog is still in the document. The
	// browser hands focus back when a dialog is closed, and it cannot for one already removed.
	useLayoutEffect( () => {
		const node = dialog.current;
		// Not every environment that renders this has the method: a test's DOM may not.
		if ( typeof node.showModal === 'function' && ! node.open ) {
			node.showModal();
		}

		return () => {
			if ( typeof node.close === 'function' && node.open ) {
				node.close();
			}
		};
	}, [] );

	const leave = () => {
		if ( ! busy ) {
			onClose();
		}
	};

	return (
		// Escape is the keyboard's way out and arrives as `cancel`. The click handler is for a
		// press on the backdrop, which is the dialog element itself, outside its panel.
		// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions
		<dialog
			ref={ dialog }
			className={ [ 'corex-dialog', className ]
				.filter( Boolean )
				.join( ' ' ) }
			aria-labelledby={ titleId }
			onCancel={ ( event ) => {
				// The caller decides whether it closes; the browser must not close it first.
				event.preventDefault();
				leave();
			} }
			onClick={ ( event ) => {
				if ( event.target === dialog.current ) {
					leave();
				}
			} }
		>
			<div className="corex-dialog__panel">
				<header className="corex-dialog__header">
					<h2 id={ titleId } className="corex-dialog__title">
						{ title }
					</h2>
					<button
						type="button"
						className="corex-dialog__close"
						onClick={ leave }
						disabled={ busy }
						aria-label={ __( 'Close', 'corex' ) }
					>
						<span
							className="dashicons dashicons-no-alt"
							aria-hidden="true"
						/>
					</button>
				</header>
				<div className="corex-dialog__body">{ children }</div>
				{ footer && (
					<footer className="corex-dialog__footer">{ footer }</footer>
				) }
			</div>
		</dialog>
	);
}
