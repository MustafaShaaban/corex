/**
 * The placeholder for a list of notifications, on the screen and in the header drawer (spec 108).
 *
 * Drawn inside the markup `NotificationItem` draws, so a placeholder card has the padding, the
 * columns and the line heights of the card that replaces it, from the same rules.
 */
import CorexSkeleton, {
	SkeletonBar,
	SkeletonBox,
} from '../components/CorexSkeleton.js';

/**
 * What a notification's text does in each place it is listed. On the screen each part is a line.
 * The drawer is a third as wide, so the same parts wrap, and it leaves out the row of actions
 * (`NotificationItem`'s `compact`). Each entry is the width of one line's bar.
 */
const SHAPES = {
	screen: {
		block: 'corex-notifications-screen',
		title: [ 'medium' ],
		text: [ 'long' ],
		meta: [ 'short' ],
		actions: [ 'short' ],
	},
	drawer: {
		block: 'corex-notification-drawer',
		title: [ 'full', 'medium' ],
		text: [ 'full', 'long' ],
		meta: [ 'long', 'long', 'short' ],
		actions: [],
	},
};

function Bars( { widths } ) {
	return widths.map( ( width, index ) => (
		<SkeletonBar key={ index } width={ width } />
	) );
}

function PlaceholderCard( { shape } ) {
	return (
		<div className="corex-notification">
			<SkeletonBox />
			<div className="corex-notification__body">
				<p className="corex-notification__title">
					<Bars widths={ shape.title } />
				</p>
				<p className="corex-notification__text">
					<Bars widths={ shape.text } />
				</p>
				<p className="corex-notification__meta">
					<Bars widths={ shape.meta } />
				</p>
			</div>
			{ shape.actions.length > 0 && (
				<div className="corex-notification__actions">
					<Bars widths={ shape.actions } />
				</div>
			) }
		</div>
	);
}

/**
 * @param {Object} props       Component props.
 * @param {string} props.place `screen` or `drawer`: where the list is.
 * @param {number} props.count How many cards to stand in for.
 * @return {Element} The placeholder.
 */
export default function NotificationSkeleton( { place, count } ) {
	const shape = SHAPES[ place ];

	return (
		<CorexSkeleton>
			<ul className={ `${ shape.block }__list` }>
				{ Array.from( { length: count }, ( _, index ) => (
					<li key={ index } className={ `${ shape.block }__item` }>
						<PlaceholderCard shape={ shape } />
					</li>
				) ) }
			</ul>
		</CorexSkeleton>
	);
}
