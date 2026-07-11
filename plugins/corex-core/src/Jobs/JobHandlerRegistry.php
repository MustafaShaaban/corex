<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

use DomainException;

final class JobHandlerRegistry
{
    /** @var array<string,JobHandler> */
    private array $handlers = [];

    public function register(JobHandler $handler): void
    {
        if (isset($this->handlers[$handler->kind()])) {
            throw new DomainException(sprintf('A handler for bounded job "%s" is already registered.', $handler->kind()));
        }

        $this->handlers[$handler->kind()] = $handler;
    }

    public function find(string $kind): ?JobHandler
    {
        return $this->handlers[$kind] ?? null;
    }
}
