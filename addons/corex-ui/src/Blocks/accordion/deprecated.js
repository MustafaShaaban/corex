/**
 * The accordion as it was saved before it held blocks (spec 136, FR-039, "Blocks that hold blocks";
 * DECISIONS #675): its questions and answers in `items`, no inner blocks, nothing saved but the
 * comment. Such a block is valid as it is, so the deprecation says itself when it applies: a block
 * with old items and no inner blocks. Opening it in the editor turns each old item into an accordion
 * item with the same question, holding a paragraph with the old answer, and empties `items`; the page
 * draws it from `items` until then.
 *
 * `items` is the list of spec 029 (`[{title, content}]`) or, before it, text with one
 * `Question | Answer` per line. WordPress drops that text on the page, since the attribute is a list;
 * the editor reads it here, so opening such an accordion brings its questions back.
 */
import { createBlock } from '@wordpress/blocks';
import metadata from './block.json';

/**
 * @param {Array<Object>|string|undefined} raw The old `items`: a list, or one `Question | Answer` per line.
 * @return {Array<{title: string, content: string}>} The old items, every line that says something.
 */
function itemsOf( raw ) {
	if ( Array.isArray( raw ) ) {
		return raw;
	}
	if ( typeof raw !== 'string' ) {
		return [];
	}

	return raw
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line !== '' )
		.map( ( line ) => {
			const [ title, ...answer ] = line.split( '|' );

			return { title: title.trim(), content: answer.join( '|' ).trim() };
		} );
}

/**
 * @param {Array<{title?: string, content?: string}>|string} items The old items.
 * @return {Array<Object>} One accordion item per old item, none left out; its answer as a
 *                         paragraph, or nothing when it had none.
 */
export function itemsToBlocks( items ) {
	return itemsOf( items ).map( ( item ) => {
		const answer = String( item?.content ?? '' );

		return createBlock(
			'corex/accordion-item',
			{ question: String( item?.title ?? '' ) },
			answer.trim() === ''
				? []
				: [ createBlock( 'core/paragraph', { content: answer } ) ]
		);
	} );
}

export const savedAsItems = {
	attributes: {
		...metadata.attributes,
		items: { ...metadata.attributes.items, type: [ 'array', 'string' ] },
	},
	supports: metadata.supports,
	save: () => null,
	isEligible: ( attributes, innerBlocks ) =>
		itemsOf( attributes.items ).length > 0 &&
		( innerBlocks?.length ?? 0 ) === 0,
	migrate: ( attributes ) => [
		{ ...attributes, items: [] },
		itemsToBlocks( attributes.items ),
	],
};
