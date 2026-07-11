<?php

/**
 * Spec 068 contracts for the REST-backed functional Email Studio surface.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function emailStudioSource(string $path): string
{
    return (string) file_get_contents(ThemeContract::root() . '/' . $path);
}

function emailStudioComponentSource(): string
{
    $source = '';
    foreach (glob(ThemeContract::root() . '/plugins/corex-config/src/Email/components/*.js') ?: [] as $file) {
        $source .= (string) file_get_contents($file);
    }

    return $source;
}

function emailStudioClientSource(): string
{
    return emailStudioSource('plugins/corex-config/src/Email/index.js')
        . emailStudioSource('plugins/corex-config/src/Email/StudioPanel.js')
        . emailStudioSource('plugins/corex-config/src/Email/useEmailStudio.js');
}

it('mounts a localized REST-backed client only when the optional email add-on is active', function () {
    $screen = emailStudioSource('plugins/corex-config/src/Email/EmailStudioScreen.php');

    expect($screen)->toContain('corex-email-studio-app')
        ->and($screen)->toContain("rest_url('corex/v1/email-studio')")
        ->and($screen)->toContain("wp_create_nonce('wp_rest')")
        ->and($screen)->toContain("'settingsUrl'")
        ->and($screen)->toContain('active_sitewide_plugins')
        ->and($screen)->toContain('wp_set_script_translations');
});

it('exposes every approved functional section in design order', function () {
    $client = emailStudioSource('plugins/corex-config/src/Email/emailStudioClient.js');

    expect($client)->toMatch("/'overview'.*'templates'.*'layouts'.*'partials'.*'variables'.*'routing'.*'preview'.*'plain'.*'test'.*'logs'.*'health'/s");
});

it('uses declared mutation and read endpoints instead of disabled placeholder controls', function () {
    $client = emailStudioClientSource();
    $helpers = emailStudioSource('plugins/corex-config/src/Email/emailStudioClient.js');
    $components = emailStudioComponentSource();

    expect($client)->toMatch("/post\\(\\s*'draft'/")
        ->and($client)->toMatch("/post\\(\\s*'activate'/")
        ->and($client)->toMatch("/post\\(\\s*'layouts'/")
        ->and($client)->toMatch("/post\\(\\s*'partials'/")
        ->and($client)->toMatch("/post\\(\\s*'routes'/")
        ->and($client)->toMatch("/post\\(\\s*'test'/")
        ->and($client)->toMatch("/post\\(\\s*'resend'/")
        ->and($client)->toContain('reply_to_rule: replyRule')
        ->and($client)->toContain('settingsUrl={ config.settingsUrl }')
        ->and($components)->toContain('name="layout_selection"')
        ->and($components)->toContain("__( 'Layout revision', 'corex' )")
        ->and($components)->not->toContain('name="layout_id"')
        ->and($components)->toContain("__( 'Reply-to source', 'corex' )")
        ->and($components)->toContain("__( 'Revise', 'corex' )")
        ->and($components)->toContain('attempt.provider_event')
        ->and($helpers)->toContain("health: ( id )")
        ->and($client)->not->toContain('Send test (disabled)');
});

it('renders desktop mobile and RTL previews in a script-disabled sandbox', function () {
    $components = emailStudioComponentSource();
    $helpers = emailStudioSource('plugins/corex-config/src/Email/emailStudioClient.js');

    expect($components)->toContain('sandbox=""')
        ->and($components)->toContain("onDevice( 'desktop' )")
        ->and($components)->toContain("onDevice( 'mobile' )")
        ->and($components)->toContain("direction === 'rtl'")
        ->and($components)->toContain('aria-pressed={ device')
        ->and($components)->toContain('aria-pressed={ direction')
        ->and($helpers)->toContain('Content-Security-Policy')
        ->and($helpers)->toContain('escapeHtml');
});

it('loads truthful templates layouts partials routes captures and attempts from the API', function () {
    $client = emailStudioSource('plugins/corex-config/src/Email/emailStudioClient.js');
    $controller = emailStudioSource('addons/corex-email/src/Studio/EmailStudioController.php');

    foreach (['templates', 'layouts', 'partials', 'routes', 'captures', 'attempts'] as $key) {
        expect($client)->toContain("{$key}: Array.isArray");
        expect($controller)->toContain("'{$key}'");
    }
});

it('ships scoped token-only responsive Email Studio styles and their SCSS source', function () {
    $css = emailStudioSource('plugins/corex-config/assets/email-studio.css');
    $scss = emailStudioSource('plugins/corex-config/assets/email-studio.scss');

    expect($css)->toBe($scss)
        ->and($css)->toContain('.corex-admin .corex-email-app')
        ->and($css)->toContain('var(--corex-admin-')
        ->and($css)->not->toMatch('/#[0-9a-f]{3,8}\b/i')
        ->and($css)->toContain('overflow-x: auto')
        ->and($css)->toContain('white-space: nowrap')
        ->and($css)->toContain('@media (max-width: 960px)')
        ->and($css)->toContain('@media (max-width: 600px)')
        ->and($css)->toContain('corex-token-allow: responsive breakpoint')
        ->and($css)->toContain('grid-template-columns: minmax(0, 1fr) minmax(0, 2fr)');
});

it('loads the shared runtime React bundle and CSS only on its own screen', function () {
    $screen = emailStudioSource('plugins/corex-config/src/Email/EmailStudioScreen.php');

    expect($screen)->toContain('$hook !== $this->hook')
        ->and($screen)->toContain("'corex-runtime'")
        ->and($screen)->toContain("'corex-email-studio'")
        ->and($screen)->toContain("['corex-admin-shell']")
        ->and($screen)->toContain('build/admin/index.js');
});
