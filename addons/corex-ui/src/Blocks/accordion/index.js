/**
 * Corex Accordion: questions that open to show their answers (spec 136 slice 5, User Story 4;
 * FR-030 to FR-032, FR-039; DECISIONS #672, #675).
 *
 * In place: the editor draws the accordion's root as the element that holds its accordion items,
 * as the page does, and each item is edited where it stands. The block saves its inner blocks only;
 * `AccordionRenderer` prints the page. Its settings are "Open one at a time" (off by default; the
 * page gives the items one `name`, so the browser keeps one open, with or without scripts) and the
 * questions' heading level (none by default), which reaches the items as block context. An
 * accordion saved before it held blocks migrates when it is opened (`./deprecated`).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	useInnerBlocksProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import { savedAsItems } from './deprecated';

const ITEM = 'corex/accordion-item';

/**
 * @return {Array<{label: string, value: string}>} The heading levels a question may take.
 */
function headingLevels() {
	return [
		{ label: __( 'None: plain text', 'corex' ), value: '0' },
		...[ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
			/* translators: %d: a heading level, from 2 to 6. */
			label: sprintf( __( 'Heading %d', 'corex' ), level ),
			value: String( level ),
		} ) ),
	];
}

function Edit( { attributes, setAttributes } ) {
	const { oneOpen, headingLevel } = attributes;
	const innerBlocksProps = useInnerBlocksProps(
		useBlockProps( { className: 'corex-accordion' } ),
		{
			allowedBlocks: [ ITEM ],
			template: [ [ ITEM ] ],
			defaultBlock: { name: ITEM },
			directInsert: true,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Accordion settings', 'corex' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Open one at a time', 'corex' ) }
						help={ __(
							'Opening a question closes the one that is open. Works without JavaScript.',
							'corex'
						) }
						checked={ !! oneOpen }
						onChange={ ( value ) =>
							setAttributes( { oneOpen: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Question heading level', 'corex' ) }
						help={ __(
							'Makes each question a heading of this level, so the questions fit the page outline.',
							'corex'
						) }
						value={ String( headingLevel || 0 ) }
						options={ headingLevels() }
						onChange={ ( value ) =>
							setAttributes( { headingLevel: Number( value ) } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...innerBlocksProps } />
		</>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
	deprecated: [ savedAsItems ],
} );
