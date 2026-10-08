/**
 * The formats an export dialog offers, by the names a person knows them by (spec 103, FR-015).
 *
 * The server says which can be written where it runs: a PDF needs a library and two PHP
 * extensions, and is not offered without them. Both exports offer what it says, in its order.
 */
import { __ } from '@wordpress/i18n';

/** What is offered before the server has answered, and where it does not say. */
const ALWAYS = [ 'xlsx', 'csv' ];

function labelOf( format ) {
	const labels = {
		xlsx: __( 'Excel workbook (.xlsx)', 'corex' ),
		csv: __( 'CSV (.csv)', 'corex' ),
		pdf: __( 'PDF document (.pdf)', 'corex' ),
	};

	return labels[ format ] || String( format ).toUpperCase();
}

/**
 * @param {string[]|undefined} available The formats the server can write, in the order to offer them.
 * @return {Array<{value:string,label:string}>} The choices of the "File type" control.
 */
export function offeredFormats( available ) {
	const named = Array.isArray( available ) && available.length;

	return ( named ? available : ALWAYS ).map( ( format ) => ( {
		value: format,
		label: labelOf( format ),
	} ) );
}

/**
 * Whether a PDF is asked for with more records than a PDF holds (FR-019a).
 *
 * @param {string}      format The chosen format.
 * @param {number|null} count  How many records the chosen scope holds; null while unknown.
 * @param {number}      most   The most a PDF holds, as the server says.
 * @return {boolean} True when the export would be refused for its size.
 */
export function tooManyForPdf( format, count, most ) {
	return (
		format === 'pdf' &&
		Number( most ) > 0 &&
		Number( count ) > Number( most )
	);
}
