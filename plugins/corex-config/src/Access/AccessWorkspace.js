import { useReducer } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';
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
				>
					{ __( 'Preview changes', 'corex' ) }
				</button>
			</header>
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
												{ cell.reason }
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
				<section>
					<h3>{ __( 'Access requests', 'corex' ) }</h3>
					<p className="corex-access__muted">
						{ __(
							'Pending request workflow is backed by the Access REST routes.',
							'corex'
						) }
					</p>
					<ul>
						{ ( config?.requests || [] ).map( ( request ) => (
							<li key={ request.id }>{ request.reason }</li>
						) ) }
					</ul>
				</section>
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
