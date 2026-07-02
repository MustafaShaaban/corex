/**
 * Newsletter Signup — editor registration.
 *
 * The block is DYNAMIC: the form (and its wiring to the real subscribe REST route)
 * is produced server-side by the PHP `corex.renderer` (NewsletterSignupRenderer), so
 * the editor uses <ServerSideRender> for a byte-for-byte preview. Editor controls only
 * customise the copy (heading / button / consent text).
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Copy', 'corex' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Heading', 'corex' ) }
						value={ attributes.heading || '' }
						onChange={ ( heading ) => setAttributes( { heading } ) }
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Button label', 'corex' ) }
						value={ attributes.buttonLabel || '' }
						onChange={ ( buttonLabel ) =>
							setAttributes( { buttonLabel } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Consent text', 'corex' ) }
						value={ attributes.consentText || '' }
						onChange={ ( consentText ) =>
							setAttributes( { consentText } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: nothing is saved to post content; the server renders it.
	save: () => null,
} );
