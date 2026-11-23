/**
 * corex/mega-menu, the mega menu item: editor registration (spec 137, FR-009, FR-013; DECISIONS #691).
 *
 * A server-rendered item of the Navigation block, edited in place (spec 136's standard, kind
 * `in-place`): the trigger as the page draws it, the panel chosen in the settings. Saves nothing.
 */
import './style.scss';
import './allow-in-navigation';

import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
