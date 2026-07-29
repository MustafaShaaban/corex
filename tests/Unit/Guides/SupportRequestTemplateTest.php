<?php

/**
 * The branded support email (spec 093).
 *
 * Two of these pin mistakes made while writing it, which is the only reason they are worth reading:
 *
 * - The template's `subject()` is what `TemplateRenderer` uses whenever a template is named — the
 *   `MailRequest`'s own subject is ignored on that path. A literal here silently threw away the site
 *   name and the category, and the email still arrived, so nothing looked wrong.
 * - The font stack was double-quoted, inside `style="…"`. That closes the attribute. Not a rendering
 *   nuance — a broken tag.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Guides\Support\SupportEmailPalette;
use Corex\Guides\Support\SupportMessage;
use Corex\Guides\Support\SupportRequestTemplate;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
});

function supportMessage(string $message = 'The retention guide stops before the prune step.'): SupportMessage
{
    return new SupportMessage(
        categoryLabel: 'A suggestion',
        message: $message,
        replyTo: 'reader@example.test',
        senderName: 'Reader',
        siteName: 'Example Site',
        siteUrl: 'https://example.test',
    );
}

it('builds its subject from placeholders, not from a literal', function () {
    $subject = (new SupportRequestTemplate())->subject(
        new \Corex\Email\Template\MailContext([]),
    );

    // The renderer merges these; a literal would have discarded the two facts that make one of
    // these triageable in a shared inbox.
    expect($subject)
        ->toContain('{{ support.site_name }}')
        ->toContain('{{ support.category }}');
});

it('supplies every placeholder its own template asks for', function () {
    $template = new SupportRequestTemplate();
    $context = supportMessage()->context();
    $markup = $template->subject(new \Corex\Email\Template\MailContext([]))
        . $template->body(new \Corex\Email\Template\MailContext([]));

    preg_match_all('/\{\{\s*support\.([a-z_]+)\s*\}\}/', $markup, $matches);

    // The pairing is the point: a template placeholder with no context key renders as an empty
    // string, silently. This is the test that would have caught `site_name` being asked for before
    // it was supplied.
    expect(array_unique($matches[1]))->not->toBeEmpty();
    foreach (array_unique($matches[1]) as $key) {
        expect($context['support'])->toHaveKey($key);
    }
});

/**
 * The style attribute has to survive being an attribute. A double-quoted font family closes it, and
 * the result is not a styling difference but malformed markup.
 */
it('never emits a double quote inside a style attribute', function () {
    $body = (new SupportRequestTemplate())->body(new \Corex\Email\Template\MailContext([]));

    preg_match_all('/style="([^"]*)"/', $body, $matches);

    expect($matches[1])->not->toBeEmpty();
    foreach ($matches[1] as $declaration) {
        expect($declaration)->not->toContain('<')->and($declaration)->not->toContain('>');
    }

    // The specific regression: the font stack must use single quotes.
    expect(SupportEmailPalette::FONT)->not->toContain('"');
});

it('carries the CoreX brass rather than the theme brand colour', function () {
    $body = (new SupportRequestTemplate())->body(new \Corex\Email\Template\MailContext([]));

    // #0B1F3B is theme.json's `primary`, which is what Layout injects as the accent. The owner
    // asked for the admin's identity, which is the brass — see SupportEmailPalette's docblock for
    // why that means literals here.
    expect($body)
        ->toContain(SupportEmailPalette::ACTION)
        ->and($body)->not->toContain('#0B1F3B');
});

/**
 * The reader's own line breaks survive without this template ever building markup from their text —
 * the renderer escapes the value, so a `<br>` would have to be assembled from input that is
 * deliberately no longer trusted.
 */
it('preserves the sender line breaks without converting them to markup', function () {
    $body = (new SupportRequestTemplate())->body(new \Corex\Email\Template\MailContext([]));

    expect($body)
        ->toContain('white-space:pre-wrap')
        ->and($body)->not->toContain('nl2br');
});
