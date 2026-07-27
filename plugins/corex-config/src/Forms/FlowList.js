import { useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import CorexSelect from '../admin/components/CorexSelect.js';
import {
	catalogRows,
	readOnlyCount,
	SOURCE_CODE_FORM,
	SOURCE_EXTERNAL,
} from './formCatalog.js';

const LIFECYCLE_STATES = [
	{ value: '', label: __( 'All states', 'corex' ) },
	{ value: 'draft', label: __( 'Draft', 'corex' ) },
	{ value: 'published', label: __( 'Published', 'corex' ) },
	{ value: 'closed', label: __( 'Closed', 'corex' ) },
	{ value: 'expired', label: __( 'Expired', 'corex' ) },
];

/**
 * Translated state names, so the row never prints a raw English identifier.
 * @param state
 */
function stateLabel( state ) {
	const labels = {
		draft: __( 'Draft', 'corex' ),
		published: __( 'Published', 'corex' ),
		closed: __( 'Closed', 'corex' ),
		expired: __( 'Expired', 'corex' ),
		registered: __( 'Registered', 'corex' ),
		inactive: __( 'Inactive', 'corex' ),
	};

	return labels[ state ] || state;
}

/**
 * Where a form came from, said in words rather than left to a colour.
 * @param source
 */
function sourceLabel( source ) {
	if ( source === SOURCE_CODE_FORM ) {
		return __( 'Defined in code', 'corex' );
	}
	if ( source === SOURCE_EXTERNAL ) {
		return __( 'Provided by a module', 'corex' );
	}

	return __( 'Visual flow', 'corex' );
}

function NewFlowForm( { busy, ownerId, onCreate } ) {
	const submit = async ( event ) => {
		event.preventDefault();
		const form = new FormData( event.currentTarget );
		const created = await onCreate( {
			slug: form.get( 'slug' ),
			name: form.get( 'name' ),
			description: form.get( 'description' ),
			ownerId,
			successMessage: __(
				'Thank you. Your submission was received.',
				'corex'
			),
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
					<input
						id="corex-flow-slug"
						name="slug"
						pattern="[a-z][a-z0-9-]*"
						required
					/>
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

/**
 * The shared cells, so an editable and a read-only row read as the same kind of thing.
 * @param root0
 * @param root0.row
 */
function RowSummary( { row } ) {
	return (
		<>
			<span className="corex-flow-list__identity">
				<strong>{ row.name }</strong>
				<code>{ row.slug }</code>
				{ /* The badge belongs with the name: it says what kind of thing this row is,
				     which is the first question a mixed list raises. Keeping it out of its own
				     column also keeps the grid at five tracks, so nothing runs off the card. */ }
				<span
					className={ `corex-flow-list__source is-${ row.source }` }
				>
					{ sourceLabel( row.source ) }
				</span>
			</span>
			<span className={ `corex-flow-list__state is-${ row.state }` }>
				{ stateLabel( row.state ) }
			</span>
			<span>
				{ sprintf(
					/* translators: %d: Number of fields in a form. */
					_n( '%d field', '%d fields', row.fieldCount, 'corex' ),
					row.fieldCount
				) }
			</span>
		</>
	);
}

function FlowRow( { row, busy, onSelect } ) {
	return (
		<li className="corex-flow-list__row is-editable">
			<button
				type="button"
				disabled={ busy }
				onClick={ () => onSelect( row.id ) }
			>
				<RowSummary row={ row } />
				<span>
					{ row.routingTarget || __( 'No fallback', 'corex' ) }
				</span>
				{ row.updatedAt ? (
					<time dateTime={ row.updatedAt }>
						{ new Date( row.updatedAt ).toLocaleString() }
					</time>
				) : (
					<span />
				) }
			</button>
		</li>
	);
}

/**
 * A form that lives in code. It is a real definition, not a stub — its fields, its validation, and
 * a route to its submissions — with one plain sentence saying why the builder cannot open it.
 * @param root0
 * @param root0.row
 * @param root0.submissionsUrl
 */
function CodeFormRow( { row, submissionsUrl } ) {
	const [ open, setOpen ] = useState( false );
	const panelId = `corex-form-detail-${ row.slug }`;
	const filterUrl = submissionsUrl
		? `${ submissionsUrl }&corex_form=slug:${ encodeURIComponent(
				row.slug
		  ) }`
		: '';

	return (
		<li className="corex-flow-list__row is-read-only">
			<button
				type="button"
				aria-expanded={ open }
				aria-controls={ panelId }
				onClick={ () => setOpen( ( current ) => ! current ) }
			>
				<RowSummary row={ row } />
				<span>
					{ row.submissionCount === null
						? __( 'Submission count unavailable', 'corex' )
						: sprintf(
								/* translators: %d: Number of submissions received by a form. */
								_n(
									'%d submission',
									'%d submissions',
									row.submissionCount,
									'corex'
								),
								row.submissionCount
						  ) }
				</span>
				<span className="corex-flow-list__disclose">
					{ open
						? __( 'Hide details', 'corex' )
						: __( 'Show details', 'corex' ) }
				</span>
			</button>
			{ open && (
				<div className="corex-flow-list__detail" id={ panelId }>
					<p className="corex-flow-list__explains">
						{ row.source === SOURCE_EXTERNAL
							? __(
									'Another module owns this form, so the visual builder cannot change it. Its fields and rules come from that module.',
									'corex'
							  )
							: __(
									'This form is defined in your code through Corex\\Forms\\FormRegistry, so the visual builder cannot change it. Edit the form class to change its fields or rules.',
									'corex'
							  ) }
					</p>
					{ row.fields.length > 0 ? (
						<table className="corex-flow-list__fields">
							<caption>
								{ __( 'Field definitions', 'corex' ) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Field', 'corex' ) }
									</th>
									<th scope="col">
										{ __( 'Type', 'corex' ) }
									</th>
									<th scope="col">
										{ __( 'Validation', 'corex' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ row.fields.map( ( field ) => (
									<tr key={ field.key }>
										<th scope="row">
											{ field.label }
											<code>{ field.key }</code>
										</th>
										<td>{ field.type }</td>
										<td>
											{ field.rules.length > 0
												? field.rules.join( ', ' )
												: __( 'No rules', 'corex' ) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p>
							{ __(
								'This form does not publish its field definitions to CoreX.',
								'corex'
							) }
						</p>
					) }
					{ row.validationSummary ? (
						<p className="corex-flow-list__validation">
							<strong>
								{ __( 'Validation summary', 'corex' ) }
							</strong>{ ' ' }
							{ row.validationSummary }
						</p>
					) : null }
					{ filterUrl ? (
						<p>
							<a className="button" href={ filterUrl }>
								{ __(
									'View submissions for this form',
									'corex'
								) }
							</a>
						</p>
					) : null }
				</div>
			) }
		</li>
	);
}

export function FlowList( {
	flows,
	catalog = [],
	submissionsUrl = '',
	status,
	ownerId,
	onLoad,
	onCreate,
	onSelect,
} ) {
	const [ search, setSearch ] = useState( '' );
	const [ lifecycle, setLifecycle ] = useState( '' );
	const [ applied, setApplied ] = useState( { search: '', lifecycle: '' } );
	const busy = status === 'loading' || status === 'mutating';

	const rows = useMemo(
		() => catalogRows( flows, catalog, applied ),
		[ flows, catalog, applied ]
	);
	const readOnly = readOnlyCount( rows );

	const filter = ( event ) => {
		event.preventDefault();
		// The applied filters, not the live inputs: flows are filtered on the server by this same
		// submit, so the client-side half has to change at the same moment or the list flickers
		// between two different filter states.
		setApplied( { search, lifecycle } );
		onLoad( search, lifecycle );
	};

	return (
		<div className="corex-flow-list">
			<NewFlowForm
				busy={ busy }
				ownerId={ ownerId }
				onCreate={ onCreate }
			/>
			<section
				className="corex-surface corex-flow-list__catalog"
				data-status={ busy ? 'loading' : 'ready' }
			>
				<header>
					<div>
						<h2>{ __( 'Forms & flows', 'corex' ) }</h2>
						<p>
							{ readOnly > 0
								? sprintf(
										/* translators: %d: Number of forms that are defined in code. */
										_n(
											'Every form CoreX knows about. %d is defined in code and shown read-only.',
											'Every form CoreX knows about. %d are defined in code and shown read-only.',
											readOnly,
											'corex'
										),
										readOnly
								  )
								: __(
										'Every form CoreX knows about. Search, filter, and open a flow.',
										'corex'
								  ) }
						</p>
					</div>
					<form
						className="corex-flow-list__filters"
						onSubmit={ filter }
					>
						<label htmlFor="corex-flow-search">
							{ __( 'Search forms', 'corex' ) }
							<input
								id="corex-flow-search"
								type="search"
								value={ search }
								onChange={ ( event ) =>
									setSearch( event.target.value )
								}
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
						<button
							type="submit"
							className="button"
							disabled={ busy }
						>
							{ __( 'Apply filters', 'corex' ) }
						</button>
					</form>
				</header>
				{ /* Announced whenever a request is in flight, not only on an empty list. Code
				     forms render from localised data with no request at all, so gating the
				     loading state on an empty list meant the flows half could load with no
				     indication that anything was still coming. */ }
				{ busy ? (
					<p role="status">{ __( 'Loading forms…', 'corex' ) }</p>
				) : null }
				{ ! busy && rows.length === 0 ? (
					<p>{ __( 'No forms match this view.', 'corex' ) }</p>
				) : null }
				<ul className="corex-flow-list__rows">
					{ rows.map( ( row ) =>
						row.editable ? (
							<FlowRow
								key={ row.key }
								row={ row }
								busy={ busy }
								onSelect={ onSelect }
							/>
						) : (
							<CodeFormRow
								key={ row.key }
								row={ row }
								submissionsUrl={ submissionsUrl }
							/>
						)
					) }
				</ul>
			</section>
		</div>
	);
}
