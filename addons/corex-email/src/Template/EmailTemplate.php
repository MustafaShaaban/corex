<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * A code-registered email template: a stable name and the subject/body sources.
 * The sources are straight-line text with `{{ path }}` placeholders, merged from
 * the MailContext by the renderer. The context is available for templates that
 * vary their text, but MVP templates return literals with placeholders.
 */
abstract class EmailTemplate
{
    abstract public function name(): string;

    abstract public function subject(MailContext $context): string;

    abstract public function body(MailContext $context): string;
}
