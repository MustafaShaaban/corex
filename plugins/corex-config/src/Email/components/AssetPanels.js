import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Field } from './shared.js';

function AssetActions( { item, onEdit, onInsert } ) {
	return (
		<div className="corex-email-app__asset-actions">
			<button
				type="button"
				className="button"
				aria-label={ sprintf(
					/* translators: %s: Email asset slug. */
					__( 'Revise %s', 'corex' ),
					item.slug
				) }
				onClick={ () => onEdit( item ) }
			>
				{ __( 'Revise', 'corex' ) }
			</button>
			{ onInsert && (
				<button
					type="button"
					className="button"
					aria-label={ sprintf(
						/* translators: %s: Email partial slug. */
						__( 'Insert %s partial in template', 'corex' ),
						item.slug
					) }
					onClick={ () => onInsert( item.slug ) }
				>
					{ __( 'Insert in template', 'corex' ) }
				</button>
			) }
		</div>
	);
}

function AssetList( { title, items, onEdit, onInsert } ) {
	return (
		<section className="corex-surface corex-email-app__rail-card">
			<h2>{ title }</h2>
			<ul className="corex-email-app__list">
				{ items.map( ( item ) => (
					<li key={ `${ item.slug }-${ item.version }` }>
						<div className="corex-email-app__asset">
							<div className="corex-email-app__asset-meta">
								<strong>{ item.name }</strong>
								<code>
									{ onInsert
										? `{{> ${ item.slug } }}`
										: item.slug }
								</code>
								<span>
									{ sprintf(
										/* translators: %d: Email asset revision number. */
										__( 'Version %d', 'corex' ),
										item.version
									) }
								</span>
							</div>
							<AssetActions
								item={ item }
								onEdit={ onEdit }
								onInsert={ onInsert }
							/>
						</div>
					</li>
				) ) }
			</ul>
		</section>
	);
}

function editorTitle( editing ) {
	return editing
		? sprintf(
				/* translators: %s: Email asset name. */
				__( 'Revise %s', 'corex' ),
				editing.name
		  )
		: __( 'Create a new revision', 'corex' );
}

function AssetEditor( { editing, fields, busy, onSubmit, onCancel } ) {
	const fieldValue = ( name ) =>
		editing?.regions?.[ name ] ?? editing?.[ name ] ?? '';
	const submit = async ( event ) => {
		const result = await onSubmit( event );
		if ( result ) {
			onCancel();
		}
	};

	return (
		<section className="corex-surface corex-email-app__editor">
			<h2>{ editorTitle( editing ) }</h2>
			<form
				key={
					editing ? `${ editing.slug }-${ editing.version }` : 'new'
				}
				className="corex-email-app__form-grid"
				onSubmit={ submit }
			>
				{ fields.map( ( [ name, label, textarea, required ] ) => (
					<Field
						key={ name }
						name={ name }
						label={ label }
						textarea={ textarea }
						wide={ textarea }
						defaultValue={ fieldValue( name ) }
						required={ required }
					/>
				) ) }
				<div className="corex-email-app__actions is-wide">
					<button className="button button-primary" disabled={ busy }>
						{ __( 'Save revision', 'corex' ) }
					</button>
					{ editing && (
						<button
							type="button"
							className="button"
							onClick={ onCancel }
						>
							{ __( 'Cancel revision', 'corex' ) }
						</button>
					) }
				</div>
			</form>
		</section>
	);
}

function AssetPanel( { title, items, busy, onSubmit, fields, onInsert } ) {
	const [ editing, setEditing ] = useState( null );
	return (
		<div className="corex-email-app__split">
			<AssetList
				title={ title }
				items={ items }
				onEdit={ setEditing }
				onInsert={ onInsert }
			/>
			<AssetEditor
				editing={ editing }
				fields={ fields }
				busy={ busy }
				onSubmit={ onSubmit }
				onCancel={ () => setEditing( null ) }
			/>
		</div>
	);
}

export function LayoutPanel( { layouts, busy, onSave } ) {
	return (
		<AssetPanel
			title={ __( 'Layouts', 'corex' ) }
			items={ layouts }
			busy={ busy }
			onSubmit={ onSave }
			fields={ [
				[ 'slug', __( 'Slug', 'corex' ), false, true ],
				[ 'name', __( 'Name', 'corex' ), false, true ],
				[ 'header', __( 'Header HTML', 'corex' ), true, false ],
				[ 'accent', __( 'Accent color (hex)', 'corex' ), false, false ],
				[
					'body',
					__( 'Body HTML with {{ content }}', 'corex' ),
					true,
					true,
				],
				[ 'button', __( 'Button HTML', 'corex' ), true, false ],
				[ 'footer', __( 'Footer HTML', 'corex' ), true, false ],
			] }
		/>
	);
}

export function PartialPanel( { partials, busy, onSave, onInsert } ) {
	return (
		<AssetPanel
			title={ __( 'Partials', 'corex' ) }
			items={ partials }
			busy={ busy }
			onSubmit={ onSave }
			onInsert={ onInsert }
			fields={ [
				[ 'slug', __( 'Slug', 'corex' ), false, true ],
				[ 'name', __( 'Name', 'corex' ), false, true ],
				[ 'kind', __( 'Kind', 'corex' ), false, true ],
				[ 'html_body', __( 'HTML body', 'corex' ), true, true ],
				[ 'plain_text', __( 'Plain text', 'corex' ), true, true ],
			] }
		/>
	);
}

export function VariablesPanel( { catalog, onInsert } ) {
	if ( Object.keys( catalog ).length === 0 ) {
		return (
			<section className="corex-surface corex-email-app__table-card">
				<h2>{ __( 'Variables', 'corex' ) }</h2>
				<p>
					{ __( 'No template variables are registered.', 'corex' ) }
				</p>
			</section>
		);
	}

	return (
		<div className="corex-email-app__stack">
			{ Object.entries( catalog ).map( ( [ group, variables ] ) => (
				<section
					key={ group }
					className="corex-surface corex-email-app__table-card"
				>
					<h2>{ group }</h2>
					<ul className="corex-email-app__variables">
						{ Object.entries( variables ).map(
							( [ key, definition ] ) => (
								<li key={ key }>
									<div>
										<strong>{ definition.label }</strong>
										<code>{ `{{ ${ key } }}` }</code>
									</div>
									<button
										type="button"
										className="button"
										aria-label={ sprintf(
											/* translators: %s: Email variable key. */ __(
												'Insert %s in template',
												'corex'
											),
											key
										) }
										onClick={ () => onInsert( key ) }
									>
										{ __( 'Insert in template', 'corex' ) }
									</button>
								</li>
							)
						) }
					</ul>
				</section>
			) ) }
		</div>
	);
}
