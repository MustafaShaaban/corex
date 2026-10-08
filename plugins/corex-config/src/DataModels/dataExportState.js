/**
 * What the Data export dialog says and sends, as plain functions (spec 103, US10).
 *
 * The same dialog as the Submissions export, over a different thing: a source's records, chosen by
 * its fields. The parts are shared; the words are these, tested without a browser.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/** The most rows one selected export may hold; the route refuses more. */
export const SELECTION_LIMIT = 500;

const SCOPE_SELECTED = 'selected';
const SCOPE_FILTERED = 'filtered';
const SCOPE_ALL = 'all';

/**
 * The search and the filters in force, in words.
 *
 * @param {Object|undefined} query  The explorer's query: `{search, filters}`.
 * @param {Array<Object>}    fields The source's fields, for the label a filter is shown under.
 * @return {string[]} One entry per filter in force; empty when none is.
 */
export function describeDataQuery( query, fields ) {
	const parts = [];

	if ( query?.search ) {
		parts.push(
			sprintf(
				/* translators: %s: what was typed in the search box. */
				__( 'Search: “%s”', 'corex' ),
				query.search
			)
		);
	}
	Object.entries( query?.filters || {} ).forEach( ( [ key, value ] ) => {
		if ( value === '' || value === null || value === undefined ) {
			return;
		}
		const field = fields.find( ( item ) => item.key === key );
		parts.push(
			sprintf(
				/* translators: 1: the name of a field that is filtered by. 2: the value it is filtered to. */
				__( '%1$s: %2$s', 'corex' ),
				field ? field.label : key,
				String( value )
			)
		);
	} );

	return parts;
}

function selectedDetail( selectedCount ) {
	if ( selectedCount === 0 ) {
		return __( 'Tick rows in the table to export only those.', 'corex' );
	}
	if ( selectedCount > SELECTION_LIMIT ) {
		return sprintf(
			/* translators: %d: the most rows one selected export may hold. */
			__(
				'More than %d rows are ticked. Use the filters to export that many.',
				'corex'
			),
			SELECTION_LIMIT
		);
	}

	return __( 'Only the rows ticked in the table.', 'corex' );
}

/**
 * The scopes as the dialog offers them, each with its count and what it covers (FR-010). Where
 * the dialog is opened with no table behind it, there are no rows and no filters to choose by,
 * and everything is the one scope.
 *
 * @param {Object}      options
 * @param {Object|null} options.counts        `{selected, filtered, all}`, or null while they load.
 * @param {number}      options.selectedCount How many rows are ticked.
 * @param {string[]}    options.filters       The filters in force, in words.
 * @param {boolean}     options.withRows      Whether there is a table of rows behind the dialog.
 * @return {Array<{value:string,label:string,detail:string,count:number|null,disabled:boolean}>} The scopes.
 */
export function dataScopes( { counts, selectedCount, filters, withRows } ) {
	const count = ( scope ) => ( counts ? counts[ scope ] : null );
	const everything = {
		value: SCOPE_ALL,
		label: __( 'Everything', 'corex' ),
		detail: withRows
			? __( 'Every record you can see, whatever the filters.', 'corex' )
			: __( 'Every record you can see.', 'corex' ),
		count: count( SCOPE_ALL ),
		disabled: false,
	};

	if ( ! withRows ) {
		return [ everything ];
	}

	return [
		{
			value: SCOPE_SELECTED,
			label: __( 'Selected rows', 'corex' ),
			detail: selectedDetail( selectedCount ),
			// None ticked is none, without asking. Some is what the server counts: a ticked row
			// can have gone out of reach.
			count: selectedCount === 0 ? 0 : count( SCOPE_SELECTED ),
			disabled: selectedCount === 0 || selectedCount > SELECTION_LIMIT,
		},
		{
			value: SCOPE_FILTERED,
			label: __( 'Current filters', 'corex' ),
			detail: filters.length
				? filters.join( ' · ' )
				: __(
						'No filter is on, so this is every record you can see.',
						'corex'
				  ),
			count: count( SCOPE_FILTERED ),
			disabled: false,
		},
		everything,
	];
}

/**
 * The scope the dialog opens on: the selection when there is one, as that is what was just done.
 *
 * @param {number}  selectedCount How many rows are ticked.
 * @param {boolean} withRows      Whether there is a table of rows behind the dialog.
 * @return {string} A scope.
 */
export function defaultDataScope( selectedCount, withRows ) {
	if ( ! withRows ) {
		return SCOPE_ALL;
	}

	return selectedCount > 0 && selectedCount <= SELECTION_LIMIT
		? SCOPE_SELECTED
		: SCOPE_FILTERED;
}

/**
 * The source's fields as columns to choose from.
 *
 * @param {Array<Object>} fields The source's fields.
 * @return {Array<{id:string,label:string,personal:boolean,hint:string}>} One choice per field.
 */
export function dataColumnChoices( fields ) {
	return fields.map( ( field ) => ( {
		id: field.key,
		label: field.label,
		personal: field.personal_data_class !== 'none',
		hint: '',
	} ) );
}

/**
 * The formats a source can be exported in. Excel is first where it is offered, as it is in the
 * Submissions export.
 *
 * @param {Object} source The source, with the actions this person may take on it.
 * @return {Array<{value:string,label:string}>} The formats.
 */
export function dataFormats( source ) {
	const csv = { value: 'csv', label: __( 'CSV (.csv)', 'corex' ) };

	return source?.actions?.export_xlsx?.visible
		? [
				{
					value: 'xlsx',
					label: __( 'Excel workbook (.xlsx)', 'corex' ),
				},
				csv,
		  ]
		: [ csv ];
}

/**
 * What each format is for, in a line under the choice.
 *
 * @param {string} format The chosen format.
 * @return {string} The line.
 */
export function dataFormatDetail( format ) {
	return format === 'xlsx'
		? __(
				'Dates and numbers sort and filter. The headings stay in view.',
				'corex'
		  )
		: __( 'Plain text that any spreadsheet or tool opens.', 'corex' );
}

/**
 * Why the export cannot be started, or '' when it can.
 *
 * @param {Object}      state
 * @param {number|null} state.count        Records the chosen scope would export; null while unknown.
 * @param {string[]}    state.columns      The keys of the chosen columns.
 * @param {boolean}     state.personal     Whether a chosen column holds personal data.
 * @param {boolean}     state.acknowledged Whether the personal-data notice is confirmed.
 * @param {string}      state.problem      Why the counts could not be asked for; '' when they could.
 * @return {string} The reason, in words.
 */
export function dataBlockedReason( {
	count,
	columns,
	personal,
	acknowledged,
	problem,
} ) {
	if ( problem ) {
		return problem;
	}
	if ( count === null ) {
		return __( 'Counting the records…', 'corex' );
	}
	if ( count === 0 ) {
		return __( 'There is nothing to export with this choice.', 'corex' );
	}
	if ( columns.length === 0 ) {
		return __( 'Choose at least one column.', 'corex' );
	}
	if ( personal && ! acknowledged ) {
		return __(
			'Confirm the personal-data notice to export these columns.',
			'corex'
		);
	}

	return '';
}

/**
 * The primary action, which says how much it will export.
 *
 * @param {number|null} count Records the chosen scope would export.
 * @return {string} The button's label.
 */
export function dataExportLabel( count ) {
	if ( ! count ) {
		return __( 'Export', 'corex' );
	}

	return sprintf(
		/* translators: %s: a number of records. */
		_n( 'Export %s record', 'Export %s records', count, 'corex' ),
		count.toLocaleString()
	);
}

/**
 * How far an export has got, for a progress bar and for the words beside it.
 *
 * @param {{processed:number,total:number}} progress The job's progress.
 * @return {{value:number,max:number,text:string}} What to show.
 */
export function dataProgress( progress ) {
	const max = Math.max( 1, Number( progress.total ) || 0 );
	const value = Math.min( max, Number( progress.processed ) || 0 );

	return {
		value,
		max,
		text: sprintf(
			/* translators: 1: records exported so far. 2: records to export. */
			__( '%1$s of %2$s records', 'corex' ),
			value.toLocaleString(),
			max.toLocaleString()
		),
	};
}

/**
 * What the export route is sent. A selection sends its rows and no query; the current filters
 * send the search, the filters and the order the table is in, and not the page it happens to be
 * on.
 *
 * @param {Object}   chosen
 * @param {string}   chosen.scope        One of the three scopes.
 * @param {number[]} chosen.selectedIds  The ticked rows.
 * @param {Object}   chosen.query        The explorer's query.
 * @param {string[]} chosen.columns      The keys of the chosen columns.
 * @param {string}   chosen.format       `xlsx` or `csv`.
 * @param {string}   chosen.separator    `comma`, `semicolon` or `tab`.
 * @param {boolean}  chosen.acknowledged Whether the personal-data notice is confirmed.
 * @return {Object} The request's body.
 */
export function buildDataExportPayload( chosen ) {
	return {
		scope: chosen.scope,
		selected_ids:
			chosen.scope === SCOPE_SELECTED ? [ ...chosen.selectedIds ] : [],
		query:
			chosen.scope === SCOPE_FILTERED
				? {
						search: chosen.query?.search || '',
						filters: { ...( chosen.query?.filters || {} ) },
						sort: chosen.query?.sort || '',
						dir: chosen.query?.dir || '',
				  }
				: {},
		columns: [ ...chosen.columns ],
		format: chosen.format,
		separator: chosen.separator,
		personal_data_acknowledged: Boolean( chosen.acknowledged ),
	};
}
