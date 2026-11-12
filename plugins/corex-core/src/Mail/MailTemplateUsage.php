<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Which kinds of email bind an Email Studio template, asked of the code that binds it, so that a
 * template that uses a variable those emails do not supply is warned about by name (spec 120,
 * story 5).
 *
 * Bound by corex-forms, whose flows bind templates to their emails.
 */
interface MailTemplateUsage
{
    /** A form's emails: a flow's team notification, submitter confirmation, failure alert. */
    public const FORM = 'form';

    /**
     * @return list<string> The kinds of email, as this interface's constants, that send the
     *                      template; empty when nothing here binds it.
     */
    public function emailsUsing(int $templateId): array;
}
