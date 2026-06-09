<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Template;

defined('ABSPATH') || exit;

/**
 * Renders a template against a context: replaces each `{{ path }}` with the
 * whitelisted context value — escaped for HTML in the body, plain in the subject —
 * then wraps the body in the brand layout. Pure (no WordPress): body values are
 * escaped with `htmlspecialchars`, so a value containing markup is never live
 * (spec FR-003, FR-004, FR-005). An unknown placeholder renders empty.
 */
final class TemplateRenderer
{
    public function __construct(private readonly Layout $layout)
    {
    }

    public function render(EmailTemplate $template, MailContext $context): RenderedEmail
    {
        $subject = $this->merge($template->subject($context), $context, escape: false);
        $body    = $this->layout->wrap($subject, $this->merge($template->body($context), $context, escape: true));

        return new RenderedEmail($subject, $body);
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
