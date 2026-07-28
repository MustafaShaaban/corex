<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * The directory uploaded personal data lives in, and the rules that keep a web server out of it
 * (spec 081, FR-015).
 *
 * A CV somebody sent to one company is not a media-library asset. In `uploads/` it gets a guessable
 * public URL and shows up in every author's media picker; the whole point of this directory is that
 * neither is true.
 *
 * **The deny rules are not the guarantee.** They are configuration files, and CoreX cannot verify a
 * server honours them — nginx ignores `.htaccess` entirely, and plenty of hosts disable
 * `AllowOverride`. The guarantee is {@see \Corex\Security\Upload\AttachmentDelivery}, which checks
 * the viewer before it reads a byte. These files are what stops a *misconfigured* server handing
 * out a file the route would have refused, which is a real and common failure and worth two hundred
 * bytes on disk.
 */
final class ProtectedUploads
{
    /** Relative to the uploads base directory. */
    public const DIRECTORY = 'corex-private';

    /**
     * Ensure the directory exists and carries its deny rules.
     *
     * Called before every store rather than once on activation: activation hooks do not run when a
     * plugin is updated by copying files over the old ones, and a directory that lost its
     * `.htaccess` that way would be silently public until somebody noticed.
     *
     * @return string absolute path to the protected directory, or '' when it cannot be created
     */
    public static function ensure(): string
    {
        $uploads = wp_upload_dir();
        if (! empty($uploads['error'])) {
            return '';
        }

        $path = trailingslashit($uploads['basedir']) . self::DIRECTORY;

        if (! wp_mkdir_p($path)) {
            return '';
        }

        self::write($path . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");

        // Directory listing, for a server that serves the folder but honours neither deny rule.
        self::write($path . '/index.php', "<?php\n// Silence is golden.\n");

        // IIS reads neither of the above.
        self::write(
            $path . '/web.config',
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<configuration><system.webServer><authorization>\n"
            . "<deny users=\"*\" />\n"
            . "</authorization></system.webServer></configuration>\n",
        );

        return $path;
    }

    /**
     * Write a guard file, once.
     *
     * Never overwrites: a site that has deliberately adjusted its own rules — a host that needs a
     * different directive to achieve the same refusal — should not have that undone on every
     * upload.
     */
    private static function write(string $file, string $contents): void
    {
        if (is_file($file)) {
            return;
        }

        // Direct write rather than WP_Filesystem: this runs inside an upload request, and
        // WP_Filesystem can demand FTP credentials it has no way to ask for here. The path is
        // ours, not caller-supplied.
        file_put_contents($file, $contents); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
}
