import { Button, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

function fieldFilterLabel( fieldLabel ) {
	/* translators: %s: field label. */
	return sprintf( __( 'Filter by %s', 'corex' ), fieldLabel );
}

export default function QueryBar( { explorer, openCreate, openExport } ) {
	const { state, source } = explorer;
	const filters = ( source?.fields || [] ).filter( ( field ) => field.filter_operators?.includes( 'equals' ) );
	return <div className="corex-data__panel-head">
		<div className="corex-data__panel-title"><h2>{ source?.label || __( 'Data', 'corex' ) }</h2>
			<span className="corex-data__qb">{ __( 'Live query', 'corex' ) }</span></div>
		<div className="corex-data__toolbar">
			<TextControl hideLabelFromVision label={ __( 'Search records', 'corex' ) }
				placeholder={ __( 'Search…', 'corex' ) } value={ state.query.search }
				onChange={ ( search ) => explorer.dispatch( { type: 'query', patch: { search } } ) } />
			{ filters.slice( 0, 2 ).map( ( field ) => <TextControl key={ field.key }
				hideLabelFromVision label={ fieldFilterLabel( field.label ) }
				placeholder={ field.label } value={ state.query.filters[ field.key ] || '' }
				onChange={ ( fieldValue ) => explorer.dispatch( { type: 'query', patch: {
					filters: { ...state.query.filters, [ field.key ]: fieldValue },
				} } ) } /> ) }
			{ explorer.can( 'create' ) && <Button variant="primary" onClick={ openCreate }>{ __( 'New record', 'corex' ) }</Button> }
			{ ( explorer.can( 'export_csv' ) || explorer.can( 'export_xlsx' ) ) &&
				<Button variant="secondary" onClick={ openExport }>{ __( 'Export', 'corex' ) }</Button> }
		</div>
	</div>;
}
