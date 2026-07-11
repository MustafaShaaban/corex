<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/** Actor and route context required to consume a mutation preview. */
final readonly class DataMutationApplyRequest
{
    public function __construct(
        public int $actorId,
        public string $token,
        public string $sourceKey,
        public string $actorLabel,
        public DateTimeImmutable $now,
    ) {
        if ($actorId < 1 || $token === '' || $sourceKey === '' || $actorLabel === '') {
            throw new InvalidArgumentException('The data mutation apply request is invalid.');
        }
    }
}
