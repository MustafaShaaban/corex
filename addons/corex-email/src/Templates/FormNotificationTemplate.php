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
 * The notification a code-defined form other than the contact form sends its team: every answer
 * with its label, and a link to the stored submission. It was a finished body with no frame;
 * it is drawn in the one frame every CoreX email is in (spec 120, story 3).
 */
final class FormNotificationTemplate extends EmailTemplate
{
    public const NAME = 'form-notification';

    public function name(): string
    {
        return self::NAME;
    }

    public function subject(MailContext $context): string
    {
        /* translators: %s: form slug */
        return sprintf(__('New "%s" form submission', 'corex'), '{{ form.slug }}');
    }

    public function body(MailContext $context): string
    {
        /* translators: %s: form slug */
        $body = '<p>' . sprintf(esc_html__('A new "%s" form submission was received:', 'corex'), '{{ form.slug }}') . '</p>';
        $rows = '';
        foreach ($context->rows('answers') as $answer) {
            $rows .= sprintf(
                '<tr><th style="padding:6px 16px 6px 0;vertical-align:top;text-align:start;font-weight:600">%s</th>'
                . '<td style="padding:6px 0;vertical-align:top;text-align:start">%s</td></tr>',
                self::text($answer['label'] ?? ''),
                nl2br(self::text($answer['value'] ?? ''), false),
            );
        }
        $body .= $rows === ''
            ? '<p>' . esc_html__('The submission contained no fields.', 'corex') . '</p>'
            : '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>';

        $address = $context->get('submission_url');
        $files = (int) $context->get('file_count', '0');
        if ($address !== '' && $files > 0) {
            $body .= '<p>' . esc_html(_n(
                'The file is opened from the submission, after signing in to the site.',
                'The files are opened from the submission, after signing in to the site.',
                $files,
                'corex',
            )) . '</p>';
        }
        if ($address !== '') {
            $body .= sprintf(
                '<p><a class="button" href="%s">%s</a></p>',
                esc_url($address),
                esc_html__('Open the submission', 'corex'),
            );
        }

        return $body;
    }

    public function kind(): string
    {
        return EmailKind::NOTIFICATION;
    }

    /**
     * A visitor's answer as text, and not as a placeholder: the renderer merges `{{ … }}` in what
     * a template returns, and an answer is not the template's to have read again.
     */
    private static function text(string $value): string
    {
        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
