import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

function actionLabel( action ) {
	const labels = {
		read: __( 'Read', 'corex' ),
		query: __( 'Query', 'corex' ),
		schema: __( 'Schema', 'corex' ),
		detail: __( 'Detail', 'corex' ),
		create: __( 'Create', 'corex' ),
		update: __( 'Update', 'corex' ),
		delete: __( 'Delete', 'corex' ),
		bulk_update: __( 'Bulk update', 'corex' ),
		bulk_delete: __( 'Bulk delete', 'corex' ),
		import_dry_run: __( 'Import dry-run', 'corex' ),
		import_commit: __( 'Import commit', 'corex' ),
		export_csv: __( 'Export CSV', 'corex' ),
		export_xlsx: __( 'Export XLSX', 'corex' ),
		migrations: __( 'Migrations', 'corex' ),
		rollback: __( 'Rollback', 'corex' ),
	};

	return labels[ action ] || action;
}

function ModelEntry( { source, open, onToggle } ) {
	const visibleActions = Object.entries( source.actions ).filter( ( [ , action ] ) => action.visible );

	return (
		/* <details>, not a button + region: disclosure is exactly what this element is for, so
		   keyboard operation and the expanded/collapsed announcement come from the browser rather
		   than from markup we have to keep correct by hand. */
		<details
			className="corex-surface corex-data-models__card"
			open={ open }
			onToggle={ ( event ) => onToggle( source.key, event.currentTarget.open ) }
		>
			<summary className="corex-data-models__card-head">
				<div>
					<h2>{ source.label }</h2>
					<code>{ source.key }</code>
				</div>
				<span className={ `corex-data-models__access is-${ source.access }` }>
					{ source.access === 'allowed' ? __( 'Allowed', 'corex' ) : __( 'Denied', 'corex' ) }
				</span>
				{ /* Enough of the model to decide whether to open it, without opening it. */ }
				<span className="corex-data-models__summary-meta">
					{ sprintf(
						/* translators: %d: number of fields on the model. */
						__( '%d fields', 'corex' ),
						source.fields.length
					) }
				</span>
			</summary>
			<div className="corex-data-models__card-body">
				<div className="corex-data-models__table-scroll" tabIndex={ 0 }>
					<table className="corex-data-models__fields">
						<thead><tr><th>{ __( 'Field', 'corex' ) }</th><th>{ __( 'Type', 'corex' ) }</th><th>{ __( 'Behavior', 'corex' ) }</th></tr></thead>
						<tbody>{ source.fields.map( ( field ) => <tr key={ field.key }><td><code>{ field.key }</code><br />{ field.label }</td>
							<td>{ field.type }</td><td>{ field.read_only ? __( 'Read only', 'corex' ) : __( 'Writable', 'corex' ) }
								{ field.personal_data_class !== 'none' ? ` · ${ field.personal_data_class }` : '' }</td></tr> ) }</tbody>
					</table>
				</div>
				<div className="corex-data-models__capabilities" aria-label={ __( 'Available actions', 'corex' ) }>
					{ visibleActions.map( ( [ key ] ) => <span key={ key }>{ actionLabel( key ) }</span> ) }
				</div>
			</div>
		</details>
	);
}

export default function ModelsPanel( { sources } ) {
	// The first model is expanded so the panel never opens as an unreadable row of closed bars;
	// the rest stay shut so a site with many models is scannable, which is the point.
	const [ expanded, setExpanded ] = useState( () => new Set( sources.length ? [ sources[ 0 ].key ] : [] ) );

	const toggle = ( key, isOpen ) => {
		setExpanded( ( current ) => {
			const next = new Set( current );
			if ( isOpen ) {
				next.add( key );
			} else {
				next.delete( key );
			}

			return next;
		} );
	};

	if ( ! sources.length ) {
		return <p className="corex-data-models__empty">{ __( 'No data models are registered.', 'corex' ) }</p>;
	}

	return <div className="corex-data-models__list">
		{ sources.map( ( source ) => <ModelEntry
			key={ source.key }
			source={ source }
			open={ expanded.has( source.key ) }
			onToggle={ toggle }
		/> ) }
	</div>;
}
