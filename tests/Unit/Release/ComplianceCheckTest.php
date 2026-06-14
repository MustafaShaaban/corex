<?php

/**
 * Unit tests for the framework-boundary compliance check (spec 050: US2, FR-004/FR-005).
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ComplianceCheck;

beforeEach(function () {
    $this->check     = new ComplianceCheck();
    $this->forbidden = ['plugins/corex-core/', 'plugins/corex-config/', 'theme/', 'packages/cli/'];
});

it('passes when every change is under the client plugin/theme/docs/specs', function () {
    $result = $this->check->evaluate(
        ['plugins/acme-site/src/Thing.php', 'themes/acme/style.css', 'docs/x.md', 'specs/1/spec.md'],
        $this->forbidden,
    );

    expect($result['passed'])->toBeTrue()
        ->and($result['violations'])->toBe([]);
});

it('fails and names a change under a forbidden framework path', function () {
    $result = $this->check->evaluate(
        ['plugins/acme-site/x.php', 'plugins/corex-core/src/Boot.php', 'theme/style.css'],
        $this->forbidden,
    );

    expect($result['passed'])->toBeFalse()
        ->and($result['violations'])->toBe(['plugins/corex-core/src/Boot.php', 'theme/style.css']);
});

it('matches by path prefix, not substring (no false positive)', function () {
    $result = $this->check->evaluate(['plugins/acme-corex-core-clone/x.php'], $this->forbidden);

    expect($result['passed'])->toBeTrue();
});

it('passes with no changed files, and an explicit override allows a framework change', function () {
    expect($this->check->evaluate([], $this->forbidden)['passed'])->toBeTrue()
        ->and($this->check->evaluate(['plugins/corex-core/x.php'], $this->forbidden, true)['passed'])->toBeTrue();
});
