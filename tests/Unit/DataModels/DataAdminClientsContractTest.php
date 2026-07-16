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

it('mounts the one Data client with actor-scoped source capabilities', function () {
    // Spec 069: there used to be two of these. `page=corex-data` and `page=corex-data-models`
    // mounted the identical DataExplorer from an identical config against the same REST base, so
    // the standalone screen was retired.
    $models = dataClientSource('plugins/corex-config/src/DataModels/DataModelsScreen.php');

    expect($models)->toContain('DataSourceService')
        ->and($models)->toContain('get_current_user_id()')
        ->and($models)->toContain("rest_url('corex/v1/data')")
        ->and($models)->toContain("wp_create_nonce('wp_rest')")
        ->and($models)->toContain('build/admin/index.js')
        ->and($models)->toContain("'corex-runtime'")
        ->and($models)->toContain('corex-data-models-app');
});

it('keeps the retired Data address working instead of deleting it', function () {
    $data = dataClientSource('plugins/corex-config/src/Data/DataAdminScreen.php');

    // It redirects and nothing else — no client, no mount, no duplicate enqueue.
    expect($data)->toContain('wp_safe_redirect')
        ->and($data)->toContain('corex-data-models')
        ->and($data)->toContain('tab=records')
        ->and($data)->not->toContain('corex-data-app')
        ->and($data)->not->toContain('add_submenu_page')
        ->and($data)->not->toContain('wp_enqueue_script');
});

it('admits either Data ability and gates each tab on the one it needs', function () {
    $models = dataClientSource('plugins/corex-config/src/DataModels/DataModelsScreen.php');
    $tabs = dataClientSource('plugins/corex-config/src/DataModels/modelClient.js');
    $controller = dataClientSource('plugins/corex-config/src/Data/DataManagementController.php');

    // MANAGE_DATA and MANAGE_DATA_MODELS are independent — neither implies the other. Gating the
    // surviving screen on models alone would lock out data-only users, and records could not be
    // gated on models because the sources it reads are gated on data.
    expect($models)->toContain('CorexAbility::MANAGE_DATA')
        ->and($models)->toContain('CorexAbility::MANAGE_DATA_MODELS')
        ->and($tabs)->toContain("key: 'records', ability: 'data'")
        ->and($tabs)->toContain("key: 'models', ability: 'models'")
        // The REST side already admitted either; the screen now matches it.
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
