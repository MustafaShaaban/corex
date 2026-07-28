/**
 * Turns a fetched record plus its source schema into the ordered rows the detail modal shows.
 *
 * The modal used to render the schema alone, reading `record[ field.key ]` off the top level. That
 * works for table sources, whose `record()` returns a flat row, but not for submissions: theirs
 * returns `{ id, date, form, fields: [ { label, value } ] }`, nesting every answer under `fields`.
 * The schema keys for those answers are not top-level properties, so each one rendered as an em
 * dash and the submitted content — the reason for opening the record at all — was never displayed.
 *
 * So the record is the authority on what it contains, and the schema only supplies order and
 * labels. Three passes, in display order: declared fields the record actually carries, then the
 * nested `fields` pairs, then anything else the record holds that nobody declared. That last pass
 * is what stops this from silently regressing when a source grows a field.
 */

/** Keys that describe the record rather than being content a reader wants to see. */
const STRUCTURAL_KEYS = [ 'id', 'fields' ];

/**
 * A key turned into a human label, matching how the server labels nested fields
 * (`SubmissionsSource::labelFields()`): separators become spaces, words are capitalised.
 *
 * @param {string} key The raw field key.
 * @return {string} The label to show.
 */
function humanize( key ) {
	return String( key )
		.replace( /[_-]+/g, ' ' )
		.replace( /\b\w/g, ( character ) => character.toUpperCase() );
}

/**
 * A value rendered for display. Objects and arrays are JSON rather than "[object Object]", which
 * tells a reader nothing; empty and absent values become an em dash.
 *
 * @param {*} value The raw value.
 * @return {string|Object} The display string, or a described attachment untouched.
 */
function display( value ) {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}

	if ( typeof value === 'boolean' ) {
		return value ? 'true' : 'false';
	}

	if ( typeof value === 'object' ) {
		// A described attachment is passed through untouched for `FieldValue` to render as a link.
		// JSON.stringify()ing it here is what turned a stored file into `{"id":4242,...}` on the
		// screen — worse than the bare integer it replaced (#138 item 6).
		if ( typeof value.id === 'number' && 'missing' in value ) {
			return value;
		}

		return JSON.stringify( value );
	}

	return String( value );
}

/**
 * @param {Object} record         The record as the detail endpoint returned it.
 * @param {Array}  [schemaFields] The source's declared fields, used for order and labels.
 * @return {Array} Rows of `{ key, label, value }`, in display order, without duplicates.
 */
export default function recordRows( record, schemaFields = [] ) {
	if ( ! record || typeof record !== 'object' ) {
		return [];
	}

	const rows = [];
	const seen = new Set();

	// 1. Declared fields the record actually carries. A declared field the record does not have is
	//    skipped rather than shown empty — an em dash should mean "no answer", not "wrong shape".
	( schemaFields || [] ).forEach( ( field ) => {
		if (
			! field ||
			! Object.prototype.hasOwnProperty.call( record, field.key )
		) {
			return;
		}
		seen.add( field.key );
		rows.push( {
			key: field.key,
			label: field.label || humanize( field.key ),
			value: display( record[ field.key ] ),
		} );
	} );

	// 2. The nested pairs, already labelled by the server.
	if ( Array.isArray( record.fields ) ) {
		record.fields.forEach( ( field, index ) => {
			if ( ! field ) {
				return;
			}
			rows.push( {
				key: `fields.${ index }`,
				label: field.label || humanize( field.key || '' ),
				value: display( field.value ),
			} );
		} );
	}

	// 3. Everything else the record holds. Undeclared today does not mean unwanted — this is the
	//    pass that keeps a new field visible without anyone remembering to declare it.
	Object.keys( record ).forEach( ( key ) => {
		if ( seen.has( key ) || STRUCTURAL_KEYS.includes( key ) ) {
			return;
		}
		rows.push( {
			key,
			label: humanize( key ),
			value: display( record[ key ] ),
		} );
	} );

	return rows;
}
