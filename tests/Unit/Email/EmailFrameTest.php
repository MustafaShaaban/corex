<?php

/**
 * The one frame every email CoreX draws is in (spec 120, story 3: FR-012 to FR-016).
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailContent;
use Corex\Email\Design\EmailKind;
use Corex\Tests\Support\FixedEmailFrame;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function framed(string $kind, array $links = [], ?EmailBrand $brand = null, string $accent = '', ?string $direction = null, string $privacy = ''): string
{
    return FixedEmailFrame::of($brand ?? EmailBrand::standard('Acme', 'https://acme.test/'), $privacy)->draw(
        new EmailContent('Hello', '<p>Thanks, Sam.</p>', 'sam@example.com', $kind, $links),
        $accent,
        $direction,
    );
}

it('says in the footer why the person receives each kind of email, and nothing for a plain message', function (string $kind, string $line) {
    $html = framed($kind);

    expect($html)->toContain('This message was sent by')
        ->and($line === '' ? ! str_contains($html, 'You receive this email') : str_contains($html, $line))->toBeTrue();
})->with([
    'a reply' => [EmailKind::REPLY, 'You receive this email because you wrote to Acme.'],
    'a notification' => [EmailKind::NOTIFICATION, 'You receive this email because this address receives notifications from Acme.'],
    'a confirmation' => [EmailKind::CONFIRMATION, 'You receive this email because this address was entered on Acme.'],
    'a newsletter' => [EmailKind::NEWSLETTER, 'You receive this email because you subscribed to updates from Acme.'],
    'an account email' => [EmailKind::ACCOUNT, 'You receive this email because of an account on Acme.'],
    'a message' => [EmailKind::MESSAGE, ''],
]);

it('links the footer to what the email gives, and to the site\'s privacy page where it has one', function () {
    $html = framed(EmailKind::NEWSLETTER, [['label' => 'Unsubscribe', 'url' => 'https://acme.test/?unsubscribe=1&t=x']], privacy: 'https://acme.test/privacy/');

    expect($html)->toMatch('#href="https://acme.test/\?unsubscribe=1&amp;t=x">Unsubscribe</a> &middot; <a [^>]*href="https://acme.test/privacy/">Privacy</a>#');
});

it('links to nothing that is not a web address, whatever it is handed', function () {
    $html = framed(EmailKind::NEWSLETTER, [
        ['label' => 'Run', 'url' => 'javascript:alert(1)'],
        ['label' => '', 'url' => 'https://acme.test/'],
    ]);

    expect($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('&middot;');
});

it('draws the card\'s edge in a layout\'s own accent, and the site\'s otherwise', function () {
    $brand = EmailBrand::standard('Acme', 'https://acme.test/');

    expect(framed(EmailKind::MESSAGE, [], $brand, '#AbCdEf'))->toContain('border-top:4px solid #abcdef')
        ->and(framed(EmailKind::MESSAGE, [], $brand, 'red'))->toContain('border-top:4px solid ' . $brand->light->accent)
        ->and(framed(EmailKind::MESSAGE, [], $brand))->toContain('border-top:4px solid ' . $brand->light->accent);
});

it('draws right to left when asked, whatever the site reads in', function () {
    expect(framed(EmailKind::MESSAGE, [], null, '', 'rtl'))->toContain('dir="rtl"')->toContain('text-align:right')
        ->and(framed(EmailKind::MESSAGE))->toContain('dir="ltr"');
});

it('shows the logo, and the name where there is none', function () {
    $withLogo = EmailBrand::standard('Acme', 'https://acme.test/', 'https://acme.test/logo.png');

    expect(framed(EmailKind::MESSAGE, [], $withLogo))->toContain('<img src="https://acme.test/logo.png"')
        ->and(framed(EmailKind::MESSAGE))->not->toContain('<img');
});

it('ends a plain-text version with the footer and why the person receives the email', function () {
    $frame = FixedEmailFrame::named('Acme');

    expect($frame->plainFooter(EmailKind::CONFIRMATION))->toBe(
        "-- \nThis message was sent by Acme (https://acme.test/).\n"
        . 'You receive this email because this address was entered on Acme.',
    )
        ->and($frame->plainFooter(EmailKind::MESSAGE))->toBe("-- \nThis message was sent by Acme (https://acme.test/).");
});

it('styles a template\'s headings and buttons, a button keeping its contrast in both schemes', function () {
    $html = FixedEmailFrame::named()->draw(new EmailContent(
        'Hello',
        '<h1>Welcome</h1><p><a class="button" href="https://acme.test/go">Go</a></p>',
        '',
        EmailKind::MESSAGE,
    ));

    expect($html)->toMatch('#<h1 dir="auto" class="cx-text" style="[^"]*font-size:24px#')
        ->and($html)->toMatch('#<a class="cx-button" style="[^"]*background:\#14151a;color:\#ffffff#')
        ->and($html)->toContain('.cx-button{background:#f4f5f7 !important;color:#1c1f26 !important}');
});
