<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

interface SocialSharingSettingsStore
{
    public function current(): SocialSharingSettings;
}
