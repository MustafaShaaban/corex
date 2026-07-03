<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Actor- and target-bound, expiring, single-use confirmation for sensitive work.
 */
final class Confirmation
{
    public function __construct(
        public readonly string $operationKind,
        public readonly string $targetHash,
        public readonly int $actorId,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?string $requiredPhrase = null,
        public readonly ?DateTimeImmutable $usedAt = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->operationKind) !== 1) {
            throw new InvalidArgumentException('Confirmation operation kind is invalid.');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $this->targetHash) !== 1) {
            throw new InvalidArgumentException('Confirmation target hash must be SHA-256.');
        }

        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Confirmation actor ID must be positive.');
        }

        if ($this->requiredPhrase !== null && trim($this->requiredPhrase) === '') {
            throw new InvalidArgumentException('Confirmation phrase cannot be blank.');
        }
    }

    public function verify(
        string $operationKind,
        string $targetHash,
        int $actorId,
        ?string $phrase,
        DateTimeImmutable $now,
    ): bool {
        if ($this->usedAt !== null || $now >= $this->expiresAt) {
            return false;
        }

        if ($operationKind !== $this->operationKind || $actorId !== $this->actorId) {
            return false;
        }

        if (! hash_equals($this->targetHash, $targetHash)) {
            return false;
        }

        return $this->requiredPhrase === null
            ? $phrase === null
            : is_string($phrase) && hash_equals($this->requiredPhrase, $phrase);
    }

    public function use(DateTimeImmutable $now): self
    {
        if ($this->usedAt !== null) {
            throw new DomainException('Confirmation has already been used.');
        }

        if ($now >= $this->expiresAt) {
            throw new DomainException('Confirmation has expired.');
        }

        return new self(
            operationKind: $this->operationKind,
            targetHash: $this->targetHash,
            actorId: $this->actorId,
            expiresAt: $this->expiresAt,
            requiredPhrase: $this->requiredPhrase,
            usedAt: $now,
        );
    }
}
