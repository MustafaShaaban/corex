<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Emails an owner designs in Email Studio and CoreX code sends: a newsletter's double opt-in,
 * an account's password reset (spec 120).
 *
 * Bound by CoreX Mail. Code that sends one asks for it by its template's slug and keeps an email
 * of its own for when it cannot be drawn: the template is a draft, CoreX Mail is off, or what an
 * owner wrote no longer draws. An email sent here goes through the shared mailer, under the
 * delivery rule every other CoreX email on it follows.
 */
interface DesignedEmails
{
    /**
     * Sends the active version of the template with this slug, drawn with the context, to these
     * addresses.
     *
     * @param list<string>        $to
     * @param array<string,mixed> $context The values its variables are filled from.
     * @param string              $path    What the Email Studio log calls it ("Account: password reset").
     *
     * @return MailResult|null What became of it; null when no active template has the slug or it
     *                         could not be drawn, which is recorded, and nothing was sent.
     */
    public function send(string $slug, array $to, array $context, string $path): ?MailResult;
}
