import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

const EVENTS = [
	'submitter_confirmation',
	'team_notification',
	'admin_failure',
];

/**
 * Each email a flow sends, in words: the event's key is the flow's, not the reader's.
 *
 * @param {string} eventName The binding's event.
 * @return {string} Its name.
 */
export function eventLabel( eventName ) {
	switch ( eventName ) {
		case 'submitter_confirmation':
			return __( 'Confirmation to the person who filled it in', 'corex' );
		case 'team_notification':
			return __( 'Notification to your team', 'corex' );
		case 'admin_failure':
			return __( 'Alert when an email fails', 'corex' );
		default:
			return eventName;
	}
}

/** What every email of a flow supplies, whatever its fields (spec 120). */
const SUPPLIED = [
	'site.name',
	'site.url',
	'links.privacy',
	'recipient.email',
	'form.title',
	'form.answers',
	'links.submission',
];

/**
 * The variables a template uses that this flow's email does not supply: what the server refuses
 * to publish until it is removed or confirmed (spec 120, story 5).
 *
 * @param {string[]} used      The template's variables.
 * @param {Object[]} schema    The flow's fields.
 * @param {string}   eventName The binding's event.
 * @return {string[]} The variables it lacks.
 */
export function missingVariables( used, schema, eventName ) {
	const supplied = new Set( [
		...SUPPLIED,
		...( eventName === 'admin_failure' ? [ 'failure.message' ] : [] ),
		...schema
			.map( ( field ) => field?.key )
			.filter( Boolean )
			.map( ( key ) => `submission.${ key }` ),
	] );

	return ( used || [] ).filter( ( key ) => ! supplied.has( key ) );
}

function MissingVariables( { fieldId, missing, accepted, onAccept } ) {
	return (
		<div className="corex-flow-editor__warning" role="status">
			<p>
				{ sprintf(
					/* translators: %s: variable keys, comma separated. */
					__(
						'This template uses %s, which this form does not supply. Each is left out when the email is sent.',
						'corex'
					),
					missing.join( ', ' )
				) }
			</p>
			<label htmlFor={ `${ fieldId }-accept-missing` }>
				<input
					id={ `${ fieldId }-accept-missing` }
					type="checkbox"
					checked={ Boolean( accepted ) }
					onChange={ ( event ) => onAccept( event.target.checked ) }
				/>
				{ __( 'Send it without them', 'corex' ) }
			</label>
		</div>
	);
}

export function EmailsTab( { routes, templates, schema = [], onChange } ) {
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
				const template = templates.find(
					( candidate ) =>
						Number( candidate.id ) === Number( binding.template_id )
				);
				const missing = template
					? missingVariables( template.variables, schema, eventName )
					: [];
				return (
					<fieldset key={ eventName }>
						<legend>{ eventLabel( eventName ) }</legend>
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
									...templates.map( ( candidate ) => ( {
										value: String( candidate.id ),
										label: candidate.name,
									} ) ),
								] }
								onChange={ ( templateId ) =>
									update( eventName, {
										template_id: Number( templateId ),
										// A confirmation is for the template it was given for.
										accept_missing: false,
									} )
								}
								block
							/>
						</div>
						{ missing.length > 0 && (
							<MissingVariables
								fieldId={ fieldId }
								missing={ missing }
								accepted={ binding.accept_missing }
								onAccept={ ( accepted ) =>
									update( eventName, {
										accept_missing: accepted,
									} )
								}
							/>
						) }
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
