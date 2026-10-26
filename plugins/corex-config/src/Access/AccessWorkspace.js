import { useReducer, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import AccessChangeDialog from './AccessChangeDialog.js';
import AccessGrantsPanel from './AccessGrantsPanel.js';
import AccessMatrixGrid from './AccessMatrixGrid.js';
import AccessRequestsPanel from './AccessRequestsPanel.js';
import CorexErrorState from '../admin/components/CorexErrorState.js';
import CorexSelect from '../admin/components/CorexSelect.js';
import { usePending, workingProps } from '../admin/components/working.js';
import { applyRoleChanges, previewRoleChanges } from './accessClient.js';
import {
	accessReducer,
	buildRoleChanges,
	initialAccessState,
} from './accessState.js';

const EFFECTS = [
	{ value: 'inherit', label: __( 'Inherit', 'corex' ) },
	{ value: 'allow', label: __( 'Allow', 'corex' ) },
	{ value: 'deny', label: __( 'Deny', 'corex' ) },
];

/**
 * Why a cell cannot be edited, in words. The server gives a code, and the code was printed.
 *
 * @param {string} reason `locked_definition` from the matrix, or `missing_role` for a role it has no cell for.
 * @return {string} The reason, translated.
 */
function lockedReason( reason ) {
	return reason === 'locked_definition'
		? __( 'Locked: this ability cannot be changed.', 'corex' )
		: __( 'This role cannot be given this ability here.', 'corex' );
}

const effectLabel = ( effect ) =>
	EFFECTS.find( ( candidate ) => candidate.value === effect )?.label ||
	effect;

/**
 * Today's editor for one role (spec 067): one choice per ability, then a preview and a confirmation.
 *
 * @param {Object}   props
 * @param {Object}   props.state    The access state.
 * @param {string}   props.role     The role's key.
 * @param {Array}    props.roles    The roles that can be edited.
 * @param {Function} props.dispatch The reducer's dispatch.
 * @return {Element} The editor.
 */
function RoleEditor( { state, role, roles, dispatch } ) {
	return (
		<>
			<div className="corex-field corex-access__mode-label">
				<span>{ __( 'Role', 'corex' ) }</span>
				<CorexSelect
					id="corex-access-role"
					label={ __( 'Role', 'corex' ) }
					value={ role }
					options={ roles.map( ( candidate ) => ( {
						value: candidate.key,
						label: candidate.name,
					} ) ) }
					onChange={ ( next ) =>
						dispatch( { type: 'selectRole', role: next } )
					}
					emptyLabel={ __( 'No roles available', 'corex' ) }
					block
				/>
			</div>
			<div className="corex-access__scroll">
				<table className="corex-access__matrix">
					<thead>
						<tr>
							<th>{ __( 'Ability', 'corex' ) }</th>
							<th>{ __( 'State', 'corex' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ state.rows.map( ( row ) => {
							const cell = row.cells[ role ] || {
								effect: 'inherit',
								editable: false,
								reason: 'missing_role',
							};
							return (
								<tr key={ row.key }>
									<th scope="row" title={ row.key }>
										{ row.label }
									</th>
									<td>
										<CorexSelect
											label={ row.label }
											value={
												state.draft?.[ role ]?.[
													row.key
												] || cell.effect
											}
											options={ EFFECTS }
											disabled={ ! cell.editable }
											onChange={ ( effect ) =>
												dispatch( {
													type: 'setEffect',
													role,
													ability: row.key,
													effect,
												} )
											}
										/>
										{ cell.reason ? (
											<span className="corex-access__muted">
												{ lockedReason( cell.reason ) }
											</span>
										) : null }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>
		</>
	);
}

export default function AccessWorkspace( { config } ) {
	// How many times the grants were changed here; the grants panel reads them again each time.
	const [ grantsRead, setGrantsRead ] = useState( 0 );
	const [ state, dispatch ] = useReducer(
		accessReducer,
		initialAccessState(),
		() =>
			accessReducer( initialAccessState(), {
				type: 'loaded',
				payload: config?.matrix || {},
			} )
	);
	// A role that administers the site holds every ability and has nothing to edit (spec 117).
	const editable = state.roles.filter( ( role ) => ! role.administers );
	const editing = state.editingRole;
	const changes = editing
		? buildRoleChanges( state.rows, state.draft, editing )
		: {};
	const [ pending, during ] = usePending();
	const roleName = ( key ) =>
		state.roles.find( ( role ) => role.key === key )?.name || key;

	const preview = async () => {
		try {
			const answer = await previewRoleChanges( config, editing, changes );
			dispatch( {
				type: 'preview',
				preview: { ...answer, role: editing },
			} );
		} catch ( reason ) {
			dispatch( { type: 'error', message: reason.message } );
		}
	};
	// A refusal is thrown, so the dialog says it where "Apply changes" was pressed.
	const apply = async () => {
		const result = await applyRoleChanges(
			config,
			state.preview.role,
			state.preview
		);
		if ( result.state !== 'completed' ) {
			throw new Error( result.message );
		}
		dispatch( {
			type: 'applied',
			role: state.preview.role,
			changes: state.preview.changes,
			message: result.message,
		} );
	};

	return (
		<section
			className="corex-surface corex-access-app"
			aria-label={ __( 'Editable CoreX access workspace', 'corex' ) }
		>
			<header className="corex-access__head corex-access__head--split">
				<div>
					<p className="corex-admin__eyebrow">
						{ __( 'COREX ABILITIES', 'corex' ) }
					</p>
					<h2>
						{ editing
							? sprintf(
									/* translators: %s: a role's name. */
									__( 'Editing %s', 'corex' ),
									roleName( editing )
								)
							: __( 'What each role can do', 'corex' ) }
					</h2>
				</div>
				{ editing ? (
					<div className="corex-access__head-actions">
						<button
							type="button"
							className="button"
							onClick={ () => dispatch( { type: 'grid' } ) }
						>
							{ __( 'Back to the grid', 'corex' ) }
						</button>
						<button
							type="button"
							className="button button-primary"
							disabled={ Object.keys( changes ).length === 0 }
							onClick={ () => during( 'preview', preview ) }
							{ ...workingProps( pending === 'preview' ) }
						>
							{ __( 'Preview changes', 'corex' ) }
						</button>
					</div>
				) : null }
			</header>
			{ state.notice?.tone === 'success' && (
				<p className="corex-access__notice" role="status">
					{ state.notice.message }
				</p>
			) }
			{ state.notice?.tone === 'error' && (
				<CorexErrorState
					scale="action"
					message={ state.notice.message }
				/>
			) }
			{ state.preview && (
				<AccessChangeDialog
					preview={ state.preview }
					roleName={ roleName( state.preview.role ) }
					rows={ state.rows }
					effectLabel={ effectLabel }
					onApply={ apply }
					onClose={ () => dispatch( { type: 'preview' } ) }
				/>
			) }
			{ state.conflicts.length ? (
				<p className="corex-access__muted">
					{ __(
						'A role plugin is active. CoreX manages only its own abilities here; WordPress capabilities stay with that plugin.',
						'corex'
					) }
				</p>
			) : null }
			{ editing ? (
				<RoleEditor
					state={ state }
					role={ editing }
					roles={ editable }
					dispatch={ dispatch }
				/>
			) : (
				<AccessMatrixGrid
					roles={ state.roles }
					rows={ state.rows }
					onEdit={ ( role ) => dispatch( { type: 'edit', role } ) }
				/>
			) }
			<div className="corex-access__panels">
				<AccessRequestsPanel
					config={ config }
					onApproved={ () => setGrantsRead( ( read ) => read + 1 ) }
				/>
				<AccessGrantsPanel
					config={ config }
					refreshKey={ grantsRead }
				/>
			</div>
		</section>
	);
}
