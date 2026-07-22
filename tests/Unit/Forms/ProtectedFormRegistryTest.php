<?php

/**
 * Unit tests for the protected-form registry (spec 071 US1: FR-001, FR-008).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Block\ProtectedFormRegistry;

it('starts empty so a page with no protected form enqueues nothing', function () {
    $registry = new ProtectedFormRegistry();

    expect($registry->isEmpty())->toBeTrue()
        ->and($registry->all())->toBe([]);
});

it('records a declared form as slug to action', function () {
    $registry = new ProtectedFormRegistry();
    $registry->declare('contact', 'corex_form_contact');

    expect($registry->isEmpty())->toBeFalse()
        ->and($registry->all())->toBe(['contact' => 'corex_form_contact']);
});

it('deduplicates the same form appearing twice on one page', function () {
    $registry = new ProtectedFormRegistry();
    $registry->declare('contact', 'corex_form_contact');
    $registry->declare('contact', 'corex_form_contact');

    expect($registry->all())->toBe(['contact' => 'corex_form_contact']);
});

it('keeps several independently protected forms with their own actions', function () {
    $registry = new ProtectedFormRegistry();
    $registry->declare('contact', 'corex_form_contact');
    $registry->declare('careers', 'corex_form_careers');

    expect($registry->all())->toBe([
        'contact' => 'corex_form_contact',
        'careers' => 'corex_form_careers',
    ]);
});
