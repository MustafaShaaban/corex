/**
 * What a control is while the request it sent is on its way (spec 108, story 3).
 *
 * One way, on every screen: the control cannot be pressed again, it says it is busy to assistive
 * technology, and it carries the attribute the admin's styles draw the loader from. The label
 * stays where it is, unseen, so the control keeps its width to the pixel and its name.
 *
 * The same three attributes, written by hand, are how a screen that is not React does it.
 */
import { createContext } from '@wordpress/element';

/**
 * The name of the control whose request is in flight on this screen, or `''`.
 *
 * For a screen whose requests share one "busy" flag: every control is disabled by that flag,
 * and the one that was pressed is the one that works. The screen provides the name, and each
 * control compares it with its own:
 *
 *     const pending = useContext( PendingControl );
 *     <button disabled={ busy } { ...workingProps( pending === 'draft' ) }>
 */
export const PendingControl = createContext( '' );

/**
 * Spread after the control's own props. A control that is not working is given nothing, so a
 * `disabled` it has for a reason of its own (nothing to save, say) is left alone:
 *
 *     <button disabled={ ! dirty } { ...workingProps( saving ) }>
 *
 * @param {boolean} working Whether the control's request is in flight.
 * @return {Object} Props to spread on the control.
 */
export function workingProps( working ) {
	return working
		? {
				disabled: true,
				'aria-busy': 'true',
				'data-corex-working': 'true',
			}
		: {};
}
