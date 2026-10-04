<?php

/**
 * Unit tests for the bar that tells somebody served the real site that visitors are not (spec 101,
 * T031, FR-016). Headless: what the bar says and links to, for whom, and that it is absent for
 * anybody who is not served the real site. That it is printed on a real page, and only there, is
 * ComingSoonNoticeIntegrationTest.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\StandalonePage;
use Corex\Config\Operations\ComingSoonDecision;
use Corex\Config\Operations\ComingSoonGuard;
use Corex\Config\Operations\ComingSoonNotice;
use Corex\Config\Operations\ComingSoonRequest;
use Corex\Config\Operations\ComingSoonSitemap;
use Corex\Config\Operations\ComingSoonTemplate;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

function comingSoonNotice(): ComingSoonNotice
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('home_url')->alias(static fn (string $path = ''): string => 'https://example.test' . $path);
    Functions\when('admin_url')->alias(static fn (string $path = ''): string => 'https://example.test/wp-admin/' . $path);
    Functions\when('add_query_arg')->alias(
        static fn (array $args, string $url): string => $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($args),
    );

    $store = new OperationsModeStore(new OperationsMode());

    return new ComingSoonNotice(
        new ComingSoonGuard($store, new ComingSoonTemplate(new StandalonePage('', '')), new ComingSoonSitemap()),
        $store,
    );
}

/** The decision for somebody who can edit posts, browsing the real site. */
function comingSoonPassedDecision(): ComingSoonDecision
{
    return ComingSoonDecision::for(new ComingSoonRequest(OperationsMode::COMING_SOON, canEditPosts: true));
}

/**
 * @return list<array{href:string,text:string}>
 */
function comingSoonLinks(string $html): array
{
    preg_match_all('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $found, PREG_SET_ORDER);

    return array_map(static fn (array $link): array => ['href' => $link[1], 'text' => trim(strip_tags($link[2]))], $found);
}

it('tells an administrator that visitors see the coming-soon page, and links to both places', function () {
    $html  = comingSoonNotice()->html(comingSoonPassedDecision(), canChangeMode: true);
    $links = comingSoonLinks($html);

    expect($html)->toContain('Coming soon is on')
        ->and($html)->toContain('Visitors see the coming-soon page')
        ->and($links)->toHaveCount(2)
        // The way to see what a visitor sees (FR-016).
        ->and($links[0]['href'])->toBe('https://example.test/?corex_visitor_view=1')
        // The screen that changes it — offered because this user can change it.
        ->and($links[1]['href'])->toBe('https://example.test/wp-admin/admin.php?page=corex-operations-security&tab=environment');
});

it('gives a user who cannot change the mode the visitor view and no link to a screen they cannot open', function () {
    // US3.4: an editor passes, is told, and is not sent to a page that would refuse them.
    $html  = comingSoonNotice()->html(comingSoonPassedDecision(), canChangeMode: false);
    $links = comingSoonLinks($html);

    expect($html)->toContain('Coming soon is on')
        ->and($links)->toHaveCount(1)
        ->and($links[0]['href'])->toBe('https://example.test/?corex_visitor_view=1')
        ->and($html)->not->toContain('wp-admin')
        ->and($html)->not->toContain('Operations');
});

it('is absent for anybody who is not served the real site', function (ComingSoonRequest $request) {
    // Whether the user could change the mode makes no difference: no bar was decided.
    expect(comingSoonNotice()->html(ComingSoonDecision::for($request), canChangeMode: true))->toBe('');
})->with([
    'a visitor at the home URL' => [fn () => new ComingSoonRequest(OperationsMode::COMING_SOON, isHome: true)],
    'a visitor being redirected' => [fn () => new ComingSoonRequest(OperationsMode::COMING_SOON)],
    'the visitor view'          => [fn () => new ComingSoonRequest(OperationsMode::COMING_SOON, canEditPosts: true, asksForVisitorView: true)],
    'an editor, in another mode' => [fn () => new ComingSoonRequest(OperationsMode::PRODUCTION, canEditPosts: true)],
]);

it('cannot be dismissed', function () {
    // FR-016 says persistent. A bar somebody closed in the first week is a bar nobody sees in the
    // sixth, when the site is still closed and they have forgotten why the client cannot see it.
    $html = comingSoonNotice()->html(comingSoonPassedDecision(), canChangeMode: true);

    expect($html)->not->toContain('<button')
        ->and(strtolower($html))->not->toContain('dismiss')
        ->and(strtolower($html))->not->toContain('close');
});

it('is a named landmark whose links each say where they go', function () {
    $html  = comingSoonNotice()->html(comingSoonPassedDecision(), canChangeMode: true);
    $links = comingSoonLinks($html);

    // An aside is a landmark only if it can be told from the page's other asides; the label is
    // what a screen-reader user hears when they land on it.
    expect($html)->toMatch('/^<aside [^>]*aria-label="[^"]+"/')
        ->and($links[0]['text'])->not->toBe('')
        ->and($links[1]['text'])->not->toBe('')
        ->and($links[0]['text'])->not->toBe($links[1]['text'])
        // Not "click here": the name alone has to say where the link goes (WCAG 2.4.4).
        ->and($links[0]['text'])->toContain('coming-soon page')
        ->and($links[1]['text'])->toContain('Operations');
});

it('carries the class the CoreX tokens are defined on, and none of its own colours', function () {
    $html = comingSoonNotice()->html(comingSoonPassedDecision(), canChangeMode: true);

    expect($html)->toMatch('/^<aside class="corex-admin corex-coming-soon-bar"/')
        ->and($html)->not->toContain('style=');
});
