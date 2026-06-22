#!/usr/bin/env node
/**
 * Verify a built shared-host `dist/` tree (spec 061, FR-061-06): required folders present, forbidden
 * paths absent, manifest valid JSON. Exits non-zero on any failure. Run after `npm run build:dist`.
 */

import { join } from 'node:path';
import { verifyDist } from './build-shared-host-dist.mjs';

const distDir = join(process.cwd(), 'dist');
const result = verifyDist(distDir);

if (result.ok) {
	console.log('verify-shared-host-dist: OK');
	process.exit(0);
}

console.error('verify-shared-host-dist: FAILED');
result.errors.forEach((e) => console.error('  - ' + e));
process.exit(1);
