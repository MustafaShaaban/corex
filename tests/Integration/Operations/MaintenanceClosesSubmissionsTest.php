<?php

/**
 * Maintenance closes public submissions (spec 112, User Story 3, FR-014 to FR-019, SC-004).
 *
 * Every request goes through the real REST server. The route's own handler never runs: a
 * `rest_dispatch_request` filter answers in its place, so a request that the guard lets through
 * reaches that stand-in and stores nothing, and no run of this file writes a submission, a
 * subscriber or an account, or sends mail, on the install it runs against. The mode is answered
 * by a `pre_option_` filter, so the install's stored mode is never written either.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

const COREX_TEST_HANDLER_REACHED = 'corex_test_handler_reached';

/** @return array<string,string> the public submission routes, by what they submit */
function corexMarkedSubmissionRoutes(): array
{
    return [
        'contact form'         => '/corex/v1/forms/contact',
        'flow'                 => '/corex/v1/flows/1/submit',
        'newsletter sign-up'   => '/corex/v1/newsletter/subscribe',
        'booking request'      => '/corex/v1/bookings/request',
        'job application'      => '/corex/v1/careers/apply',
        'account registration' => '/corex/v1/account/register',
    ];
}

function corexSubmit(string $route): WP_REST_Response
{
    return rest_do_request(new WP_REST_Request('POST', $route));
}

beforeEach(function () {
    $this->mode = OperationsMode::MAINTENANCE;
    $this->answerMode = fn (): string => $this->mode;
    add_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);

    $this->mailed = [];
    $this->spyMail = function ($short, array $atts) {
        $this->mailed[] = $atts;

        return false;
    };
    add_filter('pre_wp_mail', $this->spyMail, 10, 2);

    $this->writes = [];
    $this->spyQueries = function (string $query): string {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query) === 1) {
            $this->writes[] = $query;
        }

        return $query;
    };

    add_filter('rest_dispatch_request', static fn () => new WP_REST_Response(COREX_TEST_HANDLER_REACHED, 200));
    wp_set_current_user(0);
});

afterEach(function () {
    remove_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    remove_filter('pre_wp_mail', $this->spyMail, 10);
    remove_filter('query', $this->spyQueries);
    remove_all_filters('rest_dispatch_request');
    remove_all_filters('corex_maintenance_bypass');
    wp_set_current_user(0);
});

it('refuses each public submission in maintenance with 503, and stores and mails nothing', function (string $route) {
    rest_get_server();
    add_filter('query', $this->spyQueries);

    $response = corexSubmit($route);

    remove_filter('query', $this->spyQueries);
    expect($response->get_status())->toBe(503)
        ->and($response->get_data()['code'] ?? null)->toBe('site_in_maintenance')
        ->and($response->get_data()['message'] ?? '')->not->toBe('')
        ->and($response->get_headers()['Retry-After'] ?? null)->toBe('3600')
        ->and($this->writes)->toBe([])
        ->and($this->mailed)->toBe([]);
})->with(corexMarkedSubmissionRoutes());

it('keeps sign-in and password reset open in maintenance', function (string $route) {
    expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED);
})->with([
    'sign-in'                => '/corex/v1/account/login',
    'password reset request' => '/corex/v1/account/reset-request',
    'password reset'         => '/corex/v1/account/reset',
]);

it('lets a signed-in administrator submit in maintenance', function (string $route) {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);

    expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED);
})->with(corexMarkedSubmissionRoutes());

it('lets a submission through when the emergency bypass filter passes it', function () {
    add_filter('corex_maintenance_bypass', '__return_true');

    expect(corexSubmit('/corex/v1/forms/contact')->get_data())->toBe(COREX_TEST_HANDLER_REACHED);
});

it('accepts every submission in coming soon and in production, and again once maintenance ends', function (string $mode) {
    $this->mode = $mode;

    foreach (corexMarkedSubmissionRoutes() as $route) {
        expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED, $route);
    }
})->with([OperationsMode::COMING_SOON, OperationsMode::PRODUCTION]);

it('does not mark any other CoreX route as a public submission', function () {
    $marked = [];
    foreach (rest_get_server()->get_routes('corex/v1') as $route => $handlers) {
        foreach ($handlers as $handler) {
            if (($handler[\Corex\Http\PublicSubmission::MARKER] ?? false) === true) {
                $marked[] = $route;
            }
        }
    }

    // Other test files register the same routes again, so a route can appear more than once.
    expect(array_values(array_unique($marked)))->toEqualCanonicalizing([
        '/corex/v1/forms/(?P<slug>[a-z0-9-]+)',
        '/corex/v1/flows/(?P<id>\d+)/submit',
        '/corex/v1/newsletter/subscribe',
        '/corex/v1/bookings/request',
        '/corex/v1/careers/apply',
        '/corex/v1/account/register',
    ]);
});
