<?php

/**
 * Unit tests for spec 055 client-branding compliance.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ClientBrandingComplianceCheck;

it('passes client branding changes in generated client folders and governance docs', function () {
    $result = (new ClientBrandingComplianceCheck())->evaluate([
        'plugins/acme-site/src/Branding.php',
        'themes/acme/theme.json',
        'docs/brand.md',
        'specs/001-homepage/spec.md',
        'AGENTS.md',
    ]);

    expect($result['passed'])->toBeTrue()
        ->and($result['violations'])->toBe([]);
});

it('flags client-specific edits under Corex framework folders', function () {
    $result = (new ClientBrandingComplianceCheck())->evaluate([
        'plugins/corex-core/src/Boot.php',
        'plugins/corex-config/src/Addons/AddonRegistry.php',
        'addons/corex-email/src/MailService.php',
        'packages/cli/src/Site/SiteScaffolder.php',
        'theme/theme.json',
        'plugins/acme-site/src/Branding.php',
    ]);

    expect($result['passed'])->toBeFalse()
        ->and($result['violations'])->toBe([
            'plugins/corex-core/src/Boot.php',
            'plugins/corex-config/src/Addons/AddonRegistry.php',
            'addons/corex-email/src/MailService.php',
            'packages/cli/src/Site/SiteScaffolder.php',
            'theme/theme.json',
        ]);
});

it('allows explicit framework overrides for approved Corex changes', function () {
    $result = (new ClientBrandingComplianceCheck())->evaluate(
        ['plugins/corex-core/src/Boot.php', 'theme/theme.json'],
        true,
    );

    expect($result['passed'])->toBeTrue()
        ->and($result['violations'])->toBe([]);
});

