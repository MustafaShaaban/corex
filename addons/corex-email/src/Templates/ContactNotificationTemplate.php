<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Templates;

defined('ABSPATH') || exit;

use Corex\Email\Design\EmailKind;
use Corex\Email\Template\EmailTemplate;
use Corex\Email\Template\MailContext;

/**
 * The example template: the admin notification for a contact-form submission. Its
 * `{{ submission.* }}` placeholders are merged + escaped by the renderer; the labels
 * are translation-ready.
 */
final class ContactNotificationTemplate extends EmailTemplate
{
    public function name(): string
    {
        return 'contact-notification';
    }

    public function subject(MailContext $context): string
    {
        return __('New contact form submission', 'corex');
    }

    public function body(MailContext $context): string
    {
        return '<p>' . esc_html__('A new contact form submission was received:', 'corex') . '</p>'
            . '<table role="presentation" cellpadding="8" style="text-align:start">'
            . '<tr><th style="text-align:start">' . esc_html__('Name', 'corex') . '</th><td>{{ submission.name }}</td></tr>'
            . '<tr><th style="text-align:start">' . esc_html__('Email', 'corex') . '</th><td>{{ submission.email }}</td></tr>'
            . '<tr><th style="text-align:start">' . esc_html__('Message', 'corex') . '</th><td>{{ submission.message }}</td></tr>'
            . '</table>'
            . ($context->get('submission_url') === ''
                ? ''
                : sprintf('<p><a class="button" href="%s">%s</a></p>', esc_url($context->get('submission_url')), esc_html__('Open the submission', 'corex')));
    }

    /** A notice to the site's team (spec 120). */
    public function kind(): string
    {
        return EmailKind::NOTIFICATION;
    }
}
