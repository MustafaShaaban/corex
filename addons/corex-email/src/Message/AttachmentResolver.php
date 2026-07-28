<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Message;

defined('ABSPATH') || exit;

/**
 * Turns attachment ids into absolute paths `wp_mail()` can send (spec 081, FR-010).
 *
 * `COREX-EMAIL-ADDON.md` has described this class since the add-on was specified, and it did not
 * exist — nor did `attach()`, `attachMedia()` or `attachGenerated()`. The mail stack carried no
 * attachments field at all and `WpMailDriver` called `wp_mail()` with four arguments, so the
 * `$attachments` parameter was never passed. The documentation promised a capability the framework
 * did not have.
 *
 * **Ids in, paths out, and never the reverse.** A caller names an attachment by id; this resolves
 * it. Nothing accepts a path from a request, which is the whole of the path-traversal defence the
 * add-on spec claims — a caller who could supply `../../wp-config.php` would have a mail-to-self
 * exfiltration primitive.
 */
final class AttachmentResolver
{
    /**
     * @param list<int> $attachmentIds
     *
     * @return list<string> absolute paths to files that exist, in the order given
     */
    public function resolve(array $attachmentIds): array
    {
        $paths = [];

        foreach ($attachmentIds as $attachmentId) {
            $id = (int) $attachmentId;
            if ($id <= 0) {
                continue;
            }

            $path = get_attached_file($id);

            // A missing file is skipped, not fatal. The alternative is refusing to send an email
            // because one attachment has gone — and the message is usually the important part,
            // while the attachment is usually recoverable from the record it came from.
            if (is_string($path) && $path !== '' && is_file($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
