import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../../admin/components/CorexSelect.js';
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
	const options = [
		...( includeNone
			? [ { value: 'none', label: __( 'No reply-to rule', 'corex' ) } ]
			: [] ),
		{ value: 'context', label: __( 'Context path', 'corex' ) },
		{ value: 'literal', label: __( 'Fixed address', 'corex' ) },
	];

	return (
		<div className="corex-field">
			<span id={ `${ id }-label` }>{ label }</span>
			<CorexSelect
				id={ id }
				name={ name }
				label={ label }
				options={ options }
				defaultValue={ includeNone ? 'none' : 'context' }
				block
			/>
		</div>
	);
}

function TemplateSelect( { templates } ) {
	const label = __( 'Template', 'corex' );

	// The native control carried `required` with an empty placeholder option. A custom control
	// cannot use browser validation, so the guarantee is kept by construction instead: the list
	// holds only real templates and the first is preselected, so `template_id` is never empty.
	// When there are no templates at all the control says so and is not openable, which is more
	// honest than a form that validates and then fails at the server.
	return (
		<div className="corex-field">
			<span id="corex-email-route-template-label">{ label }</span>
			<CorexSelect
				id="corex-email-route-template"
				name="template_id"
				label={ label }
				options={ templates.map( ( template ) => ( {
					value: String( template.id ),
					label: template.name,
				} ) ) }
				emptyLabel={ __(
					'No templates yet — create one first',
					'corex'
				) }
				block
			/>
		</div>
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
