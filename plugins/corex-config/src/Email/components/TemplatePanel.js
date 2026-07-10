import { __, sprintf } from '@wordpress/i18n';
import { Field } from './shared.js';

function TemplateRail( { templates, selectedId, busy, onCreate, onSelect } ) {
	return (
		<section className="corex-surface corex-email-app__rail-card">
			<h2>{ __( 'Templates', 'corex' ) }</h2>
			<form
				className="corex-email-app__compact-form"
				onSubmit={ onCreate }
			>
				<label htmlFor="corex-email-template-slug">
					{ __( 'New template slug', 'corex' ) }
					<input
						id="corex-email-template-slug"
						name="slug"
						required
						pattern="[a-z][a-z0-9-]*"
					/>
				</label>
				<label htmlFor="corex-email-template-name">
					{ __( 'Name', 'corex' ) }
					<input
						id="corex-email-template-name"
						name="name"
						required
					/>
				</label>
				<button className="button button-primary" disabled={ busy }>
					{ __( 'Create', 'corex' ) }
				</button>
			</form>
			<ul className="corex-email-app__list">
				{ templates.map( ( template ) => (
					<li key={ template.id }>
						<button
							type="button"
							className={
								selectedId === template.id ? 'is-active' : ''
							}
							onClick={ () => onSelect( template ) }
						>
							<strong>{ template.name }</strong>
							<code>{ template.slug }</code>
							<span>
								{ template.subject ||
									__( 'No draft subject', 'corex' ) }
							</span>
							<time>
								{ new Date(
									template.updated_at
								).toLocaleString() }
							</time>
							<span>{ template.status }</span>
						</button>
					</li>
				) ) }
			</ul>
		</section>
	);
}

function LayoutSelector( { layouts, draft, error, onChange } ) {
	return (
		<label htmlFor="corex-email-layout-selection">
			{ __( 'Layout revision', 'corex' ) }
			<select
				id="corex-email-layout-selection"
				name="layout_selection"
				value={ `${ draft.layout_id }:${ draft.layout_version }` }
				aria-invalid={ error ? 'true' : undefined }
				onChange={ onChange }
			>
				<option value="0:0" disabled>
					{ __( 'Choose a layout', 'corex' ) }
				</option>
				{ layouts.map( ( layout ) => (
					<option
						key={ `${ layout.id }:${ layout.version }` }
						value={ `${ layout.id }:${ layout.version }` }
					>
						{ sprintf(
							/* translators: 1: Email layout name. 2: Layout version number. */
							__( '%1$s — version %2$d', 'corex' ),
							layout.name,
							layout.version
						) }
					</option>
				) ) }
			</select>
			{ error && (
				<span className="corex-email-app__field-error">{ error }</span>
			) }
		</label>
	);
}

function DraftFields( { layouts, draft, errors, onChange } ) {
	return (
		<div className="corex-email-app__form-grid">
			<Field
				label={ __( 'Subject', 'corex' ) }
				name="subject"
				value={ draft.subject }
				error={ errors.subject }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'From name', 'corex' ) }
				name="from_name"
				value={ draft.from_name }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'From address', 'corex' ) }
				name="from_address"
				type="email"
				value={ draft.from_address }
				error={ errors.from_address }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'Variables (comma separated)', 'corex' ) }
				name="variable_keys"
				value={ draft.variable_keys.join( ', ' ) }
				onChange={ onChange }
			/>
			<Field
				label={ __( 'HTML body', 'corex' ) }
				name="html_body"
				value={ draft.html_body }
				error={ errors.html_body }
				onChange={ onChange }
				textarea
				wide
			/>
			<label htmlFor="corex-email-plain-text-mode">
				{ __( 'Plain-text mode', 'corex' ) }
				<select
					id="corex-email-plain-text-mode"
					name="plain_text_mode"
					value={ draft.plain_text_mode }
					onChange={ onChange }
				>
					<option value="auto">
						{ __( 'Generate automatically', 'corex' ) }
					</option>
					<option value="manual">{ __( 'Manual', 'corex' ) }</option>
				</select>
			</label>
			<Field
				label={ __( 'Plain text', 'corex' ) }
				name="plain_text"
				value={ draft.plain_text }
				error={ errors.plain_text }
				onChange={ onChange }
				textarea
				wide
			/>
			<LayoutSelector
				layouts={ layouts }
				draft={ draft }
				error={ errors.layout_id }
				onChange={ onChange }
			/>
		</div>
	);
}

function TemplateEditor( {
	layouts,
	detail,
	draft,
	errors,
	busy,
	onChange,
	onSave,
	onActivate,
} ) {
	if ( ! detail ) {
		return (
			<p>{ __( 'Select or create a template to edit it.', 'corex' ) }</p>
		);
	}

	return (
		<form onSubmit={ onSave }>
			<header>
				<div>
					<h2>{ detail.template.name }</h2>
					<code>{ detail.template.slug }</code>
				</div>
				<span className="corex-email-app__badge">
					{ detail.template.status }
				</span>
			</header>
			<DraftFields
				layouts={ layouts }
				draft={ draft }
				errors={ errors }
				onChange={ onChange }
			/>
			<div className="corex-email-app__actions">
				<button className="button button-primary" disabled={ busy }>
					{ __( 'Save immutable draft', 'corex' ) }
				</button>
				{ detail.template.draft_version > 0 && (
					<button
						type="button"
						className="button"
						disabled={ busy }
						onClick={ () =>
							onActivate( detail.template.draft_version )
						}
					>
						{ __( 'Activate latest draft', 'corex' ) }
					</button>
				) }
			</div>
		</form>
	);
}

export function TemplatePanel( props ) {
	return (
		<div className="corex-email-app__split">
			<TemplateRail
				templates={ props.templates }
				selectedId={ props.detail?.template?.id }
				busy={ props.busy }
				onCreate={ props.onCreate }
				onSelect={ props.onSelect }
			/>
			<section className="corex-surface corex-email-app__editor">
				<TemplateEditor
					layouts={ props.layouts }
					detail={ props.detail }
					draft={ props.draft }
					errors={ props.errors }
					busy={ props.busy }
					onChange={ props.onDraftChange }
					onSave={ props.onSaveDraft }
					onActivate={ props.onActivate }
				/>
			</section>
		</div>
	);
}
