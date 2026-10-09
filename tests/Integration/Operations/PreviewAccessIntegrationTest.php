<?php

/**
 * Integration tests for the preview link against a real WordPress (spec 101, T046).
 *
 * PreviewAccessTest proves the rules against a store held in memory. This proves the three things
 * that only a real install can: that the option-backed store keeps a record the way the rules need
 * and does not autoload it; that leaving the mode through the real service empties it; and that
 * the guard, given a real request, turns a link into access and access into the real site —
 * and into nothing more than that.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\ComingSoonDecision;
use Corex\Config\Operations\ComingSoonGuard;
use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OptionPreviewAccessStore;
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\PreviewAccessStore;
use Corex\Tests\Support\AdminScreen;

function corexPreviewAccess(): PreviewAccess
{
    return Boot::app()->container()->make(PreviewAccess::class);
}

/**
 * @param array<string,string> $queryVars
 */
function corexPreviewVisit(string $path, array $queryVars = []): ComingSoonDecision
{
    $_SERVER['REQUEST_URI']    = (string) parse_url(home_url($path), PHP_URL_PATH)
        . (str_contains($path, '?') ? '?' . explode('?', $path, 2)[1] : '');
    $GLOBALS['wp']->query_vars = $queryVars;

    return Boot::app()->container()->make(ComingSoonGuard::class)->decision();
}

beforeEach(function () {
    $this->savedMode   = get_option('corex_operations_mode', null);
    $this->savedLog    = get_option('corex_operations_mode_log', null);
    $this->savedAccess = get_option(OptionPreviewAccessStore::OPTION, null);
    $this->savedUri    = $_SERVER['REQUEST_URI'] ?? null;
    $this->savedVars   = $GLOBALS['wp']->query_vars;
    $this->savedScreen = $GLOBALS['current_screen'] ?? null;
    $this->now         = new DateTimeImmutable('now');

    delete_option(OptionPreviewAccessStore::OPTION);
    update_option('corex_operations_mode', OperationsMode::COMING_SOON);
    wp_set_current_user(0);
    unset($GLOBALS['current_screen'], $_GET[PreviewAccess::PARAMETER], $_COOKIE[PreviewAccess::COOKIE]);
});

afterEach(function () {
    wp_set_current_user(0);
    unset($_GET[PreviewAccess::PARAMETER], $_COOKIE[PreviewAccess::COOKIE]);

    foreach ([
        'corex_operations_mode'          => $this->savedMode,
        'corex_operations_mode_log'      => $this->savedLog,
        OptionPreviewAccessStore::OPTION => $this->savedAccess,
    ] as $option => $saved) {
        if ($saved === null) {
            delete_option($option);
        } else {
            update_option($option, $saved, false);
        }
    }

    if ($this->savedUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $this->savedUri;
    }
    $GLOBALS['wp']->query_vars = $this->savedVars;

    if ($this->savedScreen === null) {
        unset($GLOBALS['current_screen']);
    } else {
        $GLOBALS['current_screen'] = $this->savedScreen;
    }
});

// The option-backed store.

it('keeps the record in an option that is not loaded on every request', function () {
    global $wpdb;
    $store = Boot::app()->container()->make(PreviewAccessStore::class);

    $store->write(str_repeat('a', 64), 1791000000, 7);

    $autoload = $wpdb->get_var($wpdb->prepare(
        "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
        OptionPreviewAccessStore::OPTION,
    ));

    expect($store)->toBeInstanceOf(OptionPreviewAccessStore::class)
        ->and($store->read())->toBe(['hash' => str_repeat('a', 64), 'issued' => 1791000000, 'user' => 7])
        // Read on a front-end request only when a preview value or cookie is present, so it has
        // no business in the autoloaded set every request pays for.
        ->and(in_array($autoload, ['off', 'no', 'auto-off'], true))->toBeTrue();
});

it('reads nothing when there is no record, and nothing from one that is not a record', function (mixed $stored) {
    $store = Boot::app()->container()->make(PreviewAccessStore::class);
    if ($stored !== null) {
        update_option(OptionPreviewAccessStore::OPTION, $stored, false);
    }

    expect($store->read())->toBeNull();
})->with([
    'no option'           => [null],
    'a string'            => ['not-a-record'],
    'an array with no hash' => [['issued' => 1, 'user' => 1]],
    'a hash that is not one' => [['hash' => 'short', 'issued' => 1, 'user' => 1]],
]);

it('removes the record when it is deleted', function () {
    $store = Boot::app()->container()->make(PreviewAccessStore::class);
    $store->write(str_repeat('a', 64), 1791000000, 7);

    $store->delete();

    expect($store->read())->toBeNull()
        ->and(get_option(OptionPreviewAccessStore::OPTION, 'gone'))->toBe('gone');
});

it('keeps only a keyed hash in the database, never the link', function () {
    global $wpdb;
    $token = corexPreviewAccess()->create(1, $this->now);

    $row = (string) $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        OptionPreviewAccessStore::OPTION,
    ));

    expect($token)->toBeString()
        ->and($row)->not->toBe('')
        ->and($row)->not->toContain($token)
        ->and(corexPreviewAccess()->accepts($token))->toBeTrue();
});

// Leaving the mode.

it('empties the store when the site leaves Coming soon through the real service', function () {
    $token = corexPreviewAccess()->create(1, $this->now);
    $grant = corexPreviewAccess()->grant($token, $this->now);

    Boot::app()->container()->make(ModeChangeService::class)->apply(new ModeChangeRequest(
        mode: OperationsMode::STAGING,
        actorId: 1,
        now: $this->now,
    ));

    expect(get_option('corex_operations_mode'))->toBe(OperationsMode::STAGING)
        ->and(get_option(OptionPreviewAccessStore::OPTION, 'gone'))->toBe('gone')
        ->and(corexPreviewAccess()->honours($grant, $this->now))->toBeFalse();
});

// The guard: a link becomes access, and access becomes the real site.

it('claims a valid link, at the home URL and at any other address', function (string $path, array $queryVars) {
    $token = corexPreviewAccess()->create(1, $this->now);
    $_GET[PreviewAccess::PARAMETER] = $token;

    $decision = corexPreviewVisit($path . '?' . PreviewAccess::PARAMETER . '=' . $token, $queryVars);

    expect($decision->outcome)->toBe(ComingSoonDecision::CLAIM)
        ->and($decision->noCache)->toBeTrue();
})->with([
    'the home URL' => ['/', []],
    'a page'       => ['/about/', ['pagename' => 'about']],
]);

it('treats a wrong, old or revoked link exactly as no link', function (string $which) {
    $old   = corexPreviewAccess()->create(1, $this->now);
    $value = match ($which) {
        'wrong'   => 'not-the-link',
        'old'     => (function () use ($old) {
            corexPreviewAccess()->regenerate(1, $this->now);

            return $old;
        })(),
        'revoked' => (function () use ($old) {
            corexPreviewAccess()->revoke();

            return $old;
        })(),
    };
    $_GET[PreviewAccess::PARAMETER] = $value;

    $withTheValue = corexPreviewVisit('/about/?' . PreviewAccess::PARAMETER . '=' . $value, ['pagename' => 'about']);
    unset($_GET[PreviewAccess::PARAMETER]);
    $withoutIt = corexPreviewVisit('/about/', ['pagename' => 'about']);

    // FR-014: the same outcome and the same cacheability. Nothing tells the two apart.
    expect($withTheValue->outcome)->toBe($withoutIt->outcome)
        ->and($withTheValue->outcome)->toBe(ComingSoonDecision::REDIRECT)
        ->and($withTheValue->noCache)->toBe($withoutIt->noCache)
        ->and($withTheValue->bar)->toBe($withoutIt->bar);
})->with(['wrong', 'old', 'revoked']);

it('serves the real site, with the preview banner, to a browser that holds a grant', function () {
    $token = corexPreviewAccess()->create(1, $this->now);
    $_COOKIE[PreviewAccess::COOKIE] = corexPreviewAccess()->grant($token, $this->now);

    $home      = corexPreviewVisit('/');
    $elsewhere = corexPreviewVisit('/about/', ['pagename' => 'about']);

    expect($home->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($elsewhere->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($elsewhere->noCache)->toBeTrue()
        ->and($elsewhere->bar)->toBe(ComingSoonDecision::BAR_PREVIEW);
});

it('ends that browser\'s access at its next request once the link is regenerated or revoked', function (string $how) {
    $token = corexPreviewAccess()->create(1, $this->now);
    $_COOKIE[PreviewAccess::COOKIE] = corexPreviewAccess()->grant($token, $this->now);
    $before = corexPreviewVisit('/about/', ['pagename' => 'about'])->outcome;

    $how === 'regenerated' ? corexPreviewAccess()->regenerate(1, $this->now) : corexPreviewAccess()->revoke();

    expect($before)->toBe(ComingSoonDecision::PASS)
        ->and(corexPreviewVisit('/about/', ['pagename' => 'about'])->outcome)->toBe(ComingSoonDecision::REDIRECT);
})->with(['regenerated', 'revoked']);

it('gives a grant no effect in any other mode', function () {
    $token = corexPreviewAccess()->create(1, $this->now);
    $_COOKIE[PreviewAccess::COOKIE] = corexPreviewAccess()->grant($token, $this->now);
    update_option('corex_operations_mode', OperationsMode::PRODUCTION);

    $decision = corexPreviewVisit('/about/', ['pagename' => 'about']);

    expect($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->noCache)->toBeFalse()
        ->and($decision->bar)->toBe(ComingSoonDecision::BAR_NONE);
});

it('gives a browser with preview access nothing in the admin that a stranger does not have', function () {
    // US4.4, FR-013. The grant is a fact about a front-end request and is no capability: WordPress
    // sees no signed-in user, and it is WordPress that guards the admin.
    $token = corexPreviewAccess()->create(1, $this->now);
    $_COOKIE[PreviewAccess::COOKIE] = corexPreviewAccess()->grant($token, $this->now);
    AdminScreen::set('dashboard');

    $decision = corexPreviewVisit('/wp-admin/');

    expect(is_user_logged_in())->toBeFalse()
        ->and(current_user_can('read'))->toBeFalse()
        ->and(current_user_can('edit_posts'))->toBeFalse()
        // The mode stands aside in the admin, for everybody, and decides nothing there.
        ->and($decision->outcome)->toBe(ComingSoonDecision::PASS)
        ->and($decision->bar)->toBe(ComingSoonDecision::BAR_NONE);
});
