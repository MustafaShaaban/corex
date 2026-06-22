#!/usr/bin/env node
/**
 * Shared-host `dist` builder (spec 061, FR-061-06).
 *
 * Assembles a flat, deployable WordPress tree in `dist/` from repo SOURCE — never from the local
 * symlinked/junctioned `wp/wp-content/`. The result is what a shared host / cPanel / Azure SFTP deploy receives.
 *
 * Build entry: `npm run build:dist [-- --client=acme] [--dry-run]`
 * Verify:      `npm run verify:dist`
 *
 * Design: the planning logic (`buildPlan`) and the verifier (`verifyDist`) are pure and exported so they can be
 * unit-tested without copying WordPress core. The CLI wires them to the filesystem.
 *
 * What goes in:
 *   - WordPress core from `wp/` (wp-admin, wp-includes, root loaders) — EXCLUDING wp-content + wp-config.php.
 *   - wp-content/plugins: the framework plugins (plugins/*) + add-ons (addons/corex-*), as real folders.
 *   - wp-content/themes: the CoreX parent theme (theme/) + the client theme (when --client is given).
 *   - the client plugin from sites/<client>/ (when --client is given).
 *   - vendor/ (production autoloader) and a corex-release.json manifest.
 *
 * What is always excluded: .git, .github, node_modules, tests, dev tooling, env/secrets, uploads, wp-config.php,
 * debug.log, caches, and agent/.claude state. `dist/` itself is git-ignored and never committed.
 */

import { existsSync, mkdirSync, rmSync, cpSync, writeFileSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, basename, relative, sep } from 'node:path';

/** Path segments that must never appear in the artifact (matched case-insensitively against any path part). */
export const FORBIDDEN_SEGMENTS = [
	'.git', '.github', 'node_modules', 'tests', '__tests__', '.claude', '.agents', '.specify',
	'.env', 'wp-config.php', 'debug.log', '.DS_Store',
];

/** Per-tree copy excludes (dev tooling, sources, state) — kept out of the production artifact. */
const DEV_EXCLUDES = [
	'node_modules', 'tests', '__tests__', '.git', '.github', '.claude', '.agents',
	'package-lock.json', 'phpunit.xml', 'phpunit.xml.dist', 'phpcs.xml', 'phpcs.xml.dist',
	'.gitignore', '.editorconfig', '.eslintrc', 'jest.config.js', 'playwright.config.js',
	'composer.lock', '.DS_Store', 'debug.log',
];

const isForbidden = (absPath) => {
	const parts = absPath.split(/[\\/]/).map((p) => p.toLowerCase());
	return FORBIDDEN_SEGMENTS.some((seg) => parts.includes(seg.toLowerCase()));
};

/**
 * Compute the build plan: the list of {from, to, kind} copy operations + the manifest. Pure — no filesystem writes.
 *
 * @param {{repoRoot:string, distDir:string, client?:string|null, version?:string}} cfg
 * @returns {{copies:Array<{from:string,to:string,kind:string}>, manifest:object, warnings:string[]}}
 */
export function buildPlan(cfg) {
	const { repoRoot, distDir, client = null } = cfg;
	const copies = [];
	const warnings = [];
	const p = (...s) => join(repoRoot, ...s);

	// 1. WordPress core (everything in wp/ except wp-content + wp-config.php).
	if (existsSync(p('wp'))) {
		for (const entry of readdirSync(p('wp'))) {
			if (entry === 'wp-content' || entry === 'wp-config.php') continue;
			copies.push({ from: p('wp', entry), to: join(distDir, entry), kind: 'core' });
		}
	} else {
		warnings.push('wp/ not found — the artifact will not include WordPress core; supply core on the target.');
	}

	// 2. Framework plugins + add-ons → wp-content/plugins (real folders).
	const pluginNames = [];
	for (const dir of ['plugins', 'addons']) {
		if (!existsSync(p(dir))) continue;
		for (const entry of readdirSync(p(dir))) {
			const src = p(dir, entry);
			if (!statSync(src).isDirectory()) continue;
			copies.push({ from: src, to: join(distDir, 'wp-content', 'plugins', entry), kind: 'plugin' });
			pluginNames.push(entry);
		}
	}

	// 3. CoreX parent theme → wp-content/themes/corex.
	const themeNames = [];
	if (existsSync(p('theme'))) {
		copies.push({ from: p('theme'), to: join(distDir, 'wp-content', 'themes', 'corex'), kind: 'theme' });
		themeNames.push('corex');
	}

	// 4. Client plugin + theme from sites/<client>/.
	if (client) {
		const siteRoot = p('sites', client);
		if (!existsSync(siteRoot)) {
			warnings.push(`sites/${client}/ not found — no client source packaged.`);
		} else {
			// Support both the target layout (sites/<c>/<c>-site, <c>-theme) and the current generator layout
			// (sites/<c>/plugins/*, sites/<c>/themes/*).
			for (const entry of readdirSync(siteRoot)) {
				const src = join(siteRoot, entry);
				if (!statSync(src).isDirectory()) continue;
				if (entry === 'plugins') {
					for (const pl of readdirSync(src)) {
						copies.push({ from: join(src, pl), to: join(distDir, 'wp-content', 'plugins', pl), kind: 'client-plugin' });
						pluginNames.push(pl);
					}
				} else if (entry === 'themes') {
					for (const th of readdirSync(src)) {
						copies.push({ from: join(src, th), to: join(distDir, 'wp-content', 'themes', th), kind: 'client-theme' });
						themeNames.push(th);
					}
				} else if (entry.endsWith('-site')) {
					copies.push({ from: src, to: join(distDir, 'wp-content', 'plugins', entry), kind: 'client-plugin' });
					pluginNames.push(entry);
				} else if (entry.endsWith('-theme')) {
					copies.push({ from: src, to: join(distDir, 'wp-content', 'themes', entry), kind: 'client-theme' });
					themeNames.push(entry);
				}
			}
		}
	}

	// 5. Production vendor autoloader.
	if (existsSync(p('vendor'))) {
		copies.push({ from: p('vendor'), to: join(distDir, 'vendor'), kind: 'vendor' });
	} else {
		warnings.push('vendor/ not found — run `composer install --no-dev --optimize-autoloader` before packaging.');
	}

	const manifest = {
		name: 'corex-shared-host-dist',
		built_at: new Date().toISOString(),
		corex_version: cfg.version ?? readCorexVersion(repoRoot),
		client: client ?? null,
		plugins: pluginNames.sort(),
		themes: themeNames.sort(),
		excludes: DEV_EXCLUDES,
		forbidden_segments: FORBIDDEN_SEGMENTS,
	};

	return { copies, manifest, warnings };
}

/** Read COREX_CORE_VERSION from the core plugin header, for the manifest. */
function readCorexVersion(repoRoot) {
	try {
		const src = readFileSync(join(repoRoot, 'plugins', 'corex-core', 'corex-core.php'), 'utf8');
		const m = src.match(/COREX_CORE_VERSION',\s*'([^']+)'/);
		return m ? m[1] : 'unknown';
	} catch {
		return 'unknown';
	}
}

/** Filter for cpSync: skip dev-excluded names and any forbidden path. */
function copyFilter(src) {
	const name = basename(src);
	if (DEV_EXCLUDES.includes(name)) return false;
	if (isForbidden(src)) return false;
	return true;
}

/** Execute the plan. Writes nothing when dryRun is true. */
export function runBuild(plan, distDir, { dryRun = false } = {}) {
	if (!dryRun) {
		rmSync(distDir, { recursive: true, force: true });
		mkdirSync(distDir, { recursive: true });
	}
	for (const op of plan.copies) {
		if (dryRun) continue;
		mkdirSync(join(op.to, '..'), { recursive: true });
		cpSync(op.from, op.to, { recursive: true, filter: copyFilter, force: true });
	}
	if (!dryRun) {
		writeFileSync(join(distDir, 'corex-release.json'), JSON.stringify(plan.manifest, null, 2) + '\n');
	}
	return plan;
}

/**
 * Verify a built dist tree. Pure-ish (reads only). Returns {ok, errors, checked}.
 *
 * @param {string} distDir
 */
export function verifyDist(distDir) {
	const errors = [];
	const must = ['wp-content', join('wp-content', 'plugins'), join('wp-content', 'themes'), 'corex-release.json'];
	for (const rel of must) {
		if (!existsSync(join(distDir, rel))) errors.push(`missing required path: ${rel}`);
	}
	// Forbidden paths must be absent anywhere in the tree.
	if (existsSync(distDir)) {
		for (const abs of walk(distDir)) {
			const relPath = relative(distDir, abs);
			const parts = relPath.split(sep).map((s) => s.toLowerCase());
			for (const seg of FORBIDDEN_SEGMENTS) {
				if (parts.includes(seg.toLowerCase())) {
					errors.push(`forbidden path present: ${relPath}`);
					break;
				}
			}
		}
	}
	// Manifest must be valid JSON with the expected shape.
	const manifestPath = join(distDir, 'corex-release.json');
	if (existsSync(manifestPath)) {
		try {
			const m = JSON.parse(readFileSync(manifestPath, 'utf8'));
			if (!Array.isArray(m.plugins) || !Array.isArray(m.themes)) {
				errors.push('corex-release.json missing plugins/themes arrays');
			}
		} catch {
			errors.push('corex-release.json is not valid JSON');
		}
	}

	// Client-asset completeness (spec 062): a theme that ships SCSS/JS sources must also ship the COMPILED
	// output — otherwise the deploy would serve a half-built client theme. Catches "packaged without building".
	errors.push(...verifyClientAssets(distDir));

	return { ok: errors.length === 0, errors };
}

/**
 * For each theme under wp-content/themes, when it carries `assets/src/scss` (or `assets/src/js`) it must also
 * carry compiled `assets/css/*.css` (or `assets/js/*.js`). Themes with no asset sources are unaffected.
 *
 * @returns {string[]}
 */
export function verifyClientAssets(distDir) {
	const errors = [];
	const themesDir = join(distDir, 'wp-content', 'themes');

	if (!existsSync(themesDir)) {
		return errors;
	}

	for (const theme of readdirSync(themesDir)) {
		const themeDir = join(themesDir, theme);
		if (!statSync(themeDir).isDirectory()) continue;

		const checks = [
			{ src: join('assets', 'src', 'scss'), out: join('assets', 'css'), ext: '.css', kind: 'SCSS' },
			{ src: join('assets', 'src', 'js'), out: join('assets', 'js'), ext: '.js', kind: 'JS' },
		];

		for (const { src, out, ext, kind } of checks) {
			if (!existsSync(join(themeDir, src))) continue;
			const outDir = join(themeDir, out);
			const built = existsSync(outDir)
				&& readdirSync(outDir).some((f) => f.toLowerCase().endsWith(ext));
			if (!built) {
				errors.push(`client theme "${theme}": ${kind} source present but no compiled ${out}/*${ext} (run the theme's npm run build before packaging)`);
			}
		}
	}

	return errors;
}

function* walk(dir) {
	for (const entry of readdirSync(dir)) {
		const abs = join(dir, entry);
		yield abs;
		if (statSync(abs).isDirectory()) yield* walk(abs);
	}
}

// CLI (run directly, not when require()'d by tests).
const isMain = basename(process.argv[1] ?? '') === 'build-shared-host-dist.mjs';
if (isMain) {
	const args = process.argv.slice(2);
	const dryRun = args.includes('--dry-run');
	const clientArg = args.find((a) => a.startsWith('--client='));
	const client = clientArg ? clientArg.split('=')[1] : null;
	const repoRoot = process.cwd();
	const distDir = join(repoRoot, 'dist');

	const plan = buildPlan({ repoRoot, distDir, client });
	plan.warnings.forEach((w) => console.warn('warning:', w));
	console.log(`${dryRun ? '[dry-run] ' : ''}packaging ${plan.copies.length} tree(s) into dist/` +
		(client ? ` (client: ${client})` : ' (framework only — pass --client=<slug> to include a client site)'));
	for (const op of plan.copies) {
		console.log(`  ${op.kind.padEnd(14)} ${relative(repoRoot, op.from)} -> ${relative(repoRoot, op.to)}`);
	}
	runBuild(plan, distDir, { dryRun });
	if (!dryRun) {
		const v = verifyDist(distDir);
		console.log(v.ok ? 'dist verified OK' : 'dist verification FAILED:\n  ' + v.errors.join('\n  '));
		process.exit(v.ok ? 0 : 1);
	}
}
