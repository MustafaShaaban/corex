<?php

/**
 * Consumer resolution and raw-value contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('records every source token reference in the consumer inventory', function () {
    $expected = ThemeContract::variableReferences();
    $actual = [];

    foreach (ThemeContract::json('specs/057-brand-tokens-logo-system/inventories/consumers.json')['consumers'] as $consumer) {
        $actual[$consumer['path']][] = $consumer['property'];
    }

    foreach ($actual as &$properties) {
        $properties = array_values(array_unique($properties));
        sort($properties);
    }
    unset($properties);
    ksort($actual);

    expect($actual)->toBe($expected);
});

it('rejects undefined custom property references before migration', function () {
    $consumers = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/consumers.json',
    )['consumers'];
    $unresolved = array_values(array_filter(
        $consumers,
        static fn (array $consumer): bool => $consumer['resolution'] === 'invalid',
    ));

    expect($unresolved)->toBe([]);
});

it('uses only known consumer resolutions and classifications', function () {
    $consumers = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/consumers.json',
    )['consumers'];
    $classifications = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    );
    $unknownResolutions = array_values(array_filter(
        $consumers,
        static fn (array $consumer): bool => ! in_array(
            $consumer['resolution'],
            ['valid', 'alias-required', 'migration-required', 'raw-allowance', 'invalid'],
            true,
        ),
    ));

    expect($unknownResolutions)->toBe([]);

    foreach (['retained', 'added', 'aliased', 'migrated', 'deprecated'] as $classification) {
        expect($classifications)->toHaveKey($classification)
            ->and($classifications[$classification])->toBeArray();
    }
});

it('rejects unrecorded raw design values outside approved allowances', function () {
    $violations = [];

    foreach (ThemeContract::sourceFiles() as $relative) {
        if (! preg_match('/\.(?:css|scss)$/', $relative)) {
            continue;
        }

        $lines = file(ThemeContract::root() . '/' . $relative, FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $index => $line) {
            if (! preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\b\d*\.?\d+(?:px|rem|em|ms)\b/', $line)) {
                continue;
            }

            $isAdminInventory = in_array($relative, [
                // The scoped admin token adapter centralizes these literals (spec 057 US4);
                // the consuming screens below read them through --corex-admin-* tokens.
                'plugins/corex-core/assets/css/corex-admin-tokens.css',
                'plugins/corex-config/assets/control-panel.css',
                'plugins/corex-config/assets/data.css',
                'plugins/corex-config/assets/insights.css',
                'addons/corex-captcha/assets/captcha-admin.css',
            ], true);
            $isFunctionalLayout = preg_match('/(?:minmax|inline-size|block-size|z-index|inset|line-height|font-weight)/', $line) === 1;
            $isDocumentedAllowance = str_contains($line, 'corex-token-allow:');
            $isComment = preg_match('/^\s*(?:\/\*|\*|\/\/)/', $line) === 1;

            if (! $isAdminInventory && ! $isFunctionalLayout && ! $isDocumentedAllowance && ! $isComment) {
                $violations[] = sprintf('%s:%d %s', $relative, $index + 1, trim($line));
            }
        }
    }

    expect($violations)->toBe([]);
});
