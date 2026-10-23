<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Closure;
use DomainException;
use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailFrame;
use Corex\Email\Design\EmailKind;
use Corex\Email\Design\PlainText;
use Corex\Mail\EmailLanguage;
use Corex\Mail\SubjectLine;

/**
 * Validates an Email Studio template and draws it: the one drawing the preview, the test send and
 * every real send are made of (spec 120).
 *
 * A template's values are escaped into its HTML and written as they are into its plain text; a
 * value with nothing in it leaves out the paragraph, list item, heading or table row it was the
 * point of, and is named in what the drawing returns.
 */
final class EmailTemplateService
{
    private const PLACEHOLDER = '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/';

    private const PARTIAL = '/\{\{\s*>\s*([a-z][a-z0-9-]*)\s*\}\}/';

    /** An element a value can be the point of: left out whole when every value in it is empty. */
    private const BLOCK = '#<(p|li|h[1-6]|tr)\b[^>]*>(?:(?!<\1\b).)*?</\1>#is';

    /** Where a layout's body puts the template's content. */
    private const CONTENT = '{{ content }}';

    private readonly EmailVariableCatalog $variables;

    /**
     * @param EmailVariableCatalog|array<string,array{type?:string,label?:string}> $variables
     *        The catalog, or the `mail.variables` configuration it is made from.
     */
    public function __construct(
        EmailVariableCatalog|array $variables,
        private readonly ?EmailPartialRepository $partials = null,
        private readonly ?EmailFrame $frame = null,
        private readonly ?EmailLanguage $language = null,
    ) {
        $this->variables = $variables instanceof EmailVariableCatalog ? $variables : new EmailVariableCatalog($variables);
    }

    /**
     * What is wrong with a template version, by field, each said in a sentence that names what
     * is wrong: an unknown variable by its key, a missing partial by its name.
     *
     * @return array<string,string>
     */
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
        if ($this->containsControlCharacter($version->subject)) {
            $errors['subject'] = __('The subject cannot contain a line break.', 'corex');
        }
        if ($this->containsControlCharacter($version->fromName)) {
            $errors['fromName'] = __('The From name cannot contain a line break.', 'corex');
        }
        // Optional: a template with none leaves from Settings → Mail's sender (spec 120).
        if ($version->fromAddress !== ''
            && ($this->containsControlCharacter($version->fromAddress)
                || filter_var($version->fromAddress, FILTER_VALIDATE_EMAIL) === false)
        ) {
            $errors['fromAddress'] = sprintf(
                /* translators: %s: what was typed as the From address. */
                __('The From address is not a valid email address: %s', 'corex'),
                $version->fromAddress,
            );
        }

        if ($this->containsUnsafeMarkup($htmlBody)) {
            $errors['html'] = __('Template HTML contains unsafe content.', 'corex');
        }

        $unknown = array_values(array_filter(
            $this->keysIn($version->subject, $htmlBody, $plainText),
            fn (string $key): bool => ! $this->variables->isKnown($key),
        ));
        if ($unknown !== []) {
            $errors['variables'] = sprintf(
                /* translators: %s: one or more variable keys, comma separated. */
                _n('Unknown variable: %s.', 'Unknown variables: %s.', count($unknown), 'corex'),
                implode(', ', $unknown),
            );
        }

        return $errors;
    }

    /**
     * The variables a version uses, partials included: what it is declared to use.
     *
     * @return list<string>
     */
    public function variablesOf(EmailTemplateVersion $version): array
    {
        return $this->variablesIn($version->subject, $version->htmlBody, $version->plainText);
    }

    /**
     * The variables a subject, an HTML body and a plain text use, partials included.
     *
     * @return list<string>
     */
    public function variablesIn(string $subject, string $htmlBody, string $plainText): array
    {
        try {
            return $this->keysIn(
                $subject,
                $this->expandPartials($htmlBody, true),
                $this->expandPartials($plainText, false),
            );
        } catch (DomainException) {
            return $this->keysIn($subject, $htmlBody, $plainText);
        }
    }

    /**
     * A sentence for each variable the given emails do not supply ("user.name is not available
     * in a form email"), by variable.
     *
     * @param list<string> $groups The {@see EmailVariableCatalog} groups the template is sent as.
     *
     * @return array<string,string>
     */
    public function unsupplied(EmailTemplateVersion $version, array $groups): array
    {
        $warnings = [];
        foreach ($this->variablesOf($version) as $key) {
            foreach (array_unique($groups) as $group) {
                if (! $this->variables->supplies($group, $key)) {
                    /* translators: 1: a variable key. 2: the emails that lack it, such as "a form email". */
                    $warnings[$key] = sprintf(__('%1$s is not available in %2$s.', 'corex'), $key, $this->variables->emailLabel($group));
                    break;
                }
            }
        }

        return $warnings;
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
            if (! $this->variables->isKnown($key)) {
                /* translators: %s: a variable key. */
                return ['variables' => sprintf(__('Unknown variable: %s.', 'corex'), $key)];
            }
        }

        return [];
    }

    /**
     * Draws a version as it is sent: its values merged, its partials expanded, arranged by its
     * layout and drawn in the frame, with its plain text.
     *
     * @param array<string,mixed> $context
     * @param string              $kind      What kind of email it is sent as ({@see EmailKind}).
     * @param string|null         $direction `rtl` or `ltr` to draw it in that direction (the
     *                                       preview's right-to-left view), or null for the site's.
     *
     * @return array{subject:string,html:string,plain:string,missing:list<string>}
     *
     * @throws DomainException When the version cannot be drawn; the message names why.
     */
    public function render(
        EmailTemplateVersion $version,
        array $context,
        ?EmailLayout $emailLayout = null,
        string $kind = EmailKind::MESSAGE,
        ?string $direction = null,
    ): array {
        $errors = $this->validateDraft($version);
        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }

        $draw = fn (): array => $this->draw($version, $context, $emailLayout, EmailKind::of($kind), $direction);

        return $this->language === null ? $draw() : $this->language->during($draw);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array{subject:string,html:string,plain:string,missing:list<string>}
     */
    private function draw(EmailTemplateVersion $version, array $context, ?EmailLayout $layout, string $kind, ?string $direction): array
    {
        $missing = [];
        $subject = $this->subjectLine($version->subject, $context, $missing);
        $content = $this->fragment($this->expandPartials($version->htmlBody, true), $context, $missing);
        $regions = $this->regions($layout, $context, $missing);
        $body = str_replace(self::CONTENT, $content, $regions['body']);
        $arranged = $regions['header'] . $body . $regions['button'] . $regions['footer'];

        $plain = $version->plainTextMode === EmailTemplateVersion::PLAIN_AUTO
            ? PlainText::of($arranged) . ($this->frame === null ? '' : "\n\n" . $this->frame->plainFooter($kind))
            : $this->plain($this->expandPartials($version->plainText, false), $context, $missing);

        $html = $this->frame === null
            ? $arranged
            : $this->frame->draw(
                new EmailContent($subject, $arranged, $this->contextValue($context, 'recipient.email'), $kind),
                $regions['accent'],
                $direction,
            );

        return [
            'subject' => $subject,
            'html'    => $html,
            'plain'   => $plain,
            'missing' => array_values(array_unique($missing)),
        ];
    }

    /**
     * A layout's regions, merged: the header and the footer without a line that only says the
     * site's name (the frame says it), the body with the place for the content kept.
     *
     * @param array<string,mixed> $context
     * @param list<string>        $missing
     *
     * @return array{header:string,body:string,button:string,footer:string,accent:string}
     */
    private function regions(?EmailLayout $layout, array $context, array &$missing): array
    {
        $regions = ['header' => '', 'body' => self::CONTENT, 'button' => '', 'footer' => '', 'accent' => ''];
        foreach ($layout?->regions ?? [] as $name => $written) {
            if ($name === 'accent') {
                $regions['accent'] = (string) $written;
                continue;
            }
            if (! array_key_exists($name, $regions)) {
                continue;
            }
            $expanded = $this->expandPartials((string) $written, true);
            if ($name === 'body') {
                $expanded = trim($expanded) === '' ? self::CONTENT : $expanded;
                $parts = explode(self::CONTENT, $expanded);
                $regions['body'] = implode(self::CONTENT, array_map(
                    fn (string $part): string => $this->fragment($part, $context, $missing),
                    $parts,
                ));
                continue;
            }
            $merged = $this->fragment($expanded, $context, $missing);
            $regions[$name] = in_array($name, ['header', 'footer'], true) ? $this->withoutSiteName($merged) : $merged;
        }

        return $regions;
    }

    /**
     * Markup with its values merged: a block whose every value is empty is left out, and each
     * value is escaped.
     *
     * @param array<string,mixed> $context
     * @param list<string>        $missing
     */
    private function fragment(string $html, array $context, array &$missing): string
    {
        $html = preg_replace_callback(
            self::BLOCK,
            function (array $block) use ($context, &$missing): string {
                preg_match_all(self::PLACEHOLDER, $block[0], $keys);
                if (($keys[1] ?? []) === []) {
                    return $block[0];
                }
                foreach ($keys[1] as $key) {
                    if (! $this->isEmpty($context, $key)) {
                        return $block[0];
                    }
                }
                if (! $this->onlyLabels($block[1], $block[0])) {
                    return $block[0];
                }
                $missing = [...$missing, ...$keys[1]];

                return '';
            },
            $html,
        ) ?? $html;

        return $this->merge($html, $context, $missing, function (string $key) use ($context): string {
            return $key === EmailVariableCatalog::ANSWERS
                ? $this->answersHtml($context)
                : self::escaped($this->contextValue($context, $key));
        });
    }

    /**
     * Whether a block whose values are all empty would say nothing but their labels: a table
     * row (a label and its value), or a paragraph, item or heading with no words of its own or
     * only a label ending in a colon ("Phone:"). A sentence with an empty value in it is kept.
     */
    private function onlyLabels(string $tag, string $block): bool
    {
        if (strtolower($tag) === 'tr') {
            return true;
        }
        $words = trim(html_entity_decode(strip_tags((string) preg_replace(self::PLACEHOLDER, '', $block)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $words === '' || str_ends_with($words, ':');
    }

    /**
     * A hand-written plain text, its values as they are: plain text is not markup.
     *
     * @param array<string,mixed> $context
     * @param list<string>        $missing
     */
    private function plain(string $text, array $context, array &$missing): string
    {
        $merged = $this->merge($text, $context, $missing, fn (string $key): string => $key === EmailVariableCatalog::ANSWERS
            ? $this->answersText($context)
            : $this->contextValue($context, $key));

        return trim((string) preg_replace('/[ \t]+\n/u', "\n", $merged));
    }

    /**
     * @param array<string,mixed>     $context
     * @param list<string>            $missing
     * @param Closure(string):string  $written How a value is written where it lands.
     */
    private function merge(string $content, array $context, array &$missing, Closure $written): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER,
            function (array $matches) use ($context, &$missing, $written): string {
                if ($this->isEmpty($context, $matches[1])) {
                    $missing[] = $matches[1];

                    return '';
                }

                return $written($matches[1]);
            },
            $content,
        ) ?? $content;
    }

    /** @param array<string,mixed> $context */
    private function isEmpty(array $context, string $key): bool
    {
        return $key === EmailVariableCatalog::ANSWERS
            ? $this->answers($context) === []
            : trim($this->contextValue($context, $key)) === '';
    }

    /**
     * The answers of a submission, as the context gives them: each a label, a value and, for a
     * file, where it is opened.
     *
     * @param array<string,mixed> $context
     *
     * @return list<array{label:string,value:string,url:string}>
     */
    private function answers(array $context): array
    {
        $answers = [];
        foreach ((array) ($context['form']['answers'] ?? []) as $answer) {
            if (! is_array($answer) || ! is_scalar($answer['label'] ?? null)) {
                continue;
            }
            $url = is_string($answer['url'] ?? null) && preg_match('#^https?://#i', $answer['url']) === 1 ? $answer['url'] : '';
            $answers[] = [
                'label' => (string) $answer['label'],
                'value' => is_scalar($answer['value'] ?? null) ? (string) $answer['value'] : '',
                'url'   => $url,
            ];
        }

        return $answers;
    }

    /** @param array<string,mixed> $context */
    private function answersHtml(array $context): string
    {
        $rows = '';
        foreach ($this->answers($context) as $answer) {
            $value = $answer['url'] === ''
                ? nl2br(self::escaped($answer['value']), false)
                : sprintf('<a href="%s">%s</a>', self::escaped($answer['url']), self::escaped($answer['value']));
            $rows .= sprintf(
                '<tr><th style="padding:6px 16px 6px 0;vertical-align:top;text-align:start;font-weight:600">%s</th>'
                . '<td style="padding:6px 0;vertical-align:top;text-align:start">%s</td></tr>',
                self::escaped($answer['label']),
                $value,
            );
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">' . $rows . '</table>';
    }

    /** @param array<string,mixed> $context */
    private function answersText(array $context): string
    {
        return implode("\n", array_map(
            static fn (array $answer): string => $answer['label'] . ': ' . $answer['value']
                . ($answer['url'] === '' ? '' : ' (' . $answer['url'] . ')'),
            $this->answers($context),
        ));
    }

    /**
     * A header or footer region without a line that only says the site's name: the frame's band
     * and footer say it already, and the shipped header and footer partials say exactly that.
     */
    private function withoutSiteName(string $html): string
    {
        $name = trim($this->frame?->siteName() ?? '');
        if ($name === '') {
            return $html;
        }
        $said = preg_quote(self::escaped($name), '#');
        $html = (string) preg_replace('#<(p|strong|b|span|div|h[1-6])\b[^>]*>\s*' . $said . '\s*</\1>#iu', '', $html);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === $name ? '' : $html;
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
    private function keysIn(string ...$texts): array
    {
        $withoutContent = str_replace(self::CONTENT, '', implode("\n", $texts));
        preg_match_all(self::PLACEHOLDER, $withoutContent, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** Into markup: a value is text there, whatever it holds. */
    private static function escaped(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * The subject as the email carries it: what was written, with each value as it is.
     *
     * A subject is a header and not markup, so nothing is stripped from it, and whatever prints
     * it escapes it there. What a header cannot hold is a line break, and a value handed in (a
     * name somebody typed into a form) can bring one: the merged subject is made one line.
     *
     * @param array<string,mixed> $context
     * @param list<string>        $missing
     */
    private function subjectLine(string $written, array $context, array &$missing): string
    {
        $merged = $this->merge($written, $context, $missing, fn (string $key): string => $this->contextValue($context, $key));

        // A byte that is not UTF-8 costs that byte. Left in, the subject could not be read as
        // text, and the email would leave with none.
        return SubjectLine::of(mb_scrub($merged, 'UTF-8'));
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

    private function expandPartials(string $content, bool $html): string
    {
        if ($this->partials === null) {
            return $content;
        }

        return preg_replace_callback(
            self::PARTIAL,
            function (array $matches) use ($html): string {
                $partial = $this->partials?->find($matches[1]);
                if ($partial === null || $partial->status !== 'active') {
                    throw new DomainException(sprintf(
                        /* translators: %s: an email partial's name, such as "header". */
                        __('The partial "%s" is not available.', 'corex'),
                        $matches[1],
                    ));
                }

                return $html ? $partial->htmlBody : $partial->plainText;
            },
            $content,
        ) ?? $content;
    }
}
