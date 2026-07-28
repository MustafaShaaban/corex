<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * The only way to read a file stored by {@see AttachmentStore} (spec 081, FR-009 / FR-015).
 *
 * The file lives outside anything the web server should serve, so it has no working public URL —
 * this route is how an authorised operator opens it. That inversion is the design: **the check
 * happens before a byte is read**, in PHP, on every request, rather than depending on a `.htaccess`
 * the server may or may not honour.
 *
 * Two things it deliberately does not do:
 *
 * - **No signed URLs with an expiry.** A signed URL is a bearer token in a browser history, a
 *   referrer header and an email forward. The viewer is already logged in; asking who they are is
 *   cheaper and does not leak.
 * - **No `X-Sendfile`/`X-Accel-Redirect`.** Both are faster and both hand the file back to the web
 *   server's configuration — the thing this class exists because CoreX cannot verify.
 */
final class AttachmentDelivery
{
    /** The `admin-post.php` action this listens on. */
    public const ACTION = 'corex_attachment';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'serve']);
    }

    /**
     * The address an authorised viewer opens.
     */
    public static function url(int $attachmentId): string
    {
        return add_query_arg(
            [
                'action' => self::ACTION,
                'id'     => $attachmentId,
                '_wpnonce' => wp_create_nonce(self::ACTION . '_' . $attachmentId),
            ],
            admin_url('admin-post.php'),
        );
    }

    public function serve(): void
    {
        $attachmentId = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified immediately below.

        if ($attachmentId <= 0
            || ! isset($_GET['_wpnonce'])
            || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::ACTION . '_' . $attachmentId)
        ) {
            wp_die(esc_html__('That link is no longer valid.', 'corex'), 403);
        }

        // The capability, not the nonce. A nonce proves the link came from us; it does not prove
        // the person holding it may read somebody's CV.
        if (! current_user_can('read_post', $attachmentId)) {
            wp_die(esc_html__('You don’t have permission to open this file.', 'corex'), 403);
        }

        if (get_post_meta($attachmentId, '_corex_protected', true) !== '1') {
            // Not ours to serve. Anything else in the media library has its own URL and its own
            // rules, and turning this into a general file reader is how it becomes an LFI.
            wp_die(esc_html__('That file is not available here.', 'corex'), 404);
        }

        $path = get_attached_file($attachmentId);
        if (! is_string($path) || ! is_file($path)) {
            wp_die(esc_html__('That file is missing.', 'corex'), 404);
        }

        $this->send($attachmentId, $path);
    }

    private function send(int $attachmentId, string $path): never
    {
        $type = get_post_mime_type($attachmentId);

        nocache_headers();
        header('Content-Type: ' . ($type !== false ? $type : 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        // `attachment`, always: an inline SVG or HTML would otherwise execute on the admin's own
        // origin, which is the one place a visitor-supplied file must never run.
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }
}
