<?php

/**
 * Unit tests for functional Blog Pro navigation metadata.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\BlogProModel;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->model = new BlogProModel();
});

it('exposes functional Blog Pro tabs without reference or sample states', function () {
    expect($this->model->tabs())->toBe([
        'analytics' => 'Analytics',
        'editorial' => 'Editorial workflow',
        'comments' => 'Comments',
        'authors' => 'Authors',
        'sharing' => 'Sharing',
    ]);

    $copy = implode(' ', $this->model->tabs());

    expect(strtolower($copy))->not->toContain('sample')
        ->not->toContain('reference')
        ->not->toContain('future');
});

it('normalizes an unknown tab to analytics', function () {
    expect($this->model->activeTab('bogus'))->toBe('analytics')
        ->and($this->model->activeTab('comments'))->toBe('comments');
});
