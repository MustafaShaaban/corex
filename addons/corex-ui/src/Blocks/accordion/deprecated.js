/**
 * The accordion as it was saved before it held blocks (spec 136, FR-039, "Blocks that hold blocks";
 * DECISIONS #675): its questions and answers in `items`, no inner blocks, nothing saved but the
 * comment. Such a block is valid as it is, so the deprecation says itself when it applies: a block
 * with old items and no inner blocks. Opening it in the editor turns each old item into an accordion
 * item with the same question, holding a paragraph with the old answer, and empties `items`; the page
 * draws it from `items` until then.
 */
import { createBlock } from '@wordpress/blocks';
import metadata from './block.json';

const hasItems = ( items ) => Array.isArray( items ) && items.length > 0;

/**
 * @param {Array<{title?: string, content?: string}>} items The old items.
 * @return {Array<Object>} One accordion item per old item, none left out; its answer as a
 *                         paragraph, or nothing when it had none.
 */
export function itemsToBlocks( items ) {
	return items.map( ( item ) => {
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
	attributes: metadata.attributes,
	supports: metadata.supports,
	save: () => null,
	isEligible: ( attributes, innerBlocks ) =>
		hasItems( attributes.items ) && ( innerBlocks?.length ?? 0 ) === 0,
	migrate: ( attributes ) => [
		{ ...attributes, items: [] },
		itemsToBlocks( attributes.items ),
	],
};
