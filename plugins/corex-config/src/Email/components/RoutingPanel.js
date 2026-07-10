import { __, sprintf } from '@wordpress/i18n';
import { Field } from './shared.js';

function RouteList( { routes } ) {
	return (
		<section className="corex-surface corex-email-app__rail-card">
			<h2>{ __( 'Routes', 'corex' ) }</h2>
			<ul className="corex-email-app__list">
				{ routes.map( ( route ) => (
					<li key={ route.id }>
						<div className="corex-email-app__asset">
							<strong>{ route.trigger }</strong>
							<span>
								{ sprintf(
									/* translators: %d: Email template numeric ID. */
									__( 'Template #%d', 'corex' ),
									route.template_id
								) }
							</span>
							<span>
								{ sprintf(
									/* translators: %d: Route priority number. */
									__( 'Priority %d', 'corex' ),
									route.priority
								) }
							</span>
							<span>
								{ route.reply_to_rule
									? __( 'Reply-to configured', 'corex' )
									: __( 'No reply-to rule', 'corex' ) }
							</span>
							<span>
								{ route.enabled
									? __( 'Enabled', 'corex' )
									: __( 'Disabled', 'corex' ) }
							</span>
						</div>
					</li>
				) ) }
			</ul>
		</section>
	);
}

function SourceSelect( { id, name, label, includeNone = false } ) {
	return (
		<label htmlFor={ id }>
			{ label }
			<select
				id={ id }
				name={ name }
				defaultValue={ includeNone ? 'none' : 'context' }
			>
				{ includeNone && (
					<option value="none">
						{ __( 'No reply-to rule', 'corex' ) }
					</option>
				) }
				<option value="context">
					{ __( 'Context path', 'corex' ) }
				</option>
				<option value="literal">
					{ __( 'Fixed address', 'corex' ) }
				</option>
			</select>
		</label>
	);
}

function TemplateSelect( { templates } ) {
	return (
		<label htmlFor="corex-email-route-template">
			{ __( 'Template', 'corex' ) }
			<select
				id="corex-email-route-template"
				name="template_id"
				required
				defaultValue=""
			>
				<option value="" disabled>
					{ __( 'Choose a template', 'corex' ) }
				</option>
				{ templates.map( ( template ) => (
					<option key={ template.id } value={ template.id }>
						{ template.name }
					</option>
				) ) }
			</select>
		</label>
	);
}

function RouteForm( { templates, busy, onSave } ) {
	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ __( 'Bind a trigger', 'corex' ) }</h2>
			<form className="corex-email-app__form-grid" onSubmit={ onSave }>
				<Field
					label={ __( 'Trigger', 'corex' ) }
					name="trigger"
					placeholder="forms.contact.submitted"
					required
				/>
				<TemplateSelect templates={ templates } />
				<Field
					label={ __( 'Flow ID (optional)', 'corex' ) }
					name="flow_id"
					type="number"
					min="1"
				/>
				<Field
					label={ __( 'Priority', 'corex' ) }
					name="priority"
					type="number"
					min="0"
					defaultValue="100"
					required
				/>
				<SourceSelect
					id="corex-email-recipient-source"
					name="recipient_source"
					label={ __( 'Recipient source', 'corex' ) }
				/>
				<Field
					label={ __( 'Recipient path or address', 'corex' ) }
					name="recipient_value"
					placeholder="submission.email"
					required
				/>
				<SourceSelect
					id="corex-email-reply-source"
					name="reply_to_source"
					label={ __( 'Reply-to source', 'corex' ) }
					includeNone
				/>
				<Field
					label={ __( 'Reply-to path or address', 'corex' ) }
					name="reply_to_value"
					placeholder="submission.email"
				/>
				<input
					type="hidden"
					name="template_version_policy"
					value="active"
				/>
				<label
					htmlFor="corex-email-route-enabled"
					className="corex-email-app__check"
				>
					<input
						id="corex-email-route-enabled"
						type="checkbox"
						name="enabled"
						defaultChecked
					/>
					{ __( 'Route enabled', 'corex' ) }
				</label>
				<div className="corex-email-app__actions is-wide">
					<button className="button button-primary" disabled={ busy }>
						{ __( 'Save route', 'corex' ) }
					</button>
				</div>
			</form>
		</section>
	);
}

export function RoutingPanel( { routes, templates, busy, onSave } ) {
	return (
		<div className="corex-email-app__split">
			<RouteList routes={ routes } />
			<RouteForm
				templates={ templates }
				busy={ busy }
				onSave={ onSave }
			/>
		</div>
	);
}
