<?php

/**
 * Unit tests for what an operator can do to the preview link (spec 101, T043; FR-011, FR-012):
 * create it, regenerate it, revoke it — each only while the site is in Coming soon, and each
 * written into the history with the operator and without the secret.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Config\Operations\PreviewAccess;
use Corex\Config\Operations\PreviewLinkResult;
use Corex\Config\Operations\PreviewLinkService;
use Corex\Tests\Fixtures\Operations\InMemoryPreviewAccessStore;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => OperationsMode::COMING_SOON];
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('wp_get_environment_type')->justReturn(OperationsMode::PRODUCTION);

    $this->modes   = new OperationsModeStore(new OperationsMode());
    $this->access  = new PreviewAccess(new InMemoryPreviewAccessStore(), 'a-key-only-the-site-knows');
    $this->service = new PreviewLinkService($this->access, $this->modes);
    $this->now     = new DateTimeImmutable('2026-10-04T12:00:00+00:00');
});

/** @return list<string> The events in the history, oldest first. */
function previewLinkEvents(OperationsModeStore $modes): array
{
    return array_reverse(array_column($modes->timeline(), 'event'));
}

it('creates the link, hands back its token once, and records who created it', function () {
    $result = $this->service->perform(PreviewLinkService::CREATE, 7, $this->now);

    expect($result->status)->toBe(PreviewLinkResult::CREATED)
        ->and($result->token)->toBeString()
        ->and($this->access->accepts($result->token))->toBeTrue()
        ->and(previewLinkEvents($this->modes))->toBe([OperationsModeStore::EVENT_PREVIEW_CREATED])
        ->and($this->modes->timeline()[0]['user'])->toBe(7);
});

it('regenerates the link, ends the old one, and records it as a regeneration', function () {
    $old    = $this->service->perform(PreviewLinkService::CREATE, 7, $this->now)->token;
    $result = $this->service->perform(PreviewLinkService::REGENERATE, 9, $this->now);

    expect($result->status)->toBe(PreviewLinkResult::REGENERATED)
        ->and($result->token)->not->toBe($old)
        ->and($this->access->accepts($old))->toBeFalse()
        ->and($this->access->accepts($result->token))->toBeTrue()
        ->and(previewLinkEvents($this->modes))->toBe([
            OperationsModeStore::EVENT_PREVIEW_CREATED,
            OperationsModeStore::EVENT_PREVIEW_REGENERATED,
        ])
        ->and($this->modes->timeline()[0]['user'])->toBe(9);
});

it('revokes the link, leaving none, and records who revoked it', function () {
    $token  = $this->service->perform(PreviewLinkService::CREATE, 7, $this->now)->token;
    $result = $this->service->perform(PreviewLinkService::REVOKE, 9, $this->now);

    expect($result->status)->toBe(PreviewLinkResult::REVOKED)
        ->and($result->token)->toBeNull()
        ->and($this->access->exists())->toBeFalse()
        ->and($this->access->accepts($token))->toBeFalse()
        ->and(previewLinkEvents($this->modes))->toBe([
            OperationsModeStore::EVENT_PREVIEW_CREATED,
            OperationsModeStore::EVENT_PREVIEW_REVOKED,
        ])
        ->and($this->modes->timeline()[0]['user'])->toBe(9);
});

it('never writes the token into the history', function () {
    // FR-012. Checked against everything the log holds, not against the fields it is meant to have.
    $created     = $this->service->perform(PreviewLinkService::CREATE, 7, $this->now)->token;
    $regenerated = $this->service->perform(PreviewLinkService::REGENERATE, 7, $this->now)->token;
    $this->service->perform(PreviewLinkService::REVOKE, 7, $this->now);

    $log = serialize($GLOBALS['corex_test_options']['corex_operations_mode_log']);

    expect($log)->not->toContain($created)
        ->and($log)->not->toContain($regenerated);
});

it('says so, and writes nothing, when there is nothing to do', function (string $operation, string $expected, bool $linkExists) {
    if ($linkExists) {
        $this->access->create(7, $this->now);
    }

    $result = $this->service->perform($operation, 7, $this->now);

    expect($result->status)->toBe($expected)
        ->and($result->token)->toBeNull()
        ->and($this->access->exists())->toBe($linkExists)
        ->and($this->modes->timeline())->toBe([]);
})->with([
    'creating over a link that exists' => [PreviewLinkService::CREATE, PreviewLinkResult::EXISTS, true],
    'regenerating when there is none'  => [PreviewLinkService::REGENERATE, PreviewLinkResult::NONE, false],
    'revoking when there is none'      => [PreviewLinkService::REVOKE, PreviewLinkResult::NONE, false],
    'an operation that does not exist' => ['publish', PreviewLinkResult::INVALID, false],
]);

it('does nothing in any other mode', function (string $operation) {
    // A link exists only while the site is in Coming soon (FR-012a). One created in another mode
    // would be a secret that opens nothing today and starts working the day the mode is switched.
    $GLOBALS['corex_test_options']['corex_operations_mode'] = OperationsMode::STAGING;

    $result = $this->service->perform($operation, 7, $this->now);

    expect($result->status)->toBe(PreviewLinkResult::NOT_COMING_SOON)
        ->and($result->token)->toBeNull()
        ->and($this->access->exists())->toBeFalse()
        ->and($this->modes->timeline())->toBe([]);
})->with([PreviewLinkService::CREATE, PreviewLinkService::REGENERATE, PreviewLinkService::REVOKE]);
