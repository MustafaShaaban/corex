<?php

/**
 * Maintenance and Forms paused close public submissions through one gate (spec 112, User Story 3,
 * FR-014 to FR-019, SC-004; spec 124, FR-004 and FR-004a).
 *
 * Every request goes through the real REST server. The route's own handler never runs: a
 * `rest_dispatch_request` filter answers in its place, so a request that the gate lets through
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

/**
 * The public submission routes, by what they submit. The Assistant's hand-off is its callback
 * form, a code-defined form posted to the forms route (spec 129), so it is here whether or not the
 * add-on is active.
 *
 * @return array<string,string>
 */
function corexMarkedSubmissionRoutes(): array
{
    return [
        'contact form'         => '/corex/v1/forms/contact',
        'flow'                 => '/corex/v1/flows/1/submit',
        'newsletter sign-up'   => '/corex/v1/newsletter/subscribe',
        'booking request'      => '/corex/v1/bookings/request',
        'job application'      => '/corex/v1/careers/apply',
        'account registration' => '/corex/v1/account/register',
        'assistant hand-off'   => '/corex/v1/forms/corex-assistant-callback',
    ];
}

/** Each mode's refusal: its code and its sentence (Maintenance's exactly as spec 112 left it). */
function corexClosingModes(): array
{
    return [
        'maintenance' => [
            OperationsMode::MAINTENANCE,
            'site_in_maintenance',
            'The site is closed for maintenance and is not taking submissions. Please try again later.',
        ],
        'forms paused' => [
            OperationsMode::FORMS_PAUSED,
            'forms_paused',
            'Forms are paused for a short while. Please try again later.',
        ],
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

    $this->withoutManageOptions = static fn (array $allcaps): array => ['manage_options' => false] + $allcaps;

    add_filter('rest_dispatch_request', static fn () => new WP_REST_Response(COREX_TEST_HANDLER_REACHED, 200));
    wp_set_current_user(0);
});

afterEach(function () {
    remove_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    remove_filter('pre_wp_mail', $this->spyMail, 10);
    remove_filter('query', $this->spyQueries);
    remove_all_filters('rest_dispatch_request');
    remove_all_filters('corex_maintenance_bypass');
    remove_filter('user_has_cap', $this->withoutManageOptions);
    wp_set_current_user(0);
});

it('refuses each public submission with 503, the mode\'s sentence and no errors, and stores and mails nothing (FR-004)', function (string $mode, string $code, string $message) {
    $this->mode = $mode;
    rest_get_server();

    foreach (corexMarkedSubmissionRoutes() as $route) {
        $this->writes = [];
        add_filter('query', $this->spyQueries);
        $response = corexSubmit($route);
        remove_filter('query', $this->spyQueries);
        $data = $response->get_data();

        expect($response->get_status())->toBe(503, $route)
            ->and($data['code'] ?? null)->toBe($code, $route)
            ->and($data['message'] ?? null)->toBe($message, $route)
            ->and($data)->not->toHaveKey('errors')
            ->and($data['data'] ?? [])->not->toHaveKey('errors')
            ->and($response->get_headers()['Retry-After'] ?? null)->toBe('3600', $route)
            // Nothing written: no submission, no subscriber, no account, and no flow's own state.
            ->and($this->writes)->toBe([], $route)
            ->and($this->mailed)->toBe([], $route);
    }
})->with(corexClosingModes());

it('refuses exactly the same routes in Maintenance and in Forms paused (spec 124, US-OPS-02 scenario 6)', function () {
    $refusedIn = function (string $mode): array {
        $this->mode = $mode;
        $refused = [];
        foreach (array_keys(rest_get_server()->get_routes('corex/v1')) as $route) {
            $path = preg_replace(['/\(\?P<id>[^)]+\)/', '/\(\?P<slug>[^)]+\)/', '/\(\?P<[a-z_]+>[^)]+\)/'], ['1', 'contact', 'x'], $route);
            if (corexSubmit((string) $path)->get_status() === 503) {
                $refused[] = $route;
            }
        }

        return $refused;
    };

    $maintenance = $refusedIn(OperationsMode::MAINTENANCE);

    expect($maintenance)->not->toBe([])
        ->and($refusedIn(OperationsMode::FORMS_PAUSED))->toBe($maintenance);
});

it('keeps sign-in and password reset open in both modes', function (string $mode) {
    $this->mode = $mode;

    foreach (['/corex/v1/account/login', '/corex/v1/account/reset-request', '/corex/v1/account/reset'] as $route) {
        expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED, $route);
    }
})->with([OperationsMode::MAINTENANCE, OperationsMode::FORMS_PAUSED]);

it('lets a signed-in administrator submit in both modes (FR-004)', function (string $mode) {
    $this->mode = $mode;
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);

    foreach (corexMarkedSubmissionRoutes() as $route) {
        expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED, $route);
    }
})->with([OperationsMode::MAINTENANCE, OperationsMode::FORMS_PAUSED]);

it('refuses a signed-in person who may not manage options in both modes (FR-004)', function (string $mode) {
    $this->mode = $mode;
    // A real signed-in account with only the administrator's ability taken away, so the test runs
    // on every install and asks the gate's own question, not whether the install has an editor.
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);
    add_filter('user_has_cap', $this->withoutManageOptions);

    expect(corexSubmit('/corex/v1/forms/contact')->get_status())->toBe(503);
})->with([OperationsMode::MAINTENANCE, OperationsMode::FORMS_PAUSED]);

it('lets a submission through when the emergency bypass filter passes it in Maintenance', function () {
    add_filter('corex_maintenance_bypass', '__return_true');

    expect(corexSubmit('/corex/v1/forms/contact')->get_data())->toBe(COREX_TEST_HANDLER_REACHED);
});

it('accepts every submission in every other mode, and again once the closing mode ends (FR-004)', function (string $mode) {
    $this->mode = $mode;

    foreach (corexMarkedSubmissionRoutes() as $route) {
        expect(corexSubmit($route)->get_data())->toBe(COREX_TEST_HANDLER_REACHED, $route);
    }
})->with([OperationsMode::DEVELOPMENT, OperationsMode::STAGING, OperationsMode::COMING_SOON, OperationsMode::PRODUCTION]);

// The chat's own token and limits run inside its handler; AssistantRouteTest proves them.
it('lets the Assistant\'s chat through the gate in both modes (FR-004a)', function (string $mode) {
    if (! isset(rest_get_server()->get_routes()['/corex/v1/assistant'])) {
        $this->markTestSkipped('The corex-assistant add-on is not active on this install.');
    }
    $this->mode = $mode;

    expect(corexSubmit('/corex/v1/assistant')->get_data())->toBe(COREX_TEST_HANDLER_REACHED);
})->with([OperationsMode::MAINTENANCE, OperationsMode::FORMS_PAUSED]);

it('does not mark any other CoreX route as a public submission, and not the Assistant\'s chat (FR-004a)', function () {
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
