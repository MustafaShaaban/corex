<?php

/**
 * The screen's sections, and the environment/mode conflict (spec 077, T006/T020 / FR-001/014).
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\OperationsSecurityScreen;

/**
 * Snapshot and restore, never delete: the integration suite runs against a real developer install
 * and this option is the site's declared operating state.
 */
beforeEach(function () {
    $this->savedMode = get_option('corex_operations_mode', null);
    $this->screen    = \Corex\Boot::app()->container()->make(OperationsSecurityScreen::class);
});

afterEach(function () {
    if ($this->savedMode === null) {
        delete_option('corex_operations_mode');

        return;
    }

    update_option('corex_operations_mode', $this->savedMode, false);
});

/** Reach a private renderer without widening the production API to suit a test. */
function invokeScreen(object $screen, string $method, mixed ...$args): string
{
    $reflected = new ReflectionMethod($screen, $method);
    $reflected->setAccessible(true);

    return (string) $reflected->invoke($screen, ...$args);
}

it('offers every section and no others', function () {
    $sections = new ReflectionMethod($this->screen, 'sections');
    $sections->setAccessible(true);

    expect(array_keys($sections->invoke($this->screen)))->toBe([
        'overview',
        'environment',
        'login',
        'hardening',
        'activity',
    ]);
});

it('falls back to the overview for a section that does not exist', function () {
    $active = new ReflectionMethod($this->screen, 'activeSection');
    $active->setAccessible(true);

    $_GET['tab'] = 'not-a-section';
    expect($active->invoke($this->screen))->toBe('overview');

    $_GET['tab'] = 'login';
    expect($active->invoke($this->screen))->toBe('login');

    unset($_GET['tab']);
    expect($active->invoke($this->screen))->toBe('overview');
});

it('says so when the declared mode and the environment disagree', function () {
    // `wp_get_environment_type()` reports 'production' on this install unless WP_ENVIRONMENT_TYPE
    // says otherwise, so declaring development is a real conflict.
    update_option('corex_operations_mode', 'development', false);

    $notice = invokeScreen($this->screen, 'environmentConflictNotice');
    $environment = wp_get_environment_type();

    if ($environment === 'development') {
        // The host declares development too, so there is nothing to warn about — and the test
        // must not pretend otherwise.
        expect($notice)->toBe('');

        return;
    }

    expect($notice)->not->toBe('')
        ->and($notice)->toContain($environment)
        ->and($notice)->toContain('development')
        // It must never suggest CoreX can change the environment.
        ->and($notice)->toContain('does not change the environment');
});

it('says nothing when they agree', function () {
    update_option('corex_operations_mode', wp_get_environment_type(), false);

    expect(invokeScreen($this->screen, 'environmentConflictNotice'))->toBe('');
});

it('says nothing when the mode was only inherited', function () {
    // An undeclared site follows `wp_get_environment_type()` by definition, so it cannot conflict
    // with it. Warning here would put a warning on every fresh install.
    delete_option('corex_operations_mode');

    expect(invokeScreen($this->screen, 'environmentConflictNotice'))->toBe('');
});
