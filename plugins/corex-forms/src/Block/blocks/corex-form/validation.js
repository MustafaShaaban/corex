/**
 * Shared client-side validation — mirrors the PHP rule semantics
 * (Corex\Forms\Validation\Rules\*) so the browser and server enforce ONE schema
 * exported from the same source of truth. The server stays authoritative; this runs
 * the identical rules for instant feedback, never as a replacement for server checks.
 *
 * Each rule returns a message KEY (the rule name) on failure, or null on pass —
 * exactly like the PHP rules. Validation bails per field at the first failing rule.
 */

function isEmpty( value ) {
	return value === null || value === undefined || String( value ).trim() === '';
}

/**
 * Mirror PHP is_numeric for the values a form yields (numbers + numeric strings).
 */
function isNumericValue( value ) {
	if ( typeof value === 'number' ) {
		return ! Number.isNaN( value );
	}

	if ( typeof value !== 'string' ) {
		return false;
	}

	const trimmed = value.trim();

	return trimmed !== '' && ! Number.isNaN( Number( trimmed ) );
}

function length( value ) {
	// Spread counts Unicode code points, matching PHP mb_strlen closely enough for
	// length bounds (the server's mb_strlen is authoritative on the edge).
	return [ ...String( value ) ].length;
}

const RULES = {
	required( value ) {
		return isEmpty( value ) ? 'required' : null;
	},
	email( value ) {
		if ( isEmpty( value ) ) {
			return null;
		}
		// Pragmatic check; the server's filter_var( …, FILTER_VALIDATE_EMAIL ) decides.
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ) ) ? null : 'email';
	},
	max( value, params ) {
		if ( isEmpty( value ) ) {
			return null;
		}
		const limit = parseInt( params[ 0 ] || '0', 10 );
		if ( isNumericValue( value ) ) {
			return Number( value ) > limit ? 'max' : null;
		}
		return length( value ) > limit ? 'max' : null;
	},
	min( value, params ) {
		if ( isEmpty( value ) ) {
			return null;
		}
		const limit = parseInt( params[ 0 ] || '0', 10 );
		if ( isNumericValue( value ) ) {
			return Number( value ) < limit ? 'min' : null;
		}
		return length( value ) < limit ? 'min' : null;
	},
	numeric( value ) {
		if ( isEmpty( value ) ) {
			return null;
		}
		return isNumericValue( value ) ? null : 'numeric';
	},
};

/**
 * @param {{rules: Array<{rule: string, params: string[]}>}} field
 * @param {*} value
 * @param {Object} allValues
 * @return {string|null} the failing rule's message key, or null
 */
export function validateField( field, value, allValues ) {
	for ( const spec of field.rules || [] ) {
		const rule = RULES[ spec.rule ];
		if ( ! rule ) {
			continue;
		}
		const error = rule( value, spec.params || [], allValues );
		if ( error ) {
			return error; // bail per field — first failing rule wins (matches PHP)
		}
	}
	return null;
}

/**
 * Validate a whole payload against an exported schema.
 *
 * @param {Array} schema  the exported field list (name, required, rules, …)
 * @param {Object} values name => value
 * @return {Object} name => message key, for the fields that failed
 */
export function validateForm( schema, values ) {
	const errors = {};

	for ( const field of schema ) {
		const present = Object.prototype.hasOwnProperty.call( values, field.name );

		// An absent optional field is valid (matches the PHP Validator).
		if ( ! present && ! field.required ) {
			continue;
		}

		const value = present ? values[ field.name ] : null;
		const error = validateField( field, value, values );

		if ( error ) {
			errors[ field.name ] = error;
		}
	}

	return errors;
}
