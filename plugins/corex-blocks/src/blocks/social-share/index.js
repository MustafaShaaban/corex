/**
 * Social Share — editor registration.
 *
 * The block is DYNAMIC: the share bar is produced server-side by the PHP
 * `corex.renderer` (SocialShareRenderer) from the real current-post permalink, so
 * the editor uses <ServerSideRender> for a byte-for-byte preview instead of a
 * duplicated JS implementation. The only editor control chooses which networks show.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, CheckboxControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

const NETWORKS = [
	{ id: 'x', label: __( 'X', 'corex' ) },
	{ id: 'facebook', label: __( 'Facebook', 'corex' ) },
	{ id: 'linkedin', label: __( 'LinkedIn', 'corex' ) },
	{ id: 'whatsapp', label: __( 'WhatsApp', 'corex' ) },
	{ id: 'email', label: __( 'Email', 'corex' ) },
];

function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const selected = attributes.networks || [];

	const toggle = ( id ) => ( checked ) => {
		const next = checked
			? [ ...selected, id ]
			: selected.filter( ( value ) => value !== id );
		setAttributes( { networks: next } );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Networks', 'corex' ) }>
					{ NETWORKS.map( ( network ) => (
						<CheckboxControl
							key={ network.id }
							__nextHasNoMarginBottom
							label={ network.label }
							checked={ selected.includes( network.id ) }
							onChange={ toggle( network.id ) }
						/>
					) ) }
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
