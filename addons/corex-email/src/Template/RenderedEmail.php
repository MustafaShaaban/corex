<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * The merged subject, the framed HTML body and the same words as plain text, produced by the
 * renderer.
 */
final class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        /** Sent as the text/plain part beside the HTML (spec 120). Null for none. */
        public readonly ?string $plainText = null,
    ) {
    }
}
