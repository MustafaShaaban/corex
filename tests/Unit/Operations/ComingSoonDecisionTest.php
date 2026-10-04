<?php

/**
 * Unit tests for what one request gets while the site is in Coming soon (spec 101, T017).
 *
 * The plan states the whole behaviour as a table of eleven rows, tried in order, first match wins.
 * `ComingSoonDecision` is that table as code, so this file is that table as tests: one case per
 * row, and one for each pair of rows whose order changes the answer. It reads no WordPress — the
 * facts arrive in a `ComingSoonRequest` — which is what lets every row be stated here.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\ComingSoonDecision;
use Corex\Config\Operations\ComingSoonRequest;
use Corex\Config\Operations\OperationsMode;

/**
 * A request while the mode is on. Every fact defaults to "no", so a bare call is an anonymous
 * visitor asking for an address that is not the home URL.
 */
function comingSoonRequest(bool ...$facts): ComingSoonRequest
{
    return new ComingSoonRequest(OperationsMode::COMING_SOON, ...$facts);
}

// Row 1 — the mode is not Coming soon.
it('passes every request, untouched, in any other mode', function (string $mode) {
    // Every fact that would otherwise decide something is set, to show that none of them is read.
    $decision = ComingSoonDecision::for(new ComingSoonRequest(
        $mode,
        carriesValidPreview: true,
        asksForVisitorView: true,
        canEditPosts: true,
        isSitemap: true,
        isHome: true,
    ));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse();
})->with([
    OperationsMode::DEVELOPMENT,
    OperationsMode::STAGING,
    OperationsMode::PRODUCTION,
    OperationsMode::MAINTENANCE,
]);

// Row 2 — admin, login, REST, AJAX or cron.
it('never intercepts the admin, the login route, REST, AJAX or cron', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(neverIntercepted: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse();
});

it('does not claim a preview link inside a context it never intercepts', function () {
    // Rows 2 and 3 in that order: a preview value on an admin address is not a front-end claim.
    $decision = ComingSoonDecision::for(comingSoonRequest(neverIntercepted: true, carriesValidPreview: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS);
});

// Row 3 — carries a preview value.
it('claims a valid preview value, and marks the answer non-cacheable', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(carriesValidPreview: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::CLAIM)
        ->and($decision->noCache)->toBeTrue();
});

it('claims a valid preview value at the home URL too, rather than serving the page', function () {
    // Rows 3 and 10: the link is usually the home URL with the value on it.
    $decision = ComingSoonDecision::for(comingSoonRequest(carriesValidPreview: true, isHome: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::CLAIM);
});

it('treats an invalid preview value exactly as if it were absent', function () {
    // The request carries no fact for an invalid value at all — which is the point (FR-014): there
    // is nothing for the decision to answer differently with.
    $elsewhere = ComingSoonDecision::for(comingSoonRequest(carriesValidPreview: false));
    $home      = ComingSoonDecision::for(comingSoonRequest(carriesValidPreview: false, isHome: true));

    expect($elsewhere->outcome)->toBe(ComingSoonDecision::REDIRECT)
        ->and($home->outcome)->toBe(ComingSoonDecision::SERVE);
});

// Row 4 — client code allowed it through.
it('passes a request that client code allowed through', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(allowedByClient: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse();
});

it('passes an allowed request before the home rule can serve the page over it', function () {
    // Rows 4 and 10. The extension point is the client's word on a request; the home URL is no
    // exception to it.
    $decision = ComingSoonDecision::for(comingSoonRequest(allowedByClient: true, isHome: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS);
});

// Row 5 — robots.txt and the favicon.
it('passes robots.txt and the favicon for everybody', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(isRobotsOrFavicon: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse();
});

// Row 6 — signed in, can edit posts, asking for the visitor view.
it('serves the page to an editor who asks to see it as a visitor, at any address', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, asksForVisitorView: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::SERVE)
        // Non-cacheable: what this address answers depends on who asked and how.
        ->and($decision->noCache)->toBeTrue();
});

it('gives the visitor view before the pass an editor would otherwise get', function () {
    // Rows 6 and 7 in that order. Reversed, the visitor view could never be reached.
    $asking  = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, asksForVisitorView: true, isHome: true));
    $working = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, isHome: true));

    expect($asking->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and($working->outcome)->toBe(ComingSoonDecision::PASS);
});

it('gives nothing to somebody who asks for the visitor view and cannot edit posts', function () {
    // The visitor view is a courtesy to people who pass. Asking for it is not a way in.
    $decision = ComingSoonDecision::for(comingSoonRequest(asksForVisitorView: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::REDIRECT);
});

// Row 7 — signed in and can edit posts.
it('passes a signed-in user who can edit posts, at home and everywhere else', function (bool $isHome) {
    $decision = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, isHome: $isHome));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        // The real site at an address a visitor gets the page or a redirect from: a cache must
        // not hand this response to anybody else (FR-015).
        ->and($decision->noCache)->toBeTrue();
})->with(['home' => true, 'elsewhere' => false]);

// Row 8 — holds valid preview access.
it('passes a preview holder rather than redirecting them', function (bool $isHome) {
    $decision = ComingSoonDecision::for(comingSoonRequest(holdsPreviewAccess: true, isHome: $isHome));

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeTrue();
})->with(['home' => true, 'elsewhere' => false]);

// Row 9 — the sitemap address.
it('answers the sitemap address with the one-URL sitemap, and lets it be cached', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(isSitemap: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::SITEMAP)
        ->and($decision->noCache)->toBeFalse();
});

it('answers the sitemap before the home rule, for a site whose sitemap address is the home path', function () {
    // Rows 9 and 10. With plain permalinks the sitemap is `/?sitemap=index`: the home path, with a
    // query string. Home first would serve the page to a crawler that asked for the sitemap.
    $decision = ComingSoonDecision::for(comingSoonRequest(isSitemap: true, isHome: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::SITEMAP);
});

// Row 10 — the home URL.
it('serves the coming-soon page at the home URL, and lets it be cached', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest(isHome: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::SERVE)
        // The same for every anonymous visitor, so a page cache in front of the site may keep it.
        ->and($decision->noCache)->toBeFalse();
});

// Row 11 — anything else.
it('redirects every other address home, and marks the redirect non-cacheable', function () {
    $decision = ComingSoonDecision::for(comingSoonRequest());

    expect($decision->outcome)->toBe(ComingSoonDecision::REDIRECT)
        // A cached redirect would outlive the mode: the address would keep bouncing after launch.
        ->and($decision->noCache)->toBeTrue();
});

it('treats a signed-in user who cannot edit posts exactly as an anonymous visitor', function () {
    // FR-007. Being signed in is not a fact the request carries at all: only "can edit posts" is,
    // so a subscriber and a stranger are the same request and cannot be told apart here.
    $subscriberElsewhere = ComingSoonDecision::for(comingSoonRequest(canEditPosts: false));
    $subscriberAtHome    = ComingSoonDecision::for(comingSoonRequest(canEditPosts: false, isHome: true));

    expect($subscriberElsewhere->outcome)->toBe(ComingSoonDecision::REDIRECT)
        ->and($subscriberAtHome->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and($subscriberAtHome->noCache)->toBeFalse();
});

// The bar — what a response that passes carries (spec 101, FR-016).

it('puts the notice on the real site served to somebody who can edit posts', function (bool $isHome) {
    $decision = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, isHome: $isHome));

    expect($decision->bar)->toBe(ComingSoonDecision::BAR_NOTICE);
})->with(['home' => true, 'elsewhere' => false]);

it('puts no notice on the visitor view, which is exactly what a visitor is served', function () {
    // US3.3. A bar on it would be the one thing on the page a visitor never sees.
    $decision = ComingSoonDecision::for(comingSoonRequest(canEditPosts: true, asksForVisitorView: true));

    expect($decision->outcome)->toBe(ComingSoonDecision::SERVE)
        ->and($decision->bar)->toBe(ComingSoonDecision::BAR_NONE);
});

it('puts no notice on anything served to a visitor, or passed for any other reason', function (ComingSoonRequest $request) {
    expect(ComingSoonDecision::for($request)->bar)->toBe(ComingSoonDecision::BAR_NONE);
})->with([
    'the page at home'          => [fn () => comingSoonRequest(isHome: true)],
    'a redirect'                => [fn () => comingSoonRequest()],
    'the sitemap'               => [fn () => comingSoonRequest(isSitemap: true)],
    'robots.txt'                => [fn () => comingSoonRequest(isRobotsOrFavicon: true)],
    'a request the client let through' => [fn () => comingSoonRequest(allowedByClient: true)],
    // Not even for an editor: the admin has its own toolbar node, and the bar is a front-end thing.
    'the admin, for an editor'  => [fn () => comingSoonRequest(neverIntercepted: true, canEditPosts: true)],
    'another mode, for an editor' => [fn () => new ComingSoonRequest(OperationsMode::PRODUCTION, canEditPosts: true)],
]);
