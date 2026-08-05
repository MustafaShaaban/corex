<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides;

defined('ABSPATH') || exit;

/**
 * One guide, contributed by Corex or by any site built on it (spec 084, FR-006 / FR-008).
 *
 * Built through a fluent chain rather than a seven-argument constructor, because the audience is a
 * site developer writing this in their own plugin and every optional field is genuinely optional:
 *
 *     Guide::for('perego-projects', __('Managing projects', 'perego'))
 *         ->inSection('content')
 *         ->onScreen('edit.php?post_type=perego_project')
 *         ->requiring('edit_posts')
 *         ->withTopic($topic);
 *
 * Immutable: every method returns a new instance, so a guide can be built from a shared base without
 * one caller's `->requiring()` leaking into another's.
 */
final class Guide
{
    /** Guides with no declared section land here, rather than vanishing from a grouped list. */
    public const SECTION_GENERAL = 'general';

    /** @param list<GuideTopic> $topics */
    private function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $summary = '',
        public readonly string $section = self::SECTION_GENERAL,
        public readonly string $screen = '',
        public readonly string $capability = '',
        public readonly int $order = 50,
        public readonly array $topics = [],
    ) {
    }

    public static function for(string $id, string $title): self
    {
        return new self($id, $title);
    }

    public function withSummary(string $summary): self
    {
        return $this->derive(['summary' => $summary]);
    }

    public function inSection(string $section): self
    {
        return $this->derive(['section' => $section === '' ? self::SECTION_GENERAL : $section]);
    }

    /**
     * The admin address this guide describes, e.g. `edit.php?post_type=corex_job`.
     *
     * Rendered on the Guides screen as a link straight to that screen, so a reader who has just
     * finished the steps is one click from doing them. A guide that describes no single screen
     * simply omits it and carries no link.
     *
     * It also fed the screen's WordPress contextual help tab until spec 097 removed that surface
     * from CoreX admin; the address itself was always the better half of that feature and stays.
     */
    public function onScreen(string $screen): self
    {
        return $this->derive(['screen' => $screen]);
    }

    /**
     * The capability a reader needs before this guide is worth showing them (FR-009).
     *
     * Empty means "anyone who can see the admin". Naming a capability the reader lacks would
     * describe a screen they cannot open, which teaches them the product is broken.
     */
    public function requiring(string $capability): self
    {
        return $this->derive(['capability' => $capability]);
    }

    public function ordered(int $order): self
    {
        return $this->derive(['order' => $order]);
    }

    public function withTopic(GuideTopic $topic): self
    {
        return $this->derive(['topics' => [...$this->topics, $topic]]);
    }

    /**
     * Whether this guide is worth showing the current user.
     *
     * Only the capability is checked here. Whether the contributing add-on is active is not a
     * question this class can answer, and deliberately is not asked: an add-on registers its guides
     * from its own service provider, so an inactive add-on never registers and there is nothing to
     * filter out. A list of add-on slugs kept here would be a second source of truth about which
     * add-ons exist, and it would be wrong the first time one was renamed.
     */
    public function isAvailableTo(): bool
    {
        return $this->capability === '' || current_user_can($this->capability);
    }

    /**
     * Everything a reader could search this guide by.
     */
    public function searchText(): string
    {
        $words = [$this->title, $this->summary];

        foreach ($this->topics as $topic) {
            $words[] = $topic->searchText();
        }

        return trim(implode(' ', array_filter($words, static fn (string $w): bool => trim($w) !== '')));
    }

    /**
     * The one place this class is constructed from an existing instance.
     *
     * Eight fields and seven withers: constructing in each would be seven chances for two adjacent
     * strings to swap places, and `title`/`summary`/`section`/`screen`/`capability` are all strings,
     * so nothing would catch it but a reader.
     *
     * @param array<string,mixed> $overrides
     */
    private function derive(array $overrides): self
    {
        return new self(
            $overrides['id'] ?? $this->id,
            $overrides['title'] ?? $this->title,
            $overrides['summary'] ?? $this->summary,
            $overrides['section'] ?? $this->section,
            $overrides['screen'] ?? $this->screen,
            $overrides['capability'] ?? $this->capability,
            $overrides['order'] ?? $this->order,
            $overrides['topics'] ?? $this->topics,
        );
    }
}
