<?php

/**
 * The Security Center says while recovery is on (spec 112, User Story 4, FR-022).
 *
 * The screen is built with a chosen {@see LoginRecovery}, everything else from the container, and
 * the login settings are answered by a filter: nothing is written to the install.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRecovery;
use Corex\Config\Security\OperationsSecurityScreen;

function securityScreenWithRecovery(bool $active): OperationsSecurityScreen
{
    $container = \Corex\Boot::app()->container();
    $arguments = array_map(
        static function (ReflectionParameter $parameter) use ($container, $active): object {
            $type = (string) $parameter->getType();

            return $type === LoginRecovery::class ? new LoginRecovery($active) : $container->make($type);
        },
        (new ReflectionMethod(OperationsSecurityScreen::class, '__construct'))->getParameters(),
    );

    return new OperationsSecurityScreen(...$arguments);
}

/** The one readiness evaluation the screen's sections read (spec 118). */
function recoverySnapshot(): \Corex\Config\Operations\ReadinessSnapshot
{
    return \Corex\Boot::app()->container()
        ->make(\Corex\Config\Operations\ProductionReadinessSnapshotFactory::class)
        ->fromCurrentSite(new DateTimeImmutable('now'));
}

/** Reach a private part of the screen without widening its API for a test. */
function callScreen(OperationsSecurityScreen $screen, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($screen, $method))->invoke($screen, ...$arguments);
}

beforeEach(function () {
    $this->settings = static fn (): array => [
        'enabled'                 => true,
        'custom_slug'             => 'team-login',
        'block_default_endpoints' => true,
    ];
    add_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $this->settings);
});

afterEach(function () {
    remove_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $this->settings);
});

it('tells the Security Center whether recovery is on', function (bool $active) {
    $config = callScreen(securityScreenWithRecovery($active), 'securityConfig');

    expect($config['recoveryActive'])->toBe($active);
})->with([true, false]);

it('says on the overview that the default login endpoints are reachable while recovery is on', function () {
    $html = callScreen(securityScreenWithRecovery(true), 'overviewCard', recoverySnapshot());

    // Spec 114, US-SAFE-11: with how recovery ends.
    expect($html)->toContain('Recovery active: the default login is reachable')
        ->and($html)->toContain('Recovery ends when the COREX_LOGIN_UNGUARD line is removed from wp-config.php.')
        ->and($html)->not->toContain('Hidden');
});

it('says they are hidden while recovery is off and protection hides them', function () {
    $html = callScreen(securityScreenWithRecovery(false), 'overviewCard', recoverySnapshot());

    expect($html)->toContain('Hidden')
        ->and($html)->not->toContain('Recovery active');
});

// Spec 114, US-SAFE-11: the row read the hiding setting alone, so a fresh install, with
// protection off and hiding on by default, said "Hidden" while the login form was served.
it('says they are reachable while protection is off, whatever the hiding setting says', function () {
    $off = static fn (): array => ['enabled' => false, 'block_default_endpoints' => true];
    add_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $off, 20);

    $html = callScreen(securityScreenWithRecovery(false), 'overviewCard', recoverySnapshot());
    remove_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $off, 20);

    expect($html)->toMatch('/Default login endpoints<\/span><span class="corex-opsec__summary-value">Reachable</')
        ->and($html)->not->toContain('>Hidden<');
});

// Spec 124, FR-013, User Story 6 scenario 7: the tab reads each switch as it is in force, and the
// previous release's two keys beside them, from either stored shape. Only the facts change here;
// slice 10 draws them.
it('gives the Login Protection tab each switch as it is in force', function (array $stored, array $switches) {
    $answer = static fn (): array => $stored + ['custom_slug' => 'team-login'];
    add_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $answer, 20);

    $policy = callScreen(securityScreenWithRecovery(false), 'securityConfig')['loginPolicy'];
    remove_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $answer, 20);

    expect([$policy['hide_wp_login'], $policy['hide_admin'], $policy['limit_attempts']])->toBe($switches)
        ->and($policy['enabled'])->toBe(in_array(true, $switches, true));
})->with([
    'previous release, protection on, hiding on' => [['enabled' => true, 'block_default_endpoints' => true], [true, true, true]],
    'previous release, protection on, hiding off' => [['enabled' => true, 'block_default_endpoints' => false], [false, false, true]],
    'previous release, protection off' => [['enabled' => false, 'block_default_endpoints' => true], [false, false, false]],
    'this release, the admin hidden alone' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], [false, true, false]],
]);

// Spec 124 slice 4 review: "Default login endpoints" says what each address does. It said
// "Reachable" with the admin hidden, where a signed-out /wp-admin/ is a missing page.
it('says what wp-login.php and wp-admin each do on the overview', function (array $stored, bool $recovery, string $value, string $note) {
    $answer = static fn (): array => $stored + ['custom_slug' => 'team-login'];
    add_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $answer, 20);

    $html = callScreen(securityScreenWithRecovery($recovery), 'overviewCard', recoverySnapshot());
    remove_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $answer, 20);

    $expected = '/Default login endpoints<\/span><span class="corex-opsec__summary-value">'
        . preg_quote(esc_html($value), '/')
        . ($note === '' ? '<\/span>' : '<span class="corex-opsec__summary-note">' . preg_quote(esc_html($note), '/') . '<\/span>')
        . '/';
    expect($html)->toMatch($expected);
})->with([
    'both hidden' => [['hide_wp_login' => true, 'hide_admin' => true, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => true], false, 'Hidden', ''],
    'wp-login.php hidden alone' => [['hide_wp_login' => true, 'hide_admin' => false, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => false], false, 'wp-login.php hidden, wp-admin reachable', 'A signed-out visit to wp-admin is sent to the login address, so the address is not private. Hide the admin as well to keep it private.'],
    'the admin hidden alone' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], false, 'wp-login.php reachable, wp-admin hidden', ''],
    'limits alone' => [['hide_wp_login' => false, 'hide_admin' => false, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => false], false, 'Reachable', ''],
    'all off' => [['enabled' => false, 'block_default_endpoints' => true], false, 'Reachable', ''],
    'the admin hidden alone, recovery on' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false], true, 'Recovery active: the default login is reachable', 'Recovery ends when the COREX_LOGIN_UNGUARD line is removed from wp-config.php.'],
]);
