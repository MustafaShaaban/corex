#!/usr/bin/env node
/**
 * Verify a built shared-host `dist/` tree (spec 061, FR-061-06; DECISIONS #267): required folders
 * present, forbidden paths and the host's `.htaccess` absent, manifest valid JSON, no dev packages
 * in the packaged vendor/, every Arabic script translation `npm run build` writes for the packaged
 * CoreX scripts (`corex-ar-<md5>.json`, spec 121), and the package loads the framework in a PHP
 * process of its own.
 * Exits non-zero on any failure. A warning (a client site plugin's block that names a file its folder
 * lacks, with no build of it: spec 135, FR-038, DECISIONS #666) is printed as `WARN <message>` and does
 * not change the exit code. Run from the repository root after `npm run build:dist`.
 */

import { join } from 'node:path';
import { verifyDist } from './build-shared-host-dist.mjs';

const distDir = join( process.cwd(), 'dist' );
const result = verifyDist( distDir );

result.warnings.forEach( ( w ) => console.warn( 'WARN ' + w ) );

if ( result.ok ) {
	console.log( 'verify-shared-host-dist: OK' );
	process.exit( 0 );
}

console.error( 'verify-shared-host-dist: FAILED' );
result.errors.forEach( ( e ) => console.error( '  - ' + e ) );
process.exit( 1 );
