<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * The neutral mail seam. A module that wants to send email depends on this
 * interface, never on a concrete mail engine; the Corex Mail add-on binds an
 * implementation. A consumer checks the container for a binding to decide whether
 * a real engine is active (detect-and-defer) — see the Forms email listener.
 *
 * Sending is best-effort: an implementation MUST NOT throw — delivery failures are
 * the engine's concern to catch and log, never the caller's request to abort.
 */
interface Mailer
{
    public function send(MailRequest $request): void;
}
