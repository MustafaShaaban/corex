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
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
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
