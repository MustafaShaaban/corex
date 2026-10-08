/**
 * CorexSkeleton — a stand-in in the shape of content that has not arrived (spec 108, story 1).
 *
 * A surface composes its own placeholder, inside its own markup: the bars and boxes go where the
 * text and the icons will, in the same elements, so the placeholder takes its padding, its gaps
 * and its line heights from the rules the content takes them from. A placeholder drawn to
 * measurements of its own is right on the day it is written and wrong after the next change to
 * the thing it stands for.
 *
 * Hidden from assistive technology, all of it. A screen reader is told that the surface is
 * loading by the surface (`CorexLoadable`); grey shapes say nothing it can read.
 *
 * The same class names, written as markup, are how the two admin screens that are not React
 * (Insights, the setup wizard) draw one.
 */

/**
 * @param {Object}  props          Component props.
 * @param {Element} props.children The surface's own markup, with bars and boxes for its content.
 * @return {Element} The placeholder.
 */
export default function CorexSkeleton( { children } ) {
	return (
		<div className="corex-admin-skeleton" aria-hidden="true">
			{ children }
		</div>
	);
}

/**
 * A line of text that is not here yet: as tall as a line of the element it is put in.
 *
 * @param {Object} props       Component props.
 * @param {string} props.width `full`, `long`, `medium` or `short`: how much of the line it fills.
 * @return {Element} The bar.
 */
export function SkeletonBar( { width = 'full' } ) {
	return (
		<span
			className={ `corex-admin-skeleton__bar corex-admin-skeleton__bar--${ width }` }
		/>
	);
}

/**
 * An icon or a control that is not here yet.
 *
 * @return {Element} The box.
 */
export function SkeletonBox() {
	return <span className="corex-admin-skeleton__box" />;
}
