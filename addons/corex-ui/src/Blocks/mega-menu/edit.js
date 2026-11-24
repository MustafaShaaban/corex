/**
 * corex/mega-menu in the editor (spec 137, FR-013, FR-021; DECISIONS #656, #691).
 *
 * In place in the menu, as the page draws its trigger: a list item holding a button whose label is
 * typed where it stands, with the caret. The panel is not drawn in the menu (it is edited on its own
 * canvas, as a template part), and is chosen in the block's settings.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import PanelControls from './panel-controls';

export default function Edit( { attributes, setAttributes } ) {
	const { label, panel } = attributes;
	const blockProps = useBlockProps( {
		className: 'wp-block-navigation-item corex-mega-menu',
	} );

	return (
		<li { ...blockProps }>
			<button
				type="button"
				className="wp-block-navigation-item__content corex-nav__trigger corex-mega-menu__trigger"
				aria-expanded="false"
			>
				<RichText
					tagName="span"
					className="wp-block-navigation-item__label corex-mega-menu__label"
					value={ label }
					onChange={ ( value ) => setAttributes( { label: value } ) }
					placeholder={ __( 'Label', 'corex' ) }
					allowedFormats={ [] }
					disableLineBreaks
					withoutInteractiveFormatting
				/>
				<svg
					className="corex-icon corex-mega-menu__caret"
					width="16"
					height="16"
					viewBox="0 0 24 24"
					fill="none"
					stroke="currentColor"
					strokeWidth="2"
					strokeLinecap="round"
					strokeLinejoin="round"
					aria-hidden="true"
					focusable="false"
				>
					<path d="m6 9 6 6 6-6" />
				</svg>
			</button>
			<InspectorControls>
				<PanelControls
					panel={ panel }
					setPanel={ ( value ) => setAttributes( { panel: value } ) }
				/>
			</InspectorControls>
		</li>
	);
}
