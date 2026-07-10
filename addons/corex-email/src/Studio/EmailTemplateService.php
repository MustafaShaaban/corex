<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DomainException;
use Corex\Email\Template\Layout;

/**
 * Validates editable content and renders only schema-declared scalar context.
 */
final class EmailTemplateService
{
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    /** @param array<string,array{type:string,label:string}> $variableSchema */
    public function __construct(
        private readonly array $variableSchema,
        private readonly ?EmailPartialRepository $partials = null,
        private readonly ?Layout $brandLayout = null,
    ) {
    }

    /** @return array<string,string> */
    public function validateDraft(EmailTemplateVersion $version): array
    {
        $errors = [];
        $htmlBody = $version->htmlBody;
        $plainText = $version->plainText;
        if (trim($version->subject) === '') {
            $errors['subject'] = __('An email subject is required.', 'corex');
        }
        if (trim($htmlBody) === '') {
            $errors['html'] = __('Email template HTML is required.', 'corex');
        }
        try {
            $htmlBody = $this->expandPartials($htmlBody, true);
            $plainText = $this->expandPartials($plainText, false);
        } catch (DomainException $exception) {
            $errors['partials'] = $exception->getMessage();
        }
        if ($this->containsControlCharacter($version->subject)
            || $this->containsControlCharacter($version->fromName)
            || $this->containsControlCharacter($version->fromAddress)
        ) {
            $errors['headers'] = __('Email header fields contain unsafe characters.', 'corex');
        }

        if (filter_var($version->fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors['fromAddress'] = __('The sender address is invalid.', 'corex');
        }

        if ($this->containsUnsafeMarkup($htmlBody)) {
            $errors['html'] = __('Template HTML contains unsafe content.', 'corex');
        }

        $declared = array_fill_keys($version->variableKeys, true);
        foreach ($version->variableKeys as $key) {
            if (! isset($this->variableSchema[$key])) {
                $errors['variables'] = __('Template variables do not match the declared schema.', 'corex');
                break;
            }
        }

        foreach ($this->placeholders($version, $htmlBody, $plainText) as $key) {
            if (! isset($declared[$key], $this->variableSchema[$key])) {
                $errors['variables'] = __('Template variables do not match the declared schema.', 'corex');
                break;
            }
        }

        return $errors;
    }

    /** @return array<string,array<string,array{type:string,label:string}>> */
    public function variableCatalog(): array
    {
        $catalog = [];
        foreach ($this->variableSchema as $key => $definition) {
            $definition['label'] = $this->variableLabel($key, $definition['label']);
            $catalog[$this->variableGroup($key)][$key] = $definition;
        }

        return $catalog;
    }

    /** @return array<string,string> */
    public function validateFragment(string $html): array
    {
        if ($this->containsUnsafeMarkup($html)) {
            return ['html' => __('Email HTML contains unsafe content.', 'corex')];
        }

        $withoutSpecialTokens = preg_replace('/\{\{\s*(?:content|>\s*[a-z0-9-]+)\s*\}\}/i', '', $html) ?? $html;
        preg_match_all(self::PLACEHOLDER, $withoutSpecialTokens, $matches);
        foreach (array_unique($matches[1] ?? []) as $key) {
            if (! isset($this->variableSchema[$key])) {
                return ['variables' => __('Email HTML contains an unknown variable.', 'corex')];
            }
        }

        return [];
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array{subject:string,html:string,plain:string}
     */
    public function render(EmailTemplateVersion $version, array $context, ?EmailLayout $emailLayout = null): array
    {
        $errors = $this->validateDraft($version);
        if ($errors !== []) {
            throw new DomainException(__('Unsafe or invalid email template content cannot be rendered.', 'corex'));
        }

        $subject = trim(strip_tags($this->merge($version->subject, $context, html: false)));
        $html    = $this->merge($this->expandPartials($version->htmlBody, true), $context, html: true);
        $plain   = $version->plainTextMode === EmailTemplateVersion::PLAIN_AUTO
            ? $this->toPlainText($html)
            : $this->merge($this->expandPartials($version->plainText, false), $context, html: false);

        if ($emailLayout !== null) {
            $html = $this->applyLayout($emailLayout, $subject, $html, $context);
        }

        return [
            'subject' => $subject,
            'html'    => $html,
            'plain'   => $plain,
        ];
    }

    private function containsControlCharacter(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    private function containsUnsafeMarkup(string $html): bool
    {
        return preg_match(
            '/<\?(?:php|=)|<\s*\/?\s*(?:script|style|iframe|object|embed|form|meta|link|base)\b|\s+on[a-z]+\s*=|(?:java|vb)script\s*:|data\s*:\s*text\/html\s*:/i',
            $html,
        ) === 1;
    }

    /** @return list<string> */
    private function placeholders(EmailTemplateVersion $version, ?string $htmlBody = null, ?string $plainText = null): array
    {
        preg_match_all(self::PLACEHOLDER, implode("\n", [
            $version->subject,
            $htmlBody ?? $version->htmlBody,
            $plainText ?? $version->plainText,
        ]), $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @param array<string,mixed> $context */
    private function merge(string $content, array $context, bool $html): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            function (array $matches) use ($context, $html): string {
                $value = $this->contextValue($context, $matches[1]);
                if ($html) {
                    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }

                return trim(strip_tags($value));
            },
            $content,
        ) ?? $content;
    }

    /** @param array<string,mixed> $context */
    private function contextValue(array $context, string $path): string
    {
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return '';
            }

            $value = $value[$segment];
        }

        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }

    private function toPlainText(string $html): string
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    private function variableGroup(string $key): string
    {
        return match (strstr($key, '.', true) ?: $key) {
            'site'                => __('Site', 'corex'),
            'user', 'recipient'   => __('Recipient', 'corex'),
            'submission', 'form'  => __('Submission', 'corex'),
            'action', 'links'     => __('Links', 'corex'),
            default               => __('Other', 'corex'),
        };
    }

    private function variableLabel(string $key, string $fallback): string
    {
        return match ($key) {
            'user.name'         => __('User name', 'corex'),
            'user.email'        => __('User email', 'corex'),
            'recipient.name'    => __('Recipient name', 'corex'),
            'recipient.email'   => __('Recipient email', 'corex'),
            'site.name'         => __('Site name', 'corex'),
            'site.url'          => __('Site URL', 'corex'),
            'action.url'        => __('Action URL', 'corex'),
            'links.unsubscribe' => __('Unsubscribe URL', 'corex'),
            'links.preferences' => __('Preferences URL', 'corex'),
            'links.privacy'     => __('Privacy URL', 'corex'),
            'submission.name'   => __('Submission name', 'corex'),
            'submission.email'  => __('Submission email', 'corex'),
            'submission.body'   => __('Submission body', 'corex'),
            default             => $fallback,
        };
    }

    private function expandPartials(string $content, bool $html): string
    {
        if ($this->partials === null) {
            return $content;
        }

        return preg_replace_callback(
            '/\{\{\s*>\s*([a-z][a-z0-9-]*)\s*\}\}/',
            function (array $matches) use ($html): string {
                $partial = $this->partials?->find($matches[1]);
                if ($partial === null || $partial->status !== 'active') {
                    throw new DomainException(__('The template references an unavailable email partial.', 'corex'));
                }

                return $html ? $partial->htmlBody : $partial->plainText;
            },
            $content,
        ) ?? $content;
    }

    /** @param array<string,mixed> $context */
    private function applyLayout(EmailLayout $layout, string $subject, string $bodyHtml, array $context): string
    {
        $regions = [];
        foreach ($layout->regions as $name => $content) {
            if ($name === 'accent') {
                $regions[$name] = $content;
                continue;
            }

            $expanded = $this->expandPartials($content, true);
            if ($name === 'body') {
                $expanded = str_replace('{{ content }}', '__COREX_EMAIL_CONTENT__', $expanded);
            }
            $regions[$name] = str_replace(
                '__COREX_EMAIL_CONTENT__',
                '{{ content }}',
                $this->merge($expanded, $context, html: true),
            );
        }

        $body = str_replace('{{ content }}', $bodyHtml, $regions['body'] ?? '{{ content }}');
        if ($this->brandLayout === null) {
            return ($regions['header'] ?? '') . $body . ($regions['button'] ?? '') . ($regions['footer'] ?? '');
        }

        return $this->brandLayout->wrap($subject, $body, $regions);
    }
}
