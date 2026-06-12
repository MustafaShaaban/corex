<?php

/**
 * Unit tests for the pure Cloudflare URL-scanner normaliser (spec 037: FR-003/004). A finished
 * scan becomes a security score + signals; an in-progress scan is reported as pending.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\Normalizers\CloudflareNormalizer;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('reports an in-progress scan as pending', function () {
    $out = (new CloudflareNormalizer())->normalize(['result' => ['task' => ['status' => 'inProgress']]]);

    expect($out['status'])->toBe('pending');
});

it('scores a clean finished scan as 100 with a not-malicious signal', function () {
    $out = (new CloudflareNormalizer())->normalize([
        'result' => [
            'task'     => ['status' => 'finished'],
            'verdicts' => ['overall' => ['malicious' => false]],
            'page'     => ['securityViolations' => []],
        ],
    ]);

    expect($out['status'])->toBe('finished')
        ->and($out['score'])->toBe(100)
        ->and($out['recommendations'])->toBe([]);
});

it('drops the score and recommends action when flagged malicious', function () {
    $out = (new CloudflareNormalizer())->normalize([
        'result' => [
            'task'     => ['status' => 'finished'],
            'verdicts' => ['overall' => ['malicious' => true]],
            'page'     => ['securityViolations' => [['a' => 1]]],
        ],
    ]);

    expect($out['status'])->toBe('finished')
        ->and($out['score'])->toBeLessThan(50)
        ->and($out['recommendations'])->not->toBe([]);
});
