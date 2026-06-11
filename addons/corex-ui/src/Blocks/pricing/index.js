/**
 * Corex Pricing — editor registration for a DYNAMIC block. The PHP PricingRenderer builds
 * the card (plan, price, feature list, CTA); the sidebar edits the fields (features are one
 * per line) and the editor previews via <ServerSideRender>.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps();
		const { plan, price, period, features, ctaText, ctaUrl } = attributes;

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Pricing', 'corex' ) }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Plan', 'corex' ) }
							value={ plan }
							onChange={ ( v ) => setAttributes( { plan: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Price', 'corex' ) }
							value={ price }
							onChange={ ( v ) => setAttributes( { price: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Period', 'corex' ) }
							value={ period }
							onChange={ ( v ) => setAttributes( { period: v } ) }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Features (one per line)', 'corex' ) }
							value={ features }
							onChange={ ( v ) => setAttributes( { features: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'CTA text', 'corex' ) }
							value={ ctaText }
							onChange={ ( v ) => setAttributes( { ctaText: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'CTA URL', 'corex' ) }
							value={ ctaUrl }
							onChange={ ( v ) => setAttributes( { ctaUrl: v } ) }
						/>
					</PanelBody>
				</InspectorControls>
				<ServerSideRender block={ metadata.name } attributes={ attributes } />
			</div>
		);
	},
	save: () => null,
} );
