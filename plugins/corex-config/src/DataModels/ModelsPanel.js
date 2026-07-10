import { __ } from '@wordpress/i18n';

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

export default function ModelsPanel( { sources } ) {
	if ( ! sources.length ) {
		return <p className="corex-data-models__empty">{ __( 'No data models are registered.', 'corex' ) }</p>;
	}

	return <div className="corex-data-models__list">{ sources.map( ( source ) =>
		<article key={ source.key } className="corex-surface corex-data-models__card">
			<header className="corex-data-models__card-head"><div><h2>{ source.label }</h2><code>{ source.key }</code></div>
				<span className={ `corex-data-models__access is-${ source.access }` }>
					{ source.access === 'allowed' ? __( 'Allowed', 'corex' ) : __( 'Denied', 'corex' ) }
				</span></header>
			<div className="corex-data-models__table-scroll" tabIndex={ 0 }><table className="corex-data-models__fields">
				<thead><tr><th>{ __( 'Field', 'corex' ) }</th><th>{ __( 'Type', 'corex' ) }</th><th>{ __( 'Behavior', 'corex' ) }</th></tr></thead>
				<tbody>{ source.fields.map( ( field ) => <tr key={ field.key }><td><code>{ field.key }</code><br />{ field.label }</td>
					<td>{ field.type }</td><td>{ field.read_only ? __( 'Read only', 'corex' ) : __( 'Writable', 'corex' ) }
						{ field.personal_data_class !== 'none' ? ` · ${ field.personal_data_class }` : '' }</td></tr> ) }</tbody>
			</table></div>
			<div className="corex-data-models__capabilities" aria-label={ __( 'Available actions', 'corex' ) }>
				{ Object.entries( source.actions ).filter( ( [ , action ] ) => action.visible )
					.map( ( [ key ] ) => <span key={ key }>{ actionLabel( key ) }</span> ) }
			</div>
		</article> ) }</div>;
}
