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

    /** How a page was persisted, recorded in `_corex_kit_page` so a reset can tell Corex-created from adopted. */
    public const PERSISTED_CREATED = 'created';
    public const PERSISTED_ADOPTED = 'adopted';

    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $action,
        public readonly string $reason,
    ) {
    }
}
