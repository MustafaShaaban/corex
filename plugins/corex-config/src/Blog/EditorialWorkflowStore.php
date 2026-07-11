<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Persistence port for native Blog post editorial metadata and status changes.
 */
interface EditorialWorkflowStore
{
    public function find(int $postId): EditorialItem;

    public function save(EditorialItem $item): void;

    public function updateNativeStatus(int $postId, string $status, ?DateTimeImmutable $scheduledAt = null): void;
}
