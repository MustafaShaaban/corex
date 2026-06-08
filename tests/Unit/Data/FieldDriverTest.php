<?php

/**
 * Unit tests for the ACF-optional field drivers + resolver (spec US2: FR-008–FR-012, SC-002).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Fields\AcfFieldDriver;
use Corex\Fields\FieldResolver;
use Corex\Fields\MetaFieldDriver;

it('reads native post meta and falls back to the default when empty', function () {
    Functions\when('get_post_meta')->alias(fn ($id, $key, $single) => $key === 'job_salary' ? '90000' : '');

    $driver = new MetaFieldDriver();

    expect($driver->get(7, 'job_salary'))->toBe('90000')
        ->and($driver->get(7, 'missing', 'fallback'))->toBe('fallback');
});

it('writes native post meta', function () {
    Functions\expect('update_post_meta')->once()->with(7, 'job_salary', 90000);

    (new MetaFieldDriver())->set(7, 'job_salary', 90000);
});

it('reads ACF fields and falls back to the default when null', function () {
    Functions\when('get_field')->alias(fn ($key, $id) => $key === 'job_salary' ? 90000 : null);

    $driver = new AcfFieldDriver();

    expect($driver->get(7, 'job_salary'))->toBe(90000)
        ->and($driver->get(7, 'missing', 'fallback'))->toBe('fallback');
});

it('writes ACF fields with the ACF argument order', function () {
    Functions\expect('update_field')->once()->with('job_salary', 90000, 7);

    (new AcfFieldDriver())->set(7, 'job_salary', 90000);
});

it('resolves to the ACF driver when ACF is available, native meta otherwise', function () {
    $meta = new MetaFieldDriver();
    $acf = new AcfFieldDriver();

    $withAcf = new FieldResolver($meta, $acf, fn (): bool => true);
    $withoutAcf = new FieldResolver($meta, $acf, fn (): bool => false);

    expect($withAcf->driver())->toBe($acf)
        ->and($withoutAcf->driver())->toBe($meta);
});
