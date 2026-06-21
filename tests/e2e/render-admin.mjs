/**
 * Admin render harness (Spec 060 / PR #59 capture-fidelity pass).
 *
 * Logs in once as an administrator against the live site and screenshots every CoreX
 * admin surface in both dark and light colour schemes, so the rendered result can be
 * compared against the approved `.dc.html` design captures. Not a test — an evidence tool.
 *
 *   node tests/e2e/render-admin.mjs [outDir] [--screens=overview,data,...]
 *
 * Env (WAMP defaults): COREX_BASE_URL, COREX_ADMIN_USER, COREX_ADMIN_PASS.
 */
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const STORAGE_STATE = path.join('tests', 'e2e', '.auth', 'admin.json');

const BASE_URL = process.env.COREX_BASE_URL || 'http://corex.local';
const ADMIN_USER = process.env.COREX_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.COREX_ADMIN_PASS || 'password';

const OUT_DIR = process.argv[2] && !process.argv[2].startsWith('--')
	? process.argv[2]
	: path.join('tests', 'e2e', 'render-out');

const screensArg = process.argv.find((a) => a.startsWith('--screens='));
const only = screensArg ? screensArg.slice('--screens='.length).split(',') : null;

const SCREENS = [
	{ name: 'overview', url: '/wp-admin/admin.php?page=corex-settings' },
	{ name: 'addons', url: '/wp-admin/admin.php?page=corex-addons' },
	{ name: 'data', url: '/wp-admin/admin.php?page=corex-data' },
	{ name: 'settings', url: '/wp-admin/admin.php?page=corex-settings-config' },
	{ name: 'insights', url: '/wp-admin/admin.php?page=corex-insights' },
	{ name: 'setup', url: '/wp-admin/admin.php?page=corex-setup' },
	{ name: 'login', url: '/wp-login.php', loggedOut: true },
].filter((s) => !only || only.includes(s.name));

(async () => {
	await mkdir(OUT_DIR, { recursive: true });
	const browser = await chromium.launch();

	for (const scheme of ['dark', 'light']) {
		// Logged-out screens (login) must NOT carry the admin session.
		const authedCtx = await browser.newContext({ viewport: { width: Number(process.env.COREX_W) || 1440, height: Number(process.env.COREX_H) || 900 }, colorScheme: scheme, storageState: STORAGE_STATE });
		const anonCtx = await browser.newContext({ viewport: { width: Number(process.env.COREX_W) || 1440, height: Number(process.env.COREX_H) || 900 }, colorScheme: scheme });

		for (const screen of SCREENS) {
			const ctx = screen.loggedOut ? anonCtx : authedCtx;
			const page = await ctx.newPage();
			await page.emulateMedia({ colorScheme: scheme });
			await page.goto(BASE_URL + screen.url, { waitUntil: 'networkidle' }).catch(() => {});
			await page.waitForTimeout(600);
			const file = path.join(OUT_DIR, `${screen.name}-${scheme}.png`);
			await page.screenshot({ path: file, fullPage: true }).catch((e) => console.error(screen.name, e.message));
			console.log('rendered', file);
			await page.close();
		}
		await authedCtx.close();
		await anonCtx.close();
	}

	await browser.close();
})();
