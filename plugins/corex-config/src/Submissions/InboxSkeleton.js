/**
 * The placeholder for the inbox's table, before a view's first answer (spec 108, slice 6).
 *
 * Drawn in the table's own markup, with the row's own button (a button, for the font a button
 * has), so a placeholder row has the padding, the columns and the two lines of text of the row
 * that replaces it, from the same rules. The trash has a column the inbox has not, and the other way round, so both have the
 * same number and one placeholder stands for either.
 */
import CorexSkeleton, {
	SkeletonBar,
	SkeletonBox,
} from '../admin/components/CorexSkeleton.js';

const ROWS = 8;

/** One width per column after the submitter: flow, status, notification or trashed, owner, received. */
const COLUMNS = [ 'long', 'medium', 'medium', 'long', 'medium' ];

export default function InboxSkeleton() {
	return (
		<CorexSkeleton>
			<div className="corex-inbox__table-wrap">
				<table className="corex-inbox__table">
					<thead>
						<tr>
							<th>
								<SkeletonBox />
							</th>
							<th>
								<SkeletonBar width="short" />
							</th>
							{ COLUMNS.map( ( _, column ) => (
								<th key={ column }>
									<SkeletonBar width="medium" />
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ Array.from( { length: ROWS }, ( _, row ) => (
							<tr key={ row }>
								<td className="corex-inbox__select-cell">
									<SkeletonBox />
								</td>
								<td>
									<button
										type="button"
										className="corex-inbox__row-button"
										disabled
									>
										<strong>
											<SkeletonBar width="medium" />
										</strong>
										<small>
											<SkeletonBar width="long" />
										</small>
									</button>
								</td>
								{ COLUMNS.map( ( width, column ) => (
									<td key={ column }>
										<SkeletonBar width={ width } />
									</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</CorexSkeleton>
	);
}
