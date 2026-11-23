/**
 * The parity helper of the CoreX editor standard (spec 136, FR-011 to FR-013; DECISIONS #671),
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
const { KNOWN_NEEDS, editorParityProblem } = require( './check' );
const {
	KINDS,
	SERVER_RENDER,
	blockIn,
	editorSourceOf,
	gateFailures,
} = require( './gate' );

module.exports = {
	EDITOR_ONLY,
	KINDS,
	KNOWN_NEEDS,
	SERVER_RENDER,
	attributeDefaults,
	blockIn,
	editorParityProblem,
	editorSourceOf,
	findFiles,
	formatTree,
	gateFailures,
	installStandIns,
	isBlockClass,
	normalizeTree,
	parityTree,
	registeredBlock,
	renderEdit,
	standIns,
};
