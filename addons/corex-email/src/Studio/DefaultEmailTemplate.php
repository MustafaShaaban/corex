<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * An Email Studio template CoreX ships, as it ships it (spec 120, story 4): what a new site starts
 * with, and what "Reset to shipped version" puts back.
 */
final readonly class DefaultEmailTemplate
{
    /**
     * @param string $kind       What kind of email it is sent as ({@see \Corex\Email\Design\EmailKind}).
     * @param string $layoutSlug The shipped layout it is arranged by.
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $kind,
        public string $layoutSlug,
        public string $subject,
        public string $htmlBody,
    ) {
    }
}
