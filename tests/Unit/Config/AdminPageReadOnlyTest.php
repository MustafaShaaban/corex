<?php

/**
 * A CoreX screen opened read-only (spec 117, FR-013): the chip, the sentence naming the ability that
 * makes changes, and how to get it. A locked ability ("Access CoreX admin", on Overview and Insights)
 * cannot be given to a role or asked for, so its sentence says only site administrators hold it.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RequestableAbilities;
use Corex\Admin\AdminPage;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_textarea')->returnArg();
    Functions\when('admin_url')->alias(static fn (string $path = ''): string => 'http://example.test/wp-admin/' . $path);
    Functions\when('wp_create_nonce')->alias(static fn (string $action): string => 'nonce-' . $action);

    $this->page = new AdminPage(null, new RequestableAbilities(CorexAbilityCatalog::defaults()));
});

it('draws the chip, the sentence and the request for a screen whose ability can be asked for (FR-013)', function () {
    $html = $this->page->open('settings', 'General', '', 'General', [], readOnly: true);

    expect($html)->toContain('corex-admin__chip--readonly')
        ->and($html)->toContain('data-corex-readonly="corex-readonly-note"')
        ->and($html)->toContain('id="corex-readonly-note"')
        ->and($html)->toContain('Changes need “Manage settings”, which site administrators hold and an owner can give in Access & Abilities.')
        ->and($html)->toContain('name="corex_access_request_nonce"');
});

it('says a locked ability is held by site administrators alone, and offers no request or role matrix (FR-013)', function (string $section) {
    $html = $this->page->open($section, 'Title', '', readOnly: true);

    expect($html)->toContain('Changes need “Access CoreX admin”, which only site administrators hold: it cannot be given to a role or requested.')
        ->and($html)->not->toContain('an owner can give in Access & Abilities')
        ->and($html)->not->toContain('through the role matrix')
        ->and($html)->not->toContain('corex_access_request');
})->with(['overview', 'insights']);

it('draws no chip and no sentence when the screen opens in full', function () {
    $html = $this->page->open('settings', 'General', '', '', []);

    expect($html)->not->toContain('corex-admin__chip--readonly')
        ->and($html)->not->toContain('data-corex-readonly')
        ->and($html)->not->toContain('corex-readonly-note');
});

it('knows which abilities are locked', function () {
    $requestable = new RequestableAbilities(CorexAbilityCatalog::defaults());

    expect($requestable->isLocked('corex_manage_admin'))->toBeTrue()
        ->and($requestable->isLocked('corex_manage_settings'))->toBeFalse()
        ->and($requestable->isLocked('not_an_ability'))->toBeFalse();
});
