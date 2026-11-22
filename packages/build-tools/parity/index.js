/**
 * The parity helper of the CoreX editor standard (spec 136, FR-011 and FR-012; DECISIONS #671),
 * exported as `@corex/build-tools/parity` so a client package tests its own blocks with it.
 * The format of a `parity.json` is described in `packages/build-tools/README.md`.
 */
const {
	EDITOR_ONLY,
	formatTree,
	isBlockClass,
	normalizeTree,
	parityTree,
} = require( './tree' );
const { attributeDefaults, renderEdit } = require( './render' );
const { standIns, installStandIns, registeredBlock } = require( './stand-ins' );
const { findFiles } = require( './find' );

/** What a fixture may say the page needs to draw the block (`needs`). */
const KNOWN_NEEDS = [ 'post' ];

module.exports = {
	EDITOR_ONLY,
	KNOWN_NEEDS,
	attributeDefaults,
	findFiles,
	formatTree,
	installStandIns,
	isBlockClass,
	normalizeTree,
	parityTree,
	registeredBlock,
	renderEdit,
	standIns,
};
