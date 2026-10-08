/**
 * What the export dialog says and sends, as plain functions (spec 103, US2 and US3).
 *
 * Kept apart from the dialog so that the words a person reads — what each choice covers, how many
 * submissions, why a choice cannot be made — are decided in one place and tested without a
 * browser.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { tooManyForPdf } from '../../admin/components/export/exportFormats.js';

/** The scopes the export route accepts, in the order the dialog offers them. */
export const SCOPES = [ 'selected', 'filtered', 'accessible' ];

/** The most rows one "selected" export may hold. The route refuses more. */
export const SELECTION_LIMIT = 100;

/**
 * The choices of columns, as a person thinks of them. Each stands for one or more of the column
 * names the route takes. `personal` marks what a visitor said or what was recorded about them:
 * choosing one needs the personal-data confirmation and the right to export personal data.
 *
 * @return {Array<{id:string,label:string,hint:string,columns:string[],personal:boolean}>} The column choices.
 */
export function columnChoices() {
	return [
		{
			id: 'details',
			label: __( 'Submission details', 'corex' ),
			hint: __( 'ID, when it was submitted, and the form.', 'corex' ),
			columns: [ 'id', 'submitted', 'form' ],
			personal: false,
		},
		{
			id: 'triage',
			label: __( 'Status and assignment', 'corex' ),
			hint: __(
				'Status, who it is assigned to, read, and test.',
				'corex'
			),
			columns: [ 'status', 'assigned_to', 'read', 'test' ],
			personal: false,
		},
		{
			id: 'answers',
			label: __( 'Answers', 'corex' ),
			hint: __(
				'One column per question, headed by the question.',
				'corex'
			),
			columns: [ 'answers' ],
			personal: true,
		},
		{
			id: 'utm',
			label: __( 'Campaign data', 'corex' ),
			hint: __(
				'Where the visitor came from, when it was recorded.',
				'corex'
			),
			columns: [ 'utm' ],
			personal: true,
		},
		{
			id: 'hidden_metadata',
			label: __( 'Hidden fields', 'corex' ),
			hint: __(
				'Values the form sent that the visitor did not type.',
				'corex'
			),
			columns: [ 'hidden_metadata' ],
			personal: true,
		},
		{
			id: 'consent_snapshot',
			label: __( 'Consent record', 'corex' ),
			hint: __( 'What the visitor agreed to, and when.', 'corex' ),
			columns: [ 'consent_snapshot' ],
			personal: true,
		},
		{
			id: 'notes',
			label: __( 'Team notes', 'corex' ),
			hint: __( 'Internal notes, one cell per submission.', 'corex' ),
			columns: [ 'notes' ],
			personal: true,
		},
	];
}

/** What is ticked when the dialog opens for somebody who has not chosen before. */
export const DEFAULT_CHOICES = [ 'details', 'triage', 'answers' ];

/**
 * The choices offered to this person: without the right to export personal data, the choices
 * that hold it are not offered at all.
 *
 * @param {boolean} canExportPersonalData Whether this person may export personal data.
 * @return {Array<Object>} The choices they may make.
 */
export function offeredChoices( canExportPersonalData ) {
	return columnChoices().filter(
		( choice ) => canExportPersonalData || ! choice.personal
	);
}

/**
 * @param {string[]} chosen Ids of the chosen column choices.
 * @return {string[]} The column names the route takes, in the dialog's order.
 */
export function columnsFor( chosen ) {
	return columnChoices()
		.filter( ( choice ) => chosen.includes( choice.id ) )
		.flatMap( ( choice ) => choice.columns );
}

/**
 * @param {string[]} chosen Ids of the chosen column choices.
 * @return {boolean} Whether any of them holds personal data.
 */
export function holdsPersonalData( chosen ) {
	return columnChoices().some(
		( choice ) => choice.personal && chosen.includes( choice.id )
	);
}

/**
 * The filters in force, in words, for the "current filters" choice (FR-011).
 *
 * @param {Object}        filters      The inbox's filters.
 * @param {Array<Object>} flows        The forms the inbox can filter by.
 * @param {Object}        statusLabels Status key => its name.
 * @param {Function}      formatDate   Turns a `YYYY-MM-DD` filter value into a date a person reads.
 * @return {string[]} One entry per filter in force; empty when none is.
 */
export function describeFilters( filters, flows, statusLabels, formatDate ) {
	const parts = [];
	const flow = String( filters.flow || '' );

	if ( flow !== '' ) {
		const match = flows.find(
			( item ) =>
				String( item.id ) === flow || `slug:${ item.slug }` === flow
		);
		parts.push(
			sprintf(
				/* translators: %s: a form's name. */
				__( 'Form: %s', 'corex' ),
				match ? match.name : flow.replace( /^slug:/, '' )
			)
		);
	}
	if ( filters.status ) {
		parts.push(
			sprintf(
				/* translators: %s: a submission status, e.g. "New". */
				__( 'Status: %s', 'corex' ),
				statusLabels[ filters.status ] || filters.status
			)
		);
	}
	if ( filters.owner ) {
		parts.push(
			sprintf(
				/* translators: %s: the owner a submission is assigned to. */
				__( 'Owner: %s', 'corex' ),
				filters.owner
			)
		);
	}
	if ( filters.dateFrom && filters.dateTo ) {
		parts.push(
			sprintf(
				/* translators: 1: the first day of a date range. 2: its last day. */
				__( '%1$s to %2$s', 'corex' ),
				formatDate( filters.dateFrom ),
				formatDate( filters.dateTo )
			)
		);
	} else if ( filters.dateFrom ) {
		parts.push(
			sprintf(
				/* translators: %s: a date. */
				__( 'From %s', 'corex' ),
				formatDate( filters.dateFrom )
			)
		);
	} else if ( filters.dateTo ) {
		parts.push(
			sprintf(
				/* translators: %s: a date. */
				__( 'Until %s', 'corex' ),
				formatDate( filters.dateTo )
			)
		);
	}
	if ( filters.search ) {
		parts.push(
			sprintf(
				/* translators: %s: what was typed in the search box. */
				__( 'Search: “%s”', 'corex' ),
				filters.search
			)
		);
	}

	return parts;
}

/**
 * The three scopes as the dialog offers them: each with its count, what it covers, and — when it
 * cannot be chosen — why (FR-010).
 *
 * @param {Object}      options
 * @param {Object|null} options.counts        `{selected, filtered, accessible}`, or null while they load.
 * @param {number}      options.selectedCount How many rows are ticked.
 * @param {string[]}    options.filters       The filters in force, in words.
 * @return {Array<{value:string,label:string,detail:string,count:number|null,disabled:boolean}>} The scopes.
 */
export function scopeOptions( { counts, selectedCount, filters } ) {
	const count = ( scope ) => ( counts ? counts[ scope ] : null );

	return [
		{
			value: 'selected',
			label: __( 'Selected rows', 'corex' ),
			detail: selectedDetail( selectedCount ),
			count: selectedCount ? count( 'selected' ) : 0,
			disabled: selectedCount === 0 || selectedCount > SELECTION_LIMIT,
		},
		{
			value: 'filtered',
			label: __( 'Current filters', 'corex' ),
			detail: filters.length
				? filters.join( ' · ' )
				: __(
						'No filter is on, so this is every submission you can see.',
						'corex'
					),
			count: count( 'filtered' ),
			disabled: false,
		},
		{
			value: 'accessible',
			label: __( 'Everything', 'corex' ),
			detail: __(
				'Every submission you can see, whatever the filters.',
				'corex'
			),
			count: count( 'accessible' ),
			disabled: false,
		},
	];
}

function selectedDetail( selectedCount ) {
	if ( selectedCount === 0 ) {
		return __( 'Tick rows in the inbox to export only those.', 'corex' );
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

	return __( 'Only the rows ticked in the inbox.', 'corex' );
}

/**
 * The scope the dialog opens on: the selection when there is one, as that is what was just done.
 *
 * @param {number} selectedCount How many rows are ticked.
 * @return {string} A scope.
 */
export function defaultScope( selectedCount ) {
	return selectedCount > 0 && selectedCount <= SELECTION_LIMIT
		? 'selected'
		: 'filtered';
}

/**
 * Why the export cannot be started, or '' when it can.
 *
 * @param {Object}      state
 * @param {number|null} state.count        Submissions the chosen scope would export; null while unknown.
 * @param {string[]}    state.chosen       Ids of the chosen column choices.
 * @param {boolean}     state.acknowledged Whether the personal-data notice is confirmed.
 * @param {string}      [state.format]     The chosen format.
 * @param {number}      [state.pdfMost]    The most submissions one PDF holds.
 * @return {string} The reason, in words.
 */
export function blockedReason( {
	count,
	chosen,
	acknowledged,
	format = 'xlsx',
	pdfMost = 0,
} ) {
	if ( count === null ) {
		return __( 'Counting the submissions…', 'corex' );
	}
	if ( count === 0 ) {
		return __( 'There is nothing to export with this choice.', 'corex' );
	}
	if ( tooManyForPdf( format, count, pdfMost ) ) {
		return sprintf(
			/* translators: %s: the most submissions one PDF holds. */
			__(
				'A PDF holds up to %s submissions. Choose fewer, or export a workbook.',
				'corex'
			),
			Number( pdfMost ).toLocaleString()
		);
	}
	if ( chosen.length === 0 ) {
		return __( 'Choose at least one group of columns.', 'corex' );
	}
	if ( holdsPersonalData( chosen ) && ! acknowledged ) {
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
 * @param {number|null} count Submissions the chosen scope would export.
 * @return {string} The button's label.
 */
export function exportLabel( count ) {
	if ( ! count ) {
		return __( 'Export', 'corex' );
	}

	return sprintf(
		/* translators: %s: a number of submissions. */
		_n( 'Export %s submission', 'Export %s submissions', count, 'corex' ),
		count.toLocaleString()
	);
}

/**
 * How far an export has got, for a progress bar and for the words beside it.
 *
 * @param {{processed:number,total:number}} progress The job's progress.
 * @return {{value:number,max:number,text:string}} What to show.
 */
export function progressOf( progress ) {
	const max = Math.max( 1, Number( progress.total ) || 0 );
	const value = Math.min( max, Number( progress.processed ) || 0 );

	return {
		value,
		max,
		text: sprintf(
			/* translators: 1: submissions exported so far. 2: submissions to export. */
			__( '%1$s of %2$s submissions', 'corex' ),
			value.toLocaleString(),
			max.toLocaleString()
		),
	};
}

/**
 * The bytes of a downloaded export, from what the route answers.
 *
 * @param {Object} artifact The download answer's `artifact`.
 * @return {Blob} The file.
 */
export function fileFrom( artifact ) {
	const body =
		typeof artifact.base64 === 'string'
			? Uint8Array.from( window.atob( artifact.base64 ), ( character ) =>
					character.charCodeAt( 0 )
				)
			: artifact.csv || '';

	return new Blob( [ body ], {
		type: artifact.content_type || 'text/csv;charset=utf-8',
	} );
}

/**
 * What a past export covered, in words (FR-028): the rows that were ticked, the filters that were
 * in force, or everything.
 *
 * @param {Object}   entry    One export from the history.
 * @param {Function} describe Turns the inbox's filters into words; `describeFilters`, with the
 *                            forms and statuses the dialog knows.
 * @return {string} What it covered.
 */
export function coverageOf( entry, describe ) {
	const words = [ scopeInWords( entry, describe ) ];

	if ( entry.include_test ) {
		words.push( __( 'with tests', 'corex' ) );
	}

	return words.join( ', ' );
}

function scopeInWords( entry, describe ) {
	if ( entry.scope === 'selected' ) {
		const count = ( entry.selected_ids || [] ).length;

		return sprintf(
			/* translators: %s: how many inbox rows were ticked. */
			_n( '%s selected row', '%s selected rows', count, 'corex' ),
			count.toLocaleString()
		);
	}
	if ( entry.scope !== 'filtered' ) {
		return __( 'Everything', 'corex' );
	}

	const query = entry.query || {};
	const filters = describe( {
		...query,
		dateFrom: query.date_from,
		dateTo: query.date_to,
	} );

	return filters.length > 0
		? filters.join( ', ' )
		: __( 'Everything in view, no filters', 'corex' );
}

const BYTES_IN_A_KILOBYTE = 1024;

/**
 * A file's size as a person reads it. An export made before sizes were kept has none, and says
 * nothing rather than "0 B".
 *
 * @param {number} bytes The size in bytes.
 * @return {string} For example "48 KB", or '' when the size is not known.
 */
export function sizeOf( bytes ) {
	const size = Number( bytes ) || 0;

	if ( size <= 0 ) {
		return '';
	}
	if ( size < BYTES_IN_A_KILOBYTE ) {
		/* translators: %s: a file size in bytes. */
		return sprintf( __( '%s B', 'corex' ), size.toLocaleString() );
	}
	if ( size < BYTES_IN_A_KILOBYTE * BYTES_IN_A_KILOBYTE ) {
		return sprintf(
			/* translators: %s: a file size in kilobytes. */
			__( '%s KB', 'corex' ),
			Math.round( size / BYTES_IN_A_KILOBYTE ).toLocaleString()
		);
	}

	return sprintf(
		/* translators: %s: a file size in megabytes. */
		__( '%s MB', 'corex' ),
		( size / BYTES_IN_A_KILOBYTE / BYTES_IN_A_KILOBYTE ).toLocaleString(
			undefined,
			{ maximumFractionDigits: 1 }
		)
	);
}

/**
 * A format by the name a person knows it by.
 *
 * @param {string} [format] `xlsx`, `csv`, or one added later.
 * @return {string} Its name.
 */
export function formatName( format = 'csv' ) {
	return format === 'xlsx'
		? __( 'Excel', 'corex' )
		: String( format ).toUpperCase();
}

/**
 * What became of a past export's file (FR-028 to FR-030): when it expires, that it expired, who
 * deleted it, or that there is none, because the export did not finish or the file is gone.
 *
 * @param {Object}   entry      One export from the history.
 * @param {Function} formatDate Turns a stored date into one a person reads.
 * @return {string} One line.
 */
export function fileNoteOf( entry, formatDate ) {
	if ( entry.state === 'deleted' ) {
		return entry.removed_by_name
			? sprintf(
					/* translators: 1: the person who deleted an exported file. 2: when. */
					__( 'Deleted by %1$s, %2$s', 'corex' ),
					entry.removed_by_name,
					formatDate( entry.removed_at )
				)
			: sprintf(
					/* translators: %s: when an exported file was deleted. */
					__( 'Deleted %s', 'corex' ),
					formatDate( entry.removed_at )
				);
	}
	if ( entry.state === 'expired' ) {
		return sprintf(
			/* translators: %s: the date an exported file expired. */
			__( 'Expired %s. It can no longer be downloaded.', 'corex' ),
			formatDate( entry.expires_at )
		);
	}
	if ( entry.state === 'pending' ) {
		return __( 'No file to download.', 'corex' );
	}

	return sprintf(
		/* translators: %s: the date an exported file will be removed. */
		__( 'Expires %s', 'corex' ),
		formatDate( entry.expires_at )
	);
}
