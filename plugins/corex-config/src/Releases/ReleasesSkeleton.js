/**
 * The placeholder for the Releases screen (spec 107, slice 3; spec 108).
 *
 * Drawn inside the panels the screen draws, so each has the padding and the line heights of the
 * panel that replaces it. The headings are bars as well: the whole placeholder is hidden from
 * assistive technology, and a heading that can be seen and not read is worse than a bar.
 */
import CorexSkeleton, {
	SkeletonBar,
} from '../admin/components/CorexSkeleton.js';

/** The width of each line's bar in the three panels: running, sending, the packages here. */
const PANELS = [
	[ 'long' ],
	[ 'long', 'medium', 'short' ],
	[ 'medium', 'medium' ],
];

export default function ReleasesSkeleton() {
	return (
		<CorexSkeleton>
			{ PANELS.map( ( lines, panel ) => (
				<div className="corex-releases__panel" key={ panel }>
					<h2 className="corex-releases__heading">
						<SkeletonBar width="short" />
					</h2>
					{ lines.map( ( width, line ) => (
						<p className="corex-releases__line" key={ line }>
							<SkeletonBar width={ width } />
						</p>
					) ) }
				</div>
			) ) }
		</CorexSkeleton>
	);
}
