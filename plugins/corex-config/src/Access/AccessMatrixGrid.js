import { __, sprintf } from '@wordpress/i18n';

/**
 * The words and mark for each state a cell resolves to (spec 117, US-ACC-02). The mark is drawn for
 * the eye only; the words are what a screen reader reads, with the row and column headers.
 *
 * @return {Object<string,{mark:string,label:string}>} By state.
 */
export function stateLabels() {
	return {
		allowed_set: { mark: '✓', label: __( 'Allowed (set here)', 'corex' ) },
		denied_set: { mark: '✕', label: __( 'Denied (set here)', 'corex' ) },
		allowed_admin: {
			mark: '★',
			label: __(
				'Allowed through site administration (inherited)',
				'corex'
			),
		},
		not_granted: {
			mark: '–',
			label: __( 'Not granted (inherited)', 'corex' ),
		},
		locked: { mark: '🔒', label: __( 'Locked', 'corex' ) },
	};
}

/**
 * Why a cell cannot be set here, in words, or null when it can.
 *
 * @param {Object} cell The cell.
 * @return {string|null} The reason.
 */
export function cellReason( cell ) {
	if ( cell.reason === 'site_administrator' ) {
		return cell.ignoredDeny
			? __(
					'Site administrators hold every CoreX ability. A Deny stored here before is kept and ignored.',
					'corex'
				)
			: __( 'Site administrators hold every CoreX ability.', 'corex' );
	}
	if ( cell.reason === 'locked_definition' ) {
		return __(
			'Locked: this ability comes only with site administration and cannot be set for a role.',
			'corex'
		);
	}
	return null;
}

/**
 * The rows under their area, in the order the server sent them.
 *
 * @param {Array} rows The matrix rows.
 * @return {Array<{key:string,label:string,rows:Array}>} One entry per area.
 */
export function rowsByArea( rows ) {
	const areas = [];
	( rows || [] ).forEach( ( row ) => {
		let area = areas.find( ( candidate ) => candidate.key === row.group );
		if ( ! area ) {
			area = { key: row.group, label: row.groupLabel, rows: [] };
			areas.push( area );
		}
		area.rows.push( row );
	} );
	return areas;
}

function Cell( { cell, rowKey, roleKey, roleName } ) {
	const { mark, label } =
		stateLabels()[ cell.state ] || stateLabels().not_granted;
	const reason = cellReason( cell );
	const tipId = `corex-access-why-${ rowKey }-${ roleKey }`;

	return (
		<td className={ `corex-access__cell is-${ cell.state }` }>
			{ /* Shown only where the grid folds into one list per ability, at a phone's width,
			   where the column header is no longer above the cell. */ }
			<span className="corex-access__cell-role">{ roleName }</span>
			<span
				className="corex-access__why"
				// A cell that cannot be set says why when it is focused or pointed at.
				tabIndex={ reason ? 0 : undefined }
				aria-describedby={ reason ? tipId : undefined }
			>
				<span
					className={ `corex-access__mark is-${ cell.state }` }
					aria-hidden="true"
				>
					{ mark }
				</span>
				<span className="corex-access__state">{ label }</span>
			</span>
			{ reason ? (
				<span className="corex-access__tip" role="tooltip" id={ tipId }>
					{ reason }
				</span>
			) : null }
		</td>
	);
}

/**
 * Every CoreX ability down the side, by area, and every role across the top, each cell in words.
 * "Edit" on a role opens today's editor for it; a role that administers the site has none. At a
 * phone's width the grid folds into one list per ability, each cell named by its role
 * (DECISIONS #424), so nothing is wider than the screen.
 *
 * @param {Object}   props
 * @param {Array}    props.roles  The roles.
 * @param {Array}    props.rows   The matrix rows.
 * @param {Function} props.onEdit Called with a role's key.
 * @return {Element} The grid.
 */
export default function AccessMatrixGrid( { roles, rows, onEdit } ) {
	return (
		<div
			className="corex-access__scroll corex-access__grid-frame"
			role="region"
			aria-label={ __( 'CoreX abilities by role', 'corex' ) }
			// The frame scrolls on its own, so a keyboard has to be able to reach it.
			tabIndex={ 0 }
		>
			<table className="corex-access__grid">
				<thead>
					<tr>
						<th scope="col">{ __( 'Ability', 'corex' ) }</th>
						{ roles.map( ( role ) => (
							<th scope="col" key={ role.key }>
								<span className="corex-access__role-name">
									{ role.name }
								</span>
								{ role.administers ? (
									<span className="corex-access__muted">
										{ __( 'Site administrator', 'corex' ) }
									</span>
								) : (
									<button
										type="button"
										className="button button-small"
										aria-label={ sprintf(
											/* translators: %s: a role's name. */
											__( 'Edit %s', 'corex' ),
											role.name
										) }
										onClick={ () => onEdit( role.key ) }
									>
										{ __( 'Edit', 'corex' ) }
									</button>
								) }
							</th>
						) ) }
					</tr>
				</thead>
				{ rowsByArea( rows ).map( ( area ) => (
					<tbody key={ area.key }>
						<tr className="corex-access__grid-area">
							<th scope="colgroup" colSpan={ roles.length + 1 }>
								{ area.label }
							</th>
						</tr>
						{ area.rows.map( ( row ) => (
							<tr key={ row.key }>
								<th scope="row" title={ row.key }>
									<span className="corex-access__ability">
										{ row.label }
									</span>
									<span className="corex-access__muted">
										{ row.description }
									</span>
								</th>
								{ roles.map( ( role ) => (
									<Cell
										key={ role.key }
										cell={
											row.cells[ role.key ] || {
												state: 'not_granted',
												reason: null,
											}
										}
										rowKey={ row.key }
										roleKey={ role.key }
										roleName={ role.name }
									/>
								) ) }
							</tr>
						) ) }
					</tbody>
				) ) }
			</table>
		</div>
	);
}
