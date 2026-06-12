<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Options;

defined('ABSPATH') || exit;

/**
 * The set of custom option pages an app has registered. The screen iterates it to add each page's
 * admin menu + render/save; keyed by slug, so re-registering a slug replaces it (spec 039).
 */
final class OptionPageRegistry
{
    /** @var array<string,OptionPage> */
    private array $pages = [];

    public function register(OptionPage $page): void
    {
        $this->pages[$page->slug()] = $page;
    }

    /**
     * @return list<OptionPage>
     */
    public function all(): array
    {
        return array_values($this->pages);
    }

    public function find(string $slug): ?OptionPage
    {
        return $this->pages[$slug] ?? null;
    }
}
