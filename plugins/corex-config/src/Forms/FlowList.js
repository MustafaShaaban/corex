import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';

const LIFECYCLE_STATES = [
	{ value: '', label: __( 'All states', 'corex' ) },
	{ value: 'draft', label: __( 'Draft', 'corex' ) },
	{ value: 'published', label: __( 'Published', 'corex' ) },
	{ value: 'closed', label: __( 'Closed', 'corex' ) },
	{ value: 'expired', label: __( 'Expired', 'corex' ) },
];

function NewFlowForm( { busy, ownerId, onCreate } ) {
	const submit = async ( event ) => {
		event.preventDefault();
		const form = new FormData( event.currentTarget );
		const created = await onCreate( {
			slug: form.get( 'slug' ),
			name: form.get( 'name' ),
			description: form.get( 'description' ),
			ownerId,
			successMessage: __( 'Thank you. Your submission was received.', 'corex' ),
		} );
		if ( created ) {
			event.currentTarget.reset();
		}
	};

	return (
		<section className="corex-surface corex-flow-list__create">
			<h2>{ __( 'New flow', 'corex' ) }</h2>
			<form onSubmit={ submit }>
				<label htmlFor="corex-flow-name">
					{ __( 'Flow name', 'corex' ) }
					<input id="corex-flow-name" name="name" required />
				</label>
				<label htmlFor="corex-flow-slug">
					{ __( 'Slug', 'corex' ) }
					<input id="corex-flow-slug" name="slug" pattern="[a-z][a-z0-9-]*" required />
				</label>
				<label htmlFor="corex-flow-description">
					{ __( 'Description', 'corex' ) }
					<textarea id="corex-flow-description" name="description" />
				</label>
				<button className="button button-primary" disabled={ busy }>
					{ __( 'Create draft', 'corex' ) }
				</button>
			</form>
		</section>
	);
}

function FlowRow( { flow, busy, onSelect } ) {
	return (
		<li>
			<button type="button" disabled={ busy } onClick={ () => onSelect( flow.id ) }>
				<span className="corex-flow-list__identity">
					<strong>{ flow.name }</strong>
					<code>{ flow.slug }</code>
				</span>
				<span className={ `corex-flow-list__state is-${ flow.state }` }>{ flow.state }</span>
				<span>{ flow.routing_target || __( 'No fallback', 'corex' ) }</span>
				<span>{ sprintf(
					/* translators: %d: Number of fields in a flow. */
					__( '%d fields', 'corex' ),
					flow.field_count
				) }</span>
				<time>{ new Date( flow.updated_at ).toLocaleString() }</time>
			</button>
		</li>
	);
}

export function FlowList( { flows, status, ownerId, onLoad, onCreate, onSelect } ) {
	const [ search, setSearch ] = useState( '' );
	const [ lifecycle, setLifecycle ] = useState( '' );
	const busy = status === 'loading' || status === 'mutating';
	const filter = ( event ) => {
		event.preventDefault();
		onLoad( search, lifecycle );
	};

	return (
		<div className="corex-flow-list">
			<NewFlowForm busy={ busy } ownerId={ ownerId } onCreate={ onCreate } />
			<section className="corex-surface corex-flow-list__catalog">
				<header>
					<div><h2>{ __( 'Flows', 'corex' ) }</h2><p>{ __( 'Search, filter, and open a persisted flow.', 'corex' ) }</p></div>
					<form className="corex-flow-list__filters" onSubmit={ filter }>
						<label htmlFor="corex-flow-search">
							{ __( 'Search flows', 'corex' ) }
							<input
								id="corex-flow-search"
								type="search"
								value={ search }
								onChange={ ( event ) => setSearch( event.target.value ) }
							/>
						</label>
						<div className="corex-field">
							<span>{ __( 'Lifecycle state', 'corex' ) }</span>
							<CorexSelect
								id="corex-flow-state-filter"
								label={ __( 'Lifecycle state', 'corex' ) }
								value={ lifecycle }
								options={ LIFECYCLE_STATES }
								onChange={ setLifecycle }
							/>
						</div>
						<button type="submit" className="button" disabled={ busy }>{ __( 'Apply filters', 'corex' ) }</button>
					</form>
				</header>
				{ busy && flows.length === 0 ? <p role="status">{ __( 'Loading flows…', 'corex' ) }</p> : null }
				{ ! busy && flows.length === 0 ? <p>{ __( 'No flows match this view.', 'corex' ) }</p> : null }
				<ul className="corex-flow-list__rows">
					{ flows.map( ( flow ) => (
						<FlowRow key={ flow.id } flow={ flow } busy={ busy } onSelect={ onSelect } />
					) ) }
				</ul>
			</section>
		</div>
	);
}
