<?php

/**
 * Functional Forms & Flows admin surface contracts (spec 068: T078–T082).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function formsFlowSource(string $path): string
{
    return (string) file_get_contents(ThemeContract::root() . '/' . $path);
}

it('mounts the localized REST client only when CoreX Forms is active', function () {
    $screen = formsFlowSource('plugins/corex-config/src/Forms/FormsFlowsScreen.php');

    expect($screen)->toContain('corex-forms-flows-app')
        ->and($screen)->toContain("rest_url('corex/v1/flows')")
        ->and($screen)->toContain("wp_create_nonce('wp_rest')")
        ->and($screen)->toContain("'ownerId'")
        ->and($screen)->toContain('active_sitewide_plugins')
        ->and($screen)->toContain("'corex-runtime'")
        ->and($screen)->toContain('wp_set_script_translations');
});

it('wires authoring lifecycle preview and marked-test controls to real client commands', function () {
    $hook = formsFlowSource('plugins/corex-config/src/Forms/useFlows.js');
    $editor = formsFlowSource('plugins/corex-config/src/Forms/FlowEditorPanel.js');

    expect($hook)->toContain('window.Corex.api.patch')
        ->and($hook)->toContain("transition( 'publish' )")
        ->and($hook)->toContain("transition( 'unpublish' )")
        ->and($hook)->toContain("transition( 'close' )")
        ->and($hook)->toContain("flowEndpoint( config.restUrl, flow.id, 'test' )")
        ->and($editor)->toContain("setStage( 'preview' )")
        ->and($editor)->not->toContain('disabled placeholder');
});

it('ships matching token-only responsive CSS and SCSS with focus RTL and reduced-motion behavior', function () {
    $css = formsFlowSource('plugins/corex-config/assets/forms-admin.css');
    $scss = formsFlowSource('plugins/corex-config/assets/forms-admin.scss');

    expect($css)->toBe($scss)
        ->and($css)->toContain('.corex-admin .corex-flows-app')
        ->and($css)->toContain('var(--corex-admin-')
        ->and($css)->not->toMatch('/#[0-9a-f]{3,8}\b/i')
        ->and($css)->toContain(':focus-visible')
        ->and($css)->toContain('overflow-x: auto')
        ->and($css)->toContain('@media (max-width: 960px)')
        ->and($css)->toContain('@media (max-width: 600px)')
        ->and($css)->toContain('@media (prefers-reduced-motion: reduce)');
});

it('defines every approved persisted-flow dynamic block', function () {
    $blocks = [
        'corex-form' => 'corex/form',
        'corex-flow' => 'corex/flow',
        'success-message' => 'corex/success-message',
        'subscribe' => 'corex/subscribe',
        'survey' => 'corex/survey',
        'cta-flow' => 'corex/cta-flow',
    ];

    foreach ($blocks as $directory => $name) {
        $metadata = formsFlowSource("plugins/corex-forms/src/Block/blocks/{$directory}/block.json");
        expect($metadata)->toContain('"name": "' . $name . '"')
            ->and($metadata)->toContain('Corex\\\\Forms\\\\Block\\\\');
    }
});
