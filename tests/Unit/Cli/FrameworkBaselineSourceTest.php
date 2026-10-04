<?php

/**
 * Unit tests for where make:site learns which framework release it is generating against
 * (spec 102, FR-006). The git source is exercised against real repositories built in a temporary
 * directory: what it answers is what git says, and a stand-in would only assert its own answer.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Site\FrameworkBaseline;
use Corex\Cli\Site\GitFrameworkBaselineSource;

function baselineRepository(): string
{
    $dir = sys_get_temp_dir() . '/corex_baseline_' . uniqid('', true);
    mkdir($dir);

    baselineGit($dir, 'init -q -b main');
    baselineGit($dir, 'config user.email test@example.com');
    baselineGit($dir, 'config user.name Test');
    baselineGit($dir, 'config commit.gpgsign false');

    return $dir;
}

function baselineGit(string $dir, string $arguments): string
{
    exec('git -C ' . escapeshellarg($dir) . ' ' . $arguments . ' 2>&1', $output, $exit);

    if ($exit !== 0) {
        throw new RuntimeException('git ' . $arguments . ' failed: ' . implode("\n", $output));
    }

    return trim(implode("\n", $output));
}

function baselineCommit(string $dir, string $file): string
{
    file_put_contents($dir . '/' . $file, $file);
    baselineGit($dir, 'add -A');
    baselineGit($dir, 'commit -q -m ' . escapeshellarg('add ' . $file));

    return baselineGit($dir, 'rev-parse HEAD');
}

it('records the commit the release tag points at', function () {
    $dir      = baselineRepository();
    $released = baselineCommit($dir, 'framework.txt');
    baselineGit($dir, 'tag v1.2.3');
    baselineCommit($dir, 'client.txt');

    $baseline = (new GitFrameworkBaselineSource($dir, 'v1.2.3'))->current();

    expect($baseline->release)->toBe('v1.2.3')
        ->and($baseline->commit)->toBe($released)
        ->and($baseline->isResolved())->toBeTrue();
});

it('records HEAD, and says the release is untagged, when the tag does not exist', function () {
    $dir  = baselineRepository();
    $head = baselineCommit($dir, 'framework.txt');

    $baseline = (new GitFrameworkBaselineSource($dir, 'v1.2.3'))->current();

    expect($baseline->commit)->toBe($head)
        ->and($baseline->release)->toContain('v1.2.3')
        ->and($baseline->release)->toContain('untagged')
        ->and($baseline->isResolved())->toBeTrue();
});

it('records an empty commit where there is no repository to ask', function () {
    $dir = sys_get_temp_dir() . '/corex_baseline_none_' . uniqid('', true);
    mkdir($dir);

    $baseline = (new GitFrameworkBaselineSource($dir, 'v1.2.3'))->current();

    expect($baseline->release)->toBe('v1.2.3')
        ->and($baseline->commit)->toBe('')
        ->and($baseline->isResolved())->toBeFalse();
});

it('is unresolved when nothing is known about the framework at all', function () {
    expect(FrameworkBaseline::unknown()->isResolved())->toBeFalse()
        ->and(FrameworkBaseline::unknown()->commit)->toBe('');
});
