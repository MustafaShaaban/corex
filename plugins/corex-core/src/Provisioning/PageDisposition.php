<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * The classification of one declared kit page: whether applying the kit will create it, adopt (populate) an
 * existing empty/placeholder page, or skip it because it already holds user content. Immutable value object,
 * shared by the apply path (spec 041) and the activation preview/summary (spec 042).
 */
final class PageDisposition
{
    public const CREATE = 'create';
    public const ADOPT  = 'adopt';
    public const SKIP   = 'skip';

    /**
     * Explicit conflict resolutions for a page that already holds user content (FR-139). These are
     * only ever produced from a deliberate operator choice; the default remains SKIP so existing
     * content is never overwritten silently (FR-143).
     */
    public const REPLACE = 'replace';
    public const SUFFIX  = 'suffix';

    /** How a page was persisted, recorded in `_corex_kit_page` so a reset can tell Corex-created from adopted. */
    public const PERSISTED_CREATED  = 'created';
    public const PERSISTED_ADOPTED  = 'adopted';
    public const PERSISTED_REPLACED = 'replaced';
    public const PERSISTED_SUFFIXED = 'suffixed';

    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $action,
        public readonly string $reason,
        /** The slug to persist under. Differs from $slug only for a SUFFIX resolution. */
        public readonly string $targetSlug = '',
    ) {
    }

    /** The slug the page will actually be persisted under (the suffixed slug for a SUFFIX action). */
    public function persistSlug(): string
    {
        return $this->targetSlug !== '' ? $this->targetSlug : $this->slug;
    }
}
