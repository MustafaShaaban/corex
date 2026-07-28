<?php

/**
 * Corrective Spec 060 shared admin shell and universal-state contracts.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
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
    Functions\when('rest_url')->alias(static fn (string $path = ''): string => 'http://example.test/wp-json/' . $path);
    Functions\when('wp_create_nonce')->alias(static fn (string $action): string => 'nonce-' . $action);
});

it('renders the branded shell with a labelled main region and page header', function () {
    $html = (new AdminPage())->open('data', 'CoreX Data', 'Manage framework records.');

    expect($html)->toContain('wrap corex-admin corex-admin--data')
        ->and($html)->toContain('<main')
        ->and($html)->toContain('aria-labelledby="corex-page-title"')
        ->and($html)->toContain('COREX FRAMEWORK')
        ->and($html)->toContain('<h1 id="corex-page-title">CoreX Data</h1>')
        ->and($html)->toContain('Manage framework records.');
});

it('gives every CoreX screen a distinct rail icon and a correct active state (spec 064)', function (string $section, string $iconClass) {
    $html = (new AdminPage())->open($section, 'Title', '');

    expect($html)->toContain('corex-admin__nav-icon--' . $iconClass)
        // The active screen carries the active class + aria-current on its own rail entry.
        ->and($html)->toContain('is-active')
        ->and($html)->toContain('aria-current="page"')
        // No real CoreX screen falls back to the generic option-page icon in the rail.
        ->and($html)->not->toContain('corex-admin__nav-icon--option-page');
})->with([
    'forms' => ['forms', 'forms'],
    'submissions' => ['submissions', 'submissions'],
    'email studio' => ['email', 'mail'],
    'data models' => ['data-models', 'data'],
    'operations & security' => ['operations-security', 'security'],
    'access & abilities' => ['access', 'access'],
]);

it('marks exactly the active screen as current in the rail', function () {
    $html = (new AdminPage())->open('submissions', 'Submissions', '');

    // The submissions entry is active; other entries are not aria-current.
    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and($html)->toContain('page=corex-submissions');
});

it('shows the breadcrumb label that matches the rail/menu for each section (spec 068 T181)', function (string $section, string $label) {
    $html = (new AdminPage())->open($section, 'Title', '');

    // The mono breadcrumb kicker names the section; "Setup Wizard" must match the menu label,
    // not the shorter "Setup" (a real rail/breadcrumb mismatch fixed in T181).
    expect($html)->toContain('Corex / ' . $label);
})->with([
    'overview' => ['overview', 'Overview'],
    'blog pro' => ['blog-pro', 'Blog Pro'],
    'email studio' => ['email', 'Email Studio'],
    'operations & security' => ['operations-security', 'Operations & Security'],
    'setup wizard' => ['setup', 'Setup Wizard'],
]);

it('renders text-labelled universal states with appropriate live roles', function (string $tone, string $role) {
    $html = (new AdminPage())->state($tone, 'State title', 'State explanation.');

    expect($html)->toContain('corex-state--' . $tone)
        ->and($html)->toContain('role="' . $role . '"')
        ->and($html)->toContain('State title')
        ->and($html)->toContain('State explanation.');
})->with([
    'loading' => ['loading', 'status'],
    'empty' => ['empty', 'status'],
    'success' => ['success', 'status'],
    'warning' => ['warning', 'status'],
    'error' => ['error', 'alert'],
    'permission denied' => ['permission-denied', 'alert'],
]);

it('renders permission denied as the designed denied surface and publishes the audit event (spec 067)', function () {
    $html = (new AdminPage())->permissionDenied('settings');

    expect($html)->toContain('corex-admin--settings')
        ->and($html)->toContain('Access denied')
        ->and($html)->toContain('corex-denied')
        // Spec 083 reversed this one too, for the same reason spec 079 reversed the action below.
        // It required the copy to name `manage_options` — which the surface did on every screen,
        // and which is false on `corex-notifications`, `corex-submissions` and `corex-data-models`.
        // The assertion was holding a false statement in place at HTTP 403. What the page can know
        // for certain is the ability a request from it asks for, so that is what it now names.
        ->and($html)->toContain('corex_manage_settings')
        ->and($html)->not->toContain('manage_options')
        ->and($html)->toContain('Back to Dashboard')
        ->and($html)->toContain('Request access')
        ->and($html)->toContain('method="post"')
        // Spec 079 reversed this assertion. It used to require the form to post to
        // `rest_url('corex/v1/access/requests')`, which is precisely the defect: a browser form
        // whose action is a REST endpoint navigates to a JSON document when submitted. The test
        // held the defect in place, so it is worth saying plainly — a test can be the reason a bug
        // survives review.
        ->and($html)->toContain('http://example.test/wp-admin/admin-post.php')
        ->and($html)->not->toContain('wp-json')
        ->and($html)->toContain('name="corex_access_request_nonce" value="nonce-corex_access_request"')
        // The section, not the ability: the server resolves what the screen requires, so a posted
        // ability cannot ask for something the screen does not need.
        ->and($html)->toContain('name="corex_section" value="settings"')
        ->and($html)->not->toContain('name="ability"')
        ->and($html)->toContain('name="reason"')
        ->and($html)->not->toContain('disabled')
        ->and($html)->not->toContain('aria-disabled="true"')
        ->and($html)->toContain('</main></div>');

    // The denial is published so the access audit log records it.
    expect(did_action('corex_admin_access_denied'))->toBe(1);
});

it('renders the denied surface as a labelled preview without publishing the audit event', function () {
    $html = (new AdminPage())->deniedPreview();

    expect($html)->toContain('corex-denied--preview')
        ->and($html)->toContain('Preview')
        ->and(did_action('corex_admin_access_denied'))->toBe(0);
});

it('appends the crumb suffix to the breadcrumb kicker', function () {
    $html = (new AdminPage())->open('access', 'CoreX Access & Abilities', '', 'Role matrix');

    // esc_html is shimmed as a pass-through here, so the raw ampersand is expected.
    expect($html)->toContain('Corex / Access & Abilities / Role matrix');
});
