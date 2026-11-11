<?php

/**
 * A value merged into an Email Studio template never makes an address a reader should not follow
 * (spec 120, FR-030): every `href` is http, https, mailto or tel and every `src` http or https,
 * read by WordPress's HTML API after the values are in, whatever shape the markup has.
 *
 * Against WordPress, because the check is WordPress's tag processor: the service is the one the
 * container builds, as every send uses it.
 *
 * @package Corex\Tests\Integration\Email
 */

declare(strict_types=1);

use Corex\Email\Studio\EmailTemplateService;
use Corex\Email\Studio\EmailTemplateVersion;

function mergedVersion(string $html): EmailTemplateVersion
{
    return new EmailTemplateVersion(1, 1, 1, 'Hello', '', '', $html, '', EmailTemplateVersion::PLAIN_AUTO, 1, 1, [], 1, new DateTimeImmutable('now'), hash('sha256', $html));
}

/** The template's own drawing, without the frame around it. */
function mergedDrawn(string $html, string $value): string
{
    $service = Corex\Boot::app()->container()->make(EmailTemplateService::class);
    $drawn = $service->render(mergedVersion($html), ['submission' => ['website' => $value, 'other' => 'https://ok.test/']]);

    return $drawn['html'];
}

it('FR-030: takes the address off a link a merged value makes unsafe, whatever shape its markup has', function (string $html, string $words) {
    $drawn = mergedDrawn($html, 'javascript:alert(1)');

    expect(strtolower($drawn))->not->toContain('javascript:')
        ->and($drawn)->toContain($words);
})->with([
    'quoted' => ['<p>See <a href="{{ submission.website }}">their site</a>.</p>', 'their site'],
    'unquoted' => ['<p><a href={{ submission.website }}>site one</a></p>', 'site one'],
    'never closed' => ['<p><a href="{{ submission.website }}">site two</p>', 'site two'],
    'another attribute first' => ['<p><a data-href="{{ submission.other }}" href="{{ submission.website }}">site three</a></p>', 'site three'],
    'after an unclosed link' => ['<p><a href="https://ok.test">x <a href="{{ submission.website }}">site four</a></p>', 'site four'],
    'an image map area' => ['<map name="m"><area href="{{ submission.website }}" alt="site five"></map>', 'site five'],
    'an image source' => ['<p><img src="{{ submission.website }}" alt="site six"></p>', 'site six'],
]);

it('FR-030: reads an address as a browser does, so case, spaces and control characters do not hide a scheme', function (string $value) {
    $drawn = mergedDrawn('<p><a href="{{ submission.website }}">site</a></p>', $value);

    // The frame's own footer links are CoreX's; the merged one is the link that says "site".
    expect($drawn)->toContain('>site</a>')
        ->and($drawn)->not->toMatch('#<a[^>]*href[^>]*>site</a>#i');
})->with([
    'upper case, spaced' => '  JavaScript:alert(1)',
    'a tab inside the scheme' => "java\tscript:alert(1)",
    'data' => 'data:text/html,<b>x</b>',
    'vbscript' => 'vbscript:msgbox(1)',
    'no scheme at all' => 'acme.test',
]);

it('FR-030: keeps an http, https, mailto or tel address, and an http or https image', function (string $html, string $value, string $kept) {
    expect(mergedDrawn($html, $value))->toContain($kept);
})->with([
    'https' => ['<p><a href="{{ submission.website }}">x</a></p>', 'https://acme.test/a?b=1&c=2', 'href="https://acme.test/a?b=1&amp;c=2"'],
    'http' => ['<p><a href="{{ submission.website }}">x</a></p>', 'http://acme.test', 'href="http://acme.test"'],
    'mailto written' => ['<p><a href="mailto:{{ submission.website }}">x</a></p>', 'jane@example.com', 'href="mailto:jane@example.com"'],
    'tel merged' => ['<p><a href="{{ submission.website }}">x</a></p>', 'tel:+201000000', 'href="tel:+201000000"'],
    'an https image' => ['<p><img src="{{ submission.website }}" alt="x"></p>', 'https://acme.test/a.png', 'src="https://acme.test/a.png"'],
]);
