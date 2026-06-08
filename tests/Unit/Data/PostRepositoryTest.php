<?php

/**
 * Unit tests for the post-backed Repository (spec US1: FR-004–FR-006).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Fixtures\Data\FakeFieldDriver;
use Corex\Tests\Fixtures\Data\Job;
use Corex\Tests\Fixtures\Data\JobRepository;

require_once __DIR__ . '/DataFixtures.php';

it('hydrates a model from a post with core and declared fields', function () {
    $fields = new FakeFieldDriver();
    $fields->set(7, 'job_salary', 90000);
    Functions\when('get_post')->alias(fn ($id) => $id === 7
        ? (object) ['ID' => 7, 'post_type' => 'job', 'post_title' => 'Dev', 'post_status' => 'publish']
        : null);

    $job = JobRepository::make($fields)->find(7);

    expect($job)->toBeInstanceOf(Job::class)
        ->and($job->id())->toBe(7)
        ->and($job->get('title'))->toBe('Dev')
        ->and($job->get('salary'))->toBe(90000);
});

it('returns null for an absent id', function () {
    Functions\when('get_post')->justReturn(null);

    expect(JobRepository::make(new FakeFieldDriver())->find(99))->toBeNull();
});

it('returns null when the post type does not match', function () {
    Functions\when('get_post')->alias(fn ($id) => (object) ['ID' => $id, 'post_type' => 'page']);

    expect(JobRepository::make(new FakeFieldDriver())->find(7))->toBeNull();
});

it('creates a post, persists declared fields, and returns the model', function () {
    $fields = new FakeFieldDriver();
    Functions\expect('wp_insert_post')->once()->andReturn(10);
    Functions\when('get_post')->alias(fn ($id) => (object) ['ID' => 10, 'post_type' => 'job', 'post_title' => 'New']);

    $job = JobRepository::make($fields)->create(['title' => 'New', 'salary' => 50000]);

    expect($job->id())->toBe(10)
        ->and($fields->get(10, 'job_salary'))->toBe(50000);
});

it('updates a post, persists declared fields, and returns the model', function () {
    $fields = new FakeFieldDriver();
    Functions\expect('wp_update_post')->once();
    Functions\when('get_post')->alias(fn ($id) => (object) ['ID' => 7, 'post_type' => 'job', 'post_title' => 'Edited']);

    $job = JobRepository::make($fields)->update(7, ['title' => 'Edited', 'salary' => 99000]);

    expect($job->get('title'))->toBe('Edited')
        ->and($fields->get(7, 'job_salary'))->toBe(99000);
});

it('deletes a post and returns a boolean', function () {
    Functions\when('wp_delete_post')->justReturn((object) ['ID' => 7]);

    expect(JobRepository::make(new FakeFieldDriver())->delete(7))->toBeTrue();
});
