/**
 * Corex Pricing — DYNAMIC block edited INLINE (spec 029). Plan/price/period/CTA text are
 * RichText on the canvas; the feature list is repeatable RichText rows (one per feature);
 * the CTA URL is a sidebar field. The PHP PricingRenderer builds the card server-side.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => {
		const blockProps = useBlockProps( { className: 'corex-pricing' } );
		const { plan, price, period, features, ctaText, ctaUrl } = attributes;

		const setFeature = ( index, value ) => {
			const next = [ ...features ];
			next[ index ] = value;
			setAttributes( { features: next } );
		};
		const addFeature = () => setAttributes( { features: [ ...features, '' ] } );
		const removeFeature = ( index ) =>
			setAttributes( { features: features.filter( ( _f, i ) => i !== index ) } );

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Call to action', 'corex' ) }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'CTA URL', 'corex' ) }
							value={ ctaUrl }
							onChange={ ( v ) => setAttributes( { ctaUrl: v } ) }
						/>
					</PanelBody>
				</InspectorControls>

				<RichText
					tagName="h3"
					className="corex-pricing__plan"
					value={ plan }
					onChange={ ( v ) => setAttributes( { plan: v } ) }
					placeholder={ __( 'Plan name', 'corex' ) }
				/>
				<p className="corex-pricing__price">
					<RichText
						tagName="span"
						value={ price }
						onChange={ ( v ) => setAttributes( { price: v } ) }
						placeholder={ __( 'Price', 'corex' ) }
					/>
					<RichText
						tagName="span"
						className="corex-pricing__period"
						value={ period }
						onChange={ ( v ) => setAttributes( { period: v } ) }
						placeholder={ __( '/mo', 'corex' ) }
					/>
				</p>

				<ul className="corex-pricing__features">
					{ features.map( ( feature, index ) => (
						<li key={ index }>
							<RichText
								tagName="span"
								value={ feature }
								onChange={ ( v ) => setFeature( index, v ) }
								placeholder={ __( 'Feature', 'corex' ) }
							/>
							<Button
								isDestructive
								variant="link"
								onClick={ () => removeFeature( index ) }
							>
								{ __( 'Remove', 'corex' ) }
							</Button>
						</li>
					) ) }
				</ul>
				<Button variant="secondary" onClick={ addFeature }>
					{ __( 'Add feature', 'corex' ) }
				</Button>

				<RichText
					tagName="span"
					className="corex-pricing__cta"
					value={ ctaText }
					onChange={ ( v ) => setAttributes( { ctaText: v } ) }
					placeholder={ __( 'CTA label', 'corex' ) }
				/>
			</div>
		);
	},
	save: () => null,
} );
