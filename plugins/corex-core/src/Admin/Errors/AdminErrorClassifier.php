<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin\Errors;

defined('ABSPATH') || exit;

/**
 * Decides what kind of refusal this is, from the HTTP status and from which upstream hook fired
 * (spec 083, FR-003).
 *
 * **It never reads the message.** WordPress does not have one refusal sentence, it has many — the
 * running install produced "Sorry, you are not allowed to access this page.", "…to list users." and
 * "…to manage options for this site." for the same class of event — and every one of them is
 * translated. Classification by string would be correct on an English install and wrong everywhere
 * else, which is the worst of both: it looks tested. See
 * `specs/083-admin-error-surface/evidence/before/refusal-matrix.md`.
 *
 * Pure: no WordPress, no state, no side effects.
 */
final class AdminErrorClassifier
{
    /** `admin_page_access_denied` fired — WordPress refused this screen for want of a capability. */
    public const MARKER_DENIED = 'denied';

    /** `check_admin_referer` failed — the link or form carried a nonce that is no longer valid. */
    public const MARKER_EXPIRED = 'expired';

    /**
     * @param int|null    $status The status the caller passed to `wp_die()`, if any.
     * @param string|null $marker One of the MARKER_* constants, set by whichever hook fired first.
     */
    public function classify(?int $status, ?string $marker = null): AdminErrorKind
    {
        // The marker outranks the status because it is the more specific fact. An expired nonce and
        // a capability denial are both 403, and telling somebody their role lacks a capability when
        // their link simply went stale sends them to ask an administrator for access they already
        // have.
        if ($marker === self::MARKER_EXPIRED) {
            return AdminErrorKind::Expired;
        }

        if ($marker === self::MARKER_DENIED) {
            return AdminErrorKind::Denied;
        }

        return match ($status) {
            401 => AdminErrorKind::Session,
            403 => AdminErrorKind::Denied,
            404 => AdminErrorKind::NotFound,
            429 => AdminErrorKind::RateLimited,
            503 => AdminErrorKind::Unavailable,
            default => AdminErrorKind::Failed,
        };
    }
}
