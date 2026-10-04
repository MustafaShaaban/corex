<?php

/**
 * Integration tests for where an operator meets the preview link (spec 101, T043, T044): the card
 * on Operations & Security, the page that shows a new link once, and the history.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OptionPreviewAccessStore;
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\PreviewLinkController;
use Corex\Config\Operations\PreviewLinkResult;
use Corex\Config\Operations\PreviewLinkService;
use Corex\Config\Security\OperationsSecurityScreen;

/** Reach a private renderer without widening the production API to suit a test. */
function corexPreviewScreen(string $method, mixed ...$args): string
{
    $screen    = Boot::app()->container()->make(OperationsSecurityScreen::class);
    $reflected = new ReflectionMethod($screen, $method);
    $reflected->setAccessible(true);

    return (string) $reflected->invoke($screen, ...$args);
}

function corexPreviewLinkService(): PreviewLinkService
{
    return Boot::app()->container()->make(PreviewLinkService::class);
}

function corexPreviewLinkController(): PreviewLinkController
{
    return Boot::app()->container()->make(PreviewLinkController::class);
}

beforeEach(function () {
    $this->savedMode   = get_option('corex_operations_mode', null);
    $this->savedLog    = get_option('corex_operations_mode_log', null);
    $this->savedAccess = get_option(OptionPreviewAccessStore::OPTION, null);
    $this->adminId     = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    $this->now         = new DateTimeImmutable('now');

    delete_option(OptionPreviewAccessStore::OPTION);
    delete_option('corex_operations_mode_log');
    update_option('corex_operations_mode', OperationsMode::COMING_SOON);
    wp_set_current_user($this->adminId);
});

afterEach(function () {
    wp_set_current_user(0);
    unset($_GET['corex_status']);

    foreach ([
        'corex_operations_mode'          => $this->savedMode,
        'corex_operations_mode_log'      => $this->savedLog,
        OptionPreviewAccessStore::OPTION => $this->savedAccess,
    ] as $option => $saved) {
        if ($saved === null) {
            delete_option($option);
        } else {
            update_option($option, $saved, false);
        }
    }
});

it('handles the preview link through one admin_post action', function () {
    expect(PreviewLinkController::ACTION)->toBe('corex_preview_link')
        ->and(has_action('admin_post_' . PreviewLinkController::ACTION))->toBeTrue();
});

it('offers to create a link when the site is in Coming soon and has none', function () {
    $card = corexPreviewScreen('previewLinkCard');

    expect($card)->toContain('No preview link exists')
        ->and($card)->toContain('name="action" value="' . PreviewLinkController::ACTION . '"')
        ->and($card)->toContain('name="' . PreviewLinkController::OPERATION . '" value="' . PreviewLinkService::CREATE . '"')
        ->and($card)->toContain('name="' . PreviewLinkController::NONCE . '"')
        ->and($card)->not->toContain('value="' . PreviewLinkService::REGENERATE . '"')
        ->and($card)->not->toContain('value="' . PreviewLinkService::REVOKE . '"')
        // What the link does and does not give, stated before anybody makes one (FR-003's spirit).
        ->and($card)->toContain('14 days')
        ->and($card)->toContain('no access to the admin');
});

it('says when the link was created and by whom, and offers to regenerate or revoke it', function () {
    $token = corexPreviewLinkService()->perform(PreviewLinkService::CREATE, $this->adminId, $this->now)->token;

    $card = corexPreviewScreen('previewLinkCard');

    expect($card)->toContain('A preview link exists')
        ->and($card)->toContain(get_userdata($this->adminId)->display_name)
        ->and($card)->toContain('value="' . PreviewLinkService::REGENERATE . '"')
        ->and($card)->toContain('value="' . PreviewLinkService::REVOKE . '"')
        ->and($card)->not->toContain('value="' . PreviewLinkService::CREATE . '"')
        // The link is shown once, on the response that made it — never again, and not here.
        ->and($card)->not->toContain($token)
        ->and($card)->not->toContain(PreviewAccess::PARAMETER . '=')
        ->and($card)->toContain('cannot be shown again');
});

it('has no preview-link card in any other mode', function (string $mode) {
    update_option('corex_operations_mode', $mode);

    expect(corexPreviewScreen('previewLinkCard'))->toBe('');
})->with([OperationsMode::DEVELOPMENT, OperationsMode::PRODUCTION, OperationsMode::MAINTENANCE]);

it('shows a new link once, whole, with what to do with it', function () {
    $result = corexPreviewLinkService()->perform(PreviewLinkService::CREATE, $this->adminId, $this->now);

    $page = corexPreviewLinkController()->linkPage($result);

    expect($page)->toStartWith('<!DOCTYPE html>')
        ->and($page)->toContain(esc_html(add_query_arg([PreviewAccess::PARAMETER => $result->token], home_url('/'))))
        ->and($page)->toContain('Preview link created')
        ->and($page)->toContain('cannot be shown again')
        ->and($page)->toContain('page=corex-operations-security')
        // An admin interstitial, not a public page.
        ->and($page)->toContain('noindex');
});

it('says the previous link has stopped working when it shows a regenerated one', function () {
    corexPreviewLinkService()->perform(PreviewLinkService::CREATE, $this->adminId, $this->now);
    $result = corexPreviewLinkService()->perform(PreviewLinkService::REGENERATE, $this->adminId, $this->now);

    $page = corexPreviewLinkController()->linkPage($result);

    expect($result->status)->toBe(PreviewLinkResult::REGENERATED)
        ->and($page)->toContain('Preview link regenerated')
        ->and($page)->toContain('previous link no longer works')
        ->and($page)->toContain(PreviewAccess::PARAMETER . '=' . $result->token);
});

it('lists link events in the history beside mode changes, with the operator and never the link', function () {
    $created     = corexPreviewLinkService()->perform(PreviewLinkService::CREATE, $this->adminId, $this->now)->token;
    $regenerated = corexPreviewLinkService()->perform(PreviewLinkService::REGENERATE, $this->adminId, $this->now)->token;
    corexPreviewLinkService()->perform(PreviewLinkService::REVOKE, $this->adminId, $this->now);

    $history = corexPreviewScreen('auditCard');

    expect($history)->toContain('Preview link created')
        ->and($history)->toContain('Preview link regenerated')
        ->and($history)->toContain('Preview link revoked')
        ->and($history)->toContain(get_userdata($this->adminId)->display_name)
        ->and($history)->not->toContain($created)
        ->and($history)->not->toContain($regenerated);
});

it('still lists mode changes in the history as it did', function () {
    Boot::app()->container()->make(\Corex\Config\Operations\OperationsModeStore::class)->set(OperationsMode::STAGING, $this->adminId);

    $history = corexPreviewScreen('auditCard');

    expect($history)->toContain('<code>coming-soon</code> &rarr; <code>staging</code>');
});

it('tells the operator what happened after an action that made no link', function (string $status, string $expected) {
    $_GET['corex_status'] = 'preview_' . $status;

    expect(corexPreviewScreen('statusNotice'))->toContain($expected);
})->with([
    'revoked'         => [PreviewLinkResult::REVOKED, 'Preview link revoked'],
    'already exists'  => [PreviewLinkResult::EXISTS, 'already exists'],
    'none to change'  => [PreviewLinkResult::NONE, 'no preview link'],
    'not coming soon' => [PreviewLinkResult::NOT_COMING_SOON, 'only while the site is in Coming soon'],
]);
