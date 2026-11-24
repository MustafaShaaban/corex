/**
 * Corex Accordion item: one question and its answer, inside an accordion (spec 136 slice 5, User
 * Story 4; FR-030, FR-032).
 *
 * In place, with the page's elements: a `details` with the question typed in its `summary` (as a
 * heading of the accordion's level, or plain text) beside the kit's chevron, and the answer, which
 * holds any blocks. The editor draws the item open and keeps it open when its question is pressed or
 * a space is typed in it, so the answer stays in reach. The block saves its inner blocks only;
 * `AccordionItemRenderer` prints the page.
 */
import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	RichText,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import { chevron } from '../accordion/chevron';

const LEVELS = [ 2, 3, 4, 5, 6 ];

/**
 * @param {number|string|undefined} level The accordion's heading level, from the block context.
 * @return {string} The question's tag: a heading of that level, or a `span` for plain text.
 */
function questionTag( level ) {
	return LEVELS.includes( Number( level ) )
		? `h${ Number( level ) }`
		: 'span';
}

/**
 * Keeps the item open: in the editor its question is a text field, not a toggle.
 *
 * @param {Event} event A press of the question, or a key let go in it.
 */
const keepOpen = ( event ) => event.preventDefault();

function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps( {
		className: 'corex-accordion__item',
		open: true,
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'corex-accordion__content' },
		{ template: [ [ 'core/paragraph' ] ] }
	);

	return (
		<details { ...blockProps }>
			<summary
				className="corex-accordion__summary"
				onClick={ keepOpen }
				onKeyUp={ keepOpen }
			>
				<RichText
					tagName={ questionTag(
						context?.[ 'corex/accordionHeadingLevel' ]
					) }
					className="corex-accordion__question"
					value={ attributes.question }
					onChange={ ( question ) => setAttributes( { question } ) }
					placeholder={ __( 'Question', 'corex' ) }
					aria-label={ __( 'Question', 'corex' ) }
					allowedFormats={ [ 'core/bold', 'core/italic' ] }
					withoutInteractiveFormatting
				/>
				{ chevron }
			</summary>
			<div { ...innerBlocksProps } />
		</details>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
