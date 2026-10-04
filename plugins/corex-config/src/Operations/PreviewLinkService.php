<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * What an operator can do to the preview link, and the rules for doing it (spec 101, FR-011 and
 * FR-012): create it, regenerate it, revoke it — only while the site is in Coming soon, and each
 * recorded in the history with the operator's name and never the link.
 *
 * {@see PreviewAccess} knows what a link is. This knows when one may be made and that making one
 * is something to write down. Capability and nonce are not here: they belong to the boundary that
 * received the request, {@see PreviewLinkController}.
 */
final class PreviewLinkService
{
    public const CREATE     = 'create';
    public const REGENERATE = 'regenerate';
    public const REVOKE     = 'revoke';

    public function __construct(
        private readonly PreviewAccess $access,
        private readonly OperationsModeStore $modes,
    ) {
    }

    public function perform(string $operation, int $actorId, DateTimeImmutable $now): PreviewLinkResult
    {
        if (! in_array($operation, [self::CREATE, self::REGENERATE, self::REVOKE], true)) {
            return new PreviewLinkResult(PreviewLinkResult::INVALID);
        }

        // A link made in another mode would be a secret that opens nothing today and starts
        // working the day the mode is switched. Leaving the mode removes the link (FR-012a); this
        // is the other half of the same rule.
        if ($this->modes->current() !== OperationsMode::COMING_SOON) {
            return new PreviewLinkResult(PreviewLinkResult::NOT_COMING_SOON);
        }

        return match ($operation) {
            self::CREATE     => $this->create($actorId, $now),
            self::REGENERATE => $this->regenerate($actorId, $now),
            self::REVOKE     => $this->revoke($actorId),
        };
    }

    private function create(int $actorId, DateTimeImmutable $now): PreviewLinkResult
    {
        $token = $this->access->create($actorId, $now);
        if ($token === null) {
            return new PreviewLinkResult(PreviewLinkResult::EXISTS);
        }

        $this->modes->record(OperationsModeStore::EVENT_PREVIEW_CREATED, $actorId);

        return new PreviewLinkResult(PreviewLinkResult::CREATED, $token);
    }

    private function regenerate(int $actorId, DateTimeImmutable $now): PreviewLinkResult
    {
        $token = $this->access->regenerate($actorId, $now);
        if ($token === null) {
            return new PreviewLinkResult(PreviewLinkResult::NONE);
        }

        $this->modes->record(OperationsModeStore::EVENT_PREVIEW_REGENERATED, $actorId);

        return new PreviewLinkResult(PreviewLinkResult::REGENERATED, $token);
    }

    private function revoke(int $actorId): PreviewLinkResult
    {
        if (! $this->access->revoke()) {
            return new PreviewLinkResult(PreviewLinkResult::NONE);
        }

        $this->modes->record(OperationsModeStore::EVENT_PREVIEW_REVOKED, $actorId);

        return new PreviewLinkResult(PreviewLinkResult::REVOKED);
    }
}
