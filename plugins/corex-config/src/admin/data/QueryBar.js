import { Button, SelectControl, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

function fieldFilterLabel( fieldLabel ) {
	/* translators: %s: field label. */
	return sprintf( __( 'Filter by %s', 'corex' ), fieldLabel );
}

/**
 * Fields we can offer real choices for instead of asking someone to type an identifier.
 *
 * Keyed by the SLUG here, because that is what this screen's sources store (meta
 * corex_form_slug). The submissions inbox filters the same forms by numeric ID
 * (meta corex_flow_id) — the two are not interchangeable, and swapping them yields a filter that
 * silently matches nothing.
 */
function choicesFor( field, flows ) {
	if ( field.key !== 'form' || ! flows.length ) {
		return null;
	}

	return [
		{ value: '', label: __( 'All forms', 'corex' ) },
		...flows.map( ( flow ) => ( { value: flow.slug, label: flow.name } ) ),
	];
}

export default function QueryBar( { explorer, openCreate, openExport, flows = [] } ) {
	const { state, source } = explorer;
	const filters = ( source?.fields || [] ).filter( ( field ) => field.filter_operators?.includes( 'equals' ) );
	return <div className="corex-data__panel-head">
		<div className="corex-data__panel-title"><h2>{ source?.label || __( 'Data', 'corex' ) }</h2>
			<span className="corex-data__qb">{ __( 'Live query', 'corex' ) }</span></div>
		<div className="corex-data__toolbar">
			<TextControl hideLabelFromVision label={ __( 'Search records', 'corex' ) }
				placeholder={ __( 'Search…', 'corex' ) } value={ state.query.search }
				onChange={ ( search ) => explorer.dispatch( { type: 'query', patch: { search } } ) } />
			{ filters.slice( 0, 2 ).map( ( field ) => {
				const patch = ( fieldValue ) => explorer.dispatch( { type: 'query', patch: {
					filters: { ...state.query.filters, [ field.key ]: fieldValue },
				} } );
				const choices = choicesFor( field, flows );

				// A list of real names where we have one; free text everywhere else, and the
				// search box above still matches text either way.
				return choices
					? <SelectControl key={ field.key } hideLabelFromVision
						label={ fieldFilterLabel( field.label ) }
						value={ state.query.filters[ field.key ] || '' }
						options={ choices } onChange={ patch } />
					: <TextControl key={ field.key }
						hideLabelFromVision label={ fieldFilterLabel( field.label ) }
						placeholder={ field.label } value={ state.query.filters[ field.key ] || '' }
						onChange={ patch } />;
			} ) }
			{ explorer.can( 'create' ) && <Button variant="primary" onClick={ openCreate }>{ __( 'New record', 'corex' ) }</Button> }
			{ ( explorer.can( 'export_csv' ) || explorer.can( 'export_xlsx' ) ) &&
				/* "Export records", not "Export": this explorer now lives on the same screen as the
				   Export tab, and two controls answering to the same name is ambiguous to anyone
				   navigating by label — a screen reader especially. It also says the more useful
				   thing: this exports the rows in view, the tab exports a model. */
				<Button variant="secondary" onClick={ openExport }>{ __( 'Export records', 'corex' ) }</Button> }
		</div>
	</div>;
}
