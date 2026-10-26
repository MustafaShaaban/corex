import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';

const EVENTS = [
	[ 'submitter_confirmation', __( 'Submitter confirmation', 'corex' ) ],
	[ 'team_notification', __( 'Team notification', 'corex' ) ],
	[ 'admin_failure', __( 'Failure alert', 'corex' ) ],
];

/** The recipient "Routed owner": whoever the submission was routed to (spec 122, FR-017). */
export const ROUTED_OWNER = 'routed_owner';

/**
 * What kind of recipient a stored mapping is: "Routed owner", "The submitter" (one of the form's
 * email questions), or "An address" (anything else, as typed).
 *
 * @param {string} mapping The stored recipient mapping.
 * @param {Array}  schema  The flow's fields.
 * @return {string} `routed`, `submitter` or `address`.
 */
export function recipientKind( mapping, schema ) {
	const value = String( mapping || '' ).trim();
	if ( value === ROUTED_OWNER ) {
		return 'routed';
	}
	const key = value.replace( /^submission\./, '' );
	if (
		value !== '' &&
		schema.some( ( field ) => field.type === 'email' && field.key === key )
	) {
		return 'submitter';
	}
	return 'address';
}

/**
 * The recipient a newly chosen kind starts with: the routed owner, the form's first email
 * question, or an empty address to type.
 *
 * @param {string} kind        `routed`, `submitter` or `address`.
 * @param {Array}  emailFields The form's email questions.
 * @return {string} The mapping.
 */
export function firstRecipient( kind, emailFields ) {
	if ( kind === 'routed' ) {
		return ROUTED_OWNER;
	}
	return kind === 'submitter' ? emailFields[ 0 ]?.key || '' : '';
}

export function EmailsTab( {
	routes,
	routing,
	schema,
	templates,
	onPreview,
	onChange,
	onRoutingChange,
} ) {
	const update = ( eventName, changes ) => {
		const existing = routes.find(
			( route ) => route.event === eventName
		) || { event: eventName, enabled: false };
		onChange( [
			...routes.filter( ( route ) => route.event !== eventName ),
			{ ...existing, ...changes },
		] );
	};
	const emailFields = schema.filter( ( field ) => field.type === 'email' );

	return (
		<section className="corex-flow-editor__panel">
			<h2>{ __( 'Email bindings', 'corex' ) }</h2>
			<p>
				{ __(
					'Bind stored Email Studio templates and choose who each email goes to.',
					'corex'
				) }
			</p>
			{ EVENTS.map( ( [ eventName, eventLabel ] ) => {
				const binding =
					routes.find( ( route ) => route.event === eventName ) || {};
				// The event name is unique within the tab, so it names this binding's controls;
				// each label points at its own control by `for` rather than wrapping it.
				const fieldId = `corex-flow-emails-${ eventName }`;
				const kind = recipientKind( binding.recipient, schema );
				return (
					<fieldset key={ eventName }>
						<legend>{ eventLabel }</legend>
						<label
							htmlFor={ `${ fieldId }-enabled` }
							className="corex-flow-editor__check"
						>
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
						<div className="corex-field">
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
						<div className="corex-field">
							<span>{ __( 'To', 'corex' ) }</span>
							<CorexSelect
								id={ `${ fieldId }-to` }
								label={ __( 'To', 'corex' ) }
								value={ kind }
								options={ [
									{
										value: 'address',
										label: __( 'An address', 'corex' ),
									},
									{
										value: 'submitter',
										label: __( 'The submitter', 'corex' ),
									},
									{
										value: 'routed',
										label: __( 'Routed owner', 'corex' ),
									},
								] }
								onChange={ ( next ) =>
									update( eventName, {
										recipient: firstRecipient(
											next,
											emailFields
										),
									} )
								}
								block
							/>
						</div>
						{ kind === 'address' ? (
							<label htmlFor={ `${ fieldId }-recipient` }>
								{ __( 'Address', 'corex' ) }
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
						) : null }
						{ kind === 'submitter' ? (
							<div className="corex-field">
								<span>
									{ __( 'Their answer to', 'corex' ) }
								</span>
								<CorexSelect
									id={ `${ fieldId }-submitter` }
									label={ __( 'Their answer to', 'corex' ) }
									value={ String(
										binding.recipient || ''
									).replace( /^submission\./, '' ) }
									options={ emailFields.map( ( field ) => ( {
										value: field.key,
										label: field.label || field.key,
									} ) ) }
									emptyLabel={ __(
										'This form asks for no email address',
										'corex'
									) }
									onChange={ ( key ) =>
										update( eventName, { recipient: key } )
									}
									block
								/>
							</div>
						) : null }
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
			<RoutedOwner
				routing={ routing }
				schema={ schema }
				onPreview={ onPreview }
				onRoutingChange={ onRoutingChange }
			/>
		</section>
	);
}

/**
 * Where "Routed owner" goes when the routing names nobody with an address, how many people a role
 * may reach, and who it would be for each rule (spec 122, FR-018, FR-019).
 *
 * @param {Object}   props
 * @param {Object}   props.routing         The routing configuration on screen.
 * @param {Array}    props.schema          The flow's fields.
 * @param {Function} props.onPreview       Asks who Routed owner is; resolves to the lines.
 * @param {Function} props.onRoutingChange Given the changed routing configuration.
 * @return {import('react').ReactElement} The section.
 */
function RoutedOwner( { routing, schema, onPreview, onRoutingChange } ) {
	const [ lines, setLines ] = useState( null );
	const routingKey = JSON.stringify( routing );

	useEffect( () => {
		let current = true;
		setLines( null );
		onPreview( routing, schema ).then( ( answer ) => {
			if ( current ) {
				setLines( answer || [] );
			}
		} );
		return () => {
			current = false;
		};
		// The routing's content, not its identity, decides when to ask again.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ routingKey ] );

	return (
		<fieldset className="corex-flow-routed-owner">
			<legend>{ __( 'Routed owner', 'corex' ) }</legend>
			<label htmlFor="corex-flow-fallback-recipient">
				{ __( 'If Routed owner has no address, send to', 'corex' ) }
				<input
					id="corex-flow-fallback-recipient"
					type="email"
					placeholder={ __( 'The site administrator', 'corex' ) }
					value={ routing.fallback_recipient || '' }
					onChange={ ( event ) =>
						onRoutingChange( {
							...routing,
							fallback_recipient: event.target.value,
						} )
					}
				/>
			</label>
			<label htmlFor="corex-flow-recipient-limit">
				{ __( 'Most people a role can reach', 'corex' ) }
				<input
					id="corex-flow-recipient-limit"
					type="number"
					min="1"
					max="100"
					value={ routing.recipient_limit || 20 }
					onChange={ ( event ) =>
						onRoutingChange( {
							...routing,
							recipient_limit: Number( event.target.value ),
						} )
					}
				/>
			</label>
			<h3>{ __( 'Who Routed owner is', 'corex' ) }</h3>
			{ lines === null ? (
				<p className="corex-flow-editor__note" role="status">
					{ __( 'Working out who Routed owner is…', 'corex' ) }
				</p>
			) : (
				<ul className="corex-flow-routed-owner__lines">
					{ lines.map( ( line ) => (
						<li key={ line.label }>
							<strong>{ line.label }</strong>
							{ ': ' }
							{ line.target }
							{ line.people.length > 0 ? (
								<span>
									{ ' · ' }
									{ line.people
										.map( ( person ) =>
											person.reachable
												? person.name
												: sprintf(
														/* translators: %s: a person's name. */
														__(
															'%s (no address)',
															'corex'
														),
														person.name
													)
										)
										.join( ', ' ) }
								</span>
							) : null }
							{ line.note ? (
								<span className="corex-flow-editor__note">
									{ ' · ' }
									{ line.note }
								</span>
							) : null }
						</li>
					) ) }
				</ul>
			) }
		</fieldset>
	);
}
