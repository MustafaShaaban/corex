<?php

/**
 * Unit tests for the pure Submissions Inbox view model (spec 063, Phase 2). No WordPress.
 * Contract: shape REAL stored submissions; never fabricate a record; truthful empty summary.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionsInbox;

it('shapes each stored submission into an inbox row with a field preview', function () {
    $rows = (new SubmissionsInbox())->rows([
        ['id' => 5, 'date' => '2026-07-01', 'form' => 'contact', 'fields' => ['name' => 'Acme', 'email' => 'a@b.test', 'message' => 'hi']],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe(5)
        ->and($rows[0]['form'])->toBe('contact')
        ->and($rows[0]['fieldCount'])->toBe(3)
        ->and($rows[0]['preview'])->toContain('Acme')
        ->and($rows[0]['preview'])->toContain('a@b.test');
});

it('returns no rows for an empty store — never a fabricated record', function () {
    expect((new SubmissionsInbox())->rows([]))->toBe([]);
});

it('collapses multi-value fields and skips blanks in the preview', function () {
    // a is blank (skipped), b is multi-value (collapsed), c fills the second preview slot.
    $rows = (new SubmissionsInbox())->rows([
        ['id' => 1, 'date' => 'x', 'form' => 'f', 'fields' => ['a' => '', 'b' => ['x', 'y'], 'c' => 'kept', 'd' => 'dropped']],
    ]);

    expect($rows[0]['preview'])->toContain('[2 values]')
        ->and($rows[0]['preview'])->toContain('kept')
        ->and($rows[0]['preview'])->not->toContain('dropped'); // preview caps at 2 non-empty fields
});

it('truncates a long preview', function () {
    $long = str_repeat('x', 200);
    $rows = (new SubmissionsInbox())->rows([
        ['id' => 1, 'date' => 'x', 'form' => 'f', 'fields' => ['a' => $long]],
    ]);

    expect(mb_strlen($rows[0]['preview']))->toBeLessThanOrEqual(60)
        ->and($rows[0]['preview'])->toEndWith('…');
});

it('reports a truthful summary and an empty flag driven by the real total', function () {
    $inbox = new SubmissionsInbox();

    expect($inbox->summary(['total' => 0, 'recent' => 0, 'recentDays' => 7])['isEmpty'])->toBeTrue()
        ->and($inbox->summary(['total' => 12, 'recent' => 3, 'recentDays' => 7]))
        ->toMatchArray(['total' => 12, 'recent' => 3, 'recentDays' => 7, 'isEmpty' => false]);
});
