/**
 * The Navigation block accepts the mega menu item (spec 137, FR-009; DECISIONS #691).
 *
 * The editor reads the blocks a Navigation block accepts from that block's settings, which its own
 * script registers over what the server sent; a server filter never reaches it. So the item is
 * added on `blocks.registerBlockType`, which runs as WordPress registers the Navigation block. This
 * file loads only with the add-on, so with the add-on off the Navigation block is as WordPress has it.
 */
// A script of WordPress's own, which the build leaves to the editor (a webpack external).
// eslint-disable-next-line import/no-extraneous-dependencies
import { addFilter } from '@wordpress/hooks';

export const NAVIGATION = 'core/navigation';
export const ITEM = 'corex/mega-menu';

/**
 * @param {Object} settings The block's settings, as the editor registers them.
 * @param {string} name     The block's name.
 * @return {Object} The Navigation block's settings with the item among its allowed blocks; any other
 *                  block's settings, and a Navigation block that lists none (it accepts every
 *                  block), as they came.
 */
export function allowInNavigation( settings, name ) {
	const allowed = settings && settings.allowedBlocks;
	if (
		name !== NAVIGATION ||
		! Array.isArray( allowed ) ||
		allowed.includes( ITEM )
	) {
		return settings;
	}

	return { ...settings, allowedBlocks: [ ...allowed, ITEM ] };
}

addFilter(
	'blocks.registerBlockType',
	'corex/mega-menu/allow-in-navigation',
	allowInNavigation
);
