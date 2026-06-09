<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * Supplies recent posts as plain {title, url} rows. The interface keeps the posts
 * block renderer headless-testable; the WordPress-backed implementation queries posts.
 */
interface PostsProvider
{
    /**
     * @return list<array{title:string,url:string}>
     */
    public function recent(int $count): array;
}
