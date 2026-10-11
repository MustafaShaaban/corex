/**
 * The way back, as a file to keep (spec 107, FR-042).
 *
 * The recovery link is shown once, before an installation is confirmed, and it is for the day
 * the admin does not load. So it has to be somewhere other than the admin: a text file the
 * administrator saves before pressing Install.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {Object} recovery
 * @param {string} recovery.link    The address that puts the previous release back.
 * @param {string} recovery.version The release being installed.
 * @return {string} What the file says.
 */
export function recoveryText( { link, version } ) {
	return [
		sprintf(
			/* translators: %s: a CoreX version number. */
			__( 'CoreX release recovery, for the installation of %s', 'corex' ),
			version
		),
		'',
		__(
			'If the site does not load after this release is installed, open the address below in a browser. It puts the previous release’s files back and takes the site out of maintenance. It does not need the admin to load.',
			'corex'
		),
		'',
		link,
		'',
		__(
			'It reverses no change to the database. Keep this file to yourself: anybody with the address can put the previous release back. It stops working when another release is installed.',
			'corex'
		),
		'',
	].join( '\n' );
}

/**
 * Hand the file to the browser to save.
 *
 * @param {Object} recovery `{ link, version }`.
 */
export function saveRecoveryFile( recovery ) {
	const file = new window.Blob( [ recoveryText( recovery ) ], {
		type: 'text/plain;charset=utf-8',
	} );
	const address = window.URL.createObjectURL( file );
	const anchor = document.createElement( 'a' );

	anchor.href = address;
	anchor.download = `corex-release-recovery-${ recovery.version }.txt`;
	document.body.appendChild( anchor );
	anchor.click();
	anchor.remove();
	window.URL.revokeObjectURL( address );
}
