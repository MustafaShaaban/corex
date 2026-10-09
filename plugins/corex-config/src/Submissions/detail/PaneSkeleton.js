/**
 * The placeholder for an open submission, while it is read (spec 108, slice 6).
 *
 * Drawn in the pane's own sections and its own list of answers, so a placeholder answer has the
 * label over the value, and the gaps, of the answer that replaces it. How many answers a form
 * asks for is part of what is being read: four stand in for them.
 */
import CorexSkeleton, {
	SkeletonBar,
} from '../../admin/components/CorexSkeleton.js';

/** The width of each answer's bar, so the four are not a block. */
const ANSWERS = [ 'medium', 'long', 'medium', 'full' ];

function PlaceholderSection( { children } ) {
	return (
		<div className="corex-pane__section">
			<h3>
				<SkeletonBar width="short" />
			</h3>
			{ children }
		</div>
	);
}

export default function PaneSkeleton() {
	return (
		<CorexSkeleton>
			<PlaceholderSection>
				<dl className="corex-pane__fields">
					{ ANSWERS.map( ( width, answer ) => (
						<div key={ answer }>
							<dt>
								<SkeletonBar width="short" />
							</dt>
							<dd>
								<SkeletonBar width={ width } />
							</dd>
						</div>
					) ) }
				</dl>
			</PlaceholderSection>
			<PlaceholderSection>
				<p className="corex-pane__muted">
					<SkeletonBar width="long" />
				</p>
			</PlaceholderSection>
		</CorexSkeleton>
	);
}
