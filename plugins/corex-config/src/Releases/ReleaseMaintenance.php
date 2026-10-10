<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * WordPress's own maintenance answer, for as long as a site is part one release and part
 * another (spec 107, plan D7; FR-024).
 *
 * WordPress reads `.maintenance` in the site's root before it loads any plugin, and answers
 * "briefly unavailable" itself. So no request runs a half-replaced site, and nothing of CoreX
 * has to be working for that to be true. It stops applying by itself ten minutes after the
 * time written in it, which is WordPress's rule and not ours.
 *
 * The file is PHP that WordPress includes. Ours lets through the one request that carries the
 * installation's key: the request that finishes the installation on the new release, and the
 * recovery link. Only the key's hash is in the file.
 *
 * CoreX's own Maintenance mode is not used. It answers after every plugin has loaded.
 */
final class ReleaseMaintenance
{
    /** The request header, and the query argument, a key is carried in. */
    public const HEADER   = 'X-Corex-Release-Key';
    public const ARGUMENT = 'corex_release_key';

    private const FILE = '.maintenance';

    /**
     * @param string $siteRoot The folder WordPress is in.
     */
    public function __construct(private readonly string $siteRoot)
    {
    }

    /**
     * Begin answering every request but the keyed one with WordPress's maintenance page.
     *
     * @param string $keyHash The SHA-256 of the installation's key, as hexadecimal.
     * @param int    $now     The time maintenance begins, as WordPress counts its ten minutes from.
     *
     * @throws ReleaseRefused When the file cannot be written: an installation does not go on without it.
     */
    public function begin(string $keyHash, int $now): void
    {
        // Direct write: nothing here can stop to ask for credentials, and the path is the site's root.
        $written = is_dir($this->siteRoot)
            && @file_put_contents($this->file(), $this->contents($keyHash, $now)) !== false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

        if (! $written) {
            throw new ReleaseRefused(ReleaseHostFacts::NOT_WRITABLE, sprintf(
                /* translators: %s: a folder's path on the server. */
                __('The site could not write its maintenance file in %s, so visitors could not be kept off a half-installed release. Nothing was changed. Ask the host to let the site write there.', 'corex'),
                rtrim($this->siteRoot, '/\\'),
            ));
        }
    }

    public function end(): void
    {
        if (is_file($this->file())) {
            unlink($this->file());
        }
    }

    public function isOn(): bool
    {
        return is_file($this->file());
    }

    /** Where the file is, for the recovery file that removes it when CoreX cannot. */
    public function file(): string
    {
        return rtrim($this->siteRoot, '/\\') . '/' . self::FILE;
    }

    /**
     * What WordPress includes. It sets `$upgrading`, which WordPress compares with the time.
     *
     * A float, where WordPress's own file has an integer: WordPress lets a request through
     * maintenance when it carries the MD5 of an integer `$upgrading`, for its plugin editor's
     * check for fatal errors. The time an installation began can be guessed, and that request
     * would run a half-replaced site.
     */
    private function contents(string $keyHash, int $now): string
    {
        $header   = 'HTTP_' . strtoupper(str_replace('-', '_', self::HEADER));
        $argument = self::ARGUMENT;

        return "<?php\n"
            . "// Written by CoreX while a release is being installed. WordPress reads this before any plugin.\n"
            . '$upgrading = ' . $now . ".0;\n"
            . '$corex_release_key = $_SERVER[' . var_export($header, true) . '] ?? ( $_GET[' . var_export($argument, true) . "] ?? '' );\n"
            . 'if ( is_string( $corex_release_key ) && hash_equals( ' . var_export($keyHash, true) . ", hash( 'sha256', \$corex_release_key ) ) ) {\n"
            . "\t\$upgrading = 0.0;\n"
            . "}\n"
            . "unset( \$corex_release_key );\n";
    }
}
