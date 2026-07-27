import { __ } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

const EVENTS = [
	'submitter_confirmation',
	'team_notification',
	'admin_failure',
];

export function EmailsTab( { routes, templates, onChange } ) {
	const update = ( eventName, changes ) => {
		const existing = routes.find(
			( route ) => route.event === eventName
		) || { event: eventName, enabled: false };
		onChange( [
			...routes.filter( ( route ) => route.event !== eventName ),
			{ ...existing, ...changes },
		] );
	};

	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Email bindings', 'corex' ) }</h2>
			<p>
				{ __(
					'Bind stored Email Studio templates and map recipients and reply-to values.',
					'corex'
				) }
			</p>
			{ EVENTS.map( ( eventName ) => {
				const binding =
					routes.find( ( route ) => route.event === eventName ) || {};
				// The event name is unique within the tab, so it names this binding's controls;
				// each label points at its own control by `for` rather than wrapping it.
				const fieldId = `corex-flow-emails-${ eventName }`;
				return (
					<fieldset key={ eventName }>
						<legend>{ eventName }</legend>
						<label htmlFor={ `${ fieldId }-enabled` }>
							<input
								id={ `${ fieldId }-enabled` }
								type="checkbox"
								checked={ Boolean( binding.enabled ) }
								onChange={ ( event ) =>
									update( eventName, {
										enabled: event.target.checked,
									} )
								}
							/>
							{ __( 'Enabled', 'corex' ) }
						</label>
						<div className="corex-flow-editor__field">
							<span>
								{ __( 'Email Studio template', 'corex' ) }
							</span>
							<CorexSelect
								label={ __( 'Email Studio template', 'corex' ) }
								value={ String( binding.template_id || '' ) }
								options={ [
									{
										value: '',
										label: __(
											'Select an active template…',
											'corex'
										),
									},
									...templates.map( ( template ) => ( {
										value: String( template.id ),
										label: template.name,
									} ) ),
								] }
								onChange={ ( templateId ) =>
									update( eventName, {
										template_id: Number( templateId ),
									} )
								}
								block
							/>
						</div>
						<label htmlFor={ `${ fieldId }-recipient` }>
							{ __( 'Recipient mapping', 'corex' ) }
							<input
								id={ `${ fieldId }-recipient` }
								value={ binding.recipient || '' }
								onChange={ ( event ) =>
									update( eventName, {
										recipient: event.target.value,
									} )
								}
							/>
						</label>
						<label htmlFor={ `${ fieldId }-reply-to` }>
							{ __( 'Reply-to mapping', 'corex' ) }
							<input
								id={ `${ fieldId }-reply-to` }
								value={ binding.reply_to || '' }
								onChange={ ( event ) =>
									update( eventName, {
										reply_to: event.target.value,
									} )
								}
							/>
						</label>
					</fieldset>
				);
			} ) }
			{ templates.length === 0 ? (
				<p>
					{ __(
						'Create and activate an Email Studio template before enabling a binding.',
						'corex'
					) }
				</p>
			) : null }
		</section>
	);
}
