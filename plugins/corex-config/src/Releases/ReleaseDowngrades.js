/**
 * The plugins and themes a package holds older versions of than the site has (spec 107,
 * FR-029). A release replaces each of its folders whole, so each of these would go back to the
 * package's version: said with what the package is, and again where the installation is
 * confirmed.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object}   props            Component props.
 * @param {Object[]} props.downgrades `{ path, name, site, package }` for each, as the site gave them.
 * @param {string}   props.says       The sentence that comes before the list.
 * @return {Element} The sentence and the list.
 */
export default function ReleaseDowngrades( { downgrades, says } ) {
	return (
		<>
			<p className="corex-releases__line">{ says }</p>
			<ul className="corex-releases__cautions">
				{ downgrades.map( ( downgrade ) => (
					<li key={ downgrade.path }>
						{ sprintf(
							/* translators: 1: a plugin's or theme's name. 2: the version the site has. 3: the older version in the package. */
							__(
								'%1$s: %2$s on the site, %3$s in the package',
								'corex'
							),
							downgrade.name,
							downgrade.site,
							downgrade.package
						) }
						<br />
						<code dir="ltr">{ downgrade.path }</code>
					</li>
				) ) }
			</ul>
		</>
	);
}
