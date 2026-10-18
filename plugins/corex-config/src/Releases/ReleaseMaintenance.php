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
 * The key is looked for in three places, because a shared host may not hand a request header to
 * PHP (FR-024a): the header as PHP was given it, the header as the web server holds it
 * (`getallheaders()`), and the address. The first that holds the key opens the gate, and the
 * place it was found in is kept for the request (`keyCameFrom()`), so an installation's record
 * says how its key got through.
 *
 * CoreX's own Maintenance mode is not used. It answers after every plugin has loaded.
 */
final class ReleaseMaintenance
{
    /** The request header, and the query argument, a key is carried in. */
    public const HEADER   = 'X-Corex-Release-Key';
    public const ARGUMENT = 'corex_release_key';

    /**
     * Where the gate found the key of a request it let through, as `keyCameFrom()` answers:
     * the header as PHP was given it, the header as the web server holds it, the address.
     */
    public const FROM_HEADER  = 'header';
    public const FROM_SERVER  = 'server_headers';
    public const FROM_ADDRESS = 'address';

    /** A request that came in without the gate's leave: maintenance was off, or had ended by itself. */
    public const NOT_NEEDED = 'not_needed';

    /** What the gate defines when it lets a request through: where the key was. */
    private const CAME_FROM = 'COREX_RELEASE_KEY_FROM';

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

    /**
     * Where the gate found the key of this request, when it let it through maintenance; or
     * `NOT_NEEDED`, when the request came in without it.
     */
    public static function keyCameFrom(): string
    {
        return defined(self::CAME_FROM) ? (string) constant(self::CAME_FROM) : self::NOT_NEEDED;
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
        return strtr(self::GATE, [
            '{NOW}'          => $now . '.0',
            '{KEY_HASH}'     => var_export($keyHash, true),
            '{SERVER_KEY}'   => var_export('HTTP_' . strtoupper(str_replace('-', '_', self::HEADER)), true),
            '{HEADER}'       => var_export(self::HEADER, true),
            '{ARGUMENT}'     => var_export(self::ARGUMENT, true),
            '{CAME_FROM}'    => var_export(self::CAME_FROM, true),
            '{FROM_HEADER}'  => var_export(self::FROM_HEADER, true),
            '{FROM_SERVER}'  => var_export(self::FROM_SERVER, true),
            '{FROM_ADDRESS}' => var_export(self::FROM_ADDRESS, true),
        ]);
    }

    /**
     * The gate. WordPress includes it inside a function, so what it sets is the function's,
     * except `$upgrading`, which WordPress declares global, and the constant, which says where
     * the key was. It may be included more than once in a request.
     */
    private const GATE = <<<'PHP'
<?php
// Written by CoreX while a release is being installed. WordPress reads this before any plugin.
$upgrading = {NOW};
$corex_release_keys = array( {FROM_HEADER} => $_SERVER[{SERVER_KEY}] ?? null );
if ( function_exists( 'getallheaders' ) ) {
	foreach ( (array) getallheaders() as $corex_release_name => $corex_release_value ) {
		if ( 0 === strcasecmp( (string) $corex_release_name, {HEADER} ) ) {
			$corex_release_keys[ {FROM_SERVER} ] = $corex_release_value;
		}
	}
}
$corex_release_keys[ {FROM_ADDRESS} ] = $_GET[{ARGUMENT}] ?? null;
foreach ( $corex_release_keys as $corex_release_from => $corex_release_key ) {
	if ( is_string( $corex_release_key ) && hash_equals( {KEY_HASH}, hash( 'sha256', $corex_release_key ) ) ) {
		$upgrading = 0.0;
		defined( {CAME_FROM} ) || define( {CAME_FROM}, $corex_release_from );
		break;
	}
}
unset( $corex_release_keys, $corex_release_name, $corex_release_value, $corex_release_from, $corex_release_key );

PHP;
}
