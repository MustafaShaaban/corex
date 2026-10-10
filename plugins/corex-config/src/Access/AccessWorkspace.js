import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AccessChangeDialog from './AccessChangeDialog.js';
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

export default function AccessWorkspace( { config } ) {
	const [ state, dispatch ] = useReducer(
		accessReducer,
		initialAccessState(),
		() =>
			accessReducer( initialAccessState(), {
				type: 'loaded',
				payload: config?.matrix || {},
			} )
	);
	const selectedRole = state.selectedRole || state.roles[ 0 ]?.key || '';
	const changes = buildRoleChanges( state.rows, state.draft, selectedRole );
	const [ pending, during ] = usePending();

	const preview = async () => {
		try {
			const answer = await previewRoleChanges(
				config,
				selectedRole,
				changes
			);
			dispatch( {
				type: 'preview',
				preview: { ...answer, role: selectedRole },
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
					<h2>{ __( 'Editable access matrix', 'corex' ) }</h2>
				</div>
				<button
					type="button"
					className="button button-primary"
					disabled={ Object.keys( changes ).length === 0 }
					onClick={ () => during( 'preview', preview ) }
					{ ...workingProps( pending === 'preview' ) }
				>
					{ __( 'Preview changes', 'corex' ) }
				</button>
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
					roleName={
						state.roles.find(
							( role ) => role.key === state.preview.role
						)?.name || state.preview.role
					}
					rows={ state.rows }
					effectLabel={ effectLabel }
					onApply={ apply }
					onClose={ () => dispatch( { type: 'preview' } ) }
				/>
			) }
			{ state.conflicts.length ? (
				<p className="corex-access__muted">
					{ __(
						'External role plugin active. Native platform capabilities stay read-only; CoreX-owned abilities remain editable.',
						'corex'
					) }
				</p>
			) : null }
			<div className="corex-field corex-access__mode-label">
				<span>{ __( 'Role', 'corex' ) }</span>
				<CorexSelect
					id="corex-access-role"
					label={ __( 'Role', 'corex' ) }
					value={ selectedRole }
					options={ state.roles.map( ( role ) => ( {
						value: role.key,
						label: role.name,
					} ) ) }
					onChange={ ( role ) =>
						dispatch( { type: 'selectRole', role } )
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
							const cell = row.cells[ selectedRole ] || {
								effect: 'inherit',
								editable: false,
								reason: 'missing_role',
							};
							return (
								<tr key={ row.key }>
									<th scope="row">
										{ row.label }
										<code className="corex-access__cap">
											{ row.key }
										</code>
									</th>
									<td>
										<CorexSelect
											label={ row.label }
											value={
												state.draft?.[ selectedRole ]?.[
													row.key
												] || cell.effect
											}
											options={ EFFECTS }
											disabled={ ! cell.editable }
											onChange={ ( effect ) =>
												dispatch( {
													type: 'setEffect',
													role: selectedRole,
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
			<div className="corex-access__panels">
				<AccessRequestsPanel config={ config } />
				<section>
					<h3>{ __( 'Audit', 'corex' ) }</h3>
					<p className="corex-access__muted">
						{ __(
							'Denied attempts and access decisions appear here as real events.',
							'corex'
						) }
					</p>
					<ul>
						{ ( config?.audit || [] ).map( ( event, index ) => (
							<li key={ index }>
								{ event.kind || event.section }
							</li>
						) ) }
					</ul>
				</section>
			</div>
		</section>
	);
}
