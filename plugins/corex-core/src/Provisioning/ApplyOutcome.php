<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * The result of applying a kit: each declared page's disposition enriched with the resulting page id, whether
 * it became the front page, and how it was persisted (created|adopted|null-for-skip), plus the modules
 * activated, flags enabled, and the front page id. Immutable value object returned by the apply path (spec 041)
 * and rendered as the "what changed" summary by the setup wizard and the activation prompt (spec 042). The
 * activator builds one and hands it back — no echo, no output (Principle III).
 */
final class ApplyOutcome
{
    /**
     * @param list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> $pages
     * @param list<string>                                                                          $modules
     * @param list<string>                                                                          $flags
     */
    public function __construct(
        private readonly array $pages,
        public readonly array $modules,
        public readonly array $flags,
        public readonly ?int $frontPageId,
    ) {
    }

    /** @return list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> */
    public function created(): array
    {
        return $this->withAction(PageDisposition::CREATE);
    }

    /** @return list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> */
    public function populated(): array
    {
        return $this->withAction(PageDisposition::ADOPT);
    }

    /** @return list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> */
    public function skipped(): array
    {
        return $this->withAction(PageDisposition::SKIP);
    }

    /**
     * @return list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}>
     */
    private function withAction(string $action): array
    {
        return array_values(array_filter(
            $this->pages,
            static fn (array $page): bool => $page['disposition']->action === $action,
        ));
    }
}
