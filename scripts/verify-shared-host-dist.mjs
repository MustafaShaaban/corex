#!/usr/bin/env node
/**
 * Verify a built shared-host `dist/` tree (spec 061, FR-061-06; DECISIONS #267): required folders
 * present, forbidden paths and the host's `.htaccess` absent, manifest valid JSON, no dev packages
 * in the packaged vendor/, and the package loads the framework in a PHP process of its own.
 * Exits non-zero on any failure. Run from the repository root after `npm run build:dist`.
 */

import { join } from 'node:path';
import { verifyDist } from './build-shared-host-dist.mjs';

const distDir = join( process.cwd(), 'dist' );
const result = verifyDist( distDir );

if ( result.ok ) {
	console.log( 'verify-shared-host-dist: OK' );
	process.exit( 0 );
}

console.error( 'verify-shared-host-dist: FAILED' );
result.errors.forEach( ( e ) => console.error( '  - ' + e ) );
process.exit( 1 );
