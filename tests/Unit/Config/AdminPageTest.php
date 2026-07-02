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
    Functions\when('admin_url')->returnArg();
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

it('renders permission denied as a complete visible CoreX screen', function () {
    $html = (new AdminPage())->permissionDenied('settings');

    expect($html)->toContain('corex-admin--settings')
        ->and($html)->toContain('Permission denied')
        ->and($html)->toContain('corex-state--permission-denied')
        ->and($html)->toContain('</main></div>');
});
