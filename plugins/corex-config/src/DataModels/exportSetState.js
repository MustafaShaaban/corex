/**
 * What the Export tab says and sends when several models are exported together, as plain
 * functions (spec 109, slice 2).
 *
 * One model's export has its own words in `dataExportState.js`: ticked rows, the filters in
 * force. Several models are every record of each, so what is left to choose is which models,
 * which of their columns, and the format of the one file.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	offeredFormats,
	tooManyForPdf,
} from '../admin/components/export/exportFormats.js';
import { listed } from '../admin/personalData.js';
import { dataColumnChoices, personalKinds } from './dataExportState.js';

const exportable = ( source ) =>
	Boolean( source?.actions?.export_csv?.visible );

/**
 * The models this person may export.
 *
 * @param {Array<Object>} sources The catalog: every source the screen was told of.
 * @return {Array<Object>} The ones with an export this person may make.
 */
export function exportableModels( sources ) {
	return ( Array.isArray( sources ) ? sources : [] ).filter( exportable );
}

/**
 * How many models are not in the list: they cannot be exported, or not by this person.
 *
 * @param {Array<Object>} sources The catalog.
 * @return {number} The count.
 */
export function modelsNotOffered( sources ) {
	return (
		( Array.isArray( sources ) ? sources : [] ).length -
		exportableModels( sources ).length
	);
}

/**
 * The models in the two groups they are listed in: what a site receives or keeps for its work,
 * then the site's own logs. A group that holds none is left out.
 *
 * @param {Array<Object>} models The models to choose from.
 * @return {Array<{key:string,label:string,all:string,models:Array<Object>}>} The groups, in order; `all` names the control that ticks a whole group.
 */
export function groupedModels( models ) {
	const groups = [
		{
			key: 'records',
			label: __( 'Records', 'corex' ),
			all: __( 'All records', 'corex' ),
		},
		{
			key: 'log',
			label: __( 'Logs', 'corex' ),
			all: __( 'All logs', 'corex' ),
		},
	];

	return groups
		.map( ( group ) => ( {
			...group,
			models: models.filter(
				// A model that does not say is listed with the records.
				( source ) => ( source.category || 'records' ) === group.key
			),
		} ) )
		.filter( ( group ) => group.models.length > 0 );
}

/**
 * The ticked models after every model of one group is ticked, or unticked.
 *
 * @param {string[]} ticked The keys ticked now.
 * @param {Object}   group  One of `groupedModels()`.
 * @param {boolean}  on     Whether to tick the group, or untick it.
 * @return {string[]} The keys ticked afterwards.
 */
export function withGroup( ticked, group, on ) {
	const keys = group.models.map( ( source ) => source.key );
	const others = ticked.filter( ( key ) => ! keys.includes( key ) );

	return on ? [ ...others, ...keys ] : others;
}

/**
 * The columns of a model that will be exported: the ones it was narrowed to, or every column
 * this person may export.
 *
 * @param {Object}                  source  The model.
 * @param {Object<string,string[]>} columns The columns a model was narrowed to, by model key.
 * @return {string[]} The keys of its columns, in the model's order.
 */
export function columnsOf( source, columns ) {
	return (
		columns[ source.key ] ||
		dataColumnChoices(
			source.fields || [],
			source.permissions?.personal_data === true
		).map( ( choice ) => choice.id )
	);
}

/**
 * How many records the models hold between them.
 *
 * @param {Array<Object>}              models The ticked models.
 * @param {Object<string,number>|null} counts Records by model key; null while they are asked for.
 * @return {number|null} The total, or null while any model's count is not known.
 */
export function setTotal( models, counts ) {
	if ( ! counts || models.some( ( source ) => ! ( source.key in counts ) ) ) {
		return null;
	}

	return models.reduce(
		( total, source ) => total + Number( counts[ source.key ] ),
		0
	);
}

/**
 * The kinds of personal data across the models' chosen columns, in words, once each.
 *
 * @param {Array<Object>}           models  The ticked models.
 * @param {Object<string,string[]>} columns The columns a model was narrowed to, by model key.
 * @return {string[]} The kinds; empty when nothing chosen holds personal data.
 */
export function setKinds( models, columns ) {
	return [
		...new Set(
			models.flatMap( ( source ) =>
				personalKinds(
					source.fields || [],
					columnsOf( source, columns )
				)
			)
		),
	];
}

/**
 * The formats several models can be exported in together: a workbook only where every one of
 * them can be a workbook.
 *
 * @param {Array<Object>}      models    The ticked models.
 * @param {string[]|undefined} available The formats the server can write where it runs.
 * @return {Array<{value:string,label:string}>} The formats.
 */
export function setFormats( models, available ) {
	const everyModelIsAWorkbook = models.every( ( source ) =>
		Boolean( source?.actions?.export_xlsx?.visible )
	);

	return offeredFormats( available ).filter(
		( format ) => format.value !== 'xlsx' || everyModelIsAWorkbook
	);
}

/**
 * What one file of several models holds, in a line under the choice of format.
 *
 * @param {string} format    The chosen format.
 * @param {number} [pdfMost] The most records one PDF holds.
 * @return {string} The line.
 */
export function setFormatDetail( format, pdfMost = 0 ) {
	if ( format === 'pdf' ) {
		return sprintf(
			/* translators: %s: the most records one PDF holds. */
			__(
				'One document, with a section for each model. Up to %s records in all.',
				'corex'
			),
			Number( pdfMost ).toLocaleString()
		);
	}

	return format === 'xlsx'
		? __( 'One workbook, with a sheet for each model.', 'corex' )
		: __( 'One archive (.zip), with a CSV file for each model.', 'corex' );
}

/**
 * Why the export cannot be started, or '' when it can.
 *
 * @param {Object}                     state
 * @param {Array<Object>}              state.models       The ticked models.
 * @param {Object<string,number>|null} state.counts       Records by model key; null while unknown.
 * @param {Object<string,string[]>}    state.columns      The columns a model was narrowed to.
 * @param {boolean}                    state.acknowledged Whether the personal-data notice is confirmed.
 * @param {string}                     state.problem      Why the counts could not be asked for; '' when they could.
 * @param {string}                     state.format       The chosen format.
 * @param {number}                     state.pdfMost      The most records one PDF holds.
 * @return {string} The reason, in words.
 */
export function setBlockedReason( {
	models,
	counts,
	columns,
	acknowledged,
	problem,
	format,
	pdfMost,
} ) {
	if ( problem ) {
		return problem;
	}
	const total = setTotal( models, counts );
	if ( total === null ) {
		return __( 'Counting the records…', 'corex' );
	}
	if ( total === 0 ) {
		return __( 'None of these models holds a record to export.', 'corex' );
	}
	if ( tooManyForPdf( format, total, pdfMost ) ) {
		return sprintf(
			/* translators: %s: the most records one PDF holds. */
			__(
				'A PDF holds up to %s records. Choose fewer models, or export a workbook.',
				'corex'
			),
			Number( pdfMost ).toLocaleString()
		);
	}
	const bare = models.find(
		( source ) => columnsOf( source, columns ).length === 0
	);
	if ( bare ) {
		return sprintf(
			/* translators: %s: the name of a data model, e.g. "Contacts". */
			__( 'Choose at least one column of %s.', 'corex' ),
			bare.label
		);
	}
	if ( setKinds( models, columns ).length > 0 && ! acknowledged ) {
		return __(
			'Confirm the personal-data notice to export these columns.',
			'corex'
		);
	}

	return '';
}

/**
 * The primary action, which says how much it will export and from how many models.
 *
 * @param {number|null} total      Records the models hold between them; null while unknown.
 * @param {number}      modelCount How many models are ticked.
 * @return {string} The button's label.
 */
export function setExportLabel( total, modelCount ) {
	if ( ! total ) {
		return __( 'Export', 'corex' );
	}

	return sprintf(
		/* translators: 1: a number of records, with its noun: "43 records". 2: a number of models, with its noun: "2 models". */
		__( 'Export %1$s from %2$s', 'corex' ),
		sprintf(
			/* translators: %s: a number of records. */
			_n( '%s record', '%s records', total, 'corex' ),
			total.toLocaleString()
		),
		sprintf(
			/* translators: %s: a number of data models. */
			_n( '%s model', '%s models', modelCount, 'corex' ),
			modelCount.toLocaleString()
		)
	);
}

/**
 * What the route for several models is sent. A model's columns are named only where it was
 * narrowed: left out, the server takes every column this person may export, which is the same
 * list this screen would have sent and cannot fall out of step with it.
 *
 * @param {Object}                  chosen
 * @param {Array<Object>}           chosen.models       The ticked models, in the list's order.
 * @param {Object<string,string[]>} chosen.columns      The columns a model was narrowed to.
 * @param {string}                  chosen.format       The chosen format.
 * @param {string}                  chosen.separator    The CSV separator's name.
 * @param {boolean}                 chosen.acknowledged Whether the personal-data notice is confirmed.
 * @return {Object} The request's body.
 */
export function buildSetPayload( chosen ) {
	return {
		parts: chosen.models.map( ( source ) =>
			chosen.columns[ source.key ]
				? { source: source.key, columns: chosen.columns[ source.key ] }
				: { source: source.key }
		),
		format: chosen.format,
		separator: chosen.separator,
		personal_data_acknowledged: Boolean( chosen.acknowledged ),
	};
}

/**
 * The models a past export covered, by name. A model that is no longer on the site is named
 * by its key, and one that could not be read when its turn came is marked.
 *
 * @param {Object}        entry   One export from the history, with its `parts`.
 * @param {Array<Object>} sources The catalog, for the models' names.
 * @return {string} The names, as one list.
 */
export function entryModels( entry, sources ) {
	const names = Object.fromEntries(
		sources.map( ( source ) => [ source.key, source.label ] )
	);

	return listed(
		( entry.parts || [] ).map( ( part ) => {
			const name = names[ part.source_key ] || part.source_key;

			return part.left_out
				? sprintf(
						/* translators: %s: the name of a data model that an export could not read. */
						__( '%s (left out)', 'corex' ),
						name
					)
				: name;
		} )
	);
}

/**
 * Whether a past export is still being written, and can be taken on from where it stopped.
 *
 * @param {Object} entry One export from the history.
 * @return {boolean} True while it has no file and its job is not done.
 */
export function canContinue( entry ) {
	return entry.state === 'queued' && entry.file_state === 'pending';
}
