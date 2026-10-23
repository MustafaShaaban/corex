<?php

/**
 * Spec 113, US1: one answer to "is this a development site" reaches Email Studio, Insights,
 * Settings and the Operations screen, and a lock refuses a mode it does not allow.
 *
 * The mode and the lock are given through option filters: nothing on the site is written.
 *
 * @package Corex\Tests\Integration\Environment
 */

declare(strict_types=1);

use Corex\Config\Insights\InsightWidgetFacts;
use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeResult;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Environment\SiteEnvironment;

beforeEach(function () {
    $this->container = Corex\Boot::app()->container();
    $GLOBALS['corex_environment_test_filters'] = [];
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->admin);
});

afterEach(function () {
    foreach ($GLOBALS['corex_environment_test_filters'] as [$hook, $callback]) {
        remove_filter($hook, $callback);
    }
    $GLOBALS['corex_environment_test_filters'] = [];
    \Corex\Tests\Support\AdminScreen::set('front');
    wp_set_current_user(0);
});

/** Answer an option with a value for this test only; `afterEach` removes the filter. */
function answerOption(string $option, string $value): void
{
    $callback = static fn (): string => $value;
    add_filter('pre_option_' . $option, $callback);
    $GLOBALS['corex_environment_test_filters'][] = ['pre_option_' . $option, $callback];
}

it('gives Email Studio, Insights and the mode store the same answer as the operations mode', function (string $mode, string $environment) {
    answerOption(OperationsModeStore::OPTION, $mode);

    $studio = rest_do_request(new WP_REST_Request('GET', '/corex/v1/email-studio'));
    $facts = $this->container->make(InsightWidgetFacts::class)->gather();
    $answer = $this->container->make(SiteEnvironment::class)->current();

    expect($studio->get_status())->toBe(200)
        ->and($studio->get_data()['data']['delivery']['environment'] ?? null)->toBe($environment)
        ->and($answer->environment)->toBe($environment)
        ->and($answer->decidedBy)->toBe(SiteEnvironment::BY_MODE)
        ->and($facts['environment'])->toBe($answer->summary())
        ->and($facts['operationsMode'])->toBe($mode);
})->with([
    'development' => [OperationsMode::DEVELOPMENT, SiteEnvironment::DEVELOPMENT],
    'staging' => [OperationsMode::STAGING, SiteEnvironment::STAGING],
    'production' => [OperationsMode::PRODUCTION, SiteEnvironment::PRODUCTION],
    'coming soon' => [OperationsMode::COMING_SOON, SiteEnvironment::PRODUCTION],
]);

it('refuses a mode the corex_app_env option does not allow, and changes nothing', function () {
    answerOption(OperationsModeStore::OPTION, OperationsMode::PRODUCTION);
    answerOption('corex_app_env', 'production');
    $writes = 0;
    $count = static function () use (&$writes): void {
        ++$writes;
    };
    add_action('update_option_' . OperationsModeStore::OPTION, $count);
    add_action('add_option_' . OperationsModeStore::OPTION, $count);

    $result = $this->container->make(ModeChangeService::class)->apply(new ModeChangeRequest(
        mode: OperationsMode::DEVELOPMENT,
        actorId: $this->admin,
        now: new DateTimeImmutable('now'),
        acknowledged: true,
    ));
    remove_action('update_option_' . OperationsModeStore::OPTION, $count);
    remove_action('add_option_' . OperationsModeStore::OPTION, $count);

    $store = $this->container->make(OperationsModeStore::class);

    expect($result->status)->toBe(ModeChangeResult::LOCKED)
        ->and($writes)->toBe(0)
        ->and($store->current())->toBe(OperationsMode::PRODUCTION)
        ->and($store->lock()?->decidedBy)->toBe(SiteEnvironment::BY_OPTION)
        ->and($store->allows(OperationsMode::COMING_SOON))->toBeTrue();
});

it('shows the lock on the Operations screen and disables the modes it refuses', function () {
    answerOption('corex_app_env', 'production');
    \Corex\Tests\Support\AdminScreen::set('toplevel_page_corex-operations-security');

    $_GET['tab'] = 'environment';
    ob_start();
    $this->container->make(\Corex\Config\Security\OperationsSecurityScreen::class)->render();
    $html = (string) ob_get_clean();
    unset($_GET['tab']);

    expect($html)->toContain('data-corex-mode-lock')
        ->and($html)->toContain('the corex_app_env option')
        ->and($html)->toMatch('/<option value="development"[^>]*disabled/')
        ->and($html)->not->toMatch('/<option value="coming-soon"[^>]*disabled/');
});
