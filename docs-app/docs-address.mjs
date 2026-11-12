/**
 * The address the docs site is built for: its site origin and its base path (spec 130).
 *
 * One rule, read by `astro.config.mjs` and by `tests/docs-links.test.js`, so the link test checks
 * the base the build actually used. The published build gets both values from GitHub Pages
 * (`.github/workflows/docs.yml` passes `actions/configure-pages`'s outputs), so the build follows
 * whatever address Pages serves, the `github.io` project page or the custom domain.
 *
 * - `COREX_DOCS_SITE`: the origin, e.g. `https://corex-docs.mustafashaaban.com`.
 * - `COREX_DOCS_BASE`: the path the site lives under. Set but empty means the domain root,
 *   which is what Pages reports for a custom domain. Unset means the project page's `/corex`.
 */

const DEFAULT_SITE = "https://mustafashaaban.github.io";
const DEFAULT_BASE = "/corex";

/**
 * The build address for an environment.
 *
 * @param {Record<string, string|undefined>} env Usually `process.env`.
 * @return {{site: string, base: string}} `base` is `/` for the root, otherwise `/path`
 *                                         with no trailing slash.
 */
export function docsAddress(env) {
  const site = (env.COREX_DOCS_SITE || DEFAULT_SITE).replace(/\/+$/, "");
  const path = (env.COREX_DOCS_BASE ?? DEFAULT_BASE).replace(/^\/+|\/+$/g, "");

  return { site, base: `/${path}` };
}
