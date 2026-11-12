<?php

/**
 * A link in a template is only drawn where it goes somewhere a reader may follow (spec 120).
 *
 * FR-012 and DECISIONS #450.4: a footer link the site does not give (the newsletter's
 * "Preferences" without `corex_newsletter_preferences_url`) is left out with its label, in the HTML
 * and in the plain text, never drawn as `<a href="">Preferences</a>` or `Preferences ()`.
 * FR-028: the variable it lacked is named. FR-030 (a merged address is checked) is proven against
 * WordPress's HTML API in `tests/Integration/Email/MergedAddressTest.php`.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Studio\EmailLayout;
use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;
use Corex\Email\Studio\EmailVariableCatalog;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
});

function linksVersion(string $html, string $plain = '', string $mode = EmailTemplateVersion::PLAIN_AUTO): EmailTemplateVersion
{
    return new EmailTemplateVersion(
        id: 1,
        templateId: 1,
        versionNumber: 1,
        subject: 'Hello',
        fromName: '',
        fromAddress: '',
        htmlBody: $html,
        plainText: $plain,
        plainTextMode: $mode,
        layoutId: 1,
        layoutVersion: 1,
        variableKeys: [],
        createdBy: 1,
        createdAt: new DateTimeImmutable('2026-11-11'),
        checksum: hash('sha256', $html . $plain),
    );
}

function linksNewsletterLayout(): EmailLayout
{
    // The shipped newsletter footer, its partials expanded as they ship.
    return new EmailLayout(1, 'newsletter', 'Newsletter', 1, [
        'header' => '', 'accent' => '', 'body' => '{{ content }}', 'button' => '',
        'footer' => '<p><a href="{{ links.unsubscribe }}">Unsubscribe</a></p><p><a href="{{ links.preferences }}">Preferences</a></p>',
    ], null, 1, new DateTimeImmutable('2026-11-11'));
}

function linksService(): EmailTemplateService
{
    return new EmailTemplateService(new EmailVariableCatalog([]));
}

it('FR-012: leaves out a link and its label when the address it needs is empty, in the HTML and the plain text', function () {
    $drawn = linksService()->render(
        linksVersion('<p>Welcome.</p>'),
        ['links' => ['unsubscribe' => 'https://acme.test/unsubscribe?t=1', 'preferences' => '']],
        linksNewsletterLayout(),
    );

    expect($drawn['html'])->toContain('<a href="https://acme.test/unsubscribe?t=1">Unsubscribe</a>')
        ->and($drawn['html'])->not->toContain('Preferences')
        ->and($drawn['html'])->not->toContain('href=""')
        ->and($drawn['html'])->not->toContain('<p></p>')
        ->and($drawn['plain'])->toContain('Unsubscribe (https://acme.test/unsubscribe?t=1)')
        ->and($drawn['plain'])->not->toContain('Preferences')
        ->and($drawn['missing'])->toBe(['links.preferences']);
});

it('FR-012: keeps the link when the site gives its address', function () {
    $drawn = linksService()->render(
        linksVersion('<p>Welcome.</p>'),
        ['links' => ['unsubscribe' => 'https://acme.test/u', 'preferences' => 'https://acme.test/prefs']],
        linksNewsletterLayout(),
    );

    expect($drawn['html'])->toContain('<a href="https://acme.test/prefs">Preferences</a>')
        ->and($drawn['plain'])->toContain('Preferences (https://acme.test/prefs)')
        ->and($drawn['missing'])->toBe([]);
});

it('FR-012: leaves out a mailto link whose only variable is empty, and keeps one inside a sentence as words', function () {
    $drawn = linksService()->render(
        linksVersion('<p><a href="mailto:{{ submission.email }}">Write back</a></p><p>Or call <a href="tel:{{ submission.phone }}">us</a> today.</p>'),
        [],
    );

    expect($drawn['html'])->not->toContain('Write back')
        ->and($drawn['html'])->not->toContain('mailto:')
        ->and($drawn['html'])->toContain('<p>Or call  today.</p>')
        ->and($drawn['missing'])->toBe(['submission.email', 'submission.phone']);
});

it('FR-012: leaves out a hand-written plain-text line that is only a label for an empty address', function () {
    $drawn = linksService()->render(
        linksVersion('<p>x</p>', "Welcome.\nUnsubscribe: {{ links.unsubscribe }}\nPreferences: {{ links.preferences }}", EmailTemplateVersion::PLAIN_MANUAL),
        ['links' => ['unsubscribe' => 'https://acme.test/u']],
    );

    expect($drawn['plain'])->toBe("Welcome.\nUnsubscribe: https://acme.test/u")
        ->and($drawn['missing'])->toBe(['links.preferences']);
});

it('FR-012: leaves the spacer cells and empty blocks of a layout alone when a link is left out', function () {
    $html = '<table><tr><td style="width:16px"></td><td>Hi</td></tr></table><div style="height:24px"></div>'
        . '<p><a href="{{ links.preferences }}">Preferences</a></p>';

    $without = linksService()->render(linksVersion($html), ['links' => ['preferences' => '']]);
    $with = linksService()->render(linksVersion($html), ['links' => ['preferences' => 'https://acme.test/prefs']]);

    expect($without['html'])->toContain('<td style="width:16px"></td>')
        ->and($without['html'])->toContain('<div style="height:24px"></div>')
        ->and($without['html'])->not->toContain('Preferences')
        ->and($with['html'])->toContain('<td style="width:16px"></td>');
});

it('FR-012: empties a table cell whose only link is left out, and keeps the cell', function () {
    $drawn = linksService()->render(
        linksVersion('<table><tr><td><a href="{{ links.preferences }}">Preferences</a></td><td>Hi</td></tr></table>'),
        [],
    );

    expect($drawn['html'])->toMatch('#<tr><td>\s*</td><td>Hi</td></tr>#')
        ->and($drawn['html'])->not->toContain('Preferences');
});
