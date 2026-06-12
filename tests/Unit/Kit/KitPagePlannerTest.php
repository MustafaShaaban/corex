<?php

/**
 * Unit tests for the pure kit-page planner (spec 031: FR-003, idempotency). No WordPress.
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Corex\Kit\KitPagePlanner;

function pages(): array
{
    return [
        ['title' => 'Home', 'slug' => 'home', 'content' => '', 'front' => true],
        ['title' => 'About', 'slug' => 'about', 'content' => ''],
        ['title' => 'Contact', 'slug' => 'contact', 'content' => ''],
    ];
}

it('plans all pages when none exist', function () {
    expect(array_column((new KitPagePlanner())->toCreate(pages(), []), 'slug'))
        ->toBe(['home', 'about', 'contact']);
});

it('skips pages whose slug already exists (idempotent)', function () {
    expect(array_column((new KitPagePlanner())->toCreate(pages(), ['home', 'about']), 'slug'))
        ->toBe(['contact']);
});

it('creates nothing when every slug exists', function () {
    expect((new KitPagePlanner())->toCreate(pages(), ['home', 'about', 'contact']))->toBe([]);
});
