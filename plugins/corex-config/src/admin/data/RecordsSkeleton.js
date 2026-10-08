/**
 * The placeholder for the records table, before a source's first answer (spec 108, story 1).
 *
 * Drawn in the table's own markup, so its cells take the table's padding and its rows the
 * table's rules. Which columns a source shows is part of the answer, so the placeholder cannot
 * know them: it has the selection box and the action every source has, and stands a handful
 * of bars in for whatever is between.
 */
import CorexSkeleton, {
	SkeletonBar,
	SkeletonBox,
} from '../components/CorexSkeleton.js';

const ROWS = 8;

/** One width per column, so the bars of a row are not a block. */
const COLUMNS = [ 'long', 'medium', 'long', 'short' ];

export default function RecordsSkeleton() {
	return (
		<CorexSkeleton>
			<div className="corex-data__table-scroll">
				<table className="widefat corex-data__table">
					<thead>
						<tr>
							<th className="corex-data__check">
								<SkeletonBox />
							</th>
							{ COLUMNS.map( ( width, column ) => (
								<th key={ column }>
									<SkeletonBar width="short" />
								</th>
							) ) }
							<th>
								<SkeletonBar width="short" />
							</th>
						</tr>
					</thead>
					<tbody>
						{ Array.from( { length: ROWS }, ( _, row ) => (
							<tr key={ row }>
								<td className="corex-data__check">
									<SkeletonBox />
								</td>
								{ COLUMNS.map( ( width, column ) => (
									<td key={ column }>
										<SkeletonBar width={ width } />
									</td>
								) ) }
								<td className="corex-data__row-actions">
									<SkeletonBar width="medium" />
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</CorexSkeleton>
	);
}
