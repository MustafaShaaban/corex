<?php

/**
 * Spec 068 contracts for the capability-derived Data and Data Models clients.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function dataClientSource(string $path): string
{
    return (string) file_get_contents(ThemeContract::root() . '/' . $path);
}

it('mounts both Data clients with actor-scoped source capabilities', function () {
    $data = dataClientSource('plugins/corex-config/src/Data/DataAdminScreen.php');
    $models = dataClientSource('plugins/corex-config/src/DataModels/DataModelsScreen.php');

    foreach ([$data, $models] as $screen) {
        expect($screen)->toContain('DataSourceService')
            ->and($screen)->toContain('get_current_user_id()')
            ->and($screen)->toContain("rest_url('corex/v1/data')")
            ->and($screen)->toContain("wp_create_nonce('wp_rest')")
            ->and($screen)->toContain('build/admin/index.js')
            ->and($screen)->toContain("'corex-runtime'");
    }

    expect($data)->toContain('corex-data-app')
        ->and($models)->toContain('corex-data-models-app');
});

it('uses explicit Data abilities at both the menu and REST permission boundaries', function () {
    $data = dataClientSource('plugins/corex-config/src/Data/DataAdminScreen.php');
    $models = dataClientSource('plugins/corex-config/src/DataModels/DataModelsScreen.php');
    $controller = dataClientSource('plugins/corex-config/src/Data/DataManagementController.php');

    expect($data)->toContain('CorexAbility::MANAGE_DATA')
        ->and($models)->toContain('CorexAbility::MANAGE_DATA_MODELS')
        ->and($controller)->toContain("'permission_callback' => [\$this, 'canManage']")
        ->and($controller)->not->toContain("'permission_callback' => '__return_true'");
});

it('replaces the legacy read-only Data Models renderer with real REST workflows', function () {
    $screen = dataClientSource('plugins/corex-config/src/DataModels/DataModelsScreen.php');
    $app = dataClientSource('plugins/corex-config/src/DataModels/DataModelsApp.js');
    $panels = implode('', array_map(
        static fn (string $file): string => (string) file_get_contents($file),
        glob(ThemeContract::root() . '/plugins/corex-config/src/DataModels/*Panel.js') ?: [],
    ));

    expect($screen)->not->toContain('DataModelsImportController')
        ->and($screen)->not->toContain('validation preview only')
        ->and($app . $panels)->toContain("'import-commit'")
        ->and($app . $panels)->toContain("'export-download'")
        ->and($app . $panels)->toContain("'migration-preview'")
        ->and($app . $panels)->toContain("'migration-apply'")
        ->and($app . $panels)->toContain("'migration-rollback'");
});

it('ships synchronized scoped token-only responsive styles for both clients', function () {
    foreach (['data', 'data-models'] as $asset) {
        $css = dataClientSource("plugins/corex-config/assets/{$asset}.css");
        $scss = dataClientSource("plugins/corex-config/assets/{$asset}.scss");

        expect($css)->toBe($scss)
            ->and($css)->toContain('.corex-admin .corex-')
            ->and($css)->toContain('var(--corex-admin-')
            ->and($css)->not->toMatch('/#[0-9a-f]{3,8}\b/i')
            ->and($css)->toContain('overflow-x: auto')
            ->and($css)->toContain('@media (max-width:');
    }
});

it('serializes nested filters and downloads binary exports through explicit base64 encoding', function () {
    $client = dataClientSource('plugins/corex-config/src/admin/dataClient.js');
    $controller = dataClientSource('plugins/corex-config/src/Data/DataManagementController.php');
    $models = dataClientSource('plugins/corex-config/src/DataModels/ExportPanel.js')
        . dataClientSource('plugins/corex-config/src/DataModels/dataModelsApi.js');

    expect($client)->toContain('params.set( `filters[${ key }]`')
        ->and($controller)->toContain("'encoding' => 'base64'")
        ->and($controller)->toContain('base64_encode(')
        ->and($models)->toContain("artifact.encoding === 'base64'")
        ->and($models)->toContain('URL.createObjectURL');
});
