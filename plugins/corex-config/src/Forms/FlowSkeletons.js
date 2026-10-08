/**
 * The placeholders of the Forms & flows screen (spec 108, story 1): the catalog before its
 * first answer, and the editor while the flow it will hold is fetched.
 *
 * Each is drawn in the markup of what it stands for, so it takes that markup's grid and padding
 * from the rules the real thing takes them from.
 */
import { __ } from '@wordpress/i18n';
import CorexLoadable from '../admin/components/CorexLoadable.js';
import CorexSkeleton, {
	SkeletonBar,
} from '../admin/components/CorexSkeleton.js';

const ROWS = 4;

/**
 * A row is a button holding five cells. The placeholder's is a button too, so the cells sit in
 * the same grid; disabled, so it is not a thing to press or to tab to.
 *
 * @return {Element} The catalog's placeholder.
 */
export function FlowRowsSkeleton() {
	return (
		<CorexSkeleton>
			<ul className="corex-flow-list__rows">
				{ Array.from( { length: ROWS }, ( _, row ) => (
					<li key={ row } className="corex-flow-list__row">
						<button type="button" disabled>
							<span className="corex-flow-list__identity">
								<SkeletonBar width="long" />
								<SkeletonBar width="medium" />
								<SkeletonBar width="short" />
							</span>
							<SkeletonBar width="medium" />
							<SkeletonBar width="medium" />
							<SkeletonBar width="long" />
							<SkeletonBar width="medium" />
						</button>
					</li>
				) ) }
			</ul>
		</CorexSkeleton>
	);
}

const WORKSPACE_LINES = [ 'medium', 'full', 'long', 'full', 'medium', 'long' ];

/**
 * The editor, before the flow it edits has arrived: its toolbar, and the workspace under it.
 *
 * @return {Element} The editor's placeholder.
 */
export function FlowEditorSkeleton() {
	return (
		<CorexLoadable
			status="loading"
			loadingLabel={ __( 'Loading the flow…', 'corex' ) }
			// A flow that cannot be read puts the catalog back, and the notice above it
			// says why: there is no failure to say in here.
			errorMessage=""
			skeleton={
				<CorexSkeleton>
					<div className="corex-flow-editor">
						<header className="corex-surface corex-flow-editor__toolbar">
							<div>
								<SkeletonBar width="short" />
								<h2>
									<SkeletonBar width="medium" />
								</h2>
								<SkeletonBar width="short" />
							</div>
						</header>
						<div className="corex-surface corex-flow-editor__workspace">
							{ WORKSPACE_LINES.map( ( width, line ) => (
								<p key={ line }>
									<SkeletonBar width={ width } />
								</p>
							) ) }
						</div>
					</div>
				</CorexSkeleton>
			}
		/>
	);
}
