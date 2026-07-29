<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

/**
 * One support message, and the context that makes it answerable (spec 093).
 *
 * Introduced because the message now has **two** renderings — a branded HTML template when Corex
 * Mail is bound, and plain text for the `wp_mail()` floor — and the parts must not be assembled
 * twice. When the controller composed the body itself, the HTML path could only have been a second
 * copy of the same fields, free to drift from the first.
 *
 * Everything here is already sanitized by {@see SupportRequestController}. This object formats; it
 * does not clean.
 */
final readonly class SupportMessage
{
    /** Beyond this it is an attachment, not a note. Applied once, here, for both renderings. */
    public const MAX_MESSAGE = 4000;

    public function __construct(
        public string $categoryLabel,
        public string $message,
        public string $replyTo,
        public string $senderName,
        public string $siteName,
        public string $siteUrl,
    ) {
    }

    public function subject(): string
    {
        return sprintf(
            /* translators: 1: the site name, 2: what the message is about. */
            __('[%1$s] Guides support: %2$s', 'corex'),
            $this->siteName,
            $this->categoryLabel,
        );
    }

    /** The message, truncated once so neither rendering has to remember to. */
    public function body(): string
    {
        return mb_substr($this->message, 0, self::MAX_MESSAGE);
    }

    public function sender(): string
    {
        return $this->senderName !== '' ? $this->senderName : __('a signed-in user', 'corex');
    }

    public function replyAddress(): string
    {
        return $this->replyTo !== '' ? $this->replyTo : __('no address given', 'corex');
    }

    /**
     * The plain-text rendering, for the `wp_mail()` floor.
     *
     * Kept because that rung sends without a `Content-Type` header, so the recipient's client treats
     * it as `text/plain` and these newlines are real. The Corex Mail rung is the opposite — its
     * driver stamps `text/html` on everything — which is why sending this string there produced one
     * run-on paragraph until spec 093.
     */
    public function plainText(): string
    {
        return implode("\n", [
            __('A CoreX Guides reader sent this from the admin.', 'corex'),
            '',
            /* translators: %s: what the message is about, e.g. "A suggestion". */
            sprintf(__('About: %s', 'corex'), $this->categoryLabel),
            /* translators: %s: the sender's display name. */
            sprintf(__('From: %s', 'corex'), $this->sender()),
            /* translators: %s: the sender's email address. */
            sprintf(__('Reply to: %s', 'corex'), $this->replyAddress()),
            /* translators: %s: the site's home URL. */
            sprintf(__('Site: %s', 'corex'), $this->siteUrl),
            '',
            __('Message:', 'corex'),
            $this->body(),
        ]);
    }

    /**
     * The merge context for {@see SupportRequestTemplate}.
     *
     * Nested under `support` because `MailContext` walks dotted paths, and a flat `message` key
     * would sit in the same namespace as the `submission.*` and `user.*` variables Email Studio
     * defines.
     *
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return [
            'support' => [
                'category' => $this->categoryLabel,
                'sender'   => $this->sender(),
                'reply_to' => $this->replyAddress(),
                'site'     => $this->siteUrl,
                // The template builds its own subject from these two, because the renderer ignores
                // the request's subject whenever a template is named.
                'site_name' => $this->siteName,
                'message'  => $this->body(),
            ],
        ];
    }
}
