<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * The way back that needs no admin (spec 107, plan D9; FR-042).
 *
 * A must-use plugin, written when an installation starts. WordPress loads must-use plugins
 * before ordinary ones, so this runs when the release that was just installed cannot. It uses
 * nothing of CoreX: every path and word it needs is written into it. Asked with the
 * installation's key, it reads the swap's journal, moves every folder back, takes the site out
 * of maintenance, and says what it did in plain text.
 *
 * Its text is English and not translated. It is read by whoever is recovering a site whose
 * plugins did not load, and the translations are in one of them.
 */
final class ReleaseRecoveryPlugin
{
    /** In the address with the key: says the key is being used to go back, not to finish. */
    public const ARGUMENT = 'corex_release_recover';

    private const FILE = 'corex-release-recovery.php';

    /**
     * @param string $mustUseFolder The site's must-use plugins folder.
     */
    public function __construct(
        private readonly string $mustUseFolder,
        private readonly ReleaseJournal $journal,
        private readonly ReleaseMaintenance $maintenance,
    ) {
    }

    /**
     * @param string $keyHash The SHA-256 of the installation's key, as hexadecimal.
     *
     * @throws ReleaseRefused When it cannot be written: an installation does not start without its way back.
     */
    public function write(string $keyHash): void
    {
        // Direct write: nothing here can stop to ask for credentials.
        $written = wp_mkdir_p($this->mustUseFolder)
            && @file_put_contents($this->file(), $this->contents($keyHash)) !== false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

        if (! $written) {
            throw new ReleaseRefused(ReleaseHostFacts::NOT_WRITABLE, sprintf(
                /* translators: %s: a folder's path on the server. */
                __('The site could not write its recovery file in %s. Without it there would be no way back if the new release did not load, so nothing was changed. Ask the host to let the site write there.', 'corex'),
                $this->mustUseFolder,
            ));
        }
    }

    public function remove(): void
    {
        if ($this->isWritten()) {
            unlink($this->file());
        }
    }

    public function isWritten(): bool
    {
        return is_file($this->file());
    }

    /**
     * The address that puts the previous release back.
     *
     * @param string $home The site's front address.
     * @param string $key  The installation's key.
     */
    public function link(string $home, string $key): string
    {
        return rtrim($home, '/') . '/?' . ReleaseMaintenance::ARGUMENT . '=' . rawurlencode($key) . '&' . self::ARGUMENT . '=1';
    }

    private function file(): string
    {
        return rtrim($this->mustUseFolder, '/\\') . '/' . self::FILE;
    }

    /**
     * The plugin. What changes from one installation to the next is written in as values:
     * the key's hash, where the journal and the maintenance file are, and the journal's words.
     */
    private function contents(string $keyHash): string
    {
        return strtr(self::PLUGIN, [
            '{KEY_ARGUMENT}' => var_export(ReleaseMaintenance::ARGUMENT, true),
            '{ASK_ARGUMENT}' => var_export(self::ARGUMENT, true),
            '{KEY_HASH}'     => var_export($keyHash, true),
            '{JOURNAL}'      => var_export($this->journal->file(), true),
            '{MAINTENANCE}'  => var_export($this->maintenance->file(), true),
            '{DONE}'         => var_export(ReleaseJournal::DONE, true),
            '{PENDING}'      => var_export(ReleaseJournal::PENDING, true),
            '{UNDONE}'       => var_export(ReleaseJournal::UNDONE, true),
            '{STUCK}'        => var_export(ReleaseJournal::STUCK, true),
        ]);
    }

    private const PLUGIN = <<<'PHP'
<?php
/**
 * Plugin Name: CoreX release recovery
 * Description: Puts back the release an installation replaced, when asked with that installation's key. CoreX writes this file when an installation starts and removes it when there is nothing to go back to. It uses nothing of CoreX, so it works when CoreX does not.
 */

defined( 'ABSPATH' ) || exit;

( static function (): void {
	$key = $_GET[{KEY_ARGUMENT}] ?? '';

	if ( ! isset( $_GET[{ASK_ARGUMENT}] ) || ! is_string( $key ) || ! hash_equals( {KEY_HASH}, hash( 'sha256', $key ) ) ) {
		return;
	}

	$file    = {JOURNAL};
	$journal = json_decode( (string) @file_get_contents( $file ), true );
	$moves   = is_array( $journal ) && isset( $journal['moves'] ) && is_array( $journal['moves'] ) ? $journal['moves'] : array();
	$said    = array();
	$stuck   = false;

	// The swap's renames, made the other way, last first.
	foreach ( array_reverse( array_keys( $moves ) ) as $index ) {
		$move = $moves[ $index ];

		if ( {DONE} !== ( $move['state'] ?? '' ) ) {
			continue;
		}
		if ( ! is_dir( dirname( $move['from'] ) ) ) {
			@mkdir( dirname( $move['from'] ), 0755, true );
		}
		if ( @rename( $move['to'], $move['from'] ) ) {
			$journal['moves'][ $index ]['state'] = {PENDING};
			$said[]                              = 'Put back: ' . $move['from'];
		} else {
			$stuck  = true;
			$said[] = 'COULD NOT put back: ' . $move['from'] . ' (it is at ' . $move['to'] . ')';
		}
	}

	if ( array() === $said ) {
		$said[] = 'Nothing needed putting back.';
	}
	if ( array() !== $moves ) {
		$journal['status'] = $stuck ? {STUCK} : {UNDONE};
		@file_put_contents( $file, json_encode( $journal ) );
	}

	if ( $stuck ) {
		$said[] = 'The site is still in maintenance: it is part one release and part the other. Move each folder named above back by hand, then delete ' . {MAINTENANCE};
	} else {
		@unlink( {MAINTENANCE} );
		$said[] = 'The site is out of maintenance.';
	}
	$said[] = 'No change to the database was reversed.';

	header( 'Content-Type: text/plain; charset=utf-8' );
	echo implode( "\n", $said ), "\n";
	exit;
} )();

PHP;
}
