import { __ } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

export function SuccessTab( {
	success,
	stateTypes,
	actions = [],
	registeredActions = [],
	onChange,
	onActionsChange = () => {},
} ) {
	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Success behavior', 'corex' ) }</h2>
			<div className="corex-field">
				<span>{ __( 'Behavior', 'corex' ) }</span>
				<CorexSelect
					id="corex-flow-success-type"
					label={ __( 'Behavior', 'corex' ) }
					value={ success.type || 'inline' }
					options={ stateTypes.map( ( type ) => ( {
						value: type,
						label: type,
					} ) ) }
					onChange={ ( type ) => onChange( { type } ) }
					block
				/>
			</div>
			{ success.type === 'inline' ? (
				<label htmlFor="corex-flow-success-message">
					{ __( 'Message', 'corex' ) }
					<textarea
						id="corex-flow-success-message"
						value={ success.message || '' }
						onChange={ ( event ) =>
							onChange( {
								...success,
								message: event.target.value,
							} )
						}
					/>
				</label>
			) : null }
			{ success.type === 'page' ? (
				<label htmlFor="corex-flow-success-page">
					{ __( 'Page ID', 'corex' ) }
					<input
						id="corex-flow-success-page"
						type="number"
						min="1"
						value={ success.page_id || '' }
						onChange={ ( event ) =>
							onChange( {
								...success,
								page_id: Number( event.target.value ),
							} )
						}
					/>
				</label>
			) : null }
			{ success.type === 'url' ? (
				<label htmlFor="corex-flow-success-url">
					{ __( 'Redirect URL', 'corex' ) }
					<input
						id="corex-flow-success-url"
						type="url"
						value={ success.url || '' }
						onChange={ ( event ) =>
							onChange( { ...success, url: event.target.value } )
						}
					/>
				</label>
			) : null }
			<AfterSubmission
				chosen={ actions }
				registered={ registeredActions }
				onChange={ onActionsChange }
			/>
		</section>
	);
}

/**
 * The flow actions a developer registered, each run once after a submission is stored (spec 122,
 * US-FLW-07). With none registered, the section says so instead of offering nothing.
 *
 * @param {Object}   props
 * @param {Array}    props.chosen     The keys this flow runs.
 * @param {Array}    props.registered The catalog's `flow_actions`.
 * @param {Function} props.onChange   Given the chosen keys.
 * @return {import('react').ReactElement} The section.
 */
function AfterSubmission( { chosen, registered, onChange } ) {
	const toggle = ( key, on ) =>
		onChange(
			on
				? [
						...chosen.filter( ( candidate ) => candidate !== key ),
						key,
					]
				: chosen.filter( ( candidate ) => candidate !== key )
		);
	const missing = chosen.filter(
		( key ) => ! registered.some( ( action ) => action.key === key )
	);

	return (
		<fieldset className="corex-flow-actions">
			<legend>{ __( 'After submission', 'corex' ) }</legend>
			{ registered.length === 0 ? (
				<p className="corex-flow-editor__note">
					{ __(
						'No actions are registered on this site. A developer adds them with the corex_flow_actions hook.',
						'corex'
					) }
				</p>
			) : (
				<>
					<p className="corex-flow-editor__note">
						{ __(
							'Each chosen action runs once, after the submission is stored. Its result is on the submission’s history; a failing action stops nothing.',
							'corex'
						) }
					</p>
					{ registered.map( ( action ) => (
						<label
							key={ action.key }
							htmlFor={ `corex-flow-action-${ action.key }` }
							className="corex-flow-editor__check"
						>
							<input
								id={ `corex-flow-action-${ action.key }` }
								type="checkbox"
								checked={ chosen.includes( action.key ) }
								onChange={ ( event ) =>
									toggle( action.key, event.target.checked )
								}
							/>
							{ action.label }
						</label>
					) ) }
				</>
			) }
			{ missing.length > 0 ? (
				<p className="corex-flow-editor__note" role="alert">
					{ __(
						'This flow runs an action the site no longer has. Untick it before publishing:',
						'corex'
					) }{ ' ' }
					{ missing.join( ', ' ) }
					<button
						type="button"
						className="button-link"
						onClick={ () =>
							onChange(
								chosen.filter(
									( key ) => ! missing.includes( key )
								)
							)
						}
					>
						{ __( 'Remove it', 'corex' ) }
					</button>
				</p>
			) : null }
		</fieldset>
	);
}
