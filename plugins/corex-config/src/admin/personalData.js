/**
 * What a field's personal-data class means, in words (spec 109, slice 1).
 *
 * The class is a key a model declares (`contact`, `identity`, …). The Models tab and the record
 * form printed that key, untranslated, and the export asked for a confirmation that named
 * nothing. One place words it for all three.
 */
import { __ } from '@wordpress/i18n';

/** What a field is when its model said nothing: asked about and guarded like personal data. */
const UNDECLARED = 'undeclared';

/**
 * Whether a field holds personal data: every class but "none", an undeclared one among them.
 *
 * @param {Object} field A field of a source, with its `personal_data_class`.
 * @return {boolean} Whether it is asked about and guarded.
 */
export function holdsPersonalData( field ) {
	return ( field?.personal_data_class || UNDECLARED ) !== 'none';
}

/**
 * Several things as one list inside a sentence, by the rules of the screen's language: commas
 * in English, and whatever Arabic's are. How a language lists things is not a string to
 * translate; the browser knows each one's.
 *
 * @param {string[]} items What to list.
 * @return {string} The list, or the one item.
 */
export function listed( items ) {
	const language =
		typeof document === 'undefined'
			? undefined
			: document.documentElement.lang || undefined;

	return new Intl.ListFormat( language, {
		style: 'short',
		type: 'unit',
	} ).format( items );
}

/**
 * A personal-data class in words, as part of a sentence ("… contains contact details").
 *
 * @param {string} className The class a field declares.
 * @return {string} The words; empty for "none". A class this screen was never told of is
 *                  worded as unclassified, never printed as its key.
 */
export function personalDataLabel( className ) {
	if ( className === 'none' ) {
		return '';
	}

	const labels = {
		contact: __( 'contact details', 'corex' ),
		identity: __( 'names and identities', 'corex' ),
		content: __( 'what people wrote', 'corex' ),
		security: __( 'sign-in and security records', 'corex' ),
		behavioral: __( 'interests and behaviour', 'corex' ),
		sensitive: __( 'sensitive details', 'corex' ),
	};

	return (
		labels[ className ] || __( 'data nobody has classified yet', 'corex' )
	);
}
