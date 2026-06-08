<?php

/**
 * Unit tests for the read-only Model value object (spec US-foundational: FR-001–FR-003).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Tests\Fixtures\Data\Job;

require_once __DIR__ . '/DataFixtures.php';

it('exposes attributes with their declared cast types', function () {
    $job = new Job([
        'id'      => 5,
        'salary'  => '90000',
        'active'  => '1',
        'created' => '2026-01-01',
    ]);

    expect($job->id())->toBe(5)
        ->and($job->get('salary'))->toBe(90000)
        ->and($job->get('active'))->toBeTrue()
        ->and($job->get('created'))->toBeInstanceOf(DateTimeImmutable::class);
});

it('returns the caller default for an absent attribute', function () {
    $job = new Job(['id' => 1]);

    expect($job->get('salary', 0))->toBe(0)
        ->and($job->get('missing'))->toBeNull();
});

it('declares its post type, fields, and belongs-to relation', function () {
    expect(Job::postType())->toBe('job')
        ->and(Job::fields())->toBe(['salary' => 'job_salary', 'company_id' => 'company_id'])
        ->and(Job::relations()['company']['type'])->toBe('belongsTo');
});

it('does not expose a public attribute setter (read-only)', function () {
    expect(method_exists(Job::class, 'set'))->toBeFalse();
});
