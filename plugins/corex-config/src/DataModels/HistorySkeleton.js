/**
 * The placeholder for a history list on the Export and Migrations tabs (spec 108, story 1).
 *
 * Both lists said "No exports yet." and "No migration runs yet." from the moment the tab
 * opened until the answer said otherwise, which on a site with a long history is a sentence
 * that is false for as long as it is on screen.
 */
import CorexSkeleton, {
	SkeletonBar,
} from '../admin/components/CorexSkeleton.js';

const RUNS = 3;

export default function HistorySkeleton() {
	return (
		<CorexSkeleton>
			<ul className="corex-data-models__history">
				{ Array.from( { length: RUNS }, ( _, run ) => (
					<li key={ run }>
						<SkeletonBar width="long" />
					</li>
				) ) }
			</ul>
		</CorexSkeleton>
	);
}

/**
 * What a list that is loaded more than once is, from what it was: a first load shows the
 * placeholder, and every one after it keeps what is there.
 *
 * @param {string} was The list's state before this load.
 * @return {string} `loading` or `refreshing`.
 */
export function loadingFrom( was ) {
	return was === 'ready' || was === 'refreshing' ? 'refreshing' : 'loading';
}
