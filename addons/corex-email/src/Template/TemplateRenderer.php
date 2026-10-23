<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailFrame;
use Corex\Email\Design\PlainText;
use Corex\Mail\EmailLanguage;

/**
 * Renders a template against a context: replaces each `{{ path }}` with the
 * whitelisted context value — escaped for HTML in the body, plain in the subject —
 * then draws the body in the one frame every CoreX email is in (spec 120), with the
 * same words as plain text beside it. Body values are escaped with `htmlspecialchars`,
 * so a value containing markup is never live (spec FR-003, FR-004, FR-005). An
 * unknown placeholder renders empty.
 */
final class TemplateRenderer
{
    public function __construct(
        private readonly EmailFrame $frame,
        private readonly EmailLanguage $language,
    ) {
    }

    public function render(EmailTemplate $template, MailContext $context): RenderedEmail
    {
        // A template's own words are translated as it is asked for them, and the frame reads
        // which way the text runs as it draws: both in the email's language.
        return $this->language->during(function () use ($template, $context): RenderedEmail {
            $subject = $this->merge($template->subject($context), $context, escape: false);
            $body    = $this->merge($template->body($context), $context, escape: true);
            $kind    = $template->kind();

            return new RenderedEmail(
                $subject,
                $this->frame->draw(new EmailContent($subject, $body, '', $kind, $template->links($context))),
                PlainText::of($body) . "\n\n" . $this->frame->plainFooter($kind),
            );
        });
    }

    private function merge(string $text, MailContext $context, bool $escape): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static function (array $matches) use ($context, $escape): string {
                $value = $context->get($matches[1]);

                return $escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
            },
            $text,
        ) ?? $text;
    }
}
