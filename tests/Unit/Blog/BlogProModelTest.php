<?php

/**
 * Unit tests for the pure Blog Pro model (spec 067). No WordPress.
 * Contract: four tabs, analytics is flagged reference (never live), and editorial/comments/authors
 * are shaped truthfully from real counts (negatives clamped).
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

it('exposes the four designed Blog Pro tabs', function () {
    expect(array_keys($this->model->tabs()))->toBe(['analytics', 'editorial', 'comments', 'authors']);
});

it('normalises an unknown tab to analytics', function () {
    expect($this->model->activeTab('bogus'))->toBe('analytics')
        ->and($this->model->activeTab('comments'))->toBe('comments');
});

it('flags the analytics layout as reference, never live', function () {
    expect($this->model->analyticsReference()['reference'])->toBeTrue()
        ->and($this->model->analyticsReference()['stats'])->not->toBe([]);
});

it('shapes the editorial queue from real post-status counts', function () {
    $rows = $this->model->editorial(['draft' => 4, 'pending' => 2, 'future' => 1, 'publish' => 9]);
    $byLabel = [];
    foreach ($rows as $r) {
        $byLabel[$r['label']] = $r['count'];
    }

    expect($byLabel['Pending review'])->toBe(2)
        ->and($byLabel['Drafts'])->toBe(4)
        ->and($byLabel['Published'])->toBe(9);
});

it('shapes comments from real counts and clamps negatives', function () {
    $rows = $this->model->comments(['approved' => 100, 'moderated' => 3, 'spam' => -5, 'trash' => 0]);
    $byLabel = [];
    foreach ($rows as $r) {
        $byLabel[$r['label']] = $r['count'];
    }

    expect($byLabel['Awaiting moderation'])->toBe(3)
        ->and($byLabel['Approved'])->toBe(100)
        ->and($byLabel['Spam'])->toBe(0); // negative clamped
});

it('shapes real authors with their post counts', function () {
    $authors = $this->model->authors([
        ['name' => 'Ada', 'posts' => 12],
        ['name' => 'Grace', 'posts' => 0],
    ]);

    expect($authors)->toBe([
        ['name' => 'Ada', 'posts' => 12],
        ['name' => 'Grace', 'posts' => 0],
    ]);
});
