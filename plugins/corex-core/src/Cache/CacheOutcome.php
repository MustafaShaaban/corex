<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * What a cache operation actually did (spec 078, FR-012).
 *
 * The command this replaces printed `success` after deleting one transient. "Success" on its own is
 * not an answer to "did that fix my problem" — an operator needs to know what was cleared, what was
 * deliberately left alone, and what CoreX could not reach here. All three are different, and only
 * the first is what people assume happened.
 */
final class CacheOutcome
{
    /** @param list<string> $cleared
     *  @param array<string,string> $skipped     entry => why it was left alone
     *  @param array<string,string> $unsupported scope/layer => why it cannot be done here
     *  @param array<string,string> $failed      entry => what went wrong
     */
    private function __construct(
        public readonly CacheScope $scope,
        public readonly array $cleared,
        public readonly array $skipped,
        public readonly array $unsupported,
        public readonly array $failed,
    ) {
    }

    public static function for(CacheScope $scope): self
    {
        return new self($scope, [], [], [], []);
    }

    public function withCleared(string $what): self
    {
        return new self($this->scope, [...$this->cleared, $what], $this->skipped, $this->unsupported, $this->failed);
    }

    public function withSkipped(string $what, string $why): self
    {
        return new self($this->scope, $this->cleared, [...$this->skipped, $what => $why], $this->unsupported, $this->failed);
    }

    public function withUnsupported(string $what, string $why): self
    {
        return new self($this->scope, $this->cleared, $this->skipped, [...$this->unsupported, $what => $why], $this->failed);
    }

    public function withFailed(string $what, string $why): self
    {
        return new self($this->scope, $this->cleared, $this->skipped, $this->unsupported, [...$this->failed, $what => $why]);
    }

    /** Did anything actually happen? Distinct from "did anything go wrong". */
    public function didSomething(): bool
    {
        return $this->cleared !== [];
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }

    /**
     * A sentence an operator can act on.
     *
     * Deliberately never just "done": it names the count, and says when the answer is that nothing
     * needed doing or that nothing here could.
     */
    public function summary(): string
    {
        if ($this->hasFailures()) {
            return sprintf(
                /* translators: %d: number of cache entries that could not be cleared. */
                _n('%d cache entry could not be cleared.', '%d cache entries could not be cleared.', count($this->failed), 'corex'),
                count($this->failed),
            );
        }

        if (! $this->didSomething() && $this->unsupported !== []) {
            return __('Nothing was cleared — that is not something CoreX can do on this site.', 'corex');
        }

        if (! $this->didSomething()) {
            return __('Nothing needed clearing.', 'corex');
        }

        return sprintf(
            /* translators: %d: number of cache entries cleared. */
            _n('%d cache entry cleared.', '%d cache entries cleared.', count($this->cleared), 'corex'),
            count($this->cleared),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'scope'       => $this->scope->value,
            'summary'     => $this->summary(),
            'cleared'     => $this->cleared,
            'skipped'     => $this->skipped,
            'unsupported' => $this->unsupported,
            'failed'      => $this->failed,
        ];
    }
}
