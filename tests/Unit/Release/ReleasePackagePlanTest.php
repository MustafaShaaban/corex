<?php

/**
 * Unit tests for the release package plan (spec 050: US1, FR-001/FR-003).
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ReleasePackagePlan;

beforeEach(function () {
    $this->plan = new ReleasePackagePlan(
        ['plugins/corex-core', 'plugins/corex-config', 'theme'],
        ['/tests/', '/specs/', 'node_modules', '.git/', 'wp-config'],
    );
});

it('includes framework source but excludes tests/specs/node_modules', function () {
    expect($this->plan->includes('plugins/corex-core/src/Boot.php'))->toBeTrue()
        ->and($this->plan->includes('theme/style.css'))->toBeTrue()
        ->and($this->plan->includes('plugins/corex-core/tests/BootTest.php'))->toBeFalse()
        ->and($this->plan->includes('plugins/corex-core/node_modules/x.js'))->toBeFalse();
});

it('excludes client/app code and specs', function () {
    expect($this->plan->includes('plugins/acme-site/src/Thing.php'))->toBeFalse()
        ->and($this->plan->includes('specs/050-x/spec.md'))->toBeFalse()
        ->and($this->plan->includes('wp-config.php'))->toBeFalse();
});

it('produces a spec-034 format manifest with no secret', function () {
    $manifest = $this->plan->manifest('0.25.0', 'https://example.com/corex/corex-0.25.0.zip', 'Bug fixes.');

    expect($manifest)->toHaveKeys(['version', 'requires', 'requires_php', 'tested', 'download_url', 'sections'])
        ->and($manifest['version'])->toBe('0.25.0')
        ->and($manifest['sections']['changelog'])->toBe('Bug fixes.')
        ->and(json_encode($manifest))->not->toContain('secret')
        ->and(json_encode($manifest))->not->toContain('key=');
});
