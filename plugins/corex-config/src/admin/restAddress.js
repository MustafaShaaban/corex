/**
 * Adding to a REST address the site handed over (`rest_url()`), as the site spells it.
 *
 * With pretty permalinks a route is a path, `/wp-json/corex/v1/submissions`. Without them it
 * is a query, `/index.php?rest_route=/corex/v1/submissions`, and a second `?` makes whatever
 * follows part of the route: WordPress knows no route of that name and answers 404. Every
 * client of this plugin that adds a query adds it here, so none of them has a sign of its own
 * to get wrong.
 *
 * A path is still added by hand (`${ restUrl }/${ id }`): the route is the last thing in
 * either spelling, so what is written after it lengthens it.
 */

/**
 * An address with a query added to it.
 *
 * @param {string}                 address The address, with or without a query of its own.
 * @param {string|URLSearchParams} query   What to add, as `name=value` pairs joined with `&`.
 * @return {string} The address, unchanged when there is nothing to add.
 */
export function withQuery( address, query ) {
	const added = String( query );
	if ( added === '' ) {
		return address;
	}

	return `${ address }${ address.includes( '?' ) ? '&' : '?' }${ added }`;
}
