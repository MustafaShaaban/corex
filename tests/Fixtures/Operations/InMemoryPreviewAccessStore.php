<?php

/**
 * @package Corex\Tests\Fixtures\Operations
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Operations;

use Corex\Config\Operations\PreviewAccessStore;

/**
 * A PreviewAccessStore that keeps its one record in memory, so the rules of preview access can be
 * tested without WordPress. `everythingStored()` exists for the one test that has to look at what
 * was kept rather than at what the rules answer: that the secret is not in it.
 */
final class InMemoryPreviewAccessStore implements PreviewAccessStore
{
    /** @var array{hash:string,issued:int,user:int}|null */
    private ?array $record = null;

    public function read(): ?array
    {
        return $this->record;
    }

    public function write(string $hash, int $issued, int $user): void
    {
        $this->record = ['hash' => $hash, 'issued' => $issued, 'user' => $user];
    }

    public function delete(): void
    {
        $this->record = null;
    }

    public function everythingStored(): string
    {
        return serialize($this->record);
    }
}
