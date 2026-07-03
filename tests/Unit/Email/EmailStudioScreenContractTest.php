<?php

/**
 * Spec 067 contracts for the server-rendered Email Studio surface.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Config\Email\EmailStudioScreen;
use Corex\Tests\Support\ThemeContract;

/** Return one method's source so markup contracts stay scoped to the relevant view. */
function emailStudioMethodSource(string $method): string
{
    $reflection = new ReflectionMethod(EmailStudioScreen::class, $method);
    $lines = file($reflection->getFileName());

    return implode('', array_slice(
        $lines === false ? [] : $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    ));
}

it('exposes the approved studio and template-detail tabs in design order', function () {
    $studio = emailStudioMethodSource('render');
    $detail = emailStudioMethodSource('templateDetail');
    $detailTabs = strstr($detail, '// phpcs:', true);

    expect($studio)->toMatch("/'overview'.*'templates'.*'layouts'.*'partials'.*'variables'/s")
        ->and($detailTabs)->not->toBeFalse()
        ->and($detailTabs)->toMatch("/'edit'.*'preview'.*'plain'.*'test'.*'routing'.*'logs'/s")
        ->and($detail)->toContain("aria-current=\"page\"");
});

it('renders the active brand layout as a sandboxed structural preview', function () {
    $layout = emailStudioMethodSource('layoutsTab');

    expect($layout)->toContain('layoutPreview')
        ->and($layout)->toContain('<iframe')
        ->and($layout)->toContain('sandbox')
        ->and($layout)->toContain('Brand layout preview');
});

it('keeps unsupported template mutations visibly disabled with exact reasons', function () {
    $edit = emailStudioMethodSource('editDetail');
    $testSend = emailStudioMethodSource('testDetail');
    $routing = emailStudioMethodSource('routingDetail');

    expect($edit)->toContain('defined in code')
        ->and($testSend)->toContain('disabled')
        ->and($testSend)->toContain('capability + nonce gated')
        ->and($testSend)->toContain('per-send result')
        ->and($routing)->toContain('set in code');
});

it('labels every real registry template with its truthful registered state', function () {
    $templates = emailStudioMethodSource('templatesTab');

    expect($templates)->toContain('corex-email-studio__row-status')
        ->and($templates)->toContain("esc_html__('Registered', 'corex')");
});

it('derives variable rows from registered template sources instead of a fabricated common list', function () {
    $variables = emailStudioMethodSource('variablesTab');
    $logs = emailStudioMethodSource('logsDetail');

    expect($variables)->toContain('studio->variables(')
        ->and($variables)->toContain("__('Registered templates', 'corex')")
        ->and($variables)->not->toContain("'site.name'")
        ->and($logs)->toContain("__('Sent', 'corex')")
        ->and($logs)->toContain("__('Failed', 'corex')")
        ->and($logs)->toContain("__('(no subject)', 'corex')");
});

it('uses the real template renderer and delivery-log repository without fabricating data', function () {
    $source = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Email/EmailStudioScreen.php',
    );

    expect($source)->toContain('TemplateRegistry::class')
        ->and($source)->toContain('TemplateRenderer::class')
        ->and($source)->toContain('EmailLogRepository::class')
        ->and($source)->toContain("->byStatus('sent')")
        ->and($source)->toContain("->byStatus('failed')");
});

it('ships scoped token-only responsive Email Studio styles', function () {
    $css = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/assets/email-studio.css',
    );
    $variables = emailStudioMethodSource('variablesTab');

    expect($css)->toContain('.corex-admin .corex-email-studio')
        ->and($css)->toContain('var(--corex-admin-')
        ->and($css)->not->toMatch('/#[0-9a-f]{3,8}\b/i')
        ->and($css)->toContain('.corex-email-studio__table-scroll')
        ->and($css)->toContain('overflow-x: auto')
        ->and($css)->toContain('min-inline-size: 32rem')
        ->and($css)->toContain('white-space: nowrap')
        ->and($css)->toContain('@media (max-width: 782px)')
        ->and($css)->toContain('grid-template-columns: minmax(0, 1fr) auto')
        ->and($variables)->toContain('corex-email-studio__table-scroll');
});

it('loads Email Studio CSS only on its own screen through the shared admin adapter', function () {
    $source = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Email/EmailStudioScreen.php',
    );

    expect($source)->toContain('$hook !== $this->hook')
        ->and($source)->toContain("'corex-email-studio'")
        ->and($source)->toContain("['corex-admin-shell']");
});
