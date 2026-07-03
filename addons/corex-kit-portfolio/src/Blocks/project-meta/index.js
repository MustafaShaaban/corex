/**
 * Corex Project Meta — editor registration for a DYNAMIC block. The definition list is produced by
 * the PHP renderer (ProjectMetaRenderer), which shows only the fields a project actually has, so the
 * editor previews it via <ServerSideRender> to match the front end exactly.
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
