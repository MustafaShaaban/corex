/**
 * What a control is given while the request it sent is on its way (spec 108, story 3).
 *
 * One way, on every screen: the control cannot be pressed again, it says it is busy to assistive
 * technology, and it carries the attribute the admin's styles draw the loader from. The label
 * stays where it is, unseen, so the control keeps its width to the pixel and its name.
 *
 * Spread after the control's own props. A control that is not working is given nothing, so a
 * `disabled` it has for a reason of its own (nothing to save, say) is left alone:
 *
 *     <button disabled={ ! dirty } { ...workingProps( saving ) }>
 *
 * The same three attributes, written by hand, are how a screen that is not React does it.
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
