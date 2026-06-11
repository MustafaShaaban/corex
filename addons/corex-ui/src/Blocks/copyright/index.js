/**
 * Corex Copyright — editor registration for a DYNAMIC block. No attributes:
 * the year + site name are resolved server-side. The editor previews the PHP
 * render via <ServerSideRender>.
 */
import './style.scss';

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: () => {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<ServerSideRender block={ metadata.name } />
			</div>
		);
	},
	save: () => null,
} );
